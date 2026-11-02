<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Api;

/**
 * Sends one HTTP request. Isolated so that tests can simulate the API.
 */
interface TransportInterface
{
    /**
     * Send a request and return its raw response.
     *
     * @param string $method GET, POST or PATCH
     * @param string $url Absolute URL
     * @param array $headers Request headers, by name
     * @param string|null $body Request body
     * @param int $timeout Total timeout, in seconds
     * @return array{status: int, body: string, headers: array<string, string>}
     * @throws ApiException When the API cannot be reached
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): array;
}
