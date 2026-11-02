<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\State;

/**
 * State of the connection, or the link to create a free account.
 */
class Connection extends Field
{
    public const SCOPES = 'org:read scenarios:read scenarios:write runs:read runs:trigger '
        . 'assistant:run assistant:save status:read status:write';

    /**
     * @param Context $context
     * @param Config $config
     * @param State $state
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly State $state,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * Row without scope label nor "use default" checkbox: the setting is global.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope();
        $element->unsCanUseWebsiteValue();
        $element->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Connection state.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        if (!$this->config->isConnected()) {
            return '<p>' . $this->escapeHtml(__('No Pathly account is connected yet.')) . '</p>'
                . '<p><a class="action-default" target="_blank" rel="noopener noreferrer" href="'
                . $this->escapeUrl(Config::SIGNUP_URL) . '">'
                . $this->escapeHtml(__('Create a free account')) . '</a></p>'
                . '<p class="note">' . $this->escapeHtml(__(
                    'The Solo plan is free, without credit card. Then, in Pathly: Settings, API keys. '
                    . 'Paste the key below.'
                )) . '</p>' . $this->notes();
        }
        $organization = (string) $this->state->get('organization', '');
        $space = (string) $this->state->get('space', '');
        $plan = (string) $this->state->get('plan', '');
        $html = '<p>' . $this->escapeHtml(__('Connected to Pathly: %1', $organization === '' ? '-' : $organization));
        if ($space !== '') {
            $html .= ' · ' . $this->escapeHtml(__('Space %1', $space));
        }
        if ($plan !== '') {
            $html .= ' · ' . $this->escapeHtml(__('Plan %1', $plan));
        }
        $html .= '</p>';
        $pingId = (string) $this->state->get('pingId', '');
        $pingError = (string) $this->state->get('pingError', '');
        if ($pingId !== '') {
            $html .= '<p>' . $this->escapeHtml(__('The Ping of the shop is active.')) . ' <a target="_blank"'
                . ' rel="noopener noreferrer" href="' . $this->escapeUrl(Config::APP_URL . '/monitors/' . $pingId)
                . '">' . $this->escapeHtml(__('Open in Pathly')) . '</a></p>';
        } elseif ($pingError !== '') {
            $html .= '<p class="message message-warning">' . $this->escapeHtml(
                __('The Ping of the shop could not be created: %1', $pingError)
            ) . '</p>';
        }
        return $html . $this->notes();
    }

    /**
     * What the key needs and what is sent.
     *
     * @return string
     */
    private function notes(): string
    {
        return '<p class="note">' . $this->escapeHtml(__(
            'Preferably a key limited to a Space, with the scopes %1.',
            self::SCOPES
        )) . '</p><p class="note">' . $this->escapeHtml(__(
            'Only the address of the shop and the versions of Magento and of the module are sent to Pathly. '
            . 'No customer data, no order.'
        )) . '</p><p class="note"><em>' . $this->escapeHtml(
            "Pathly watches the path. Ping if it's up. Flow if a user can walk it. "
            . 'Chain if the services behind it hold.'
        ) . '</em></p>';
    }
}
