<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

if (!function_exists('aao_is_enabled')) {
    /**
     * Check whether the addon module is enabled.
     *
     * @return bool
     */
    function aao_is_enabled()
    {
        $enabledValue = Capsule::table('tbladdonmodules')
            ->where('module', 'auto_accept_orders')
            ->where('setting', 'enabled')
            ->value('value');

        if ($enabledValue === null) {
            return false;
        }

        return in_array(strtolower((string) $enabledValue), ['1', 'on', 'true', 'yes'], true);
    }
}

if (!function_exists('aao_get_config_value')) {
    /**
     * Fetch addon config value by setting key.
     *
     * @param string $key
     *
     * @return string
     */
    function aao_get_config_value($key)
    {
        return (string) Capsule::table('tbladdonmodules')
            ->where('module', 'auto_accept_orders')
            ->where('setting', $key)
            ->value('value');
    }
}

if (!function_exists('aao_resolve_admin_username')) {
    /**
     * Resolve configured admin or fallback to the first active Full Administrator.
     *
     * Returns null and logs a module error if no Full Administrator exists.
     * A Full Administrator account is required; falling back to limited-permission
     * accounts risks silent AcceptOrder failures due to insufficient permissions.
     *
     * @return string|null
     */
    function aao_resolve_admin_username()
    {
        $configuredUsername = trim(aao_get_config_value('admin_username'));

        if ($configuredUsername !== '') {
            $validConfigured = Capsule::table('tbladmins as a')
                ->join('tbladminroles as r', 'r.id', '=', 'a.roleid')
                ->where('a.username', $configuredUsername)
                ->where('a.disabled', 0)
                ->where('r.name', 'Full Administrator')
                ->value('a.username');

            if (!empty($validConfigured)) {
                return (string) $validConfigured;
            }
        }

        $fallbackFullAdmin = Capsule::table('tbladmins as a')
            ->join('tbladminroles as r', 'r.id', '=', 'a.roleid')
            ->where('a.disabled', 0)
            ->where('r.name', 'Full Administrator')
            ->orderBy('a.id', 'asc')
            ->value('a.username');

        if (!empty($fallbackFullAdmin)) {
            return (string) $fallbackFullAdmin;
        }

        logModuleCall(
            'auto_accept_orders',
            'aao_resolve_admin_username',
            [],
            ['error' => 'No active Full Administrator found. Configure one in Setup > Addon Modules > Auto Accept Orders.'],
            null,
            []
        );

        return null;
    }
}

if (!function_exists('aao_claim_order')) {
    /**
     * Atomically claim an order+trigger pair for processing.
     *
     * Uses INSERT IGNORE against a unique index on (order_id, trigger_hook).
     * If a concurrent hook fires for the same order and trigger, the second
     * insert is silently ignored and this function returns null — the caller
     * should bail without calling AcceptOrder.
     *
     * @param int|string $orderId
     * @param string     $trigger
     *
     * @return int|null Inserted log row ID, or null if already claimed.
     */
    function aao_claim_order($orderId, $trigger)
    {
        try {
            $pdo = Capsule::connection()->getPdo();
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO mod_autoaccept_logs (order_id, trigger_hook, status_response, created_at) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([(int) $orderId, (string) $trigger, 'PENDING', date('Y-m-d H:i:s')]);

            if ($stmt->rowCount() === 0) {
                return null;
            }

            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            aao_handle_error('aao_claim_order', $e);
            return null;
        }
    }
}

if (!function_exists('aao_finalize_log')) {
    /**
     * Update a previously claimed log row with the real API response.
     *
     * @param int   $logId
     * @param mixed $response
     *
     * @return void
     */
    function aao_finalize_log($logId, $response)
    {
        try {
            $encoded = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                $encoded = json_encode(['error' => 'Failed to encode API response']);
            }

            Capsule::table('mod_autoaccept_logs')
                ->where('id', (int) $logId)
                ->update(['status_response' => (string) $encoded]);
        } catch (\Throwable $e) {
            aao_handle_error('aao_finalize_log', $e);
        }
    }
}

if (!function_exists('aao_handle_error')) {
    /**
     * Log handled exceptions without breaking checkout/order flow.
     *
     * @param string     $context
     * @param \Throwable $e
     *
     * @return void
     */
    function aao_handle_error($context, \Throwable $e)
    {
        logModuleCall(
            'auto_accept_orders',
            $context,
            [],
            ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
            null,
            []
        );
    }
}

add_hook('InvoicePaid', 1, function ($vars) {
    try {
        if (!aao_is_enabled()) {
            return;
        }

        $adminUsername = aao_resolve_admin_username();
        if (empty($adminUsername)) {
            return;
        }

        $invoiceId = isset($vars['invoiceid']) ? (int) $vars['invoiceid'] : 0;
        if ($invoiceId <= 0) {
            return;
        }

        // An invoice can be linked to multiple orders (e.g. renewals, upgrades,
        // addons). Process every pending order, not just the most recent.
        $orders = Capsule::table('tblorders')
            ->select('id', 'status')
            ->where('invoiceid', $invoiceId)
            ->where('status', 'Pending')
            ->get();

        foreach ($orders as $order) {
            $logId = aao_claim_order($order->id, 'InvoicePaid');
            if ($logId === null) {
                continue;
            }

            $result = localAPI('AcceptOrder', ['orderid' => (int) $order->id], $adminUsername);
            aao_finalize_log($logId, $result);
        }
    } catch (\Throwable $e) {
        aao_handle_error('InvoicePaid', $e);
    }
});

add_hook('ShoppingCartCheckoutComplete', 1, function ($vars) {
    try {
        if (!aao_is_enabled()) {
            return [];
        }

        $adminUsername = aao_resolve_admin_username();
        if (empty($adminUsername)) {
            return [];
        }

        $orderId = isset($vars['orderid']) ? (int) $vars['orderid'] : 0;
        if ($orderId <= 0) {
            return [];
        }

        $order = Capsule::table('tblorders')
            ->select('id', 'status', 'amount')
            ->where('id', $orderId)
            ->first();

        if (!$order || $order->status !== 'Pending') {
            return [];
        }

        // Tolerance check instead of string comparison to safely handle any
        // floating-point representation of zero stored by WHMCS.
        if (abs((float) $order->amount) >= 0.005) {
            return [];
        }

        $logId = aao_claim_order($order->id, 'FreeOrder');
        if ($logId === null) {
            return [];
        }

        $result = localAPI('AcceptOrder', ['orderid' => (int) $order->id], $adminUsername);
        aao_finalize_log($logId, $result);
    } catch (\Throwable $e) {
        aao_handle_error('ShoppingCartCheckoutComplete', $e);
    }

    return [];
});
