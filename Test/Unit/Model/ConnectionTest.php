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
use Pathly\Monitoring\Model\Connection;
use Pathly\Monitoring\Model\Dashboard\StatusProvider;
use Pathly\Monitoring\Model\ErrorMessage;
use Pathly\Monitoring\Model\Ping;
use Pathly\Monitoring\Model\State;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    use Helper;

    private const KEY = 'sp_AbCdEf123456-_';

    private Client $client;
    private ClientFactory $clients;
    private State $state;
    private Ping $ping;
    private StatusProvider $status;

    private function connection(array $state = []): Connection
    {
        $this->client = $this->createMock(Client::class);
        $this->clients = $this->createMock(ClientFactory::class);
        $this->clients->method('create')->willReturn($this->client);
        $this->state = $this->memoryState($state);
        $this->ping = $this->createMock(Ping::class);
        $this->status = $this->createMock(StatusProvider::class);
        return new Connection($this->clients, $this->state, $this->ping, new ErrorMessage(), $this->status);
    }

    public function testValidKey(): void
    {
        $connection = $this->connection();
        $this->clients->expects($this->once())->method('create')->with(self::KEY);
        $this->client->method('getAccount')->willReturn(['plan' => ['name' => 'Pro']]);
        $this->assertSame(['plan' => ['name' => 'Pro']], $connection->validate(self::KEY));
    }

    public function testSomethingElseThanAKey(): void
    {
        $connection = $this->connection();
        $this->clients->expects($this->never())->method('create');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('This is not a Pathly API key');
        $connection->validate('password123');
    }

    public function testRefusedKey(): void
    {
        $connection = $this->connection();
        $this->client->method('getAccount')->willThrowException(new ApiException('Unauthorized', 401));
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The key was not saved. Pathly refused the API key');
        $connection->validate(self::KEY);
    }

    public function testConnectWithANewKeyForgetsThePreviousAccount(): void
    {
        $connection = $this->connection(['keyHash' => 'old', 'pingId' => 'old-ping', 'flowIds' => ['f']]);
        $this->ping->expects($this->once())->method('ensure')->with(self::KEY);
        $this->status->expects($this->once())->method('clean');
        $connection->connect(self::KEY, [
            'organization' => ['name' => 'Shop'],
            'space' => ['name' => 'Client A'],
            'plan' => ['name' => 'Pro'],
        ]);
        $this->assertNull($this->state->get('pingId'));
        $this->assertNull($this->state->get('flowIds'));
        $this->assertSame('Shop', $this->state->get('organization'));
        $this->assertSame('Client A', $this->state->get('space'));
        $this->assertSame('Pro', $this->state->get('plan'));
        $this->assertSame(16, strlen($this->state->get('keyHash')));
        $this->assertStringNotContainsString(self::KEY, json_encode($this->flag));
    }

    public function testConnectWithTheSameKeyKeepsTheFlows(): void
    {
        $hash = substr(hash('sha256', self::KEY), 0, 16);
        $connection = $this->connection(['keyHash' => $hash, 'flowIds' => ['f']]);
        $connection->connect(self::KEY);
        $this->assertSame(['f'], $this->state->get('flowIds'));
        $this->assertSame('', $this->state->get('organization'));
    }

    public function testDisconnect(): void
    {
        $connection = $this->connection(['pingId' => 'p']);
        $this->status->expects($this->once())->method('clean');
        $connection->disconnect();
        $this->assertSame([], $this->flag);
    }
}
