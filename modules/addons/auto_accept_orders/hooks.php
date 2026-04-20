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
     * Resolve configured admin or fallback to an active Full Administrator.
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

        $fallbackAnyActiveAdmin = Capsule::table('tbladmins')
            ->where('disabled', 0)
            ->orderBy('id', 'asc')
            ->value('username');

        return !empty($fallbackAnyActiveAdmin) ? (string) $fallbackAnyActiveAdmin : null;
    }
}

if (!function_exists('aao_log_result')) {
    /**
     * Persist hook attempt result to custom logs table.
     *
     * @param int|string $orderId
     * @param string $trigger
     * @param mixed $response
     *
     * @return void
     */
    function aao_log_result($orderId, $trigger, $response)
    {
        $encoded = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = json_encode(['error' => 'Failed to encode API response']);
        }

        Capsule::table('mod_autoaccept_logs')->insert([
            'order_id' => (int) $orderId,
            'trigger_hook' => (string) $trigger,
            'status_response' => (string) $encoded,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

if (!function_exists('aao_handle_error')) {
    /**
     * Log handled exceptions without breaking checkout/order flow.
     *
     * @param string $context
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

        $order = Capsule::table('tblorders')
            ->select('id', 'status')
            ->where('invoiceid', $invoiceId)
            ->orderBy('id', 'desc')
            ->first();

        if (!$order || $order->status !== 'Pending') {
            return;
        }

        $result = localAPI('AcceptOrder', ['orderid' => (int) $order->id], $adminUsername);
        aao_log_result($order->id, 'InvoicePaid', $result);
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

        if (!$order) {
            return [];
        }

        $amountNormalized = number_format((float) $order->amount, 2, '.', '');
        if ($amountNormalized !== '0.00' || $order->status !== 'Pending') {
            return [];
        }

        $result = localAPI('AcceptOrder', ['orderid' => (int) $order->id], $adminUsername);
        aao_log_result($order->id, 'FreeOrder', $result);
    } catch (\Throwable $e) {
        aao_handle_error('ShoppingCartCheckoutComplete', $e);
    }

    return [];
});
