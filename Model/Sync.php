<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use InvalidArgumentException;
use Pathly\Monitoring\Model\Api\ApiException;
use Psr\Log\LoggerInterface;

/**
 * Work of the cron, every 5 minutes, and of bin/magento pathly:sync.
 *
 * Nothing happens before an API key is saved. Then: retry the Ping if its
 * creation failed (at most once an hour), replay the Flows if an update asked
 * for it, refresh the badge if it is on.
 */
class Sync
{
    public const PING_RETRY_SEC = 3600;

    /**
     * @param Config $config
     * @param State $state
     * @param Ping $ping
     * @param Replay $replay
     * @param Badge $badge
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly State $state,
        private readonly Ping $ping,
        private readonly Replay $replay,
        private readonly Badge $badge,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run once.
     *
     * @param bool $forceReplay Replay the Flows now
     * @return array Summary, for the command line
     */
    public function run(bool $forceReplay = false): array
    {
        if (!$this->config->isConnected()) {
            return ['connected' => false];
        }
        $summary = ['connected' => true, 'ping' => $this->state->get('pingId')];
        $lastAttempt = (int) $this->state->get('pingAttemptAt', 0);
        if ($summary['ping'] === null && time() - $lastAttempt >= self::PING_RETRY_SEC) {
            $summary['ping'] = $this->ping->ensure();
        }
        $summary['replay'] = $this->replay->check($forceReplay);
        $summary['badge'] = 'off';
        if ($this->config->isBadgeEnabled()) {
            try {
                $this->badge->refresh();
                $summary['badge'] = 'refreshed';
            } catch (ApiException | InvalidArgumentException $e) {
                // The message comes from Pathly or from the client: never the API key.
                $this->logger->warning('Pathly: the badge could not be refreshed. ' . $e->getMessage());
                $summary['badge'] = 'error';
            }
        }
        return $summary;
    }
}
