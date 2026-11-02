<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use Pathly\Monitoring\Model\Config;
use Pathly\Monitoring\Model\Flows;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;

class FlowsTest extends TestCase
{
    use Helper;

    public function testSavedAndTypedFlowsWithoutDuplicate(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getExtraFlowIds')->willReturn(['b', 'c']);
        $flows = new Flows($this->memoryState(['flowIds' => ['a', 7, 'b']]), $config);
        $this->assertSame(['a', 'b', 'c'], $flows->ids());
        $flows->add('a');
        $flows->add('d');
        $this->assertSame(['a', 'b', 'd'], $this->flag['flowIds']);
    }
}
