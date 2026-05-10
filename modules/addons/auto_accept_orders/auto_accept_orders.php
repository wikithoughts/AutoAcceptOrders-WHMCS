<?php

declare(strict_types=1);

use AutoAcceptOrders\Service\Factory;
use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/autoload.php';

/**
 * Define module configuration.
 *
 * @return array
 */
function auto_accept_orders_config(): array
{
    return [
        'name'        => 'Auto Accept Orders',
        'description' => 'Automatically accepts paid and free pending orders.',
        'version'     => '1.2.0',
        'author'      => 'vercaa.com',
        'fields'      => [
            'enabled'         => [
                'FriendlyName' => 'Enable',
                'Type'         => 'yesno',
                'Description'  => 'Tick to enable automatic order acceptance.',
            ],
            'admin_username'  => [
                'FriendlyName' => 'Admin Username',
                'Type'         => 'text',
                'Size'         => '30',
                'Description'  => 'Optional. If blank or invalid, the first active Full Administrator is used. A Full Administrator (or an account with the Accept Orders permission) is required.',
            ],
            'verbose_logging' => [
                'FriendlyName' => 'Verbose Logging',
                'Type'         => 'yesno',
                'Description'  => 'When enabled, successful acceptances are also logged to Utilities &gt; Module Log.',
            ],
        ],
    ];
}

/**
 * Activate module: create logs table with unique dedup index.
 *
 * @return array
 */
function auto_accept_orders_activate(): array
{
    if (PHP_VERSION_ID < 70400) {
        return [
            'status'      => 'error',
            'description' => 'Auto Accept Orders requires PHP 7.4 or higher. Your server is running PHP ' . PHP_VERSION . '.',
        ];
    }

    if (defined('WHMCS\Application::VERSION') && version_compare(\WHMCS\Application::VERSION, '8.0', '<')) {
        return [
            'status'      => 'error',
            'description' => 'Auto Accept Orders requires WHMCS 8.0 or higher.',
        ];
    }

    try {
        if (!Capsule::schema()->hasTable('mod_autoaccept_logs')) {
            Capsule::schema()->create('mod_autoaccept_logs', function ($table): void {
                $table->increments('id');
                $table->integer('order_id')->unsigned();
                $table->string('trigger_hook', 50);
                $table->text('status_response');
                $table->dateTime('created_at');
                $table->unique(['order_id', 'trigger_hook'], 'aao_order_trigger_unique');
            });
        } else {
            $existing = Capsule::select(
                "SHOW INDEX FROM mod_autoaccept_logs WHERE Key_name = 'aao_order_trigger_unique'"
            );
            if (empty($existing)) {
                Capsule::statement(
                    'ALTER TABLE mod_autoaccept_logs ADD UNIQUE KEY aao_order_trigger_unique (order_id, trigger_hook)'
                );
            }
        }

        return [
            'status'      => 'success',
            'description' => 'Auto Accept Orders module activated successfully.',
        ];
    } catch (\Throwable $e) {
        return [
            'status'      => 'error',
            'description' => 'Activation failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Deactivate module.
 *
 * mod_autoaccept_logs is intentionally preserved so the audit trail is not
 * lost when the module is toggled off and on.
 *
 * @return array
 */
function auto_accept_orders_deactivate(): array
{
    return [
        'status'      => 'success',
        'description' => 'Auto Accept Orders module deactivated. The mod_autoaccept_logs table has been preserved for audit purposes.',
    ];
}

/**
 * Upgrade module schema.
 *
 * Each migration block is gated on the version being upgraded from so
 * upgrades are idempotent even if run multiple times.
 *
 * @param array $vars  Contains 'version' — the currently installed version.
 * @return array
 */
function auto_accept_orders_upgrade(array $vars): array
{
    $version = isset($vars['version']) ? (string) $vars['version'] : '0.0.0';

    try {
        // 1.0.0 → 1.1.0: add unique index.
        if (version_compare($version, '1.1.0', '<') && Capsule::schema()->hasTable('mod_autoaccept_logs')) {
            $existing = Capsule::select(
                "SHOW INDEX FROM mod_autoaccept_logs WHERE Key_name = 'aao_order_trigger_unique'"
            );
            if (empty($existing)) {
                Capsule::statement(
                    'ALTER TABLE mod_autoaccept_logs ADD UNIQUE KEY aao_order_trigger_unique (order_id, trigger_hook)'
                );
            }
        }

        // 1.1.0 → 1.2.0: deduplicate (order_id, trigger_hook) pairs before
        // any future schema changes. Keeps the highest-id (newest) row per pair.
        // This is a no-op on clean tables and covers direct 1.0.0 → 1.2.0 jumps.
        if (version_compare($version, '1.2.0', '<') && Capsule::schema()->hasTable('mod_autoaccept_logs')) {
            Capsule::statement(
                'DELETE l1 FROM mod_autoaccept_logs l1
                 INNER JOIN mod_autoaccept_logs l2
                   ON l1.order_id    = l2.order_id
                  AND l1.trigger_hook = l2.trigger_hook
                  AND l1.id < l2.id'
            );

            // Ensure the unique index exists (covers 1.0.0 → 1.2.0 direct).
            $existing = Capsule::select(
                "SHOW INDEX FROM mod_autoaccept_logs WHERE Key_name = 'aao_order_trigger_unique'"
            );
            if (empty($existing)) {
                Capsule::statement(
                    'ALTER TABLE mod_autoaccept_logs ADD UNIQUE KEY aao_order_trigger_unique (order_id, trigger_hook)'
                );
            }
        }

        return [
            'status'      => 'success',
            'description' => 'Auto Accept Orders upgraded successfully.',
        ];
    } catch (\Throwable $e) {
        return [
            'status'      => 'error',
            'description' => 'Upgrade failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Render admin module page.
 *
 * @param array $vars
 * @return void
 */
function auto_accept_orders_output(array $vars): void
{
    try {
        Factory::createOutputController()->handle($vars);
    } catch (\Throwable $e) {
        echo '<div style="padding:16px;border:1px solid #d2d2d2;color:#111;background:#fff;margin-top:16px;">';
        echo 'Unable to load admin page: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo '</div>';
    }
}
