<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit\Model;

use Magento\Framework\FlagManager;
use Pathly\Monitoring\Model\State;
use Pathly\Monitoring\Test\Unit\Helper;
use PHPUnit\Framework\TestCase;

class StateTest extends TestCase
{
    use Helper;

    public function testReadSetRemoveAndClear(): void
    {
        $state = $this->memoryState(['pingId' => 'p1']);
        $this->assertSame('p1', $state->get('pingId'));
        $this->assertSame('none', $state->get('missing', 'none'));
        $state->set(['flowIds' => ['f1'], 'pingId' => null]);
        $this->assertSame(['flowIds' => ['f1']], $this->flag);
        $this->assertSame(['flowIds' => ['f1']], $state->all());
        $state->clear();
        $this->assertSame([], $state->all());
        $this->assertSame([], $this->flag);
    }

    public function testReloadReadsTheDatabaseAgain(): void
    {
        $state = $this->memoryState(['a' => 1]);
        $this->assertSame(1, $state->get('a'));
        $this->flag = ['a' => 2];
        $this->assertSame(1, $state->get('a'));
        $state->reload();
        $this->assertSame(2, $state->get('a'));
    }

    public function testFlagThatIsNotAnArray(): void
    {
        $flags = $this->createMock(FlagManager::class);
        $flags->method('getFlagData')->willReturn('broken');
        $this->assertSame([], (new State($flags))->all());
    }
}
