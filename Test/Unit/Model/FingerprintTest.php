<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\PackageInfo;
use Pathly\Monitoring\Model\Fingerprint;
use Pathly\Monitoring\Model\UserAgent;
use PHPUnit\Framework\TestCase;

class FingerprintTest extends TestCase
{
    private function fingerprint(string $magento, array $modules): string
    {
        $metadata = $this->createMock(ProductMetadataInterface::class);
        $metadata->method('getVersion')->willReturn($magento);
        $metadata->method('getEdition')->willReturn('Community');
        $list = $this->createMock(ModuleListInterface::class);
        $list->method('getNames')->willReturn(array_keys($modules));
        $packages = $this->createMock(PackageInfo::class);
        $packages->method('getVersion')->willReturnCallback(fn ($name) => $modules[$name]);
        return (new Fingerprint($metadata, $list, $packages))->current();
    }

    public function testChangesWithAnyVersion(): void
    {
        $base = $this->fingerprint('2.4.8', ['Magento_Catalog' => '104.0.8', 'Vendor_Pay' => '1.0.0']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $base);
        $this->assertSame($base, $this->fingerprint('2.4.8', ['Vendor_Pay' => '1.0.0', 'Magento_Catalog' => '104.0.8']));
        $this->assertNotSame($base, $this->fingerprint('2.4.8', ['Magento_Catalog' => '104.0.8', 'Vendor_Pay' => '1.0.1']));
        $this->assertNotSame($base, $this->fingerprint('2.4.8-p1', ['Magento_Catalog' => '104.0.8', 'Vendor_Pay' => '1.0.0']));
        $this->assertNotSame($base, $this->fingerprint('2.4.8', ['Magento_Catalog' => '104.0.8']));
    }

    public function testUserAgentNamesOnlyTheVersions(): void
    {
        $metadata = $this->createMock(ProductMetadataInterface::class);
        $metadata->method('getVersion')->willReturn('2.4.6-p15');
        $metadata->method('getEdition')->willReturn('Enterprise');
        $this->assertSame('pathly-magento/1.0.0 Magento/2.4.6-p15 (Enterprise)', (new UserAgent($metadata))->get());
    }
}
