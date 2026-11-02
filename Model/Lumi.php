<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use Magento\Framework\Exception\LocalizedException;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\ClientFactory;
use Pathly\Monitoring\Model\Dashboard\StatusProvider;

/**
 * Lumi, the assistant of Pathly, prepares the Flow of the checkout.
 *
 * Lumi recognizes Magento (cart up to the payment page, sign-in, contact
 * form). Each proposed Flow is replayed once by Pathly before it is shown.
 * The administrator validates one in a click. The only address sent is the
 * address of the shop, from the Magento settings.
 */
class Lumi
{
    private const DONE = ['ready', 'not_found', 'failed'];

    /**
     * @param ClientFactory $clients
     * @param Config $config
     * @param State $state
     * @param ErrorMessage $errors
     * @param StatusProvider $status
     * @param Flows $flows
     */
    public function __construct(
        private readonly ClientFactory $clients,
        private readonly Config $config,
        private readonly State $state,
        private readonly ErrorMessage $errors,
        private readonly StatusProvider $status,
        private readonly Flows $flows
    ) {
    }

    /**
     * Start a trial on the shop.
     *
     * @return array{trialId: string, siteKind: string|null}
     * @throws LocalizedException
     */
    public function start(): array
    {
        $url = $this->config->getStoreUrl();
        try {
            $client = $this->clients->create();
            $detect = $client->detectSite($url);
            if (empty($detect['available'])) {
                throw new LocalizedException($this->unavailable((string) ($detect['reason'] ?? '')));
            }
            $trialId = $client->startTrial($url);
        } catch (ApiException $e) {
            throw new LocalizedException(__($this->errors->from($e)));
        }
        $this->state->set(['lumiTrialId' => $trialId]);
        $kind = $detect['siteKind'] ?? null;
        return ['trialId' => $trialId, 'siteKind' => is_string($kind) ? $kind : null];
    }

    /**
     * Progress of a trial, and its Flows once they were replayed.
     *
     * @param string $trialId
     * @return array{status: string, done: bool, reason: string|null, proposals: array}
     * @throws LocalizedException
     */
    public function status(string $trialId): array
    {
        try {
            $view = $this->clients->create()->getTrial($trialId);
        } catch (ApiException $e) {
            throw new LocalizedException(__($this->errors->from($e)));
        }
        $item = is_array($view['item'] ?? null) ? $view['item'] : [];
        $status = (string) ($item['status'] ?? 'waiting');
        $proposals = [];
        foreach ((array) ($item['proposals'] ?? []) as $proposal) {
            if (!is_array($proposal)) {
                continue;
            }
            $proposals[] = [
                'index' => (int) ($proposal['index'] ?? 0),
                'kind' => (string) ($proposal['kind'] ?? 'custom'),
                'steps' => count((array) ($proposal['steps'] ?? [])),
                'stoppedBefore' => isset($proposal['stoppedBefore']) ? (string) $proposal['stoppedBefore'] : null,
                'saved' => !empty($proposal['savedMonitorId']),
            ];
        }
        $reason = $item['reason'] ?? null;
        return [
            'status' => $status,
            'done' => in_array($status, self::DONE, true),
            'reason' => is_string($reason) ? $reason : null,
            'proposals' => $proposals,
        ];
    }

    /**
     * Save one proposed Flow. Saved twice, it stays one Flow.
     *
     * @param string $trialId
     * @param int $index
     * @return string Identifier of the Flow
     * @throws LocalizedException
     */
    public function save(string $trialId, int $index): string
    {
        try {
            $result = $this->clients->create()->saveTrial($trialId, $index);
            $flowId = (string) ($result['item']['id'] ?? '');
        } catch (ApiException $e) {
            $existing = $e->getBody()['monitorId'] ?? null;
            if ($e->getErrorCode() !== 'already_saved' || !is_string($existing)) {
                throw new LocalizedException(__($this->errors->from($e)));
            }
            $flowId = $existing;
        }
        if ($flowId === '') {
            throw new LocalizedException(__('Unexpected answer from Pathly.'));
        }
        $this->flows->add($flowId);
        $this->status->clean();
        return $flowId;
    }

    /**
     * Why Lumi cannot try the shop.
     *
     * @param string $reason
     * @return \Magento\Framework\Phrase
     */
    private function unavailable(string $reason): \Magento\Framework\Phrase
    {
        $messages = [
            'off' => __('Lumi is paused. You can record your Flow yourself in Pathly, without code.'),
            'quota' => __(
                'You have used all of today\'s Lumi trials. Try again tomorrow, or choose a plan with more trials.'
            ),
            'unreachable' => __('Pathly could not open the shop. Check that it is online and public.'),
            'url_blocked' => __('Pathly cannot open this address: the shop must be public.'),
        ];
        return $messages[$reason] ?? __('Lumi cannot prepare a Flow for this shop right now.');
    }
}
