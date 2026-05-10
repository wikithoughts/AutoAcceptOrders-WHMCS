<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Adapter;

use AutoAcceptOrders\Contract\LoggerInterface;

/**
 * LoggerInterface implementation that delegates to WHMCS logModuleCall().
 */
class WhmcsModuleLogger implements LoggerInterface
{
    public function log(string $module, string $action, array $request, $response): void
    {
        logModuleCall($module, $action, $request, $response, null, []);
    }
}
