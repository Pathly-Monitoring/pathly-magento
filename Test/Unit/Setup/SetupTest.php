<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Pathly\Monitoring\Block\Widget\Badge;
use Pathly\Monitoring\Console\Command\SyncCommand;
use Pathly\Monitoring\Cron\Sync as SyncCron;
use Pathly\Monitoring\Model\Replay;
use Pathly\Monitoring\Model\Sync;
use Pathly\Monitoring\Setup\RecurringData;
use Pathly\Monitoring\Setup\Uninstall;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SetupTest extends TestCase
{
    public function testSetupUpgradeOnlyAsksForAReplay(): void
    {
        $replay = $this->createMock(Replay::class);
        $replay->expects($this->once())->method('markPending')->with('setup_upgrade');
        $replay->expects($this->never())->method('check');
        (new RecurringData($replay))->install(
            $this->createMock(ModuleDataSetupInterface::class),
            $this->createMock(ModuleContextInterface::class)
        );
    }

    public function testUninstallDeletesSettingsStateJobsAndWidgets(): void
    {
        $deleted = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('delete')->willReturnCallback(function ($table, $where) use (&$deleted) {
            $deleted[$table] = $where;
            return 1;
        });
        $setup = $this->createMock(SchemaSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(fn ($name) => 'm_' . $name);
        (new Uninstall())->uninstall($setup, $this->createMock(ModuleContextInterface::class));
        $this->assertSame([
            'm_core_config_data' => ['path LIKE ?' => 'pathly_monitoring/%'],
            'm_flag' => ['flag_code LIKE ?' => 'pathly_monitoring%'],
            'm_cron_schedule' => ['job_code LIKE ?' => 'pathly_monitoring%'],
            'm_widget_instance' => ['instance_type = ?' => Badge::class],
        ], $deleted);
    }

    public function testCronRunsTheSynchronization(): void
    {
        $sync = $this->createMock(Sync::class);
        $sync->expects($this->once())->method('run')->with(false);
        (new SyncCron($sync))->execute();
    }

    public function testCommand(): void
    {
        $sync = $this->createMock(Sync::class);
        $sync->method('run')->willReturnCallback(fn ($force) => ['connected' => true, 'forced' => $force]);
        $tester = new CommandTester(new SyncCommand($sync, new Json()));
        $this->assertSame(0, $tester->execute(['--replay' => true]));
        $this->assertSame('{"connected":true,"forced":true}', trim($tester->getDisplay()));
        $this->assertSame('pathly:sync', (new SyncCommand($sync, new Json()))->getName());
    }

    public function testCommandBeforeConnection(): void
    {
        $sync = $this->createMock(Sync::class);
        $sync->method('run')->willReturn(['connected' => false]);
        $tester = new CommandTester(new SyncCommand($sync, new Json()));
        $this->assertSame(1, $tester->execute([]));
    }
}
