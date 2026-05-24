<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Unit;

use AutoAcceptOrders\Repository\OrderRepository;
use AutoAcceptOrders\Tests\Stub\InMemoryDatabase;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AutoAcceptOrders\Repository\OrderRepository
 */
class OrderRepositoryTest extends TestCase
{
    private function makeRepo(array $orders = []): OrderRepository
    {
        $db = new InMemoryDatabase();
        if ($orders) {
            $db->seed('tblorders', $orders);
        }
        return new OrderRepository($db);
    }

    public function testGetPendingOrdersByInvoice_returnsMatchingOrders(): void
    {
        $repo = $this->makeRepo([
            ['id' => 1, 'invoiceid' => 10, 'status' => 'Pending', 'amount' => '0.00'],
            ['id' => 2, 'invoiceid' => 10, 'status' => 'Active',  'amount' => '10.00'],
            ['id' => 3, 'invoiceid' => 10, 'status' => 'Pending', 'amount' => '5.00'],
            ['id' => 4, 'invoiceid' => 99, 'status' => 'Pending', 'amount' => '0.00'],
        ]);

        $orders = $repo->getPendingOrdersByInvoice(10);

        self::assertCount(2, $orders);
        $ids = array_map(fn ($o) => (int) $o->id, $orders);
        self::assertContains(1, $ids);
        self::assertContains(3, $ids);
    }

    public function testGetPendingOrdersByInvoice_returnsEmpty_whenNonePending(): void
    {
        $repo = $this->makeRepo([
            ['id' => 1, 'invoiceid' => 10, 'status' => 'Active', 'amount' => '10.00'],
        ]);

        self::assertEmpty($repo->getPendingOrdersByInvoice(10));
    }

    public function testFindById_returnsOrder_whenExists(): void
    {
        $repo = $this->makeRepo([
            ['id' => 5, 'invoiceid' => 20, 'status' => 'Pending', 'amount' => '0.00'],
        ]);

        $order = $repo->findById(5);

        self::assertNotNull($order);
        self::assertEquals(5, $order->id);
        self::assertEquals('Pending', $order->status);
    }

    public function testFindById_returnsNull_whenNotFound(): void
    {
        $repo = $this->makeRepo();
        self::assertNull($repo->findById(999));
    }
}
