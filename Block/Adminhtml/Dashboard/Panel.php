<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Block\Adminhtml\Dashboard;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\Dashboard\StatusProvider;

/**
 * Pathly panel of the admin dashboard.
 */
class Panel extends Template
{
    public const ACL = 'Pathly_Monitoring::dashboard';

    /** @var string */
    protected $_template = 'Pathly_Monitoring::dashboard/panel.phtml';

    /**
     * @param Context $context
     * @param StatusProvider $status
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly StatusProvider $status,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Data of the panel.
     *
     * @return array
     */
    public function getPanel(): array
    {
        return $this->status->get();
    }

    /**
     * Settings of the module.
     *
     * @return string
     */
    public function getSettingsUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'pathly_monitoring']);
    }

    /**
     * Link to create a free account.
     *
     * @return string
     */
    public function getSignupUrl(): string
    {
        return Config::SIGNUP_URL;
    }

    /**
     * Web app of Pathly.
     *
     * @return string
     */
    public function getAppUrl(): string
    {
        return Config::APP_URL . '/dashboard';
    }

    /**
     * Label of a state.
     *
     * @param string|null $status
     * @return string
     */
    public function statusLabel(?string $status): string
    {
        $labels = [
            'ok' => __('OK'),
            'fail' => __('Failing'),
            'error' => __('Failing'),
            'degraded' => __('Slow'),
        ];
        return (string) ($status === null ? __('Not run yet') : ($labels[$status] ?? __('Failing')));
    }

    /**
     * CSS class of a state.
     *
     * @param string|null $status
     * @return string
     */
    public function statusClass(?string $status): string
    {
        if ($status === null) {
            return 'pathly-pending';
        }
        return $status === 'ok' ? 'pathly-ok' : ($status === 'degraded' ? 'pathly-slow' : 'pathly-fail');
    }

    /**
     * Quota as "used / limit", the limit being "unlimited" when the plan has none.
     *
     * @param array $quota
     * @return string
     */
    public function quotaLabel(array $quota): string
    {
        $limit = $quota['limit'] ?? null;
        return (int) ($quota['used'] ?? 0) . ' / ' . ($limit === null ? (string) __('unlimited') : (int) $limit);
    }

    /**
     * Name of a quota.
     *
     * @param string $name
     * @return string
     */
    public function quotaName(string $name): string
    {
        $names = [
            'ping' => __('Ping'),
            'flow' => __('Flow'),
            'runs' => __('Flow minutes this month'),
            'assistantTrials' => __('Lumi trials today'),
        ];
        return (string) ($names[$name] ?? $name);
    }

    /**
     * Render only for roles allowed to see it.
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->_authorization->isAllowed(self::ACL)) {
            return '';
        }
        return parent::_toHtml();
    }
}
