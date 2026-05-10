<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Unit;

use AutoAcceptOrders\Admin\LogQuery;
use AutoAcceptOrders\Repository\LogRepository;
use AutoAcceptOrders\Tests\Stub\FrozenClock;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AutoAcceptOrders\Repository\LogRepository
 */
class LogRepositoryTest extends TestCase
{
    private function makeFakeDb(): LogTestDatabase
    {
        return new LogTestDatabase();
    }

    private function makeRepo(LogTestDatabase $db, FrozenClock $clock): LogRepository
    {
        return new LogRepository($db, $clock);
    }

    public function testClaim_returnsId_onFirstClaim(): void
    {
        $db    = $this->makeFakeDb();
        $clock = new FrozenClock('2026-05-10 12:00:00');
        $repo  = $this->makeRepo($db, $clock);

        $id = $repo->claimOrder(1, 'InvoicePaid');

        self::assertNotNull($id);
        self::assertSame(1, $id);
    }

    public function testClaim_returnsNull_onDuplicateFreshClaim(): void
    {
        $db    = $this->makeFakeDb();
        $clock = new FrozenClock('2026-05-10 12:00:00');
        $repo  = $this->makeRepo($db, $clock);

        // First claim succeeds.
        $db->simulateExistingRow(1, 'InvoicePaid', 'PENDING', '2026-05-10 11:55:00');
        $db->setInsertIgnoreResult(null);

        $result = $repo->claimOrder(1, 'InvoicePaid');

        // 11:55 is only 5 min ago; threshold is 15 min → not stale → null.
        self::assertNull($result);
    }

    public function testClaim_reclaimsRow_whenPreviousClaimIsStaleAndPending(): void
    {
        $db    = $this->makeFakeDb();
        $clock = new FrozenClock('2026-05-10 12:00:00');
        $repo  = $this->makeRepo($db, $clock);

        // Row is 20 minutes old — past the 15-minute threshold.
        $db->simulateExistingRow(7, 'InvoicePaid', 'PENDING', '2026-05-10 11:40:00');
        $db->setInsertIgnoreResult(null);
        $db->setAffectingStatementResult(1);

        $result = $repo->claimOrder(1, 'InvoicePaid');

        self::assertSame(7, $result);
    }

    public function testClaim_doesNotReclaim_whenPreviousClaimIsFinalized(): void
    {
        $db    = $this->makeFakeDb();
        $clock = new FrozenClock('2026-05-10 12:00:00');
        $repo  = $this->makeRepo($db, $clock);

        // Row is finalized (not PENDING) — no re-claim regardless of age.
        $db->simulateExistingRow(7, 'InvoicePaid', '{"result":"success"}', '2026-05-10 11:00:00');
        $db->setInsertIgnoreResult(null);
        $db->setAffectingStatementResult(0);

        $result = $repo->claimOrder(1, 'InvoicePaid');

        self::assertNull($result);
    }

    public function testFinalize_storesEncodedJson(): void
    {
        $db    = $this->makeFakeDb();
        $clock = new FrozenClock();
        $repo  = $this->makeRepo($db, $clock);

        $db->seed('mod_autoaccept_logs', [
            ['id' => 3, 'order_id' => 1, 'trigger_hook' => 'InvoicePaid', 'status_response' => 'PENDING', 'created_at' => '2026-05-10 12:00:00'],
        ]);

        $repo->finalizeLog(3, ['result' => 'success']);

        $rows = $db->getTable('mod_autoaccept_logs');
        self::assertSame('{"result":"success"}', $rows[3]['status_response']);
    }

    public function testFinalize_storesErrorPlaceholder_whenResponseUnencodable(): void
    {
        $db    = $this->makeFakeDb();
        $clock = new FrozenClock();
        $repo  = $this->makeRepo($db, $clock);

        $db->seed('mod_autoaccept_logs', [
            ['id' => 4, 'order_id' => 2, 'trigger_hook' => 'FreeOrder', 'status_response' => 'PENDING', 'created_at' => '2026-05-10 12:00:00'],
        ]);

        // Resources cannot be JSON-encoded.
        $resource = fopen('php://memory', 'r');
        $repo->finalizeLog(4, $resource);
        fclose($resource);

        $rows = $db->getTable('mod_autoaccept_logs');
        self::assertStringContainsString('Failed to encode', $rows[4]['status_response']);
    }
}

/**
 * Specialised InMemoryDatabase that handles LogRepository's raw SQL patterns.
 */
class LogTestDatabase extends \AutoAcceptOrders\Tests\Stub\InMemoryDatabase
{
    private ?int $insertIgnoreResult = 1;
    private int $nextInsertId = 1;
    private int $affectingStatementResult = 0;

    /** @var array|null  The row returned by SELECT for reclaim checks */
    private ?array $existingRow = null;

    public function setInsertIgnoreResult(?int $result): void
    {
        $this->insertIgnoreResult = $result;
        if ($result !== null) {
            $this->nextInsertId = $result;
        }
    }

    public function setAffectingStatementResult(int $result): void
    {
        $this->affectingStatementResult = $result;
    }

    public function simulateExistingRow(int $rowId, string $trigger, string $status, string $createdAt): void
    {
        $this->existingRow = ['id' => $rowId, 'trigger_hook' => $trigger, 'status_response' => $status, 'created_at' => $createdAt];
    }

    public function insertIgnore(string $sql, array $bindings = []): ?int
    {
        $result = $this->insertIgnoreResult;
        if ($result !== null) {
            $this->nextInsertId++;
        }
        return $result;
    }

    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->affectingStatementResult;
    }

    public function select(string $sql, array $bindings = []): array
    {
        if (strpos($sql, 'SELECT id FROM') !== false && $this->existingRow !== null) {
            return [(object) ['id' => $this->existingRow['id']]];
        }
        // For LogQuery queries just return empty.
        return [];
    }
}
