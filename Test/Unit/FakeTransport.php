<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit;

use Pathly\Monitoring\Model\Api\TransportInterface;

/**
 * Simulated Pathly API: answers from a queue and records each request.
 */
class FakeTransport implements TransportInterface
{
    /** @var array<int, array> */
    public array $requests = [];

    /** @var array<int, array|\Throwable> */
    private array $answers = [];

    /**
     * Queue an answer.
     *
     * @param int $status
     * @param mixed $body Array (JSON-encoded) or raw string
     * @param array $headers
     * @return $this
     */
    public function answer(int $status, mixed $body, array $headers = []): self
    {
        $this->answers[] = [
            'status' => $status,
            'body' => is_string($body) ? $body : json_encode($body),
            'headers' => $headers,
        ];
        return $this;
    }

    /**
     * Queue a failure.
     *
     * @param \Throwable $error
     * @return $this
     */
    public function fail(\Throwable $error): self
    {
        $this->answers[] = $error;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function send(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body', 'timeout');
        $answer = array_shift($this->answers);
        if ($answer instanceof \Throwable) {
            throw $answer;
        }
        return $answer ?? ['status' => 500, 'body' => '', 'headers' => []];
    }
}
