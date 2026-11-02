<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model\Api;

use Magento\Framework\App\ProductMetadataInterface;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\ClientFactory;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\UserAgent;
use Pathly\Monitoring\Test\Unit\FakeTransport;
use PHPUnit\Framework\TestCase;

class ClientFactoryTest extends TestCase
{
    private FakeTransport $transport;
    private Config $config;
    private ClientFactory $factory;

    protected function setUp(): void
    {
        $this->transport = new FakeTransport();
        $this->config = $this->createMock(Config::class);
        $metadata = $this->createMock(ProductMetadataInterface::class);
        $metadata->method('getVersion')->willReturn('2.4.8-p3');
        $metadata->method('getEdition')->willReturn('Community');
        $this->factory = new ClientFactory($this->transport, $this->config, new UserAgent($metadata));
    }

    public function testKeyBeingSavedWins(): void
    {
        $this->config->expects($this->never())->method('getApiKey');
        $this->transport->answer(200, []);
        $this->factory->create('sp_new_key_1234', 3)->getAccount();
        $request = $this->transport->requests[0];
        $this->assertSame('Bearer sp_new_key_1234', $request['headers']['Authorization']);
        $this->assertSame('pathly-magento/1.0.0 Magento/2.4.8-p3 (Community)', $request['headers']['User-Agent']);
        $this->assertSame(3, $request['timeout']);
    }

    public function testStoredKey(): void
    {
        $this->config->method('getApiKey')->willReturn('sp_stored_key_1');
        $this->transport->answer(200, []);
        $this->factory->create()->getAccount();
        $this->assertSame('Bearer sp_stored_key_1', $this->transport->requests[0]['headers']['Authorization']);
        $this->assertSame(10, $this->transport->requests[0]['timeout']);
    }

    public function testNothingIsSentWithoutKey(): void
    {
        $this->config->method('getApiKey')->willReturn('');
        try {
            $this->factory->create();
            $this->fail('Expected an exception');
        } catch (ApiException $e) {
            $this->assertSame('not_configured', $e->getErrorCode());
            $this->assertSame(0, $e->getStatus());
        }
        $this->assertSame([], $this->transport->requests);
    }
}
