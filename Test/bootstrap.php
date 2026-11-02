<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

/*
 * Unit tests run against the sources of Magento Open Source (MAGENTO_ROOT,
 * prepared by dev/magento-sources.sh) and PHPUnit from dev/tools/phpunit.
 */
$magentoRoot = getenv('MAGENTO_ROOT') ?: dirname(__DIR__, 2) . '/magento';
if (!is_file($magentoRoot . '/vendor/autoload.php')) {
    throw new RuntimeException('Magento sources not found. Set MAGENTO_ROOT (see dev/magento-sources.sh).');
}
if (!defined('BP')) {
    define('BP', $magentoRoot);
}
require dirname(__DIR__) . '/dev/tools/phpunit/vendor/autoload.php';
$loader = require $magentoRoot . '/vendor/autoload.php';
$loader->addPsr4('Pathly\\Monitoring\\', dirname(__DIR__));
require_once $magentoRoot . '/lib/internal/Magento/Framework/Phrase/__.php';
