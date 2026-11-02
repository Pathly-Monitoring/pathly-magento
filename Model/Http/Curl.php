<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Http;

/**
 * Magento cURL client that can also send PATCH requests with a body.
 */
class Curl extends \Magento\Framework\HTTP\Client\Curl
{
    /**
     * Send a request with any method.
     *
     * @param string $method GET, POST or PATCH
     * @param string $uri Absolute URL
     * @param string|null $body Request body
     * @return void
     * @throws \Exception When cURL fails
     */
    public function send(string $method, string $uri, ?string $body): void
    {
        if ($method === 'GET') {
            $this->get($uri);
            return;
        }
        if ($method === 'POST') {
            $this->post($uri, (string) $body);
            return;
        }
        if ($body !== null) {
            $this->setOption(CURLOPT_POSTFIELDS, $body);
        }
        $this->makeRequest($method, $uri);
    }
}
