<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model\Http;

use Pathly\Monitoring\Model\Api\ApiException;
use Pathly\Monitoring\Model\Http\Curl;
use Pathly\Monitoring\Model\Http\CurlFactory;
use Pathly\Monitoring\Model\Http\Transport;
use PHPUnit\Framework\TestCase;

class TransportTest extends TestCase
{
    public function testSendsThroughCurlAndNormalizesHeaders(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects($this->once())->method('setTimeout')->with(3);
        $options = [];
        $curl->method('setOption')->willReturnCallback(function ($name, $value) use (&$options) {
            $options[$name] = $value;
        });
        $curl->expects($this->once())->method('setHeaders')->with(['Accept' => 'application/json']);
        $curl->expects($this->once())->method('send')->with('PATCH', 'https://api.pathlyhq.com/v1/x', '{}');
        $curl->method('getHeaders')->willReturn(['Retry-After' => '60', 'Set-Cookie' => ['a=1', 'b=2']]);
        $curl->method('getStatus')->willReturn(429);
        $curl->method('getBody')->willReturn('{"error":"busy"}');
        $factory = $this->createMock(CurlFactory::class);
        $factory->method('create')->willReturn($curl);

        $response = (new Transport($factory))->send(
            'PATCH',
            'https://api.pathlyhq.com/v1/x',
            ['Accept' => 'application/json'],
            '{}',
            3
        );

        $this->assertSame(
            ['status' => 429, 'body' => '{"error":"busy"}', 'headers' => ['retry-after' => '60', 'set-cookie' => 'a=1, b=2']],
            $response
        );
        $this->assertSame(3, $options[CURLOPT_CONNECTTIMEOUT]);
        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
    }

    public function testConnectTimeoutIsCappedAtFiveSeconds(): void
    {
        $curl = $this->createMock(Curl::class);
        $options = [];
        $curl->method('setOption')->willReturnCallback(function ($name, $value) use (&$options) {
            $options[$name] = $value;
        });
        $curl->method('getHeaders')->willReturn([]);
        $factory = $this->createMock(CurlFactory::class);
        $factory->method('create')->willReturn($curl);
        (new Transport($factory))->send('GET', 'https://api.pathlyhq.com/v1/account', [], null, 10);
        $this->assertSame(5, $options[CURLOPT_CONNECTTIMEOUT]);
    }

    public function testNetworkErrorBecomesApiException(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->method('send')->willThrowException(new \Exception('Could not resolve host'));
        $factory = $this->createMock(CurlFactory::class);
        $factory->method('create')->willReturn($curl);
        try {
            (new Transport($factory))->send('GET', 'https://api.pathlyhq.com/v1/account', [], null, 10);
            $this->fail('Expected an exception');
        } catch (ApiException $e) {
            $this->assertSame(0, $e->getStatus());
            $this->assertSame('Pathly could not be reached: Could not resolve host', $e->getMessage());
        }
    }

    public function testFactoryCreatesAFreshClient(): void
    {
        $factory = new CurlFactory();
        $this->assertInstanceOf(Curl::class, $factory->create());
        $this->assertNotSame($factory->create(), $factory->create());
    }
}
