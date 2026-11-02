<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use InvalidArgumentException;
use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Badge;
use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\Ping;
use Pathly\Monitoring\Model\Replay;
use Pathly\Monitoring\Model\Sync;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SyncTest extends TestCase
{
    use Helper;

    private bool $connected = true;
    private bool $badgeOn = false;
    private Ping $ping;
    private Replay $replay;
    private Badge $badge;
    private LoggerInterface $logger;

    private function sync(array $state = []): Sync
    {
        $config = $this->createMock(Config::class);
        $config->method('isConnected')->willReturnCallback(fn () => $this->connected);
        $config->method('isBadgeEnabled')->willReturnCallback(fn () => $this->badgeOn);
        $this->ping = $this->createMock(Ping::class);
        $this->replay = $this->createMock(Replay::class);
        $this->replay->method('check')->willReturn(['result' => 'nothing']);
        $this->badge = $this->createMock(Badge::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        return new Sync($config, $this->memoryState($state), $this->ping, $this->replay, $this->badge, $this->logger);
    }

    public function testNothingBeforeConnection(): void
    {
        $this->connected = false;
        $sync = $this->sync();
        $this->ping->expects($this->never())->method('ensure');
        $this->replay->expects($this->never())->method('check');
        $this->assertSame(['connected' => false], $sync->run());
    }

    public function testRetriesTheMissingPingOnceAnHour(): void
    {
        $sync = $this->sync(['pingAttemptAt' => time() - 4000]);
        $this->ping->expects($this->once())->method('ensure')->willReturn('ping');
        $this->replay->expects($this->once())->method('check')->with(true);
        $this->assertSame(
            ['connected' => true, 'ping' => 'ping', 'replay' => ['result' => 'nothing'], 'badge' => 'off'],
            $sync->run(true)
        );
    }

    public function testDoesNotRetryTooSoon(): void
    {
        $sync = $this->sync(['pingAttemptAt' => time() - 60]);
        $this->ping->expects($this->never())->method('ensure');
        $this->assertNull($sync->run()['ping']);
    }

    public function testRefreshesTheBadge(): void
    {
        $this->badgeOn = true;
        $sync = $this->sync(['pingId' => 'p']);
        $this->badge->expects($this->once())->method('refresh');
        $this->assertSame('refreshed', $sync->run()['badge']);
    }

    public function testBadgeErrorIsLoggedWithoutTheKey(): void
    {
        $this->badgeOn = true;
        $sync = $this->sync(['pingId' => 'p']);
        $this->badge->method('refresh')->willThrowException(new ApiException('Not found', 404));
        $this->logger->expects($this->once())->method('warning')
            ->with('Pathly: the badge could not be refreshed. Not found');
        $this->assertSame('error', $sync->run()['badge']);
    }

    public function testUnexpectedBadgeAddress(): void
    {
        $this->badgeOn = true;
        $sync = $this->sync(['pingId' => 'p']);
        $this->badge->method('refresh')->willThrowException(new InvalidArgumentException('Unexpected badge address.'));
        $this->assertSame('error', $sync->run()['badge']);
    }
}
