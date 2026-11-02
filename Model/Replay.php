<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use Magento\Framework\App\MaintenanceMode;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\ClientFactory;
use Pathly\Monitoring\Model\Dashboard\StatusProvider;

/**
 * Replay of the Flows after an update of Magento or of a module.
 *
 * Two signals ask for a replay: every bin/magento setup:upgrade (Setup\RecurringData),
 * and a change of the fingerprint of the installed versions (checked by the cron).
 * The replay itself waits for the end of the maintenance mode, then calls
 * POST /v1/scenarios/{id}/run for each Flow. Two replays are at least 10 minutes apart.
 */
class Replay
{
    public const MIN_INTERVAL_SEC = 600;

    /**
     * @param Config $config
     * @param State $state
     * @param ClientFactory $clients
     * @param Fingerprint $fingerprint
     * @param MaintenanceMode $maintenance
     * @param ErrorMessage $errors
     * @param StatusProvider $status
     * @param Flows $flows
     */
    public function __construct(
        private readonly Config $config,
        private readonly State $state,
        private readonly ClientFactory $clients,
        private readonly Fingerprint $fingerprint,
        private readonly MaintenanceMode $maintenance,
        private readonly ErrorMessage $errors,
        private readonly StatusProvider $status,
        private readonly Flows $flows
    ) {
    }

    /**
     * Ask for a replay at the next check. Nothing is sent here.
     *
     * @param string $reason
     * @return void
     */
    public function markPending(string $reason): void
    {
        $this->state->reload();
        $this->state->set(['replayPending' => ['reason' => $reason, 'at' => time()]]);
    }

    /**
     * Replay the Flows if an update asked for it.
     *
     * @param bool $force Replay now, whatever the signals and the interval
     * @return array{result: string, queued?: int, errors?: string[]}
     */
    public function check(bool $force = false): array
    {
        if (!$this->config->isConnected()) {
            return ['result' => 'not_connected'];
        }
        $pending = $this->pending();
        if (!$force && $pending === null) {
            return ['result' => 'nothing'];
        }
        if (!$force && !$this->config->isReplayEnabled()) {
            $this->state->set(['replayPending' => null]);
            return ['result' => 'disabled'];
        }
        if ($this->maintenance->isOn()) {
            return ['result' => 'maintenance'];
        }
        $last = $this->state->get('lastReplay', []);
        $lastAt = is_array($last) ? (int) ($last['at'] ?? 0) : 0;
        if (!$force && time() - $lastAt < self::MIN_INTERVAL_SEC) {
            return ['result' => 'too_soon'];
        }
        $ids = $this->flows->ids();
        if ($ids === []) {
            $this->state->set(['replayPending' => null]);
            return ['result' => 'no_flow'];
        }
        $client = $this->clients->create();
        $queued = 0;
        $errors = [];
        foreach ($ids as $id) {
            try {
                $client->runScenario($id);
                $queued++;
            } catch (ApiException $e) {
                $errors[] = $this->errors->from($e);
            }
        }
        $this->state->set([
            'replayPending' => null,
            'lastReplay' => [
                'at' => time(),
                'reason' => $pending['reason'] ?? 'manual',
                'queued' => $queued,
                'errors' => $errors,
            ],
        ]);
        $this->status->clean();
        return ['result' => 'replayed', 'queued' => $queued, 'errors' => $errors];
    }

    /**
     * Pending request of a replay, after comparing the fingerprint of the installed versions.
     *
     * @return array|null
     */
    private function pending(): ?array
    {
        $current = $this->fingerprint->current();
        $known = $this->state->get('fingerprint');
        $pending = $this->state->get('replayPending');
        if ($known === null) {
            $this->state->set(['fingerprint' => $current]);
        } elseif ($known !== $current) {
            $pending = ['reason' => 'modules', 'at' => time()];
            $this->state->set(['fingerprint' => $current, 'replayPending' => $pending]);
        }
        return is_array($pending) ? $pending : null;
    }
}
