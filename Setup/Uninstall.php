<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

/**
 * bin/magento module:uninstall Pathly_Monitoring (module installed with Composer).
 */
class Uninstall implements UninstallInterface
{
    /**
     * @param Cleanup $cleanup
     */
    public function __construct(private readonly Cleanup $cleanup)
    {
    }

    /**
     * @inheritdoc
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->cleanup->run($setup->getConnection(), fn (string $name) => $setup->getTable($name));
    }
}
