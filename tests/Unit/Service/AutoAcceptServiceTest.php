<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Unit\Service;

use AutoAcceptOrders\Repository\AdminRepository;
use AutoAcceptOrders\Repository\ConfigRepository;
use AutoAcceptOrders\Repository\LogRepository;
use AutoAcceptOrders\Repository\OrderRepository;
use AutoAcceptOrders\Service\AutoAcceptService;
use AutoAcceptOrders\Tests\Stub\ArrayApi;
use AutoAcceptOrders\Tests\Stub\ArrayLogger;
use AutoAcceptOrders\Tests\Stub\FrozenClock;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AutoAcceptOrders\Service\AutoAcceptService
 */
class AutoAcceptServiceTest extends TestCase
{
    private function makeService(
        array $settings = [],
        array $admins = [],
        array $orders = [],
        array $logs = [],
        ?ArrayApi $api = null,
        ?ArrayLogger $logger = null
    ): array {
        $db = new ServiceTestDatabase($settings, $admins, $orders, $logs);
        $clock  = new FrozenClock();
        $logger = $logger ?? new ArrayLogger();
        $api    = $api ?? new ArrayApi();

        $config    = new ConfigRepository($db);
        $adminRepo = new AdminRepository($db, $logger);
        $logRepo   = new LogRepository($db, $clock);
        $orderRepo = new OrderRepository($db);

        $service = new AutoAcceptService($config, $adminRepo, $logRepo, $orderRepo, $api, $logger);

        return [$service, $api, $logger, $db];
    }

    public function testProcessInvoicePaid_returnsEarly_whenDisabled(): void
    {
        [$service, $api] = $this->makeService(['enabled' => '0']);

        $service->processInvoicePaid(10);

        self::assertSame(0, $api->count(), 'AcceptOrder should not be called when disabled');
    }

    public function testProcessInvoicePaid_returnsEarly_whenNoAdmin(): void
    {
        [$service, $api] = $this->makeService(['enabled' => 'yes']);
        // No admins seeded → resolveAdminUsername returns null

        $service->processInvoicePaid(10);

        self::assertSame(0, $api->count());
    }

    public function testProcessInvoicePaid_acceptsAllPendingOrders(): void
    {
        $orders = [
            ['id' => 1, 'invoiceid' => 10, 'status' => 'Pending', 'amount' => '5.00'],
            ['id' => 2, 'invoiceid' => 10, 'status' => 'Pending', 'amount' => '10.00'],
            ['id' => 3, 'invoiceid' => 10, 'status' => 'Active',  'amount' => '5.00'],
        ];
        [$service, $api] = $this->makeService(
            ['enabled' => 'yes'],
            [['id' => 1, 'username' => 'admin', 'disabled' => 0, 'roleid' => 1]],
            $orders
        );

        $service->processInvoicePaid(10);

        self::assertSame(2, $api->count(), 'Should call AcceptOrder for each pending order');
    }

    public function testProcessFreeOrder_skips_whenAmountAboveTolerance(): void
    {
        $orders = [
            ['id' => 5, 'invoiceid' => 20, 'status' => 'Pending', 'amount' => '0.01'],
        ];
        [$service, $api] = $this->makeService(
            ['enabled' => 'yes'],
            [['id' => 1, 'username' => 'admin', 'disabled' => 0, 'roleid' => 1]],
            $orders
        );

        $service->processFreeOrder(5);

        self::assertSame(0, $api->count());
    }

    public function testProcessFreeOrder_accepts_whenAmountIsZero(): void
    {
        $orders = [
            ['id' => 5, 'invoiceid' => 20, 'status' => 'Pending', 'amount' => '0.00'],
        ];
        [$service, $api] = $this->makeService(
            ['enabled' => 'yes'],
            [['id' => 1, 'username' => 'admin', 'disabled' => 0, 'roleid' => 1]],
            $orders
        );

        $service->processFreeOrder(5);

        self::assertSame(1, $api->count());
        self::assertSame('AcceptOrder', $api->getCalls()[0]['command']);
    }

