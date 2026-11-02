<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use Pathly\Monitoring\Model\Api\ApiException;

/**
 * Readable message for an administrator, from an error of the API.
 */
class ErrorMessage
{
    /**
     * Message to show.
     *
     * @param ApiException $e
     * @return string
     */
    public function from(ApiException $e): string
    {
        $code = $e->getErrorCode();
        $status = $e->getStatus();
        $byCode = [
            'not_configured' => __('Connect Pathly first: paste an API key in Stores > Configuration > Pathly.'),
            'assistant_off' => __('Lumi is paused. You can record your Flow yourself in Pathly, without code.'),
            'assistant_quota' => __(
                'You have used all of today\'s Lumi trials. Try again tomorrow, or choose a plan with more trials.'
            ),
            'assistant_busy' => __('Lumi is very busy. Try again in a minute.'),
            'PLAN_LIMIT' => __('Your Pathly plan has reached its limit: %1', $e->getMessage()),
        ];
        if ($code !== null && isset($byCode[$code])) {
            return (string) $byCode[$code];
        }
        $byStatus = [
            0 => __('Pathly could not be reached. Try again in a few minutes.'),
            401 => __('Pathly refused the API key. Create a new key in Pathly, then paste it here.'),
            403 => __('The API key lacks a permission: %1', $e->getMessage()),
            429 => __('Too many requests to Pathly. Try again in a minute.'),
        ];
        return (string) ($byStatus[$status] ?? $e->getMessage());
    }
}
