<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Define module configuration.
 *
 * @return array
 */
function auto_accept_orders_config()
{
    return [
        'name' => 'Auto Accept Orders',
        'description' => 'Automatically accepts paid and free pending orders.',
        'version' => '1.0.0',
        'author' => 'vercaa.com',
        'fields' => [
            'enabled' => [
                'FriendlyName' => 'Enable',
                'Type' => 'yesno',
                'Description' => 'Tick to enable automatic order acceptance.',
            ],
            'admin_username' => [
                'FriendlyName' => 'Admin Username',
                'Type' => 'text',
                'Size' => '30',
                'Description' => 'Optional. If blank/invalid, fallback uses the first active Full Administrator.',
            ],
        ],
    ];
}

/**
 * Activate module and create logs table.
 *
 * @return array
 */
function auto_accept_orders_activate()
{
    try {
        if (!Capsule::schema()->hasTable('mod_autoaccept_logs')) {
            Capsule::schema()->create('mod_autoaccept_logs', function ($table) {
                $table->increments('id');
                $table->integer('order_id')->unsigned()->index();
                $table->string('trigger_hook', 50);
                $table->text('status_response');
                $table->dateTime('created_at');
            });
        }

        return [
            'status' => 'success',
            'description' => 'Auto Accept Orders module activated successfully.',
        ];
    } catch (\Throwable $e) {
        return [
            'status' => 'error',
            'description' => 'Activation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Deactivate module and drop logs table.
 *
 * @return array
 */
function auto_accept_orders_deactivate()
{
    try {
        Capsule::schema()->dropIfExists('mod_autoaccept_logs');

        return [
            'status' => 'success',
            'description' => 'Auto Accept Orders module deactivated successfully.',
        ];
    } catch (\Throwable $e) {
        return [
            'status' => 'error',
            'description' => 'Deactivation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Render admin module page.
 *
 * @param array $vars
 *
 * @return void
 */
function auto_accept_orders_output($vars)
{
    try {
        $logs = Capsule::table('mod_autoaccept_logs as logs')
            ->leftJoin('tblorders as o', 'o.id', '=', 'logs.order_id')
            ->select(
                'logs.id',
                'logs.order_id',
                'logs.trigger_hook',
                'logs.status_response',
                'logs.created_at',
                'o.userid as client_id',
                'o.amount as order_amount'
            )
            ->orderBy('logs.id', 'desc')
            ->limit(15)
            ->get();
    } catch (\Throwable $e) {
        echo '<div style="padding:16px;border:1px solid #d2d2d2;color:#111;background:#fff;margin-top:16px;">';
        echo 'Unable to load logs: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '</div>';
        return;
    }

    echo '<style>
        .aao-wrap{max-width:1200px;margin:24px 0;padding:0;color:#111;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;}
        .aao-title{font-size:22px;line-height:1.4;font-weight:600;margin:0 0 8px;}
        .aao-sub{font-size:14px;line-height:1.6;color:#2c2c2c;margin:0 0 22px;}
        .aao-table{width:100%;border-collapse:collapse;background:#fff;}
        .aao-table th,.aao-table td{border:1px solid #d7d7d7;padding:12px 14px;vertical-align:top;text-align:left;font-size:13px;line-height:1.5;}
        .aao-table th{background:#c6b797;color:#111;font-weight:600;}
        .aao-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;font-size:12px;white-space:pre-wrap;word-break:break-word;}
        .aao-empty{padding:14px;border:1px solid #d7d7d7;background:#fff;font-size:13px;}
    </style>';

    echo '<div class="aao-wrap">';
    echo '<h2 class="aao-title">Auto Accept Orders</h2>';
    echo '<p class="aao-sub">Last 15 automated acceptance attempts.</p>';

    if (count($logs) === 0) {
        echo '<div class="aao-empty">No log records found.</div>';
        echo '</div>';
        return;
    }

    echo '<table class="aao-table">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>Order ID</th>';
    echo '<th>Trigger</th>';
    echo '<th>Client ID</th>';
    echo '<th>Order Amount</th>';
    echo '<th>Response</th>';
    echo '<th>Created At</th>';
    echo '</tr></thead><tbody>';

    foreach ($logs as $log) {
        $clientId = is_null($log->client_id) ? 'N/A' : (string) $log->client_id;
        $orderAmount = is_null($log->order_amount) ? 'N/A' : (string) $log->order_amount;

        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) $log->id, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $log->order_id, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $log->trigger_hook, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($clientId, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($orderAmount, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td class="aao-mono">' . htmlspecialchars((string) $log->status_response, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $log->created_at, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}
