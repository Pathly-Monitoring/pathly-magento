<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

namespace Pathly\Monitoring\Test\Unit;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    public function testModuleIsRegistered(): void
    {
        require dirname(__DIR__, 2) . '/registration.php';
        $registrar = new ComponentRegistrar();
        $this->assertSame(
            dirname(__DIR__, 2),
            $registrar->getPath(ComponentRegistrar::MODULE, 'Pathly_Monitoring')
        );
    }
}
