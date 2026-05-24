<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Contract;

/**
 * Wraps WHMCS's logModuleCall() for testability.
 */
interface LoggerInterface
{
    /**
     * @param string $module
     * @param string $action
     * @param array  $request
     * @param mixed  $response
     * @return void
     */
    public function log(string $module, string $action, array $request, $response): void;
}
