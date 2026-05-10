<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Contract;

/**
 * Wraps WHMCS's localAPI() for testability.
 */
interface ApiInterface
{
    /**
     * @param string $command
     * @param array  $params
     * @param string $adminUsername
     * @return array
     */
    public function call(string $command, array $params, string $adminUsername): array;
}
