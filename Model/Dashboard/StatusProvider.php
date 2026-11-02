<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Dashboard;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\Client;
use Pathly\Monitoring\Model\Api\ClientFactory;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\ErrorMessage;
use Pathly\Monitoring\Model\Flows;
use Pathly\Monitoring\Model\State;

/**
 * What the panel of the admin dashboard shows, kept 5 minutes in the cache.
 *
 * State of the Ping and of the Flows, date and screenshot of the last
 * failure, plan and quotas. The screenshot link is signed by Pathly and
 * valid 15 minutes: the admin's browser loads it from api.pathlyhq.com.
 */
class StatusProvider
{
    public const CACHE_KEY = 'pathly_monitoring_dashboard';
    public const CACHE_TAG = 'PATHLY_MONITORING';
    public const CACHE_LIFETIME = 300;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    private const SHOT = '#^https://api\.pathlyhq\.com/public/run-shot/[A-Za-z0-9._-]+$#';
    private const BILLING = '#^https://pathlyhq\.com/[A-Za-z0-9/_-]*$#';

    /**
     * @param ClientFactory $clients
     * @param Config $config
     * @param State $state
     * @param Flows $flows
     * @param ErrorMessage $errors
     * @param CacheInterface $cache
     * @param Json $json
     */
    public function __construct(
        private readonly ClientFactory $clients,
        private readonly Config $config,
        private readonly State $state,
        private readonly Flows $flows,
        private readonly ErrorMessage $errors,
        private readonly CacheInterface $cache,
        private readonly Json $json
    ) {
    }

    /**
     * Data of the panel.
     *
     * @return array
     */
    public function get(): array
    {
        if (!$this->config->isConnected()) {
            return ['connected' => false];
        }
        $cached = $this->cache->load(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            $data = $this->json->unserialize($cached);
            if (is_array($data)) {
                return $data;
            }
        }
        $data = $this->fetch();
        $this->cache->save($this->json->serialize($data), self::CACHE_KEY, [self::CACHE_TAG], self::CACHE_LIFETIME);
        return $data;
    }

    /**
     * Forget the cached copy, after a change.
     *
     * @return void
     */
    public function clean(): void
    {
        $this->cache->remove(self::CACHE_KEY);
    }

    /**
     * Ask Pathly.
     *
     * @return array
     */
    private function fetch(): array
    {
        $data = [
            'connected' => true,
            'error' => null,
            'account' => null,
            'ping' => null,
            'pingError' => $this->state->get('pingError'),
            'flows' => [],
            'lastFailure' => null,
            'lastReplay' => $this->state->get('lastReplay'),
            'fetchedAt' => time(),
        ];
        try {
            $client = $this->clients->create(null, 5);
            $data['account'] = $this->account($client->getAccount());
            $pingId = (string) $this->state->get('pingId', '');
            if (preg_match(self::UUID, $pingId)) {
                $data['ping'] = $this->scenario($client, $pingId);
            }
            $failures = [];
            foreach ($this->flows->ids() as $flowId) {
                $flow = $this->scenario($client, $flowId);
                $data['flows'][] = $flow;
                if ($flow['status'] !== null && $flow['status'] !== 'ok') {
                    $failures[] = $flow;
                }
            }
            if ($data['ping'] !== null && $data['ping']['status'] !== null && $data['ping']['status'] !== 'ok') {
                $failures[] = $data['ping'];
            }
            $data['lastFailure'] = $this->lastFailure($client, $failures);
        } catch (ApiException $e) {
            $data['error'] = $this->errors->from($e);
        }
        return $data;
    }

    /**
     * Plan and quotas.
     *
     * @param array $account
     * @return array
     */
    private function account(array $account): array
    {
        $quotas = [];
        foreach (['ping', 'flow', 'runs', 'assistantTrials'] as $name) {
            $quota = $account['quotas'][$name] ?? null;
            if (is_array($quota)) {
                $quotas[$name] = [
                    'used' => (int) ($quota['used'] ?? 0),
                    'limit' => isset($quota['limit']) ? (int) $quota['limit'] : null,
                ];
            }
        }
        $billing = (string) ($account['billingUrl'] ?? '');
        return [
            'organization' => (string) ($account['organization']['name'] ?? ''),
            'space' => (string) ($account['space']['name'] ?? ''),
            'plan' => (string) ($account['plan']['name'] ?? ''),
            'billingUrl' => preg_match(self::BILLING, $billing) ? $billing : Config::APP_URL . '/billing',
            'quotas' => $quotas,
        ];
    }

    /**
     * Name and last state of a scenario.
     *
     * @param Client $client
     * @param string $id
     * @return array{id: string, name: string, status: string|null, url: string}
     */
    private function scenario(Client $client, string $id): array
    {
        $scenario = $client->getScenario($id);
        $status = $scenario['lastStatus'] ?? null;
        return [
            'id' => $id,
            'name' => (string) ($scenario['name'] ?? ''),
            'status' => is_string($status) ? $status : null,
            'url' => Config::APP_URL . '/monitors/' . $id,
        ];
    }

    /**
     * Newest failed run of the failing scenarios, with its screenshot when there is one.
     *
     * @param Client $client
     * @param array $failing
     * @return array|null
     */
    private function lastFailure(Client $client, array $failing): ?array
    {
        $best = null;
        foreach ($failing as $scenario) {
            foreach ($client->listRuns($scenario['id'], 10) as $run) {
                if (($run['status'] ?? 'ok') === 'ok') {
                    continue;
                }
                $at = (string) ($run['created_at'] ?? '');
                if ($best === null || strcmp($at, $best['at']) > 0) {
                    $best = [
                        'runId' => (string) ($run['id'] ?? ''),
                        'scenario' => $scenario['name'],
                        'url' => $scenario['url'],
                        'at' => $at,
                        'message' => (string) ($run['message'] ?? ''),
                        'screenshotUrl' => null,
                    ];
                }
                break;
            }
        }
        if ($best !== null && preg_match(self::UUID, $best['runId'])) {
            $shot = (string) ($client->getRun($best['runId'])['screenshotUrl'] ?? '');
            $best['screenshotUrl'] = preg_match(self::SHOT, $shot) ? $shot : null;
        }
        return $best;
    }
}
