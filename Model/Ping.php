<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\ClientFactory;

/**
 * Ping of the shop: attached when Pathly already has one, created otherwise.
 */
class Ping
{
    /** Every plan accepts a Ping every 5 minutes. */
    public const INTERVAL_SEC = 300;

    private const URL = '#^[a-z][a-z0-9+.-]*://([^/?\#]+)([^?\#]*)#i';

    /**
     * @param ClientFactory $clients
     * @param Config $config
     * @param State $state
     * @param ErrorMessage $errors
     */
    public function __construct(
        private readonly ClientFactory $clients,
        private readonly Config $config,
        private readonly State $state,
        private readonly ErrorMessage $errors
    ) {
    }

    /**
     * Attach or create the Ping of the shop, and remember it.
     *
     * @param string|null $apiKey Key being saved, the stored key when null
     * @return string|null Identifier of the Ping, null when it failed (the reason is kept)
     */
    public function ensure(?string $apiKey = null): ?string
    {
        $url = $this->config->getStoreUrl();
        $this->state->set(['pingAttemptAt' => time()]);
        try {
            $id = $this->attachOrCreate($apiKey, $url);
        } catch (ApiException $e) {
            $this->state->set(['pingError' => $this->errors->from($e)]);
            return null;
        }
        $this->state->set(['pingId' => $id, 'pingUrl' => $url, 'pingError' => null]);
        return $id;
    }

    /**
     * Identifier of the existing Ping of the shop, or of a new one.
     *
     * @param string|null $apiKey
     * @param string $url
     * @return string
     * @throws ApiException
     */
    private function attachOrCreate(?string $apiKey, string $url): string
    {
        $client = $this->clients->create($apiKey);
        $id = $this->find($client->listScenarios(), $url);
        if ($id !== null) {
            return $id;
        }
        $created = $client->createScenario(
            [
                'name' => 'Ping · ' . $this->site($url),
                'type' => 'http',
                'url' => $url,
                'method' => 'GET',
                'intervalSec' => self::INTERVAL_SEC,
            ],
            'magento-ping-' . gmdate('Ymd') . '-' . substr(sha1($url), 0, 20)
        );
        $id = (string) ($created['id'] ?? '');
        if ($id === '') {
            throw new ApiException('Unexpected answer from Pathly.', 200);
        }
        return $id;
    }

    /**
     * Existing Ping of the same site, whatever the scheme, "www." or a final slash.
     *
     * @param array $scenarios
     * @param string $url
     * @return string|null
     */
    private function find(array $scenarios, string $url): ?string
    {
        $wanted = $this->normalize($url);
        if ($wanted === '') {
            return null;
        }
        foreach ($scenarios as $scenario) {
            $isPing = ($scenario['type'] ?? null) === 'http' && empty($scenario['httpChain']);
            if ($isPing && $this->normalize((string) ($scenario['url'] ?? '')) === $wanted) {
                return (string) $scenario['id'];
            }
        }
        return null;
    }

    /**
     * Host, port and path, lower case, without "www." nor final slash. Empty when it is not a URL.
     *
     * @param string $url
     * @return string
     */
    private function normalize(string $url): string
    {
        if (!preg_match(self::URL, strtolower(trim($url)), $match)) {
            return '';
        }
        return preg_replace('/^www\./', '', $match[1]) . '/' . trim($match[2], '/');
    }

    /**
     * Host of the shop, for the name of the Ping.
     *
     * @param string $url
     * @return string
     */
    private function site(string $url): string
    {
        return preg_match(self::URL, $url, $match) ? $match[1] : $url;
    }
}
