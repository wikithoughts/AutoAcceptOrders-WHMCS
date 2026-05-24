<?php

declare(strict_types=1);

if (!function_exists('localAPI')) {
    function localAPI(string $command, array $params, string $adminUsername): array
    {
        return ['result' => 'success'];
    }
}

if (!function_exists('logModuleCall')) {
    function logModuleCall(string $module, string $action, $request, $response, $processedData = null, array $replaceVars = []): void
    {
        // no-op stub
    }
}

if (!function_exists('add_hook')) {
    function add_hook(string $event, int $priority, callable $callback): void
    {
        // no-op stub
    }
}
