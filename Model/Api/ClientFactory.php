<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Api;

use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\UserAgent;

/**
 * Build a client with the stored key, or with a key being saved.
 */
class ClientFactory
{
    /**
     * @param TransportInterface $transport
     * @param Config $config
     * @param UserAgent $userAgent
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly Config $config,
        private readonly UserAgent $userAgent
    ) {
    }

    /**
     * Create a client.
     *
     * @param string|null $apiKey Key to use, the stored key when null
     * @param int $timeout Seconds
     * @return Client
     * @throws ApiException When no key is stored
     */
    public function create(?string $apiKey = null, int $timeout = 10): Client
    {
        $key = $apiKey ?? $this->config->getApiKey();
        if ($key === '') {
            throw new ApiException('Pathly is not connected yet.', 0, 'not_configured');
        }
        return new Client($this->transport, $key, $this->userAgent->get(), $timeout);
    }
}
