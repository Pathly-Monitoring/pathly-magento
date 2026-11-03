<?php
// Module used by the integration test to simulate the update of a module.
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Test_Dummy',
    __DIR__
);
