<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Pathly\Monitoring\Model\Replay;

/**
 * Run by Magento at the end of every bin/magento setup:upgrade.
 *
 * It only notes that the Flows must be replayed: the cron replays them once
 * the maintenance mode is off. Nothing is sent to Pathly here.
 */
class RecurringData implements InstallDataInterface
{
    /**
     * @param Replay $replay
     */
    public function __construct(private readonly Replay $replay)
    {
    }

    /**
     * @inheritdoc
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->replay->markPending('setup_upgrade');
    }
}
