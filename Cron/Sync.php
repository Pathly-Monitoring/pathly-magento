<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Cron;

use Pathly\Monitoring\Model\Sync as SyncModel;

/**
 * Cron job pathly_monitoring_sync, every 5 minutes.
 */
class Sync
{
    /**
     * @param SyncModel $sync
     */
    public function __construct(private readonly SyncModel $sync)
    {
    }

    /**
     * Run the synchronization.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->sync->run();
    }
}
