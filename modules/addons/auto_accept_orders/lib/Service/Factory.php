<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Service;

use AutoAcceptOrders\Adapter\CapsuleDatabase;
use AutoAcceptOrders\Adapter\SystemClock;
use AutoAcceptOrders\Adapter\WhmcsCsrf;
use AutoAcceptOrders\Adapter\WhmcsLocalApi;
use AutoAcceptOrders\Adapter\WhmcsModuleLogger;
use AutoAcceptOrders\Admin\OutputController;
use AutoAcceptOrders\Repository\AdminRepository;
use AutoAcceptOrders\Repository\ConfigRepository;
use AutoAcceptOrders\Repository\LogRepository;
use AutoAcceptOrders\Repository\OrderRepository;

/**
 * Wires the real (WHMCS-backed) object graph.
 *
 * Tests bypass this class entirely and inject fakes via constructors.
 * A static override seam (::override()) is available for integration tests
 * that load hooks.php and need to swap the service.
 */
class Factory
{
    /** @var AutoAcceptService|null */
    private static $override = null;

    /**
     * Set a test-double service. Pass null to clear.
     *
     * @param AutoAcceptService|null $service
     * @return void
     */
    public static function override(?AutoAcceptService $service): void
    {
        self::$override = $service;
    }

    /**
     * Return the production AutoAcceptService (or the test override).
     *
     * @return AutoAcceptService
     */
    public static function createService(): AutoAcceptService
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $db     = new CapsuleDatabase();
        $clock  = new SystemClock();
        $api    = new WhmcsLocalApi();
        $logger = new WhmcsModuleLogger();

        $config    = new ConfigRepository($db);
        $adminRepo = new AdminRepository($db, $logger);
        $logRepo   = new LogRepository($db, $clock);
        $orderRepo = new OrderRepository($db);

        return new AutoAcceptService($config, $adminRepo, $logRepo, $orderRepo, $api, $logger);
    }

    /**
     * Return a fully-wired OutputController.
     *
     * @return OutputController
     */
    public static function createOutputController(): OutputController
    {
        $db     = new CapsuleDatabase();
        $clock  = new SystemClock();
        $api    = new WhmcsLocalApi();
        $logger = new WhmcsModuleLogger();

        $config    = new ConfigRepository($db);
        $adminRepo = new AdminRepository($db, $logger);
        $logRepo   = new LogRepository($db, $clock);
        $orderRepo = new OrderRepository($db);

        $service = new AutoAcceptService($config, $adminRepo, $logRepo, $orderRepo, $api, $logger);
        $csrf    = new WhmcsCsrf();

        return new OutputController($logRepo, $service, $csrf);
    }
}
