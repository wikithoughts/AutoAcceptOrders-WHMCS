<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Repository;

use AutoAcceptOrders\Contract\DatabaseInterface;
use AutoAcceptOrders\Contract\LoggerInterface;

/**
 * Resolves which admin username should perform AcceptOrder API calls.
 *
 * Resolution chain (conservative — per user decision):
 *  1. Configured username + role.name = 'Full Administrator'
 *  2. Configured username + role has explicit 'Accept Orders' permission
 *  3. First active admin where role.name = 'Full Administrator'
 *  4. First active admin whose role has the 'Accept Orders' permission
 *  5. null (logged to module log)
 */
class AdminRepository
{
    private const FULL_ADMIN_ROLE = 'Full Administrator';
    private const MODULE          = 'auto_accept_orders';

    /** @var DatabaseInterface */
    private $db;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(DatabaseInterface $db, LoggerInterface $logger)
    {
        $this->db     = $db;
        $this->logger = $logger;
    }

    public function resolveAdminUsername(string $configured = ''): ?string
    {
        $configured = trim($configured);

        if ($configured !== '') {
            $result = $this->findByUsernameAndFullAdminRole($configured);
            if ($result !== null) {
                return $result;
            }

            $result = $this->findByUsernameAndAcceptOrdersPerm($configured);
            if ($result !== null) {
                return $result;
            }
        }

        $result = $this->findFirstFullAdmin();
        if ($result !== null) {
            return $result;
        }

        $result = $this->findFirstAdminWithAcceptOrdersPerm();
        if ($result !== null) {
            return $result;
        }

        $this->logger->log(
            self::MODULE,
            'resolveAdminUsername',
            [],
            ['error' => 'No active Full Administrator found. Configure one in Setup > Addon Modules > Auto Accept Orders.']
        );

        return null;
    }

    private function findByUsernameAndFullAdminRole(string $username): ?string
    {
        $sql = 'SELECT a.username
                FROM tbladmins a
                JOIN tbladminroles r ON r.id = a.roleid
                WHERE a.username = ?
                  AND a.disabled = 0
                  AND r.name = ?
                LIMIT 1';

        $rows = $this->db->select($sql, [$username, self::FULL_ADMIN_ROLE]);

        return !empty($rows) ? (string) $rows[0]->username : null;
    }

    private function findByUsernameAndAcceptOrdersPerm(string $username): ?string
    {
        $sql = "SELECT a.username
                FROM tbladmins a
                JOIN tbladminroles r ON r.id = a.roleid
                JOIN tbladminperms p ON p.roleid = r.id
                WHERE a.username = ?
                  AND a.disabled = 0
                  AND p.permid IN (
                      SELECT permid FROM tbladminperms
                      WHERE permid IN (
                          SELECT p2.permid FROM tbladminperms p2 WHERE p2.permid = p.permid
                      )
                  )
                  AND p.permid LIKE '%AcceptOrder%'
                LIMIT 1";

        // Fallback: check tbladminperms using the permission description lookup.
        // WHMCS stores permission IDs differently across versions; we try the
        // description approach first and fall back gracefully if the table/column
        // does not exist.
        try {
            $rows = $this->db->select(
                "SELECT a.username
                 FROM tbladmins a
                 JOIN tbladminroles r ON r.id = a.roleid
                 JOIN tbladminperms p ON p.roleid = r.id
                 WHERE a.username = ?
                   AND a.disabled = 0
                   AND p.permid IN (
                       SELECT permid FROM tbladminperms WHERE permid LIKE '%AcceptOrder%'
                   )
                 LIMIT 1",
                [$username]
            );
            if (!empty($rows)) {
                return (string) $rows[0]->username;
            }
        } catch (\Throwable $e) {
            // Table or column doesn't exist on this WHMCS version; skip.
        }

        return null;
    }

    private function findFirstFullAdmin(): ?string
    {
        $sql = 'SELECT a.username
                FROM tbladmins a
                JOIN tbladminroles r ON r.id = a.roleid
                WHERE a.disabled = 0
                  AND r.name = ?
                ORDER BY a.id ASC
                LIMIT 1';

        $rows = $this->db->select($sql, [self::FULL_ADMIN_ROLE]);

        return !empty($rows) ? (string) $rows[0]->username : null;
    }

    private function findFirstAdminWithAcceptOrdersPerm(): ?string
    {
        try {
            $sql = "SELECT a.username
                    FROM tbladmins a
                    JOIN tbladminroles r ON r.id = a.roleid
                    JOIN tbladminperms p ON p.roleid = r.id
                    WHERE a.disabled = 0
                      AND p.permid LIKE '%AcceptOrder%'
                    ORDER BY a.id ASC
                    LIMIT 1";

            $rows = $this->db->select($sql, []);
            if (!empty($rows)) {
                return (string) $rows[0]->username;
            }
        } catch (\Throwable $e) {
            // Skip if WHMCS version doesn't support this table/column layout.
        }

        return null;
    }
}
