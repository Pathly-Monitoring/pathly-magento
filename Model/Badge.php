<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Model;

use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Api\ClientFactory;

/**
 * Availability badge, off by default.
 *
 * Turning it on publishes the status page of the account (or of the Space of
 * the key). The shop keeps a copy of the SVG badge, refreshed by the cron,
 * and serves it itself: a visitor of the shop never loads anything from Pathly.
 */
class Badge
{
    private const STATUS_PAGE = '#^https://pathlyhq\.com/status/[a-z0-9-]+(/[a-z0-9-]+)?$#';

    /**
     * @param ClientFactory $clients
     * @param State $state
     */
    public function __construct(
        private readonly ClientFactory $clients,
        private readonly State $state
    ) {
    }

    /**
     * Publish the status page and keep its badge.
     *
     * @return void
     * @throws ApiException
     */
    public function enable(): void
    {
        $client = $this->clients->create();
        $wasPublic = false;
        foreach ($client->listStatusPages() as $page) {
            $wasPublic = $wasPublic || !empty($page['public']);
        }
        $page = $client->createStatusPage(true);
        $this->state->set([
            'statusPage' => [
                'id' => (string) ($page['id'] ?? ''),
                'url' => (string) ($page['url'] ?? ''),
                'badgeUrl' => (string) ($page['badgeUrl'] ?? ''),
                'publishedByModule' => !$wasPublic,
            ],
        ]);
        $this->refresh();
    }

    /**
     * Hide the badge. The status page is unpublished only if the module published it.
     *
     * @return void
     * @throws ApiException
     */
    public function disable(): void
    {
        $page = $this->page();
        if (!empty($page['publishedByModule']) && $page['id'] !== '') {
            $this->clients->create()->updateStatusPage($page['id'], false);
        }
        $this->state->set(['statusPage' => null, 'badgeSvg' => null, 'badgeFetchedAt' => null]);
    }

    /**
     * Download the badge again.
     *
     * @return void
     * @throws ApiException
     */
    public function refresh(): void
    {
        $page = $this->page();
        if ($page['badgeUrl'] === '') {
            return;
        }
        $svg = $this->clients->create()->fetchBadge($page['badgeUrl']);
        $this->state->set(['badgeSvg' => $svg, 'badgeFetchedAt' => time()]);
    }

    /**
     * Stored SVG badge, null before the first download.
     *
     * @return string|null
     */
    public function getSvg(): ?string
    {
        $svg = $this->state->get('badgeSvg');
        return is_string($svg) && $svg !== '' ? $svg : null;
    }

    /**
     * Public status page the badge links to, null when unknown.
     *
     * @return string|null
     */
    public function getStatusPageUrl(): ?string
    {
        $url = $this->page()['url'];
        return preg_match(self::STATUS_PAGE, $url) ? $url : null;
    }

    /**
     * Stored status page.
     *
     * @return array{id: string, url: string, badgeUrl: string, publishedByModule: bool}
     */
    private function page(): array
    {
        $page = $this->state->get('statusPage');
        $page = is_array($page) ? $page : [];
        return [
            'id' => (string) ($page['id'] ?? ''),
            'url' => (string) ($page['url'] ?? ''),
            'badgeUrl' => (string) ($page['badgeUrl'] ?? ''),
            'publishedByModule' => !empty($page['publishedByModule']),
        ];
    }
}
