<?php

declare(strict_types=1);

use AutoAcceptOrders\Hook\FreeOrderHandler;
use AutoAcceptOrders\Hook\InvoicePaidHandler;
use AutoAcceptOrders\Service\Factory;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/autoload.php';

add_hook('InvoicePaid', 1, function (array $vars): void {
    try {
        (new InvoicePaidHandler(Factory::createService()))->handle($vars);
    } catch (\Throwable $e) {
        logModuleCall(
            'auto_accept_orders',
            'InvoicePaid',
            [],
            ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
            null,
            []
        );
    }
});

add_hook('ShoppingCartCheckoutComplete', 1, function (array $vars): array {
    try {
        return (new FreeOrderHandler(Factory::createService()))->handle($vars);
    } catch (\Throwable $e) {
        logModuleCall(
            'auto_accept_orders',
            'ShoppingCartCheckoutComplete',
            [],
            ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
            null,
            []
        );
    }
    return [];
});
