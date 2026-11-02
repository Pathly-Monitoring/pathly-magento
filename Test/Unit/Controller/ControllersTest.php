<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Controller;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Pathly\Monitoring\Controller\Adminhtml\Lumi\Save;
use Pathly\Monitoring\Controller\Adminhtml\Lumi\Start;
use Pathly\Monitoring\Controller\Adminhtml\Lumi\Status;
use Pathly\Monitoring\Controller\Badge\Svg;
use Pathly\Monitoring\Model\Badge;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\Lumi;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;

class ControllersTest extends TestCase
{
    use Helper;

    private const TRIAL = '0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab';

    private array $params = [];
    private ?array $answer = null;
    private Lumi $lumi;

    /**
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    private function action(string $class): object
    {
        $this->installObjectManager();
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn ($name) => $this->params[$name] ?? null);
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnCallback(function ($data) use ($json) {
            $this->answer = $data;
            return $json;
        });
        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($json);
        $this->lumi = $this->createMock(Lumi::class);
        return new $class($context, $this->lumi, $factory);
    }

    public function testStartNeedsTheConsent(): void
    {
        $action = $this->action(Start::class);
        $this->lumi->expects($this->never())->method('start');
        $action->execute();
        $this->assertSame(['ok' => false, 'message' => 'Confirm that you manage this shop.'], $this->answer);
        $this->assertSame('Pathly_Monitoring::lumi', Start::ADMIN_RESOURCE);
    }

    public function testStart(): void
    {
        $action = $this->action(Start::class);
        $this->params = ['confirm_owner' => '1'];
        $this->lumi->method('start')->willReturn(['trialId' => self::TRIAL, 'siteKind' => 'magento']);
        $action->execute();
        $this->assertSame(['ok' => true, 'trialId' => self::TRIAL, 'siteKind' => 'magento'], $this->answer);
    }

    public function testStatus(): void
    {
        $action = $this->action(Status::class);
        $this->params = ['trial' => self::TRIAL];
        $this->lumi->method('status')->with(self::TRIAL)->willReturn(['status' => 'ready', 'done' => true]);
        $action->execute();
        $this->assertSame(['ok' => true, 'status' => 'ready', 'done' => true], $this->answer);
    }

    public function testStatusOfAnUnknownTrial(): void
    {
        $action = $this->action(Status::class);
        $this->params = ['trial' => '../x'];
        $this->lumi->expects($this->never())->method('status');
        $action->execute();
        $this->assertSame(['ok' => false, 'message' => 'Unknown trial.'], $this->answer);
    }

    public function testSave(): void
    {
        $action = $this->action(Save::class);
        $this->params = ['trial' => self::TRIAL, 'index' => '2'];
        $this->lumi->method('save')->with(self::TRIAL, 2)->willReturn('flow-id');
        $action->execute();
        $this->assertSame(['ok' => true, 'flowId' => 'flow-id'], $this->answer);
    }

    public function testSaveRefused(): void
    {
        $action = $this->action(Save::class);
        $this->params = ['trial' => self::TRIAL];
        $this->lumi->method('save')->willThrowException(new LocalizedException(__('Plan limit')));
        $action->execute();
        $this->assertSame(['ok' => false, 'message' => 'Plan limit'], $this->answer);
    }

    private function svg(bool $enabled, ?string $svg): array
    {
        $headers = [];
        $state = ['code' => 200, 'contents' => null];
        $raw = $this->createMock(Raw::class);
        $raw->method('setHeader')->willReturnCallback(function ($name, $value) use (&$headers, $raw) {
            $headers[$name] = $value;
            return $raw;
        });
        $raw->method('setHttpResponseCode')->willReturnCallback(function ($code) use (&$state, $raw) {
            $state['code'] = $code;
            return $raw;
        });
        $raw->method('setContents')->willReturnCallback(function ($contents) use (&$state, $raw) {
            $state['contents'] = $contents;
            return $raw;
        });
        $factory = $this->createMock(ResultFactory::class);
        $factory->method('create')->with(ResultFactory::TYPE_RAW)->willReturn($raw);
        $config = $this->createMock(Config::class);
        $config->method('isBadgeEnabled')->willReturn($enabled);
        $badge = $this->createMock(Badge::class);
        $badge->method('getSvg')->willReturn($svg);
        $this->assertSame($raw, (new Svg($factory, $config, $badge))->execute());
        return $state + ['headers' => $headers];
    }

    public function testBadgeIsServedByTheShop(): void
    {
        $result = $this->svg(true, '<svg></svg>');
        $this->assertSame(200, $result['code']);
        $this->assertSame('<svg></svg>', $result['contents']);
        $this->assertSame('image/svg+xml; charset=utf-8', $result['headers']['Content-Type']);
        $this->assertSame('nosniff', $result['headers']['X-Content-Type-Options']);
        $this->assertStringContainsString('sandbox', $result['headers']['Content-Security-Policy']);
    }

    public function testBadgeOffAnswers404(): void
    {
        $this->assertSame(404, $this->svg(false, '<svg></svg>')['code']);
        $result = $this->svg(true, null);
        $this->assertSame(404, $result['code']);
        $this->assertSame('', $result['contents']);
    }
}
