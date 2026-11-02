<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Api;

use RuntimeException;

/**
 * Error answered by the Pathly API, or raised when the API cannot be reached.
 *
 * The message never contains the API key: requests are not echoed back.
 */
class ApiException extends RuntimeException
{
    /**
     * @param string $message Readable message, from the API when it gave one
     * @param int $status HTTP status, 0 when the API could not be reached
     * @param string|null $errorCode Machine code of the API (assistant_quota, PLAN_LIMIT...)
     * @param array $body Decoded error body
     * @param int|null $retryAfter Seconds to wait, from the Retry-After header
     */
    public function __construct(
        string $message,
        private readonly int $status = 0,
        private readonly ?string $errorCode = null,
        private readonly array $body = [],
        private readonly ?int $retryAfter = null
    ) {
        parent::__construct($message, $status);
    }

    /**
     * HTTP status, 0 when the API could not be reached.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Machine code of the API error, if any.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Decoded error body.
     *
     * @return array
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Seconds to wait before retrying, if the API said so.
     *
     * @return int|null
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
