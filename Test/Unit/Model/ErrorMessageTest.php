<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\ErrorMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ErrorMessageTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'not configured' => [0, 'not_configured', 'Connect Pathly first'],
            'assistant off' => [403, 'assistant_off', 'Lumi is paused'],
            'assistant quota' => [403, 'assistant_quota', 'all of today\'s Lumi trials'],
            'assistant busy' => [429, 'assistant_busy', 'Lumi is very busy'],
            'plan limit' => [403, 'PLAN_LIMIT', 'Your Pathly plan has reached its limit: API says'],
            'network' => [0, null, 'Pathly could not be reached'],
            'bad key' => [401, null, 'Pathly refused the API key'],
            'scope' => [403, 'scope', 'The API key lacks a permission: API says'],
            'rate limit' => [429, null, 'Too many requests'],
            'other' => [400, null, 'API says'],
        ];
    }

    #[DataProvider('cases')]
    public function testMessage(int $status, ?string $code, string $expected): void
    {
        $message = (new ErrorMessage())->from(new ApiException('API says', $status, $code));
        $this->assertStringContainsString($expected, $message);
    }
}
