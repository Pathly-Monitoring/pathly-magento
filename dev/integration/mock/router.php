<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

// phpcs:ignoreFile -- simulator of the Pathly API for the integration test, never shipped.

/*
 * Simulated Pathly API (contract of Pathly-Monitoring/pathly#662), served
 * as https://api.pathlyhq.com to the shop under test. Every request is
 * written to /data/requests.jsonl, the scenarios to /data/state.json.
 */
const KEY = 'sp_integration_key_0123456789';
const TRIAL = '7d1f0a52-3b8e-4c6a-9f10-5a2b3c4d5e6f';
const FLOW = '9e8d7c6b-5a49-4382-8170-6f5e4d3c2b1a';
const RUN = '1a2b3c4d-5e6f-4a7b-8c9d-0e1f2a3b4c5d';
const PAGE = '2b3c4d5e-6f7a-4b8c-9d0e-1f2a3b4c5d6e';

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$raw = file_get_contents('php://input');
$body = json_decode($raw ?: 'null', true);

file_put_contents('/data/requests.jsonl', json_encode([
    'method' => $method,
    'uri' => $uri,
    'authorization' => $headers['authorization'] ?? null,
    'userAgent' => $headers['user-agent'] ?? null,
    'idempotencyKey' => $headers['idempotency-key'] ?? null,
    'body' => $body,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

$stateFile = '/data/state.json';
$state = is_file($stateFile) ? json_decode(file_get_contents($stateFile), true) : ['scenarios' => [], 'saved' => []];
$save = static function () use (&$state, $stateFile): void {
    file_put_contents($stateFile, json_encode($state));
};
$send = static function (int $status, $data, string $type = 'application/json'): void {
    http_response_code($status);
    header('Content-Type: ' . $type);
    echo is_string($data) ? $data : json_encode($data);
    exit;
};

// Public routes: no key.
if ($method === 'GET' && preg_match('#^/public/status/[a-z0-9/-]+/badge\.svg$#', $path)) {
    $send(200, '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="20"><rect width="120" height="20" fill="#1f7a3a"/>'
        . '<text x="8" y="14" fill="#fff" font-size="11" font-family="sans-serif">Pathly · up</text></svg>', 'image/svg+xml');
}
if ($method === 'GET' && str_starts_with($path, '/public/run-shot/')) {
    $send(200, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ), 'image/png');
}

if (($headers['authorization'] ?? '') !== 'Bearer ' . KEY) {
    $send(401, ['error' => 'Clé d\'API invalide']);
}

switch (true) {
    case $method === 'GET' && $path === '/v1/account':
        $send(200, [
            'organization' => ['id' => 'org', 'name' => 'Boutique test'],
            'space' => null,
            'plan' => ['id' => 'solo', 'name' => 'Solo', 'priceMonthlyEur' => 0],
            'billingUrl' => 'https://pathlyhq.com/billing',
            'quotas' => [
                'ping' => ['used' => count($state['scenarios']), 'limit' => 5, 'remaining' => 4],
                'flow' => ['used' => count($state['saved']), 'limit' => 1, 'remaining' => 1],
                'runs' => ['used' => 12, 'limit' => 120, 'remaining' => 108, 'period' => 'month'],
                'assistantTrials' => ['used' => 1, 'limit' => 2, 'remaining' => 1, 'period' => 'day'],
                'spaces' => null,
            ],
        ]);
        // no break: $send exits
    case $method === 'GET' && $path === '/v1/scenarios':
        $send(200, ['items' => array_values($state['scenarios']), 'nextCursor' => null]);
        // no break
    case $method === 'POST' && $path === '/v1/scenarios':
        $id = sprintf('%08x-0000-4000-8000-%012x', count($state['scenarios']) + 1, time());
        $state['scenarios'][$id] = ['id' => $id, 'type' => 'http', 'httpChain' => null, 'lastStatus' => 'ok'] + $body;
        $save();
        $send(201, $state['scenarios'][$id]);
        // no break
    case $method === 'GET' && preg_match('#^/v1/scenarios/([0-9a-f-]{36})$#', $path, $m) === 1:
        if ($m[1] === FLOW) {
            $send(200, ['id' => FLOW, 'name' => 'cart · localhost', 'type' => 'browser', 'lastStatus' => 'fail']);
        }
        $send(200, $state['scenarios'][$m[1]] ?? ['id' => $m[1], 'name' => 'Ping', 'lastStatus' => 'ok']);
        // no break
    case $method === 'POST' && preg_match('#^/v1/scenarios/([0-9a-f-]{36})/run$#', $path) === 1:
        $send(200, ['ok' => true, 'queued' => true, 'jobId' => 'job']);
        // no break
    case $method === 'GET' && $path === '/v1/runs':
        $send(200, ['items' => [
            ['id' => RUN, 'status' => 'fail', 'created_at' => '2026-11-02T08:15:00.000Z',
                'message' => 'Le bouton « Passer commande » est absent'],
        ], 'nextCursor' => null]);
        // no break
    case $method === 'GET' && $path === '/v1/runs/' . RUN:
        $send(200, ['id' => RUN, 'status' => 'fail', 'screenshotUrl' => 'https://api.pathlyhq.com/public/run-shot/'
            . RUN . '.1767225600.sig', 'screenshotExpiresAt' => '2026-11-02T08:30:00.000Z']);
        // no break
    case $method === 'POST' && $path === '/v1/flow-assistant/detect':
        $send(200, ['available' => true, 'reason' => null, 'siteKind' => 'magento', 'ai' => false,
            'limit' => 2, 'remaining' => 1]);
        // no break
    case $method === 'POST' && $path === '/v1/flow-assistant/trials':
        if (($body['confirmOwner'] ?? null) !== true) {
            $send(400, ['error' => 'Confirmez que vous gérez ce site.', 'code' => 'confirm_owner']);
        }
        $send(202, ['id' => TRIAL]);
        // no break
    case $method === 'GET' && $path === '/v1/flow-assistant/trials/' . TRIAL:
        $steps = static fn (int $n) => array_fill(0, $n, ['action' => 'click']);
        $send(200, ['item' => [
            'id' => TRIAL, 'status' => 'ready', 'siteKind' => 'magento', 'mode' => 'template',
            'reason' => null, 'rejected' => 0,
            'proposals' => [
                ['index' => 0, 'kind' => 'cart', 'source' => 'template', 'steps' => $steps(6),
                    'stoppedBefore' => 'Passer commande', 'replay' => ['ok' => true, 'durationMs' => 9000]]
                    + (in_array(0, $state['saved'], true) ? ['savedMonitorId' => FLOW] : []),
                ['index' => 1, 'kind' => 'login', 'source' => 'template', 'steps' => $steps(4),
                    'replay' => ['ok' => true, 'durationMs' => 5000]],
            ],
        ]]);
        // no break
    case $method === 'POST' && $path === '/v1/flow-assistant/trials/' . TRIAL . '/save':
        $index = (int) ($body['index'] ?? -1);
        if (in_array($index, $state['saved'], true)) {
            $send(409, ['error' => 'Ce Flow est déjà enregistré.', 'code' => 'already_saved', 'monitorId' => FLOW]);
        }
        if ($index !== 0) {
            $send(403, ['error' => 'Votre offre Solo comprend 1 Flow.', 'code' => 'PLAN_LIMIT']);
        }
        $state['saved'][] = $index;
        $save();
        $send(201, ['item' => ['id' => FLOW, 'name' => 'cart · localhost']]);
        // no break
    case $method === 'GET' && $path === '/v1/status-pages':
        $send(200, ['items' => empty($state['page']) ? [] : [$state['page']]]);
        // no break
    case ($method === 'POST' && $path === '/v1/status-pages') || ($method === 'PATCH' && $path === '/v1/status-pages/' . PAGE):
        $state['page'] = [
            'id' => PAGE, 'kind' => 'organization', 'spaceId' => null, 'name' => 'Boutique test',
            'slug' => 'boutique-test', 'public' => (bool) ($body['public'] ?? false),
            'url' => 'https://pathlyhq.com/status/boutique-test',
            'badgeUrl' => 'https://api.pathlyhq.com/public/status/boutique-test/badge.svg',
        ];
        $save();
        $send($method === 'POST' ? 201 : 200, $state['page']);
        // no break
    default:
        $send(404, ['error' => 'Route inconnue du simulateur : ' . $method . ' ' . $path]);
}
