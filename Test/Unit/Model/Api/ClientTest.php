<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model\Api;

use InvalidArgumentException;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\Client;
use Pathly\Monitoring\Test\Unit\FakeTransport;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private const ID = '0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab';

    private FakeTransport $transport;
    private Client $client;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->client = new Client($this->transport, 'sp_secret_key_123', 'pathly-magento/1.0.0', 7);
    }

    public function testAccountSendsKeyAndUserAgentToTheApiOnly(): void
    {
        $this->transport->answer(200, ['organization' => ['name' => 'Shop']]);
        $this->assertSame(['organization' => ['name' => 'Shop']], $this->client->getAccount());
        $request = $this->transport->requests[0];
        $this->assertSame('GET', $request['method']);
        $this->assertSame('https://api.pathlyhq.com/v1/account', $request['url']);
        $this->assertSame('Bearer sp_secret_key_123', $request['headers']['Authorization']);
        $this->assertSame('pathly-magento/1.0.0', $request['headers']['User-Agent']);
        $this->assertArrayNotHasKey('Content-Type', $request['headers']);
        $this->assertNull($request['body']);
        $this->assertSame(7, $request['timeout']);
    }

    public function testListScenariosFollowsCursors(): void
    {
        $this->transport
            ->answer(200, ['items' => [['id' => 'a'], 'junk'], 'nextCursor' => 'c2'])
            ->answer(200, ['items' => [['id' => 'b']], 'nextCursor' => ''])
            ->answer(200, ['items' => [['id' => 'never']]]);
        $this->assertSame([['id' => 'a'], ['id' => 'b']], $this->client->listScenarios());
        $this->assertSame('https://api.pathlyhq.com/v1/scenarios?limit=100', $this->transport->requests[0]['url']);
        $this->assertSame(
            'https://api.pathlyhq.com/v1/scenarios?limit=100&cursor=c2',
            $this->transport->requests[1]['url']
        );
        $this->assertCount(2, $this->transport->requests);
    }

    public function testListScenariosStopsAfterTwentyPages(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->transport->answer(200, ['items' => 'not a list', 'nextCursor' => 'next']);
        }
        $this->assertSame([], $this->client->listScenarios());
        $this->assertCount(20, $this->transport->requests);
    }

    public function testScenarioRoutes(): void
    {
        $this->transport
            ->answer(200, ['id' => self::ID])
            ->answer(201, ['id' => self::ID])
            ->answer(201, ['id' => self::ID])
            ->answer(200, ['ok' => true]);
        $this->client->getScenario(strtoupper(self::ID));
        $this->client->createScenario(['name' => 'Ping'], 'magento-ping-key');
        $this->client->createScenario(['name' => 'Ping']);
        $this->client->runScenario(self::ID);
        [$get, $create, $plain, $run] = $this->transport->requests;
        $this->assertSame('https://api.pathlyhq.com/v1/scenarios/' . self::ID, $get['url']);
        $this->assertSame('POST', $create['method']);
        $this->assertSame('magento-ping-key', $create['headers']['Idempotency-Key']);
        $this->assertSame('application/json', $create['headers']['Content-Type']);
        $this->assertSame('{"name":"Ping"}', $create['body']);
        $this->assertArrayNotHasKey('Idempotency-Key', $plain['headers']);
        $this->assertSame('https://api.pathlyhq.com/v1/scenarios/' . self::ID . '/run', $run['url']);
        $this->assertSame('{}', $run['body']);
    }

    public function testRuns(): void
    {
        $this->transport
            ->answer(200, ['items' => [['id' => 'r1']]])
            ->answer(200, ['id' => self::ID, 'screenshotUrl' => null]);
        $this->assertSame([['id' => 'r1']], $this->client->listRuns(self::ID, 5));
        $this->assertSame(['id' => self::ID, 'screenshotUrl' => null], $this->client->getRun(self::ID));
        $this->assertSame(
            'https://api.pathlyhq.com/v1/runs?scenarioId=' . self::ID . '&limit=5',
            $this->transport->requests[0]['url']
        );
        $this->assertSame('https://api.pathlyhq.com/v1/runs/' . self::ID, $this->transport->requests[1]['url']);
    }

    public function testLumiRoutes(): void
    {
        $this->transport
            ->answer(200, ['available' => true])
            ->answer(202, ['id' => self::ID])
            ->answer(200, ['item' => ['status' => 'ready']])
            ->answer(201, ['item' => ['id' => self::ID]]);
        $this->assertSame(['available' => true], $this->client->detectSite('https://shop.example/'));
        $this->assertSame(self::ID, $this->client->startTrial('https://shop.example/'));
        $this->client->getTrial(self::ID);
        $this->client->saveTrial(self::ID, 2);
        [$detect, $trial, $get, $save] = $this->transport->requests;
        $this->assertSame('{"url":"https://shop.example/"}', $detect['body']);
        $this->assertSame('{"url":"https://shop.example/","confirmOwner":true}', $trial['body']);
        $this->assertSame('https://api.pathlyhq.com/v1/flow-assistant/trials/' . self::ID, $get['url']);
        $this->assertSame('https://api.pathlyhq.com/v1/flow-assistant/trials/' . self::ID . '/save', $save['url']);
        $this->assertSame('{"index":2}', $save['body']);
    }

    public function testStartTrialRefusesAnAnswerWithoutIdentifier(): void
    {
        $this->transport->answer(202, []);
        $this->expectException(InvalidArgumentException::class);
        $this->client->startTrial('https://shop.example/');
    }

    public function testStatusPages(): void
    {
        $this->transport
            ->answer(200, ['items' => [['id' => self::ID]]])
            ->answer(201, ['id' => self::ID])
            ->answer(200, ['id' => self::ID]);
        $this->assertSame([['id' => self::ID]], $this->client->listStatusPages());
        $this->client->createStatusPage(true);
        $this->client->updateStatusPage(self::ID, false);
        [, $create, $patch] = $this->transport->requests;
        $this->assertSame('{"public":true}', $create['body']);
        $this->assertSame('PATCH', $patch['method']);
        $this->assertSame('{"public":false}', $patch['body']);
    }

    public function testBadgeIsDownloadedWithoutTheKey(): void
    {
        $url = 'https://api.pathlyhq.com/public/status/shop/space/badge.svg';
        $this->transport->answer(200, " <?xml version=\"1.0\"?>\n<svg xmlns=\"http://www.w3.org/2000/svg\"></svg>");
        $this->assertStringStartsWith('<?xml', $this->client->fetchBadge($url));
        $request = $this->transport->requests[0];
        $this->assertSame($url, $request['url']);
        $this->assertArrayNotHasKey('Authorization', $request['headers']);
    }

    public function testBadgeFromAnotherHostIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client->fetchBadge('https://evil.example/public/status/shop/badge.svg');
    }

    public function testBadgeMustBeSvg(): void
    {
        $this->transport->answer(200, '<html>no</html>');
        $this->expectException(ApiException::class);
        $this->client->fetchBadge('https://api.pathlyhq.com/public/status/shop/badge.svg');
    }

    public function testBadgeMustBeFound(): void
    {
        $this->transport->answer(404, '<svg></svg>');
        $this->expectException(ApiException::class);
        $this->client->fetchBadge('https://api.pathlyhq.com/public/status/shop/badge.svg');
    }

    public function testInvalidIdentifierNeverReachesThePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->client->getScenario('../../admin');
        } finally {
            $this->assertSame([], $this->transport->requests);
        }
    }

    public function testErrorWithCodeAndRetryAfter(): void
    {
        $this->transport->answer(
            429,
            ['error' => 'Lumi is busy', 'code' => 'assistant_busy'],
            ['retry-after' => '60']
        );
        try {
            $this->client->startTrial('https://shop.example/');
            $this->fail('Expected an exception');
        } catch (ApiException $e) {
            $this->assertSame('Lumi is busy', $e->getMessage());
            $this->assertSame(429, $e->getStatus());
            $this->assertSame('assistant_busy', $e->getErrorCode());
            $this->assertSame(60, $e->getRetryAfter());
            $this->assertSame(['error' => 'Lumi is busy', 'code' => 'assistant_busy'], $e->getBody());
        }
    }

    public function testErrorWithoutBody(): void
    {
        $this->transport->answer(502, 'Bad gateway', ['retry-after' => 'soon']);
        try {
            $this->client->getAccount();
            $this->fail('Expected an exception');
        } catch (ApiException $e) {
            $this->assertSame('Pathly answered with HTTP 502.', $e->getMessage());
            $this->assertNull($e->getErrorCode());
            $this->assertNull($e->getRetryAfter());
            $this->assertSame([], $e->getBody());
        }
    }

    public function testSuccessWithoutJson(): void
    {
        $this->transport->answer(200, 'not json');
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Unexpected answer from Pathly.');
        $this->client->getAccount();
    }
}
