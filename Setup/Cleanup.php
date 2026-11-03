<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Pathly\Monitoring\Block\Widget\Badge;

/**
 * Everything the module leaves in the database: settings, state, scheduled
 * jobs and badge widgets. The Ping and the Flows stay in the Pathly account,
 * where they can be deleted.
 */
class Cleanup
{
    /**
     * Delete it.
     *
     * @param AdapterInterface $connection
     * @param callable $table Real name of a table (with its prefix)
     * @return void
     */
    public function run(AdapterInterface $connection, callable $table): void
    {
        $connection->delete($table('core_config_data'), ['path LIKE ?' => 'pathly_monitoring/%']);
        $connection->delete($table('flag'), ['flag_code LIKE ?' => 'pathly_monitoring%']);
        $connection->delete($table('cron_schedule'), ['job_code LIKE ?' => 'pathly_monitoring%']);
        $connection->delete($table('widget_instance'), ['instance_type = ?' => Badge::class]);
    }
}
