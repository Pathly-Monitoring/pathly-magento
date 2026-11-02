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
use Pathly\Monitoring\Block\Widget\Badge;

/**
 * bin/magento module:uninstall Pathly_Monitoring: settings, state, scheduled
 * jobs and badge widgets are deleted. The Ping and the Flows stay in the
 * Pathly account, where they can be deleted.
 */
class Uninstall implements UninstallInterface
{
    /**
     * @inheritdoc
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $connection = $setup->getConnection();
        $connection->delete($setup->getTable('core_config_data'), ['path LIKE ?' => 'pathly_monitoring/%']);
        $connection->delete($setup->getTable('flag'), ['flag_code LIKE ?' => 'pathly_monitoring%']);
        $connection->delete($setup->getTable('cron_schedule'), ['job_code LIKE ?' => 'pathly_monitoring%']);
        $connection->delete($setup->getTable('widget_instance'), ['instance_type = ?' => Badge::class]);
    }
}
