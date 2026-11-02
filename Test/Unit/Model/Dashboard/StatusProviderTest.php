<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model\Dashboard;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\Client;
use Pathly\Monitoring\Model\Api\ClientFactory;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\Dashboard\StatusProvider;
use Pathly\Monitoring\Model\ErrorMessage;
use Pathly\Monitoring\Model\Flows;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;

class StatusProviderTest extends TestCase
{
    use Helper;

    private const PING = '0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab';
    private const FLOW_A = '11111111-2222-3333-4444-555555555555';
    private const FLOW_B = '22222222-3333-4444-5555-666666666666';
    private const FLOW_C = '33333333-4444-5555-6666-777777777777';
    private const RUN = '44444444-5555-6666-7777-888888888888';

    private bool $connected = true;
    private ?string $cached = null;
    private ?string $saved = null;
    private Client $client;
    private ClientFactory $clients;
    private CacheInterface $cache;

    private function provider(array $state = []): StatusProvider
    {
        $config = $this->createMock(Config::class);
        $config->method('isConnected')->willReturnCallback(fn () => $this->connected);
        $config->method('getExtraFlowIds')->willReturn([]);
        $this->client = $this->createMock(Client::class);
        $this->clients = $this->createMock(ClientFactory::class);
        $this->clients->method('create')->willReturn($this->client);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->method('load')->willReturnCallback(fn () => $this->cached);
        $this->cache->method('save')->willReturnCallback(function ($data) {
            $this->saved = $data;
            return true;
        });
        $memory = $this->memoryState($state);
        return new StatusProvider(
            $this->clients,
            $config,
            $memory,
            new Flows($memory, $config),
            new ErrorMessage(),
            $this->cache,
            new Json()
        );
    }

    public function testNotConnected(): void
    {
        $this->connected = false;
        $provider = $this->provider();
        $this->clients->expects($this->never())->method('create');
        $this->assertSame(['connected' => false], $provider->get());
    }

    public function testCachedCopy(): void
    {
        $provider = $this->provider();
        $this->cached = '{"connected":true,"from":"cache"}';
        $this->clients->expects($this->never())->method('create');
        $this->assertSame(['connected' => true, 'from' => 'cache'], $provider->get());
    }

    public function testFullPanel(): void
    {
        $provider = $this->provider([
            'pingId' => self::PING,
            'flowIds' => [self::FLOW_A, self::FLOW_B, self::FLOW_C],
            'lastReplay' => ['at' => 1],
        ]);
        $this->cached = '"not an array"';
        $this->clients->expects($this->once())->method('create')->with(null, 5);
        $this->client->method('getAccount')->willReturn([
            'organization' => ['name' => 'Shop'],
            'space' => null,
            'plan' => ['name' => 'Pro'],
            'billingUrl' => 'https://pathlyhq.com/billing',
            'quotas' => [
                'ping' => ['used' => 1, 'limit' => 50],
                'flow' => ['used' => 2, 'limit' => null],
                'runs' => 'broken',
            ],
        ]);
        $this->client->method('getScenario')->willReturnCallback(fn ($id) => [
            self::PING => ['name' => 'Ping · shop', 'lastStatus' => 'fail'],
            self::FLOW_A => ['name' => 'cart', 'lastStatus' => 'ok'],
            self::FLOW_B => ['name' => 'login', 'lastStatus' => 'fail'],
            self::FLOW_C => ['name' => 'new', 'lastStatus' => 7],
        ][$id]);
        $this->client->method('listRuns')->willReturnCallback(fn ($id) => [
            self::PING => [
                ['id' => 'ok', 'status' => 'ok', 'created_at' => '2026-11-02T12:00:00Z'],
                ['id' => 'old', 'status' => 'fail', 'created_at' => '2026-11-01T10:00:00Z'],
            ],
            self::FLOW_B => [
                ['id' => self::RUN, 'status' => 'fail', 'created_at' => '2026-11-02T09:00:00Z', 'message' => 'No cart'],
                ['id' => 'older', 'status' => 'fail', 'created_at' => '2026-10-01T09:00:00Z'],
            ],
        ][$id]);
        $this->client->expects($this->once())->method('getRun')->with(self::RUN)
            ->willReturn(['screenshotUrl' => 'https://api.pathlyhq.com/public/run-shot/abc.123.sig']);

        $panel = $provider->get();

        $this->assertNull($panel['error']);
        $this->assertSame([
            'organization' => 'Shop',
            'space' => '',
            'plan' => 'Pro',
            'billingUrl' => 'https://pathlyhq.com/billing',
            'quotas' => ['ping' => ['used' => 1, 'limit' => 50], 'flow' => ['used' => 2, 'limit' => null]],
        ], $panel['account']);
        $this->assertSame('fail', $panel['ping']['status']);
        $this->assertSame('https://pathlyhq.com/monitors/' . self::PING, $panel['ping']['url']);
        $this->assertSame([null, 'ok', 'fail'], [$panel['flows'][2]['status'], $panel['flows'][0]['status'], $panel['flows'][1]['status']]);
        $this->assertSame([
            'runId' => self::RUN,
            'scenario' => 'login',
            'url' => 'https://pathlyhq.com/monitors/' . self::FLOW_B,
            'at' => '2026-11-02T09:00:00Z',
            'message' => 'No cart',
            'screenshotUrl' => 'https://api.pathlyhq.com/public/run-shot/abc.123.sig',
        ], $panel['lastFailure']);
        $this->assertSame(['at' => 1], $panel['lastReplay']);
        $this->assertSame($panel, json_decode((string) $this->saved, true));
    }

