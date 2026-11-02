<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model\Http;

use Pathly\Monitoring\Model\Http\Curl;
use PHPUnit\Framework\TestCase;

/**
 * Real requests to a local PHP server that echoes them.
 */
class CurlTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;
    private static string $base = '';

    public static function setUpBeforeClass(): void
    {
        $port = random_int(20000, 40000);
        $router = __DIR__ . '/_files/echo.php';
        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        self::$base = 'http://127.0.0.1:' . $port;
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port);
            if ($socket) {
                fclose($socket);
                return;
            }
            usleep(100000);
        }
        self::fail('The local server did not start');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    /**
     * @return array
     */
    private function call(string $method, ?string $body): array
    {
        $curl = new Curl();
        $curl->setHeaders(['Content-Type' => 'application/json']);
        $curl->send($method, self::$base . '/v1/echo', $body);
        $this->assertSame(200, $curl->getStatus());
        return json_decode($curl->getBody(), true);
    }

    public function testGet(): void
    {
        $this->assertSame(['method' => 'GET', 'body' => ''], $this->call('GET', null));
    }

    public function testPost(): void
    {
        $this->assertSame(['method' => 'POST', 'body' => '{"a":1}'], $this->call('POST', '{"a":1}'));
    }

    public function testPatchWithBody(): void
    {
        $this->assertSame(['method' => 'PATCH', 'body' => '{"public":false}'], $this->call('PATCH', '{"public":false}'));
    }

    public function testPatchWithoutBody(): void
    {
        $this->assertSame(['method' => 'PATCH', 'body' => ''], $this->call('PATCH', null));
    }
}
