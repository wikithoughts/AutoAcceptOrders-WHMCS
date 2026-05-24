<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Admin;

use AutoAcceptOrders\Admin\LogQuery;
use AutoAcceptOrders\Admin\OutputController;
use AutoAcceptOrders\Contract\ClockInterface;
use AutoAcceptOrders\Contract\DatabaseInterface;
use AutoAcceptOrders\Repository\AdminRepository;
use AutoAcceptOrders\Repository\ConfigRepository;
use AutoAcceptOrders\Repository\LogRepository;
use AutoAcceptOrders\Repository\OrderRepository;
use AutoAcceptOrders\Service\AutoAcceptService;
use AutoAcceptOrders\Tests\Stub\ArrayApi;
use AutoAcceptOrders\Tests\Stub\ArrayLogger;
use AutoAcceptOrders\Tests\Stub\FrozenClock;
use AutoAcceptOrders\Tests\Stub\InMemoryDatabase;
use AutoAcceptOrders\Tests\Stub\StubCsrf;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AutoAcceptOrders\Admin\OutputController
 * @covers \AutoAcceptOrders\Admin\LogQuery
 */
class OutputControllerTest extends TestCase
{
    private function makeController(
        array $logs = [],
        ?AutoAcceptService $service = null
    ): OutputController {
        $db    = new InMemoryDatabase();
        $clock = new FrozenClock();

        $logRepo = new LogQueryTestRepository($db, $clock, $logs);

        if ($service === null) {
            $emptyDb   = new InMemoryDatabase();
            $logger    = new ArrayLogger();
            $api       = new ArrayApi();
            $config    = new ConfigRepository($emptyDb);
            $adminRepo = new AdminRepository($emptyDb, $logger);
            $orderRepo = new OrderRepository($emptyDb);
            $service   = new AutoAcceptService($config, $adminRepo, $logRepo, $orderRepo, $api, $logger);
        }

        return new OutputController($logRepo, $service, new StubCsrf());
    }

    public function testGet_rendersTableWithLogs(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $controller = $this->makeController([
            ['id' => 1, 'order_id' => 10, 'trigger_hook' => 'InvoicePaid', 'status_response' => '{"result":"success"}', 'created_at' => '2026-05-10 12:00:00'],
        ]);

        ob_start();
        $controller->handle(['modulelink' => 'index.php?module=auto_accept_orders', 'adminid' => 1]);
        $output = ob_get_clean();

        self::assertStringContainsString('Auto Accept Orders', $output);
        self::assertStringContainsString('InvoicePaid', $output);
    }

    public function testGet_showsEmptyMessage_whenNoLogs(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];

        $controller = $this->makeController([]);

        ob_start();
        $controller->handle(['modulelink' => 'index.php?module=auto_accept_orders', 'adminid' => 1]);
        $output = ob_get_clean();

        self::assertStringContainsString('No log records found', $output);
    }

    public function testPost_rejectsMissingCsrf(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['aao_action' => 'reprocess', 'id' => '1'];
        // No token — StubCsrf.validate('') returns false.

        $controller = $this->makeController();

        ob_start();
        $controller->handle(['modulelink' => 'index.php?m=auto_accept_orders', 'adminid' => 1]);
        $output = ob_get_clean();

        self::assertStringContainsString('Forbidden', $output);
    }

    public function testPost_rejectsBadCsrf(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['aao_action' => 'reprocess', 'id' => '1', 'token' => 'wrong-token'];

        $controller = $this->makeController();

        ob_start();
        $controller->handle(['modulelink' => 'index.php?m=auto_accept_orders', 'adminid' => 1]);
        $output = ob_get_clean();

        self::assertStringContainsString('Forbidden', $output);
    }

    public function testPost_rejectsWhenNoAdminSession(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['aao_action' => 'reprocess', 'id' => '1', 'token' => StubCsrf::TOKEN];

        $controller = $this->makeController();

        ob_start();
        $controller->handle(['modulelink' => 'index.php?m=auto_accept_orders', 'adminid' => 0]);
        $output = ob_get_clean();

        self::assertStringContainsString('Forbidden', $output);
    }

    public function testLogQuery_parsesGetParams(): void
    {
        $query = LogQuery::fromRequest([
            'page'     => '3',
            'per_page' => '50',
            'trigger'  => 'InvoicePaid',
            'status'   => 'PENDING',
            'order_id' => '42',
        ]);

        self::assertSame(3, $query->page);
        self::assertSame(50, $query->perPage);
        self::assertSame('InvoicePaid', $query->trigger);
        self::assertSame('PENDING', $query->status);
        self::assertSame(42, $query->orderId);
    }

    public function testLogQuery_sanitisesInvalidTrigger(): void
    {
        $query = LogQuery::fromRequest(['trigger' => 'malicious<script>']);
        self::assertSame('', $query->trigger);
    }

    public function testLogQuery_sanitisesInvalidStatus(): void
    {
        $query = LogQuery::fromRequest(['status' => 'UNKNOWN']);
        self::assertSame('', $query->status);
    }

    public function testLogQuery_clampsPageToMinimumOne(): void
    {
        $query = LogQuery::fromRequest(['page' => '-5']);
        self::assertSame(1, $query->page);
    }

    public function testLogQuery_toQueryString_includesActiveFilters(): void
    {
        $query = new LogQuery(2, 25, 'FreeOrder', 'OK', 7);
        $qs    = $query->toQueryString();

        self::assertStringContainsString('page=2', $qs);
        self::assertStringContainsString('trigger=FreeOrder', $qs);
        self::assertStringContainsString('status=OK', $qs);
        self::assertStringContainsString('order_id=7', $qs);
    }

    public function tearDown(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET  = [];
        $_POST = [];
    }
}

/**
 * LogRepository subclass that returns seeded rows directly without raw SQL.
 */
class LogQueryTestRepository extends LogRepository
{
    /** @var array */
    private $rows;

    public function __construct(DatabaseInterface $db, ClockInterface $clock, array $rows)
    {
        parent::__construct($db, $clock);
        $this->rows = array_map(function ($r) {
            return (object) array_merge(['client_id' => null, 'order_amount' => null], $r);
        }, $rows);
    }

    public function query(LogQuery $query): array
    {
        return $this->rows;
    }

    public function count(LogQuery $query): int
    {
        return count($this->rows);
    }
}
