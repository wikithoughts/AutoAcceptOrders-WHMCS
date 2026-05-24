<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Repository;

use AutoAcceptOrders\Admin\LogQuery;
use AutoAcceptOrders\Contract\ClockInterface;
use AutoAcceptOrders\Contract\DatabaseInterface;

/**
 * Manages mod_autoaccept_logs: claim, finalize, query, paginate.
 */
class LogRepository
{
    /**
     * Rows stuck in PENDING for longer than this many minutes are eligible
     * for re-claim on the next hook fire.
     */
    public const STALE_PENDING_MINUTES = 15;

    private const TABLE = 'mod_autoaccept_logs';

    /** @var DatabaseInterface */
    private $db;

    /** @var ClockInterface */
    private $clock;

    public function __construct(DatabaseInterface $db, ClockInterface $clock)
    {
        $this->db    = $db;
        $this->clock = $clock;
    }

    /**
     * Atomically claim an order+trigger pair for processing.
     *
     * 1. INSERT IGNORE against the unique (order_id, trigger_hook) index.
     * 2. If already claimed, check if the existing row is stale-PENDING
     *    and, if so, update it (race-safe: WHERE status_response='PENDING').
     * 3. Return the log row ID on success, null if already owned by another actor.
     *
     * @param int    $orderId
     * @param string $trigger
     * @return int|null
     */
    public function claimOrder(int $orderId, string $trigger): ?int
    {
        $now = $this->clock->now();

        $id = $this->db->insertIgnore(
            'INSERT IGNORE INTO ' . self::TABLE . ' (order_id, trigger_hook, status_response, created_at) VALUES (?, ?, ?, ?)',
            [$orderId, $trigger, 'PENDING', $now]
        );

        if ($id !== null) {
            return $id;
        }

        // Row already exists — check if it is stale-PENDING and can be re-claimed.
        $staleThreshold = $this->clock->minutesAgo(self::STALE_PENDING_MINUTES);

        $affected = $this->db->affectingStatement(
            'UPDATE ' . self::TABLE . '
             SET created_at = ?, status_response = ?
             WHERE order_id = ?
               AND trigger_hook = ?
               AND status_response = ?
               AND created_at < ?',
            [$now, 'PENDING', $orderId, $trigger, 'PENDING', $staleThreshold]
        );

        if ($affected === 0) {
            return null;
        }

        // We re-claimed it. Retrieve the row id.
        $row = $this->db->select(
            'SELECT id FROM ' . self::TABLE . ' WHERE order_id = ? AND trigger_hook = ? LIMIT 1',
            [$orderId, $trigger]
        );

        return !empty($row) ? (int) $row[0]->id : null;
    }

    /**
     * Update a claimed row with the real API response.
     *
     * @param int   $logId
     * @param mixed $response
     * @return void
     */
    public function finalizeLog(int $logId, $response): void
    {
        $encoded = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = (string) json_encode(['error' => 'Failed to encode API response']);
        }

        $this->db->update(self::TABLE, ['id' => $logId], ['status_response' => $encoded]);
    }

    /**
     * Return paginated log rows with an optional order join and filters.
     *
     * @param LogQuery $query
     * @return array
     */
    public function query(LogQuery $query): array
    {
        [$sql, $bindings] = $this->buildSelect($query);
        $sql .= ' ORDER BY logs.id DESC LIMIT ? OFFSET ?';
        $bindings[] = $query->perPage;
        $bindings[] = ($query->page - 1) * $query->perPage;

        return $this->db->select($sql, $bindings);
    }

    /**
     * Return total row count matching the query filters (ignores pagination).
     *
     * @param LogQuery $query
     * @return int
     */
    public function count(LogQuery $query): int
    {
        [$sql, $bindings] = $this->buildSelect($query, true);
        $rows = $this->db->select($sql, $bindings);
        return !empty($rows) ? (int) $rows[0]->total : 0;
    }

    /**
     * Find a single log row by ID.
     *
     * @param int $logId
     * @return object|null
     */
    public function findById(int $logId): ?object
    {
        $rows = $this->db->select(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = ? LIMIT 1',
            [$logId]
        );
        return !empty($rows) ? (object) $rows[0] : null;
    }

    /**
     * Build the SELECT (or COUNT) SQL + bindings shared by query() and count().
     *
     * @param LogQuery $q
     * @param bool     $countOnly
     * @return array{string, array}
     */
    private function buildSelect(LogQuery $q, bool $countOnly = false): array
    {
        $select = $countOnly
            ? 'SELECT COUNT(*) AS total'
            : 'SELECT logs.id, logs.order_id, logs.trigger_hook, logs.status_response, logs.created_at,
                      o.userid AS client_id, o.amount AS order_amount';

        $sql = $select . '
                FROM ' . self::TABLE . ' logs
                LEFT JOIN tblorders o ON o.id = logs.order_id
                WHERE 1=1';

        $bindings = [];

        if ($q->trigger !== '') {
            $sql        .= ' AND logs.trigger_hook = ?';
            $bindings[]  = $q->trigger;
        }

        if ($q->orderId !== null) {
            $sql        .= ' AND logs.order_id = ?';
            $bindings[]  = $q->orderId;
        }

        if ($q->status !== '') {
            switch ($q->status) {
                case 'PENDING':
                    $sql        .= " AND logs.status_response = 'PENDING'";
                    break;
                case 'OK':
                    $sql        .= " AND logs.status_response LIKE '%\"result\":\"success\"%'";
                    break;
                case 'ERROR':
                    $sql        .= " AND logs.status_response != 'PENDING'"
                                .  " AND logs.status_response NOT LIKE '%\"result\":\"success\"%'";
                    break;
            }
        }

        return [$sql, $bindings];
    }
}
