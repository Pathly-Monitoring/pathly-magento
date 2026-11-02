<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use Magento\Framework\App\ProductMetadataInterface;

/**
 * User agent of the requests: the versions of the module and of Magento, nothing else.
 */
class UserAgent
{
    /**
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(private readonly ProductMetadataInterface $productMetadata)
    {
    }

    /**
     * For example "pathly-magento/1.0.0 Magento/2.4.8 (Community)".
     *
     * @return string
     */
    public function get(): string
    {
        return sprintf(
            'pathly-magento/%s Magento/%s (%s)',
            Version::MODULE,
            $this->productMetadata->getVersion(),
            $this->productMetadata->getEdition()
        );
    }
}
