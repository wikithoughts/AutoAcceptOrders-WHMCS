<?php

declare(strict_types=1);

/**
 * PSR-4 autoloader for the AutoAcceptOrders\ namespace.
 *
 * Maps AutoAcceptOrders\Foo\Bar → lib/Foo/Bar.php relative to this file.
 * No composer or PHAR required — safe on any WHMCS installation.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'AutoAcceptOrders\\';
    $baseDir = __DIR__ . '/lib/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});
