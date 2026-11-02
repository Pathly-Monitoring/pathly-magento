<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Controller\Badge;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Pathly\Monitoring\Model\Badge;
use Pathly\Monitoring\Model\Config;

/**
 * The badge, served by the shop itself from its stored copy (pathly/badge/svg).
 *
 * The visitor's browser asks the shop, never Pathly. When the badge is off,
 * the address answers 404.
 */
class Svg implements HttpGetActionInterface
{
    /**
     * @param ResultFactory $resultFactory
     * @param Config $config
     * @param Badge $badge
     */
    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly Config $config,
        private readonly Badge $badge
    ) {
    }

    /**
     * SVG badge, or 404.
     *
     * @return Raw
     */
    public function execute()
    {
        /** @var Raw $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $svg = $this->config->isBadgeEnabled() ? $this->badge->getSvg() : null;
        if ($svg === null) {
            $result->setHttpResponseCode(404);
            $result->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            return $result->setContents('');
        }
        $result->setHeader('Content-Type', 'image/svg+xml; charset=utf-8', true);
        $result->setHeader('Cache-Control', 'public, max-age=300', true);
        $result->setHeader('X-Content-Type-Options', 'nosniff', true);
        $result->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox", true);
        return $result->setContents($svg);
    }
}