    public function testScreenshotFromAnotherHostIsDropped(): void
    {
        $provider = $this->provider(['flowIds' => [self::FLOW_A]]);
        $this->client->method('getAccount')->willReturn(['billingUrl' => 'https://evil.example/']);
        $this->client->method('getScenario')->willReturn(['name' => 'cart', 'lastStatus' => 'error']);
        $this->client->method('listRuns')->willReturn([['id' => self::RUN, 'status' => 'error']]);
        $this->client->method('getRun')->willReturn(['screenshotUrl' => 'https://evil.example/x.png']);
        $panel = $provider->get();
        $this->assertSame('https://pathlyhq.com/billing', $panel['account']['billingUrl']);
        $this->assertNull($panel['ping']);
        $this->assertNull($panel['lastFailure']['screenshotUrl']);
        $this->assertSame('', $panel['lastFailure']['at']);
    }

    public function testFailureWithoutRunIdentifier(): void
    {
        $provider = $this->provider(['pingId' => self::PING]);
        $this->client->method('getAccount')->willReturn([]);
        $this->client->method('getScenario')->willReturn(['lastStatus' => 'fail']);
        $this->client->method('listRuns')->willReturn([['status' => 'fail', 'created_at' => '2026-11-02']]);
        $this->client->expects($this->never())->method('getRun');
        $panel = $provider->get();
        $this->assertSame('', $panel['lastFailure']['runId']);
        $this->assertSame('', $panel['ping']['name']);
    }

    public function testPingNotRunYetAndNoFailure(): void
    {
        $provider = $this->provider(['pingId' => 'not-an-id', 'pingError' => 'Plan limit']);
        $this->client->method('getAccount')->willReturn([]);
        $this->client->expects($this->never())->method('listRuns');
        $panel = $provider->get();
        $this->assertNull($panel['ping']);
        $this->assertSame('Plan limit', $panel['pingError']);
        $this->assertNull($panel['lastFailure']);
    }

    public function testPingOkOrNeverRun(): void
    {
        $provider = $this->provider(['pingId' => self::PING]);
        $this->client->method('getAccount')->willReturn([]);
        $this->client->method('getScenario')->willReturnOnConsecutiveCalls(['lastStatus' => 'ok'], ['lastStatus' => null]);
        $this->client->expects($this->never())->method('listRuns');
        $this->assertSame('ok', $provider->get()['ping']['status']);
        $this->cached = null;
        $this->assertNull($provider->get()['ping']['status']);
    }

    public function testApiErrorIsShown(): void
    {
        $provider = $this->provider();
        $this->client->method('getAccount')->willThrowException(new ApiException('No', 401));
        $this->assertStringContainsString('refused the API key', $provider->get()['error']);
    }

    public function testClean(): void
    {
        $provider = $this->provider();
        $this->cache->expects($this->once())->method('remove')->with(StatusProvider::CACHE_KEY);
        $provider->clean();
    }
}
