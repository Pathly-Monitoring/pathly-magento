<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\Client;
use Pathly\Monitoring\Model\Api\ClientFactory;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\ErrorMessage;
use Pathly\Monitoring\Model\Ping;
use Pathly\Monitoring\Model\State;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;

class PingTest extends TestCase
{
    use Helper;

    private Client $client;
    private State $state;

    private function ping(string $storeUrl): Ping
    {
        $this->client = $this->createMock(Client::class);
        $clients = $this->createMock(ClientFactory::class);
        $clients->method('create')->willReturn($this->client);
        $config = $this->createMock(Config::class);
        $config->method('getStoreUrl')->willReturn($storeUrl);
        $this->state = $this->memoryState();
        return new Ping($clients, $config, $this->state, new ErrorMessage());
    }

    public function testExistingPingIsAttachedWithoutDuplicate(): void
    {
        $ping = $this->ping('https://www.Shop.example:8443/fr/');
        $this->client->method('listScenarios')->willReturn([
            ['id' => 'browser', 'type' => 'browser', 'url' => null],
            ['id' => 'chain', 'type' => 'http', 'url' => 'http://shop.example:8443/fr', 'httpChain' => [['url' => 'x']]],
            ['id' => 'other', 'type' => 'http', 'url' => 'http://other.example/'],
            ['id' => 'broken', 'type' => 'http', 'url' => 'not a url'],
            ['id' => 'nourl', 'type' => 'http'],
            ['id' => 'mine', 'type' => 'http', 'url' => 'http://shop.example:8443/fr'],
        ]);
        $this->client->expects($this->never())->method('createScenario');
        $this->assertSame('mine', $ping->ensure('sp_key_12345'));
        $this->assertSame('mine', $this->state->get('pingId'));
        $this->assertSame('https://www.Shop.example:8443/fr/', $this->state->get('pingUrl'));
        $this->assertNull($this->state->get('pingError'));
        $this->assertIsInt($this->state->get('pingAttemptAt'));
    }

    public function testPingIsCreatedWithAnIdempotencyKey(): void
    {
        $ping = $this->ping('https://shop.example/');
        $this->client->method('listScenarios')->willReturn([]);
        $this->client->expects($this->once())->method('createScenario')->with(
            [
                'name' => 'Ping · shop.example',
                'type' => 'http',
                'url' => 'https://shop.example/',
                'method' => 'GET',
                'intervalSec' => 300,
            ],
            $this->matchesRegularExpression('/^magento-ping-\d{8}-[0-9a-f]{20}$/')
        )->willReturn(['id' => 'new']);
        $this->assertSame('new', $ping->ensure());
    }

    public function testAddressThatIsNotAUrlIsNeverMatched(): void
    {
        $ping = $this->ping('shop');
        $this->client->method('listScenarios')->willReturn([['id' => 'x', 'type' => 'http', 'url' => 'shop']]);
        $this->client->expects($this->once())->method('createScenario')
            ->with($this->callback(fn ($data) => $data['name'] === 'Ping · shop'))
            ->willReturn(['id' => 'new']);
        $this->assertSame('new', $ping->ensure());
    }

    public function testUnexpectedAnswerIsKeptAsError(): void
    {
        $ping = $this->ping('https://shop.example/');
        $this->client->method('listScenarios')->willReturn([]);
        $this->client->method('createScenario')->willReturn([]);
        $this->assertNull($ping->ensure());
        $this->assertSame('Unexpected answer from Pathly.', $this->state->get('pingError'));
        $this->assertNull($this->state->get('pingId'));
    }

    public function testRefusalIsKeptAsError(): void
    {
        $ping = $this->ping('https://shop.example/');
        $this->client->method('listScenarios')->willThrowException(new ApiException('No', 401));
        $this->assertNull($ping->ensure());
        $this->assertStringContainsString('refused the API key', $this->state->get('pingError'));
    }
}