    public function testProcessFreeOrder_skips_whenOrderNotPending(): void
    {
        $orders = [
            ['id' => 5, 'invoiceid' => 20, 'status' => 'Active', 'amount' => '0.00'],
        ];
        [$service, $api] = $this->makeService(
            ['enabled' => 'yes'],
            [['id' => 1, 'username' => 'admin', 'disabled' => 0, 'roleid' => 1]],
            $orders
        );

        $service->processFreeOrder(5);

        self::assertSame(0, $api->count());
    }

    public function testVerboseLogging_logsSuccess_whenEnabled(): void
    {
        $logger = new ArrayLogger();
        $orders = [
            ['id' => 5, 'invoiceid' => 20, 'status' => 'Pending', 'amount' => '0.00'],
        ];
        [$service] = $this->makeService(
            ['enabled' => 'yes', 'verbose_logging' => 'yes'],
            [['id' => 1, 'username' => 'admin', 'disabled' => 0, 'roleid' => 1]],
            $orders,
            [],
            null,
            $logger
        );

        $service->processFreeOrder(5);

        self::assertTrue($logger->hasCallMatching('accept_success'));
    }

    public function testVerboseLogging_doesNotLogSuccess_whenDisabled(): void
    {
        $logger = new ArrayLogger();
        $orders = [
            ['id' => 5, 'invoiceid' => 20, 'status' => 'Pending', 'amount' => '0.00'],
        ];
        [$service] = $this->makeService(
            ['enabled' => 'yes', 'verbose_logging' => '0'],
            [['id' => 1, 'username' => 'admin', 'disabled' => 0, 'roleid' => 1]],
            $orders,
            [],
            null,
            $logger
        );

        $service->processFreeOrder(5);

        self::assertFalse($logger->hasCallMatching('accept_success'));
    }
}

/**
 * Combined test database that handles all tables needed by AutoAcceptService tests.
 */
class ServiceTestDatabase extends \AutoAcceptOrders\Tests\Stub\InMemoryDatabase
{
    private int $nextId = 100;

    public function __construct(
        array $settings,
        array $admins,
        array $orders,
        array $logs
    ) {
        $settingRows = [];
        $id = 1;
        foreach ($settings as $key => $value) {
            $settingRows[] = ['id' => $id++, 'module' => 'auto_accept_orders', 'setting' => $key, 'value' => $value];
        }
        if ($settingRows) {
            $this->seed('tbladdonmodules', $settingRows);
        }
        if ($admins) {
            $this->seed('tbladmins', $admins);
        }
        if ($orders) {
            $this->seed('tblorders', $orders);
        }
        if ($logs) {
            $this->seed('mod_autoaccept_logs', $logs);
        }
    }

    public function select(string $sql, array $bindings = []): array
    {
        // Admin resolution — return first row from tbladmins with username.
        if (strpos($sql, 'tbladmins') !== false) {
            $rows = $this->getTable('tbladmins');
            if (!empty($rows)) {
                $row = reset($rows);
                return [(object) ['username' => $row['username']]];
            }
            return [];
        }

        // Reclaim SELECT id FROM mod_autoaccept_logs.
        if (strpos($sql, 'SELECT id FROM mod_autoaccept_logs') !== false) {
            $logs = $this->getTable('mod_autoaccept_logs');
            if (!empty($logs)) {
                $row = reset($logs);
                return [(object) ['id' => $row['id']]];
            }
            return [];
        }

        return [];
    }

    public function insertIgnore(string $sql, array $bindings = []): ?int
    {
        $orderId = (int) ($bindings[0] ?? 0);
        $trigger = (string) ($bindings[1] ?? '');
        $now     = (string) ($bindings[3] ?? date('Y-m-d H:i:s'));

        $logs = $this->getTable('mod_autoaccept_logs');
        foreach ($logs as $row) {
            if ($row['order_id'] == $orderId && $row['trigger_hook'] === $trigger) {
                return null; // duplicate
            }
        }

        $newId = $this->nextId++;
        $this->seed('mod_autoaccept_logs', [
            ['id' => $newId, 'order_id' => $orderId, 'trigger_hook' => $trigger, 'status_response' => 'PENDING', 'created_at' => $now],
        ]);
        return $newId;
    }

    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return 0;
    }
}
