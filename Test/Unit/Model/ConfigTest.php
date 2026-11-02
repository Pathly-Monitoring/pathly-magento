<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Pathly\Monitoring\Model\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private array $values = [];
    private ?Store $store = null;

    private function config(): Config
    {
        $scope = $this->createMock(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(fn ($path) => $this->values[$path] ?? null);
        $scope->method('isSetFlag')->willReturnCallback(fn ($path) => !empty($this->values[$path]));
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(fn ($value) => $value === 'encrypted' ? ' sp_key_1234 ' : '');
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getDefaultStoreView')->willReturnCallback(fn () => $this->store);
        return new Config($scope, $encryptor, $stores);
    }

    public function testKeyIsDecrypted(): void
    {
        $this->values[Config::PATH_API_KEY] = 'encrypted';
        $this->assertSame('sp_key_1234', $this->config()->getApiKey());
        $this->assertTrue($this->config()->isConnected());
    }

    public function testNoKey(): void
    {
        $this->assertSame('', $this->config()->getApiKey());
        $this->assertFalse($this->config()->isConnected());
    }

    public function testFlags(): void
    {
        $this->assertFalse($this->config()->isReplayEnabled());
        $this->assertFalse($this->config()->isBadgeEnabled());
        $this->values[Config::PATH_REPLAY] = '1';
        $this->values[Config::PATH_BADGE] = '1';
        $this->assertTrue($this->config()->isReplayEnabled());
        $this->assertTrue($this->config()->isBadgeEnabled());
    }

    public function testExtraFlowIdsKeepOnlyIdentifiers(): void
    {
        $this->assertSame([], $this->config()->getExtraFlowIds());
        $this->values[Config::PATH_EXTRA_FLOWS] = "0B6C8F8E-1A2B-4C3D-8E9F-0123456789AB, https://evil.example;\n"
            . '0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab 11111111-2222-3333-4444-555555555555';
        $this->assertSame(
            ['0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab', '11111111-2222-3333-4444-555555555555'],
            $this->config()->getExtraFlowIds()
        );
    }

    public function testStoreUrlOfTheDefaultStoreView(): void
    {
        $this->store = $this->createMock(Store::class);
        $this->store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_WEB, true)->willReturn('https://shop.example/');
        $this->assertSame('https://shop.example/', $this->config()->getStoreUrl());
    }

    public function testStoreUrlFallsBackOnTheSetting(): void
    {
        $this->values['web/secure/base_url'] = 'https://fallback.example/';
        $this->assertSame('https://fallback.example/', $this->config()->getStoreUrl());
        $this->store = $this->createMock(Store::class);
        $this->store->method('getBaseUrl')->willReturn('');
        $this->assertSame('https://fallback.example/', $this->config()->getStoreUrl());
    }
}
