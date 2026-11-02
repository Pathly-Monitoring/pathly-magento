<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\PackageInfo;

/**
 * Fingerprint of what is installed: Magento version and edition, enabled
 * modules and the version of their package. It changes when Magento or a
 * module is updated, enabled or disabled. It stays in the shop.
 */
class Fingerprint
{
    /**
     * @param ProductMetadataInterface $productMetadata
     * @param ModuleListInterface $moduleList
     * @param PackageInfo $packageInfo
     */
    public function __construct(
        private readonly ProductMetadataInterface $productMetadata,
        private readonly ModuleListInterface $moduleList,
        private readonly PackageInfo $packageInfo
    ) {
    }

    /**
     * Current fingerprint.
     *
     * @return string
     */
    public function current(): string
    {
        $parts = [$this->productMetadata->getEdition() . ' ' . $this->productMetadata->getVersion()];
        $names = $this->moduleList->getNames();
        sort($names);
        foreach ($names as $name) {
            $parts[] = $name . '@' . $this->packageInfo->getVersion($name);
        }
        return sha1(implode("\n", $parts));
    }
}
