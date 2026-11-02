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
 * Connection of the shop to a Pathly account, with an API key.
 */
class Connection
{
    /**
     * @param ClientFactory $clients
     * @param State $state
     * @param Ping $ping
     * @param ErrorMessage $errors
     * @param StatusProvider $status
     */
    public function __construct(
        private readonly ClientFactory $clients,
        private readonly State $state,
        private readonly Ping $ping,
        private readonly ErrorMessage $errors,
        private readonly StatusProvider $status
    ) {
    }

    /**
     * Check a key with Pathly before it is saved.
     *
     * @param string $apiKey
     * @return array Account of the key
     * @throws LocalizedException When Pathly refuses the key or cannot be reached
     */
    public function validate(string $apiKey): array
    {
        if (!preg_match('/^sp_[A-Za-z0-9_-]{8,}$/', $apiKey)) {
            throw new LocalizedException(__('This is not a Pathly API key: it starts with sp_.'));
        }
        try {
            return $this->clients->create($apiKey)->getAccount();
        } catch (ApiException $e) {
            throw new LocalizedException(__('The key was not saved. %1', $this->errors->from($e)));
        }
    }

    /**
     * Use a key that was just saved: forget the previous account, then attach or create the Ping.
     *
     * @param string $apiKey
     * @param array $account Account returned by the validation
     * @return void
     */
    public function connect(string $apiKey, array $account = []): void
    {
        $hash = substr(hash('sha256', $apiKey), 0, 16);
        if ($this->state->get('keyHash') !== $hash) {
            $this->state->clear();
        }
        $this->state->set([
            'keyHash' => $hash,
            'connectedAt' => time(),
            'organization' => (string) ($account['organization']['name'] ?? ''),
            'space' => (string) ($account['space']['name'] ?? ''),
            'plan' => (string) ($account['plan']['name'] ?? ''),
        ]);
        $this->ping->ensure($apiKey);
        $this->status->clean();
    }

    /**
     * The key was removed: forget everything. Ping and Flows stay in the Pathly account.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->state->clear();
        $this->status->clean();
    }
}
