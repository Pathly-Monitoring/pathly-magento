<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\Client;
use Pathly\Monitoring\Model\Api\ClientFactory;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\Dashboard\StatusProvider;
use Pathly\Monitoring\Model\ErrorMessage;
use Pathly\Monitoring\Model\Flows;
use Pathly\Monitoring\Model\Lumi;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LumiTest extends TestCase
{
    use Helper;

    private const TRIAL = '0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab';
    private const FLOW = '11111111-2222-3333-4444-555555555555';

    private Client $client;
    private StatusProvider $status;

    private function lumi(array $state = []): Lumi
    {
        $this->client = $this->createMock(Client::class);
        $clients = $this->createMock(ClientFactory::class);
        $clients->method('create')->willReturn($this->client);
        $config = $this->createMock(Config::class);
        $config->method('getStoreUrl')->willReturn('https://shop.example/');
        $config->method('getExtraFlowIds')->willReturn([]);
        $this->status = $this->createMock(StatusProvider::class);
        $memory = $this->memoryState($state);
        return new Lumi($clients, $config, $memory, new ErrorMessage(), $this->status, new Flows($memory, $config));
    }

    public function testStartSendsOnlyTheAddressOfTheShop(): void
    {
        $lumi = $this->lumi();
        $this->client->expects($this->once())->method('detectSite')->with('https://shop.example/')
            ->willReturn(['available' => true, 'siteKind' => 'magento']);
        $this->client->expects($this->once())->method('startTrial')->with('https://shop.example/')
            ->willReturn(self::TRIAL);
        $this->assertSame(['trialId' => self::TRIAL, 'siteKind' => 'magento'], $lumi->start());
        $this->assertSame(self::TRIAL, $this->flag['lumiTrialId']);
    }

    public function testStartWithoutSiteKind(): void
    {
        $lumi = $this->lumi();
        $this->client->method('detectSite')->willReturn(['available' => true, 'siteKind' => 3]);
        $this->client->method('startTrial')->willReturn(self::TRIAL);
        $this->assertNull($lumi->start()['siteKind']);
    }

    public static function unavailable(): array
    {
        return [
            ['off', 'Lumi is paused'],
            ['quota', 'all of today\'s Lumi trials'],
            ['unreachable', 'could not open the shop'],
            ['url_blocked', 'the shop must be public'],
            [null, 'cannot prepare a Flow for this shop'],
        ];
    }

    #[DataProvider('unavailable')]
    public function testStartWhenLumiCannotTry(?string $reason, string $message): void
    {
        $lumi = $this->lumi();
        $this->client->method('detectSite')->willReturn(['available' => false, 'reason' => $reason]);
        $this->client->expects($this->never())->method('startTrial');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($message);
        $lumi->start();
    }

    public function testStartRefusedByTheApi(): void
    {
        $lumi = $this->lumi();
        $this->client->method('detectSite')->willReturn(['available' => true]);
        $this->client->method('startTrial')->willThrowException(new ApiException('Busy', 429, 'assistant_busy'));
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Lumi is very busy');
        $lumi->start();
    }

    public function testStatusOfAReadyTrial(): void
    {
        $lumi = $this->lumi();
        $this->client->method('getTrial')->with(self::TRIAL)->willReturn([
            'item' => [
                'status' => 'ready',
                'reason' => null,
                'proposals' => [
                    ['index' => 0, 'kind' => 'cart', 'steps' => [[], [], []], 'stoppedBefore' => 'Pay'],
                    'junk',
                    ['index' => 2, 'kind' => 'login', 'steps' => [[], []], 'savedMonitorId' => self::FLOW],
                    [],
                ],
            ],
        ]);
        $this->assertSame([
            'status' => 'ready',
            'done' => true,
            'reason' => null,
            'proposals' => [
                ['index' => 0, 'kind' => 'cart', 'steps' => 3, 'stoppedBefore' => 'Pay', 'saved' => false],
                ['index' => 2, 'kind' => 'login', 'steps' => 2, 'stoppedBefore' => null, 'saved' => true],
                ['index' => 0, 'kind' => 'custom', 'steps' => 0, 'stoppedBefore' => null, 'saved' => false],
            ],
        ], $lumi->status(self::TRIAL));
    }

    public function testStatusOfATrialInProgress(): void
    {
        $lumi = $this->lumi();
        $this->client->method('getTrial')->willReturn(['item' => 'broken']);
        $this->assertSame(
            ['status' => 'waiting', 'done' => false, 'reason' => null, 'proposals' => []],
            $lumi->status(self::TRIAL)
        );
    }

    public function testStatusOfAFailedTrial(): void
    {
        $lumi = $this->lumi();
        $this->client->method('getTrial')->willReturn(['item' => ['status' => 'failed', 'reason' => 'timeout']]);
        $status = $lumi->status(self::TRIAL);
        $this->assertTrue($status['done']);
        $this->assertSame('timeout', $status['reason']);
    }

    public function testStatusRefused(): void
    {
        $lumi = $this->lumi();
        $this->client->method('getTrial')->willThrowException(new ApiException('Essai introuvable', 404));
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Essai introuvable');
        $lumi->status(self::TRIAL);
    }

    public function testSaveRemembersTheFlow(): void
    {
        $lumi = $this->lumi(['flowIds' => ['old']]);
        $this->client->expects($this->once())->method('saveTrial')->with(self::TRIAL, 1)
            ->willReturn(['item' => ['id' => self::FLOW, 'name' => 'cart · shop.example']]);
        $this->status->expects($this->once())->method('clean');
        $this->assertSame(self::FLOW, $lumi->save(self::TRIAL, 1));
        $this->assertSame(['old', self::FLOW], $this->flag['flowIds']);
    }

    public function testSavingTwiceKeepsOneFlow(): void
    {
        $lumi = $this->lumi(['flowIds' => [self::FLOW]]);
        $this->client->method('saveTrial')->willThrowException(
            new ApiException('Already saved', 409, 'already_saved', ['monitorId' => self::FLOW])
        );
        $this->assertSame(self::FLOW, $lumi->save(self::TRIAL, 0));
        $this->assertSame([self::FLOW], $this->flag['flowIds']);
    }

    public function testAlreadySavedWithoutIdentifier(): void
    {
        $lumi = $this->lumi();
        $this->client->method('saveTrial')->willThrowException(new ApiException('Already saved', 409, 'already_saved'));
        $this->expectException(LocalizedException::class);
        $lumi->save(self::TRIAL, 0);
    }

    public function testPlanLimit(): void
    {
        $lumi = $this->lumi();
        $this->client->method('saveTrial')->willThrowException(
            new ApiException('Flow limit of the Solo plan', 403, 'PLAN_LIMIT', ['monitorId' => 'ignored'])
        );
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Your Pathly plan has reached its limit: Flow limit of the Solo plan');
        $lumi->save(self::TRIAL, 0);
    }

    public function testUnexpectedSaveAnswer(): void
    {
        $lumi = $this->lumi();
        $this->client->method('saveTrial')->willReturn([]);
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unexpected answer from Pathly.');
        $lumi->save(self::TRIAL, 0);
    }
}
