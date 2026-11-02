<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use Pathly\Monitoring\Model\Api\Client;
use Pathly\Monitoring\Model\Api\ClientFactory;
use Pathly\Monitoring\Model\Badge;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;

class BadgeTest extends TestCase
{
    use Helper;

    private const PAGE = '0b6c8f8e-1a2b-4c3d-8e9f-0123456789ab';
    private const BADGE = 'https://api.pathlyhq.com/public/status/shop/badge.svg';

    private Client $client;

    private function badge(array $state = []): Badge
    {
        $this->client = $this->createMock(Client::class);
        $clients = $this->createMock(ClientFactory::class);
        $clients->method('create')->willReturn($this->client);
        return new Badge($clients, $this->memoryState($state));
    }

    private function page(): array
    {
        return ['id' => self::PAGE, 'url' => 'https://pathlyhq.com/status/shop', 'badgeUrl' => self::BADGE];
    }

    public function testEnablePublishesAPrivatePage(): void
    {
        $badge = $this->badge();
        $this->client->method('listStatusPages')->willReturn([['public' => false]]);
        $this->client->expects($this->once())->method('createStatusPage')->with(true)->willReturn($this->page());
        $this->client->method('fetchBadge')->with(self::BADGE)->willReturn('<svg></svg>');
        $badge->enable();
        $this->assertTrue($this->flag['statusPage']['publishedByModule']);
        $this->assertSame('<svg></svg>', $badge->getSvg());
        $this->assertSame('https://pathlyhq.com/status/shop', $badge->getStatusPageUrl());
        $this->assertIsInt($this->flag['badgeFetchedAt']);
    }

    public function testEnableKeepsTrackOfAPageThatWasAlreadyPublic(): void
    {
        $badge = $this->badge();
        $this->client->method('listStatusPages')->willReturn([['public' => true], ['public' => false]]);
        $this->client->method('createStatusPage')->willReturn([]);
        $this->client->expects($this->never())->method('fetchBadge');
        $badge->enable();
        $this->assertFalse($this->flag['statusPage']['publishedByModule']);
        $this->assertNull($badge->getSvg());
        $this->assertNull($badge->getStatusPageUrl());
    }

    public function testDisableUnpublishesOnlyWhatTheModulePublished(): void
    {
        $badge = $this->badge([
            'statusPage' => $this->page() + ['publishedByModule' => true],
            'badgeSvg' => '<svg></svg>',
        ]);
        $this->client->expects($this->once())->method('updateStatusPage')->with(self::PAGE, false);
        $badge->disable();
        $this->assertSame([], $this->flag);
    }

    public function testDisableLeavesAPageThatWasPublicBefore(): void
    {
        $badge = $this->badge(['statusPage' => $this->page() + ['publishedByModule' => false]]);
        $this->client->expects($this->never())->method('updateStatusPage');
        $badge->disable();
    }

    public function testDisableWithoutIdentifier(): void
    {
        $badge = $this->badge(['statusPage' => ['publishedByModule' => true]]);
        $this->client->expects($this->never())->method('updateStatusPage');
        $badge->disable();
        $this->assertNull($badge->getStatusPageUrl());
    }

    public function testRefreshAndStoredValues(): void
    {
        $badge = $this->badge(['statusPage' => 'broken', 'badgeSvg' => '']);
        $this->assertNull($badge->getSvg());
        $this->client->expects($this->never())->method('fetchBadge');
        $badge->refresh();
    }

    public function testStatusPageOnAnotherHostIsNotLinked(): void
    {
        $badge = $this->badge(['statusPage' => ['url' => 'https://evil.example/status/shop']]);
        $this->assertNull($badge->getStatusPageUrl());
    }
}
