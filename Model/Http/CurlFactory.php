<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model\Http;

/**
 * A new cURL client for each request: the Magento client keeps its state.
 */
class CurlFactory
{
    /**
     * Create a cURL client.
     *
     * @return Curl
     */
    public function create(): Curl
    {
        return new Curl();
    }
}
