<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Console\Command;

use Magento\Framework\Serialize\Serializer\Json;
use Pathly\Monitoring\Model\Sync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento pathly:sync [--replay]: what the cron does, now.
 */
class SyncCommand extends Command
{
    /**
     * @param Sync $sync
     * @param Json $json
     * @param string|null $name
     */
    public function __construct(
        private readonly Sync $sync,
        private readonly Json $json,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('pathly:sync')
            ->setDescription('Pathly: attach the Ping, replay the Flows after an update, refresh the badge.')
            ->addOption('replay', null, InputOption::VALUE_NONE, 'Replay the Flows of the shop now.');
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $summary = $this->sync->run((bool) $input->getOption('replay'));
        $output->writeln($this->json->serialize($summary));
        return empty($summary['connected']) ? 1 : 0;
    }
}
