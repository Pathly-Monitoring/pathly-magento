<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Http;

use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\TransportInterface;

/**
 * Transport over the Magento cURL client. HTTPS only, no redirect followed.
 */
class Transport implements TransportInterface
{
    /**
     * @param CurlFactory $curlFactory
     */
    public function __construct(private readonly CurlFactory $curlFactory)
    {
    }

    /**
     * @inheritdoc
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout($timeout);
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
        $curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $curl->setHeaders($headers);
        try {
            $curl->send($method, $url, $body);
        } catch (\Exception $e) {
            throw new ApiException('Pathly could not be reached: ' . $e->getMessage());
        }
        $responseHeaders = [];
        foreach ($curl->getHeaders() as $name => $value) {
            $responseHeaders[strtolower((string) $name)] = is_array($value) ? implode(', ', $value) : (string) $value;
        }
        return ['status' => $curl->getStatus(), 'body' => $curl->getBody(), 'headers' => $responseHeaders];
    }
}
