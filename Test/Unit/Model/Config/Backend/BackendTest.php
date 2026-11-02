<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Badge;
use Pathly\Monitoring\Model\Config\Backend\ApiKey;
use Pathly\Monitoring\Model\Config\Backend\BadgeToggle;
use Pathly\Monitoring\Model\Connection;
use Pathly\Monitoring\Model\ErrorMessage;
use PHPUnit\Framework\TestCase;

class BackendTest extends TestCase
{
    private Connection $connection;
    private ?string $oldValue = null;

    private function context(): Context
    {
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));
        return $context;
    }

    private function config(): ScopeConfigInterface
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(fn () => $this->oldValue);
        return $config;
    }

    private function apiKey(): ApiKey
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(fn ($value) => 'enc:' . $value);
        $this->connection = $this->createMock(Connection::class);
        return new ApiKey(
            $this->context(),
            $this->createMock(Registry::class),
            $this->config(),
            $this->createMock(TypeListInterface::class),
            $encryptor,
            $this->connection
        );
    }

    public function testNewKeyIsCheckedEncryptedThenConnected(): void
    {
        $model = $this->apiKey();
        $model->setPath('pathly_monitoring/connection/api_key');
        $model->setValue(' sp_new_key_123 ');
        $this->connection->expects($this->once())->method('validate')->with('sp_new_key_123')
            ->willReturn(['plan' => ['name' => 'Solo']]);
        $this->connection->expects($this->once())->method('connect')
            ->with('sp_new_key_123', ['plan' => ['name' => 'Solo']]);
        $this->connection->expects($this->never())->method('disconnect');
        $model->beforeSave();
        $this->assertSame('enc:sp_new_key_123', $model->getValue());
        $this->assertTrue($model->isSaveAllowed());
        $model->afterSave();
        $model->afterSave();
    }

    public function testRefusedKeyIsNotSaved(): void
    {
        $model = $this->apiKey();
        $model->setValue('sp_bad_key_1234');
        $this->connection->method('validate')->willThrowException(new LocalizedException(__('Refused')));
        $this->expectException(LocalizedException::class);
        $model->beforeSave();
    }

    public function testUnchangedKeyDoesNothing(): void
    {
        $model = $this->apiKey();
        $model->setValue('******');
        $this->connection->expects($this->never())->method('validate');
        $this->connection->expects($this->never())->method('connect');
        $this->connection->expects($this->never())->method('disconnect');
        $model->beforeSave();
        $this->assertFalse($model->isSaveAllowed());
        $model->afterSave();
    }

    public function testRemovedKeyDisconnects(): void
    {
        $model = $this->apiKey();
        $model->setValue('');
        $this->connection->expects($this->never())->method('validate');
        $this->connection->expects($this->once())->method('disconnect');
        $model->beforeSave();
        $model->afterSave();
    }

    private function badgeToggle(Badge $badge): BadgeToggle
    {
        return new BadgeToggle(
            $this->context(),
            $this->createMock(Registry::class),
            $this->config(),
            $this->createMock(TypeListInterface::class),
            $badge,
            new ErrorMessage()
        );
    }

    public function testBadgeTurnedOn(): void
    {
        $badge = $this->createMock(Badge::class);
        $badge->expects($this->once())->method('enable');
        $badge->expects($this->never())->method('disable');
        $this->oldValue = '0';
        $model = $this->badgeToggle($badge);
        $model->setValue('1');
        $model->afterSave();
    }

    public function testBadgeTurnedOff(): void
    {
        $badge = $this->createMock(Badge::class);
        $badge->expects($this->once())->method('disable');
        $this->oldValue = '1';
        $model = $this->badgeToggle($badge);
        $model->setValue('0');
        $model->afterSave();
    }

    public function testBadgeUnchanged(): void
    {
        $badge = $this->createMock(Badge::class);
        $badge->expects($this->never())->method('enable');
        $badge->expects($this->never())->method('disable');
        $this->oldValue = '1';
        $model = $this->badgeToggle($badge);
        $model->setValue('1');
        $model->afterSave();
    }

    public function testBadgeRefusedByPathly(): void
    {
        $badge = $this->createMock(Badge::class);
        $badge->method('enable')->willThrowException(new ApiException('Not connected', 0, 'not_configured'));
        $this->oldValue = '0';
        $model = $this->badgeToggle($badge);
        $model->setValue('1');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Badge: Connect Pathly first');
        $model->afterSave();
    }
}
