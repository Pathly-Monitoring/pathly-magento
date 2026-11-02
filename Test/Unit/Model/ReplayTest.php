<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use Magento\Framework\App\MaintenanceMode;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\Client;
use Pathly\Monitoring\Model\Api\ClientFactory;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\Dashboard\StatusProvider;
use Pathly\Monitoring\Model\ErrorMessage;
use Pathly\Monitoring\Model\Fingerprint;
use Pathly\Monitoring\Model\Flows;
use Pathly\Monitoring\Model\Replay;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;

class ReplayTest extends TestCase
{
    use Helper;

    private const FLOW_A = '0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab';
    private const FLOW_B = '11111111-2222-3333-4444-555555555555';

    private bool $connected = true;
    private bool $replayEnabled = true;
    private bool $maintenance = false;
    private string $fingerprint = 'fp1';
    private array $extra = [];
    private Client $client;
    private ClientFactory $clients;
    private StatusProvider $status;

    private function replay(array $state = []): Replay
    {
        $config = $this->createMock(Config::class);
        $config->method('isConnected')->willReturnCallback(fn () => $this->connected);
        $config->method('isReplayEnabled')->willReturnCallback(fn () => $this->replayEnabled);
        $config->method('getExtraFlowIds')->willReturnCallback(fn () => $this->extra);
        $fingerprint = $this->createMock(Fingerprint::class);
        $fingerprint->method('current')->willReturnCallback(fn () => $this->fingerprint);
        $maintenance = $this->createMock(MaintenanceMode::class);
        $maintenance->method('isOn')->willReturnCallback(fn () => $this->maintenance);
        $this->client = $this->createMock(Client::class);
        $this->clients = $this->createMock(ClientFactory::class);
        $this->clients->method('create')->willReturn($this->client);
        $this->status = $this->createMock(StatusProvider::class);
        $memory = $this->memoryState($state);
        return new Replay(
            $config,
            $memory,
            $this->clients,
            $fingerprint,
            $maintenance,
            new ErrorMessage(),
            $this->status,
            new Flows($memory, $config)
        );
    }

    public function testNothingBeforeConnection(): void
    {
        $this->connected = false;
        $replay = $this->replay();
        $this->clients->expects($this->never())->method('create');
        $this->assertSame(['result' => 'not_connected'], $replay->check());
        $this->assertSame([], $this->flag);
    }

    public function testFirstCheckOnlyRecordsTheFingerprint(): void
    {
        $this->assertSame(['result' => 'nothing'], $this->replay(['flowIds' => [self::FLOW_A]])->check());
        $this->assertSame('fp1', $this->flag['fingerprint']);
    }

    public function testSameFingerprintNothingToDo(): void
    {
        $replay = $this->replay(['fingerprint' => 'fp1', 'flowIds' => [self::FLOW_A]]);
        $this->client->expects($this->never())->method('runScenario');
        $this->assertSame(['result' => 'nothing'], $replay->check());
    }

    public function testModuleUpdateReplaysEveryFlow(): void
    {
        $this->extra = [self::FLOW_B, self::FLOW_A];
        $replay = $this->replay(['fingerprint' => 'fp0', 'flowIds' => [self::FLOW_A, 42]]);
        $this->fingerprint = 'fp2';
        $called = [];
        $this->client->method('runScenario')->willReturnCallback(function ($id) use (&$called) {
            $called[] = $id;
            if ($id === self::FLOW_B) {
                throw new ApiException('Scenario not found', 404);
            }
            return ['ok' => true];
        });
        $this->status->expects($this->once())->method('clean');
        $result = $replay->check();
        $this->assertSame([self::FLOW_A, self::FLOW_B], $called);
        $this->assertSame(['result' => 'replayed', 'queued' => 1, 'errors' => ['Scenario not found']], $result);
        $this->assertSame('fp2', $this->flag['fingerprint']);
        $this->assertArrayNotHasKey('replayPending', $this->flag);
        $this->assertSame('modules', $this->flag['lastReplay']['reason']);
    }

    public function testSetupUpgradeAsksForAReplay(): void
    {
        $replay = $this->replay(['fingerprint' => 'fp1', 'flowIds' => [self::FLOW_A], 'lastReplay' => 'broken']);
        $replay->markPending('setup_upgrade');
        $this->assertSame('setup_upgrade', $this->flag['replayPending']['reason']);
        $this->client->expects($this->once())->method('runScenario')->with(self::FLOW_A);
        $this->assertSame('replayed', $replay->check()['result']);
        $this->assertSame('setup_upgrade', $this->flag['lastReplay']['reason']);
    }

    public function testDisabledReplayForgetsTheRequest(): void
    {
        $this->replayEnabled = false;
        $replay = $this->replay(['fingerprint' => 'fp1', 'replayPending' => ['reason' => 'setup_upgrade']]);
        $this->assertSame(['result' => 'disabled'], $replay->check());
        $this->assertArrayNotHasKey('replayPending', $this->flag);
    }

    public function testWaitsForTheEndOfMaintenance(): void
    {
        $this->maintenance = true;
        $replay = $this->replay(['fingerprint' => 'fp1', 'replayPending' => ['reason' => 'setup_upgrade']]);
        $this->assertSame(['result' => 'maintenance'], $replay->check());
        $this->assertArrayHasKey('replayPending', $this->flag);
    }

    public function testTwoReplaysAreTenMinutesApart(): void
    {
        $replay = $this->replay([
            'fingerprint' => 'fp1',
            'flowIds' => [self::FLOW_A],
            'replayPending' => ['reason' => 'setup_upgrade'],
            'lastReplay' => ['at' => time() - 60],
        ]);
        $this->assertSame(['result' => 'too_soon'], $replay->check());
        $this->assertArrayHasKey('replayPending', $this->flag);
    }

    public function testNoFlowYet(): void
    {
        $replay = $this->replay(['fingerprint' => 'fp1', 'replayPending' => ['reason' => 'setup_upgrade']]);
        $this->assertSame(['result' => 'no_flow'], $replay->check());
        $this->assertArrayNotHasKey('replayPending', $this->flag);
    }

    public function testForcedReplayIgnoresSignalsAndInterval(): void
    {
        $this->replayEnabled = false;
        $replay = $this->replay([
            'fingerprint' => 'fp1',
            'flowIds' => [self::FLOW_A],
            'lastReplay' => ['at' => time()],
        ]);
        $this->client->expects($this->once())->method('runScenario');
        $this->assertSame(['result' => 'replayed', 'queued' => 1, 'errors' => []], $replay->check(true));
        $this->assertSame('manual', $this->flag['lastReplay']['reason']);
    }
}
