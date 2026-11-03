<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Pathly\Monitoring\Setup\Cleanup;

/**
 * Nothing to install. Reverted by bin/magento module:uninstall, including
 * --non-composer for a module copied into app/code: the data of the module
 * is deleted.
 */
class RemoveDataOnUninstall implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @param ModuleDataSetupInterface $setup
     * @param Cleanup $cleanup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $setup,
        private readonly Cleanup $cleanup
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function revert()
    {
        $this->cleanup->run($this->setup->getConnection(), fn (string $name) => $this->setup->getTable($name));
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
