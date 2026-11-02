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
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\Flows;

/**
 * Button that asks Lumi to prepare the Flow of the checkout, and its result.
 */
class Lumi extends Field
{
    public const ACL = 'Pathly_Monitoring::lumi';

    /**
     * @param Context $context
     * @param Config $config
     * @param Flows $flows
     * @param Json $json
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly Flows $flows,
        private readonly Json $json,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * Row without scope label nor "use default" checkbox.
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
     * Button, consent and result area. The script is Pathly_Monitoring/js/lumi.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        if (!$this->config->isConnected()) {
            return '<p>' . $this->escapeHtml(__('Save an API key first.')) . '</p>';
        }
        if (!$this->_authorization->isAllowed(self::ACL)) {
            return '<p>' . $this->escapeHtml(__('Your role does not allow you to use Lumi.')) . '</p>';
        }
        $count = count($this->flows->ids());
        $init = [
            'Pathly_Monitoring/js/lumi' => [
                'startUrl' => $this->getUrl('pathly/lumi/start'),
                'statusUrl' => $this->getUrl('pathly/lumi/status'),
                'saveUrl' => $this->getUrl('pathly/lumi/save'),
                'text' => $this->texts(),
            ],
        ];
        return '<div class="pathly-lumi" data-mage-init="' . $this->escapeHtmlAttr($this->json->serialize($init)) . '">'
            . '<p>' . $this->escapeHtml(__(
                'Lumi recognizes Magento and prepares the Flow of your checkout: add a product to the cart, '
                . 'then go to the payment page. Each proposed Flow is replayed before it is shown to you.'
            )) . '</p>'
            . '<p class="note">' . $this->escapeHtml(__(
                'Lumi pays nothing, submits no form and only types test values. Each run creates a test cart.'
            )) . '</p>'
            . '<p><label><input type="checkbox" class="pathly-lumi-owner"/> '
            . $this->escapeHtml(__('I manage this shop and allow Pathly to open it to prepare the Flow.'))
            . '</label></p>'
            . '<p><button type="button" class="action-secondary pathly-lumi-start" disabled="disabled">'
            . $this->escapeHtml(__('Prepare the Flow with Lumi')) . '</button></p>'
            . '<div class="pathly-lumi-output" aria-live="polite"></div>'
            . '<p class="note">' . $this->escapeHtml(__('Flows of the shop: %1', $count)) . '</p>'
            . '</div>';
    }

    /**
     * Texts of the script, translated here.
     *
     * @return array<string, string>
     */
    private function texts(): array
    {
        return [
            'working' => (string) __('Lumi is preparing your Flows. It takes one to two minutes.'),
            'none' => (string) __('Lumi did not find a Flow that passes on this shop. You can record it in Pathly.'),
            'failed' => (string) __('Lumi could not finish. Try again later.'),
            'validate' => (string) __('Validate this Flow'),
            'saved' => (string) __('Flow saved. Pathly runs it now, and after each update.'),
            'already' => (string) __('Already saved'),
            'error' => (string) __('Pathly could not be reached. Try again in a few minutes.'),
            'steps' => (string) __('%1 steps'),
            'stoppedBefore' => (string) __('stops before "%1"'),
            'kind_cart' => (string) __('Cart up to the payment page'),
            'kind_login' => (string) __('Customer sign-in'),
            'kind_contact' => (string) __('Contact form, filled and not sent'),
            'kind_custom' => (string) __('Other path'),
        ];
    }
}
