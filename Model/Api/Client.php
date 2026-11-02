<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Api;

use InvalidArgumentException;

/**
 * Client of the public Pathly API (/v1), limited to what the module needs.
 *
 * Every request goes to https://api.pathlyhq.com: the base URL is a constant,
 * paths are fixed and identifiers are checked before they enter a path. The
 * module never calls a URL typed by a user.
 */
class Client
{
    public const BASE_URL = 'https://api.pathlyhq.com';

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    private const BADGE = '#^https://api\.pathlyhq\.com/public/status/[a-z0-9-]+(/[a-z0-9-]+)?/badge\.svg$#';
    private const MAX_PAGES = 20;

    /**
     * @param TransportInterface $transport
     * @param string $apiKey Pathly API key (sp_...)
     * @param string $userAgent Module and Magento versions
     * @param int $timeout Seconds
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $apiKey,
        private readonly string $userAgent,
        private readonly int $timeout = 10
    ) {
    }

    /**
     * Organization, Space, plan and quotas of the key.
     *
     * @return array
     */
    public function getAccount(): array
    {
        return $this->request('GET', '/v1/account');
    }

    /**
     * Every scenario the key can see (Ping, Flow, Chain), all pages.
     *
     * @return array<int, array>
     */
    public function listScenarios(): array
    {
        $items = [];
        $cursor = null;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['limit' => 100];
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }
            $result = $this->request('GET', '/v1/scenarios?' . http_build_query($query));
            foreach ($this->items($result) as $item) {
                $items[] = $item;
            }
            $next = $result['nextCursor'] ?? null;
            $cursor = is_string($next) && $next !== '' ? $next : null;
            if ($cursor === null) {
                break;
            }
        }
        return $items;
    }

    /**
     * One scenario.
     *
     * @param string $id
     * @return array
     */
    public function getScenario(string $id): array
    {
        return $this->request('GET', '/v1/scenarios/' . $this->id($id));
    }

    /**
     * Create a scenario.
     *
     * @param array $data
     * @param string|null $idempotencyKey Same key, same answer: no duplicate on retry
     * @return array
     */
    public function createScenario(array $data, ?string $idempotencyKey = null): array
    {
        $headers = $idempotencyKey === null ? [] : ['Idempotency-Key' => $idempotencyKey];
        return $this->request('POST', '/v1/scenarios', $data, $headers);
    }

    /**
     * Queue a run of a scenario now.
     *
     * @param string $id
     * @return array
     */
    public function runScenario(string $id): array
    {
        return $this->request('POST', '/v1/scenarios/' . $this->id($id) . '/run', []);
    }

    /**
     * Latest runs of a scenario, newest first.
     *
     * @param string $scenarioId
     * @param int $limit
     * @return array<int, array>
     */
    public function listRuns(string $scenarioId, int $limit = 20): array
    {
        $query = http_build_query(['scenarioId' => $this->id($scenarioId), 'limit' => $limit]);
        return $this->items($this->request('GET', '/v1/runs?' . $query));
    }

    /**
     * One run, with a signed link to the screenshot of the failed step.
     *
     * @param string $id
     * @return array
     */
    public function getRun(string $id): array
    {
        return $this->request('GET', '/v1/runs/' . $this->id($id));
    }

    /**
     * Lumi: recognize the shop and tell whether a trial is possible.
     *
     * @param string $url Address of the shop
     * @return array
     */
    public function detectSite(string $url): array
    {
        return $this->request('POST', '/v1/flow-assistant/detect', ['url' => $url]);
    }

    /**
     * Lumi: prepare Flows for the shop. The answer comes later.
     *
     * @param string $url Address of the shop
     * @return string Identifier of the trial
     */
    public function startTrial(string $url): string
    {
        $result = $this->request('POST', '/v1/flow-assistant/trials', ['url' => $url, 'confirmOwner' => true]);
        return $this->id((string) ($result['id'] ?? ''));
    }

    /**
     * Lumi: read a trial and its proposed Flows.
     *
     * @param string $id
     * @return array
     */
    public function getTrial(string $id): array
    {
        return $this->request('GET', '/v1/flow-assistant/trials/' . $this->id($id));
    }

    /**
     * Lumi: save one proposed Flow as a monitored Flow.
     *
     * @param string $id Trial
     * @param int $index Proposal
     * @return array
     */
    public function saveTrial(string $id, int $index): array
    {
        return $this->request('POST', '/v1/flow-assistant/trials/' . $this->id($id) . '/save', ['index' => $index]);
    }

    /**
     * Status pages the key can see.
     *
     * @return array<int, array>
     */
    public function listStatusPages(): array
    {
        return $this->items($this->request('GET', '/v1/status-pages'));
    }

    /**
     * Create the status page of the organization, or of the Space of the key.
     *
     * @param bool $public
     * @return array
     */
    public function createStatusPage(bool $public): array
    {
        return $this->request('POST', '/v1/status-pages', ['public' => $public]);
    }

    /**
     * Publish or unpublish a status page.
     *
     * @param string $id
     * @param bool $public
     * @return array
     */
    public function updateStatusPage(string $id, bool $public): array
    {
        return $this->request('PATCH', '/v1/status-pages/' . $this->id($id), ['public' => $public]);
    }

    /**
     * Download the SVG badge of a status page. The badge is public: no key is sent.
     *
     * @param string $badgeUrl As returned by the API
     * @return string SVG document
     */
    public function fetchBadge(string $badgeUrl): string
    {
        if (!preg_match(self::BADGE, $badgeUrl)) {
            throw new InvalidArgumentException('Unexpected badge address.');
        }
        $response = $this->transport->send(
            'GET',
            $badgeUrl,
            ['Accept' => 'image/svg+xml', 'User-Agent' => $this->userAgent],
            null,
            $this->timeout
        );
        $svg = trim($response['body']);
        if ($response['status'] !== 200 || !preg_match('/^(<\?xml[^>]*>\s*)?<svg[\s>]/', $svg)) {
            throw new ApiException('The badge could not be read.', $response['status']);
        }
        return $svg;
    }

    /**
     * Check an identifier before it enters a path.
     *
     * @param string $id
     * @return string
     */
    private function id(string $id): string
    {
        if (!preg_match(self::UUID, $id)) {
            throw new InvalidArgumentException('Invalid Pathly identifier.');
        }
        return strtolower($id);
    }

    /**
     * Items of a list answer.
     *
     * @param array $result
     * @return array<int, array>
     */
    private function items(array $result): array
    {
        $items = $result['items'] ?? [];
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * Send a JSON request and decode the answer.
     *
     * @param string $method
     * @param string $path Path under /v1, with its query string
     * @param array|null $body
     * @param array $extraHeaders Extra headers
     * @return array
     */
    private function request(string $method, string $path, ?array $body = null, array $extraHeaders = []): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ] + $extraHeaders;
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body === [] ? new \stdClass() : $body, JSON_UNESCAPED_SLASHES);
        }
        $response = $this->transport->send($method, self::BASE_URL . $path, $headers, $payload, $this->timeout);
        $decoded = json_decode($response['body'], true);
        $data = is_array($decoded) ? $decoded : [];
        $status = $response['status'];
        if ($status >= 200 && $status < 300) {
            if (!is_array($decoded)) {
                throw new ApiException('Unexpected answer from Pathly.', $status);
            }
            return $data;
        }
        $retry = $response['headers']['retry-after'] ?? null;
        throw new ApiException(
            is_string($data['error'] ?? null) ? $data['error'] : 'Pathly answered with HTTP ' . $status . '.',
            $status,
            is_string($data['code'] ?? null) ? $data['code'] : null,
            $data,
            is_numeric($retry) ? (int) $retry : null
        );
    }
}
