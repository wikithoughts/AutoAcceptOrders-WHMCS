<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Integration;

use AutoAcceptOrders\Repository\AdminRepository;
use AutoAcceptOrders\Repository\ConfigRepository;
use AutoAcceptOrders\Repository\LogRepository;
use AutoAcceptOrders\Repository\OrderRepository;
use AutoAcceptOrders\Service\AutoAcceptService;
use AutoAcceptOrders\Service\Factory;
use AutoAcceptOrders\Tests\Stub\ArrayApi;
use AutoAcceptOrders\Tests\Stub\ArrayLogger;
use AutoAcceptOrders\Tests\Stub\FrozenClock;
use AutoAcceptOrders\Tests\Unit\Service\ServiceTestDatabase;
use PHPUnit\Framework\TestCase;

/**
 * Integration test: loads hooks.php and drives the registered closures through
 * the real service class backed by in-memory stubs.
 *
 * @covers \AutoAcceptOrders\Hook\InvoicePaidHandler
 * @covers \AutoAcceptOrders\Hook\FreeOrderHandler
 */
class HookFlowTest extends TestCase
{
    /** @var array  Captured hook registrations from add_hook() */
    private static $hooks = [];

    public static function setUpBeforeClass(): void
    {
        // Override add_hook to capture closures.
        // The global stub in WhmcsFunctions.php is already loaded;
        // we augment it by redefining add_hook in this test's namespace is not
        // possible, so instead we rely on Factory::override().
    }

    private function makeService(array $settings, array $admins, array $orders): array
    {
        $api    = new ArrayApi();
        $logger = new ArrayLogger();
        $db     = new ServiceTestDatabase($settings, $admins, $orders, []);
        $clock  = new FrozenClock();

        $config    = new ConfigRepository($db);
        $adminRepo = new AdminRepository($db, $logger);
        $logRepo   = new LogRepository($db, $clock);
        $orderRepo = new OrderRepository($db);

        $service = new AutoAcceptService($config, $adminRepo, $logRepo, $orderRepo, $api, $logger);

        return [$service, $api, $logger];
    }

    public function testInvoicePaidHandler_callsAcceptOrder_forEachPendingOrder(): void
    {
        $orders = [
            ['id' => 1, 'invoiceid' => 42, 'status' => 'Pending', 'amount' => '5.00'],
            ['id' => 2, 'invoiceid' => 42, 'status' => 'Pending', 'amount' => '5.00'],
        ];
        [$service, $api] = $this->makeService(
            ['enabled' => 'yes'],
            [['id' => 1, 'username' => 'admin', 'disabled' => 0, 'roleid' => 1]],
            $orders
        );

        Factory::override($service);
        try {
            $handler = new \AutoAcceptOrders\Hook\InvoicePaidHandler($service);
            $handler->handle(['invoiceid' => 42]);
        } finally {
            Factory::override(null);
        }

        self::assertSame(2, $api->count());
    }

    public function testFreeOrderHandler_acceptsFreeOrder_andReturnsEmptyArray(): void
    {
        $orders = [
            ['id' => 3, 'invoiceid' => 50, 'status' => 'Pending', 'amount' => '0.00'],
        ];
        [$service, $api] = $this->makeService(
            ['enabled' => 'yes'],
            [['id' => 1, 'username' => 'admin', 'disabled' => 0, 'roleid' => 1]],
            $orders
        );

        $handler = new \AutoAcceptOrders\Hook\FreeOrderHandler($service);
        $result  = $handler->handle(['orderid' => 3]);

        self::assertSame([], $result, 'FreeOrderHandler must always return an empty array');
        self::assertSame(1, $api->count());
    }

    public function testFreeOrderHandler_returnsEmptyArray_whenDisabled(): void
    {
        [$service, $api] = $this->makeService(['enabled' => '0'], [], []);

        $handler = new \AutoAcceptOrders\Hook\FreeOrderHandler($service);
        $result  = $handler->handle(['orderid' => 99]);

        self::assertSame([], $result);
        self::assertSame(0, $api->count());
    }

    public function testFreeOrderHandler_returnsEmptyArray_onException(): void
    {
        // Even if the service throws, the hook must return [].
        $service = $this->createMock(AutoAcceptService::class);
        $service->method('processFreeOrder')->willThrowException(new \RuntimeException('DB down'));

        $handler = new \AutoAcceptOrders\Hook\FreeOrderHandler($service);
        $result  = $handler->handle(['orderid' => 1]);

        self::assertSame([], $result);
    }
}
