<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Unit;

use AutoAcceptOrders\Repository\AdminRepository;
use AutoAcceptOrders\Tests\Stub\ArrayLogger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AutoAcceptOrders\Repository\AdminRepository
 */
class AdminRepositoryTest extends TestCase
{
    private function makeRepo(array $selectResults, ?ArrayLogger $logger = null): AdminRepository
    {
        $logger = $logger ?? new ArrayLogger();
        $db     = new AdminTestDatabase($selectResults);
        return new AdminRepository($db, $logger);
    }

    public function testResolve_returnsConfigured_whenValidFullAdmin(): void
    {
        $repo = $this->makeRepo([
            [(object) ['username' => 'admin1']],  // findByUsernameAndFullAdminRole
        ]);

        self::assertSame('admin1', $repo->resolveAdminUsername('admin1'));
    }

    public function testResolve_fallsBackToFirstFullAdmin_whenConfiguredInvalid(): void
    {
        $repo = $this->makeRepo([
            [],                                   // configured+FullAdmin role → not found
            [],                                   // configured+AcceptOrders perm → not found
            [(object) ['username' => 'fallback']], // first full admin
        ]);

        self::assertSame('fallback', $repo->resolveAdminUsername('bad_user'));
    }

    public function testResolve_returnsNull_andLogs_whenNoFullAdmin(): void
    {
        $logger = new ArrayLogger();
        $repo   = $this->makeRepo([[], [], [], [], []], $logger);

        self::assertNull($repo->resolveAdminUsername(''));
        self::assertTrue($logger->hasCallMatching('resolveAdminUsername'));
    }

    public function testResolve_findsAdminByAcceptOrdersPerm_whenRoleRenamed(): void
    {
        $repo = $this->makeRepo([
            [],                                    // configured+FullAdmin → not found
            [(object) ['username' => 'perm_admin']], // configured+AcceptOrders perm → found
        ]);

        self::assertSame('perm_admin', $repo->resolveAdminUsername('perm_admin'));
    }

    public function testResolve_usesFirstFullAdmin_whenNoConfigured(): void
    {
        $repo = $this->makeRepo([
            [(object) ['username' => 'first_admin']],
        ]);

        self::assertSame('first_admin', $repo->resolveAdminUsername(''));
    }

    public function testResolve_findsFirstAdminWithAcceptOrdersPerm_asFinalFallback(): void
    {
        // When configured='', the two configured-username queries are skipped.
        // Queue position 0 → findFirstFullAdmin (empty), position 1 → AcceptOrders perm.
        $repo = $this->makeRepo([
            [],                                         // findFirstFullAdmin → not found
            [(object) ['username' => 'perm_fallback']], // findFirstAdminWithAcceptOrdersPerm → found
        ]);

        self::assertSame('perm_fallback', $repo->resolveAdminUsername(''));
    }
}

/**
 * Programmatic database stub for AdminRepository.
 * Returns results from a queue, one per select() call.
 */
class AdminTestDatabase extends \AutoAcceptOrders\Tests\Stub\InMemoryDatabase
{
    /** @var array */
    private $queue;

    /** @var int */
    private $cursor = 0;

    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function select(string $sql, array $bindings = []): array
    {
        if ($this->cursor < count($this->queue)) {
            return $this->queue[$this->cursor++];
        }
        return [];
    }
}
