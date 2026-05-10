<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Mark WHMCS environment so module files don't die().
if (!defined('WHMCS')) {
    define('WHMCS', true);
}

// Load the module's own autoloader so AutoAcceptOrders\ classes resolve.
require_once __DIR__ . '/../modules/addons/auto_accept_orders/autoload.php';

// Conditionally define WHMCS global functions that tests rely on.
// Each guard prevents redeclaration if a WHMCS environment is somehow present.
require_once __DIR__ . '/Stub/WhmcsFunctions.php';
