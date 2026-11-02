<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use Pathly\Monitoring\Model\Badge as BadgeModel;
use Pathly\Monitoring\Model\Config;

/**
 * Availability badge, as a widget or in the footer. Shows nothing while the badge is off.
 */
class Badge extends Template implements BlockInterface
{
    /** @var string */
    protected $_template = 'Pathly_Monitoring::badge.phtml';

    /**
     * @param Context $context
     * @param Config $config
     * @param BadgeModel $badge
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly BadgeModel $badge,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether there is a badge to show.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->config->isBadgeEnabled() && $this->badge->getSvg() !== null;
    }

    /**
     * Image served by the shop.
     *
     * @return string
     */
    public function getImageUrl(): string
    {
        return $this->getUrl('pathly/badge/svg');
    }

    /**
     * Public status page, or null.
     *
     * @return string|null
     */
    public function getStatusPageUrl(): ?string
    {
        return $this->badge->getStatusPageUrl();
    }

    /**
     * Render only when the badge is on.
     *
     * @return string
     */
    protected function _toHtml()
    {
        return $this->isVisible() ? parent::_toHtml() : '';
    }
}
