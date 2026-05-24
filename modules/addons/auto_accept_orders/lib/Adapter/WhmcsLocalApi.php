<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Adapter;

use AutoAcceptOrders\Contract\ApiInterface;

/**
 * ApiInterface implementation that delegates to WHMCS localAPI().
 */
class WhmcsLocalApi implements ApiInterface
{
    public function call(string $command, array $params, string $adminUsername): array
    {
        $result = localAPI($command, $params, $adminUsername);
        return is_array($result) ? $result : [];
    }
}
