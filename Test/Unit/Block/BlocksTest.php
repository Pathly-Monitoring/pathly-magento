<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Block;

use Magento\Backend\Block\Template\Context as BackendContext;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Pathly\Monitoring\Block\Adminhtml\Dashboard\Panel;
use Pathly\Monitoring\Block\Adminhtml\System\Config\Connection;
use Pathly\Monitoring\Block\Adminhtml\System\Config\Lumi;
use Pathly\Monitoring\Block\Widget\Badge;
use Pathly\Monitoring\Model\Badge as BadgeModel;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\Dashboard\StatusProvider;
use Pathly\Monitoring\Model\Flows;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;

class BlocksTest extends TestCase
{
    use Helper;

    private bool $connected = true;
    private bool $allowed = true;

    private function config(): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('isConnected')->willReturnCallback(fn () => $this->connected);
        $config->method('isBadgeEnabled')->willReturnCallback(fn () => $this->connected);
        return $config;
    }

    private function backendContext(): BackendContext
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(fn () => $this->allowed);
        return $this->blockContext(BackendContext::class, [
            'getAuthorization' => $authorization,
            'getEventManager' => $this->createMock(EventManager::class),
        ]);
    }

    private function element(): AbstractElement
    {
        $element = $this->getMockBuilder(AbstractElement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getElementHtml', 'getHtmlId'])
            ->getMock();
        $element->setData([
            'scope' => 'default',
            'can_use_website_value' => 1,
            'can_use_default_value' => 1,
            'label' => 'Label',
        ]);
        return $element;
    }

    /**
     * Protected _getElementHtml(), called as Magento does.
     */
    private function elementHtml(object $block): string
    {
        $method = new \ReflectionMethod($block, '_getElementHtml');
        return $method->invoke($block, $this->element());
    }

    public function testConnectionBlockBeforeConnection(): void
    {
        $this->connected = false;
        $block = new Connection(
            $this->backendContext(),
            $this->config(),
            $this->memoryState(),
            [],
            $this->createMock(SecureHtmlRenderer::class)
        );
        $html = $this->elementHtml($block);
        $this->assertStringContainsString('Create a free account', $html);
        $this->assertStringContainsString('ref=magento&amp;utm_source=magento&amp;utm_medium=module', $html);
        $this->assertStringContainsString('org:read scenarios:read', $html);
    }

    public function testConnectionBlockWhenConnected(): void
    {
        $state = $this->memoryState([
            'organization' => 'Shop <b>',
            'space' => 'Client A',
            'plan' => 'Pro',
            'pingId' => '0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab',
        ]);
        $block = new Connection($this->backendContext(), $this->config(), $state, [], $this->createMock(SecureHtmlRenderer::class));
        $html = $this->elementHtml($block);
        $this->assertStringContainsString('Connected to Pathly: Shop &lt;b&gt;', $html);
        $this->assertStringContainsString('Space Client A', $html);
        $this->assertStringContainsString('Plan Pro', $html);
        $this->assertStringContainsString('https://pathlyhq.com/monitors/0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab', $html);
    }

    public function testConnectionBlockWithAPingError(): void
    {
        $state = $this->memoryState(['pingError' => 'Plan limit']);
        $block = new Connection($this->backendContext(), $this->config(), $state, [], $this->createMock(SecureHtmlRenderer::class));
        $html = $this->elementHtml($block);
        $this->assertStringContainsString('Connected to Pathly: -', $html);
        $this->assertStringNotContainsString('· Space', $html);
        $this->assertStringContainsString('could not be created: Plan limit', $html);
    }

    public function testConnectionBlockWithNothingYet(): void
    {
        $block = new Connection($this->backendContext(), $this->config(), $this->memoryState(), [], $this->createMock(SecureHtmlRenderer::class));
        $this->assertStringNotContainsString('Ping of the shop', $this->elementHtml($block));
    }

    public function testRowsHaveNoScope(): void
    {
        foreach ([
            new Connection($this->backendContext(), $this->config(), $this->memoryState(), [], $this->createMock(SecureHtmlRenderer::class)),
            $this->lumiBlock(),
        ] as $block) {
            $element = $this->element();
            $html = $block->render($element);
            $this->assertNull($element->getScope());
            $this->assertNull($element->getCanUseWebsiteValue());
            $this->assertNull($element->getCanUseDefaultValue());
            $this->assertStringContainsString('<td', $html);
        }
    }

    private function lumiBlock(): Lumi
    {
        $flows = $this->createMock(Flows::class);
        $flows->method('ids')->willReturn(['a', 'b']);
        return new Lumi($this->backendContext(), $this->config(), $flows, new Json(), [], $this->createMock(SecureHtmlRenderer::class));
    }

    public function testLumiBlock(): void
    {
        $html = $this->elementHtml($this->lumiBlock());
        $this->assertStringContainsString('data-mage-init="', $html);
        $this->assertStringContainsString('Pathly_Monitoring\/js\/lumi', html_entity_decode($html));
        $this->assertStringContainsString('https:\/\/shop.example\/pathly\/lumi\/start', html_entity_decode($html));
        $this->assertStringContainsString('Flows of the shop: 2', $html);
        $this->assertStringContainsString('pathly-lumi-owner', $html);
    }

    public function testLumiBlockBeforeConnection(): void
    {
        $this->connected = false;
        $this->assertStringContainsString('Save an API key first.', $this->elementHtml($this->lumiBlock()));
    }

    public function testLumiBlockForARoleWithoutLumi(): void
    {
        $this->allowed = false;
        $this->assertStringContainsString('does not allow you to use Lumi', $this->elementHtml($this->lumiBlock()));
    }

    private function panel(): Panel
    {
        $status = $this->createMock(StatusProvider::class);
        $status->method('get')->willReturn(['connected' => true]);
        return new Panel($this->backendContext(), $status);
    }

    public function testPanelHelpers(): void
    {
        $panel = $this->panel();
        $this->assertSame(['connected' => true], $panel->getPanel());
        $this->assertSame(
            'https://shop.example/adminhtml/system_config/edit?section=pathly_monitoring',
            $panel->getSettingsUrl()
        );
        $this->assertSame(Config::SIGNUP_URL, $panel->getSignupUrl());
        $this->assertSame('https://pathlyhq.com/dashboard', $panel->getAppUrl());
        $this->assertSame('OK', $panel->statusLabel('ok'));
        $this->assertSame('Failing', $panel->statusLabel('fail'));
        $this->assertSame('Slow', $panel->statusLabel('degraded'));
        $this->assertSame('Failing', $panel->statusLabel('strange'));
        $this->assertSame('Not run yet', $panel->statusLabel(null));
        $this->assertSame('pathly-pending', $panel->statusClass(null));
        $this->assertSame('pathly-ok', $panel->statusClass('ok'));
        $this->assertSame('pathly-slow', $panel->statusClass('degraded'));
        $this->assertSame('pathly-fail', $panel->statusClass('fail'));
        $this->assertSame('3 / 50', $panel->quotaLabel(['used' => 3, 'limit' => 50]));
        $this->assertSame('0 / unlimited', $panel->quotaLabel([]));
        $this->assertSame('Ping', $panel->quotaName('ping'));
        $this->assertSame('Lumi trials today', $panel->quotaName('assistantTrials'));
        $this->assertSame('other', $panel->quotaName('other'));
    }

    public function testPanelIsHiddenFromOtherRoles(): void
    {
        $this->allowed = false;
        $method = new \ReflectionMethod(Panel::class, '_toHtml');
        $this->assertSame('', $method->invoke($this->panel()));
    }

    public function testPanelRendersItsTemplate(): void
    {
        $panel = $this->panel();
        $panel->setTemplate('');
        $method = new \ReflectionMethod(Panel::class, '_toHtml');
        $this->assertSame('', $method->invoke($panel));
    }

    private function badgeBlock(?string $svg): Badge
    {
        $model = $this->createMock(BadgeModel::class);
        $model->method('getSvg')->willReturn($svg);
        $model->method('getStatusPageUrl')->willReturn('https://pathlyhq.com/status/shop');
        $context = $this->blockContext(Context::class, ['getEventManager' => $this->createMock(EventManager::class)]);
        return new Badge($context, $this->config(), $model);
    }

    public function testBadgeWidget(): void
    {
        $block = $this->badgeBlock('<svg></svg>');
        $this->assertTrue($block->isVisible());
        $this->assertSame('https://shop.example/pathly/badge/svg', $block->getImageUrl());
        $this->assertSame('https://pathlyhq.com/status/shop', $block->getStatusPageUrl());
        $block->setTemplate('');
        $method = new \ReflectionMethod(Badge::class, '_toHtml');
        $this->assertSame('', $method->invoke($block));
    }

    public function testBadgeWidgetIsEmptyWhileOff(): void
    {
        $this->assertFalse($this->badgeBlock(null)->isVisible());
        $this->connected = false;
        $block = $this->badgeBlock('<svg></svg>');
        $this->assertFalse($block->isVisible());
        $method = new \ReflectionMethod(Badge::class, '_toHtml');
        $this->assertSame('', $method->invoke($block));
    }
}
