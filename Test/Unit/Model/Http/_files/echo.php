<?php
/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
declare(strict_types=1);

// Router of the local test server: echoes the method and the body.
header('Content-Type: application/json');
// phpcs:ignore Magento2.Security.Superglobal
$method = $_SERVER['REQUEST_METHOD'];
echo json_encode(['method' => $method, 'body' => file_get_contents('php://input')]);
