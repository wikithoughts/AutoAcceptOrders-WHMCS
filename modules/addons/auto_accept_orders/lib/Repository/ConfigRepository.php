<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Repository;

use AutoAcceptOrders\Contract\DatabaseInterface;

/**
 * Reads addon module settings from tbladdonmodules.
 */
class ConfigRepository
{
    private const MODULE = 'auto_accept_orders';

    /** @var DatabaseInterface */
    private $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    public function isEnabled(): bool
    {
        $value = $this->db->getValue('tbladdonmodules', [
            'module'  => self::MODULE,
            'setting' => 'enabled',
        ], 'value');

        if ($value === null) {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'on', 'true', 'yes'], true);
    }

    public function get(string $key): string
    {
        return (string) $this->db->getValue('tbladdonmodules', [
            'module'  => self::MODULE,
            'setting' => $key,
        ], 'value');
    }

    public function isVerboseLoggingEnabled(): bool
    {
        $value = $this->db->getValue('tbladdonmodules', [
            'module'  => self::MODULE,
            'setting' => 'verbose_logging',
        ], 'value');

        if ($value === null) {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'on', 'true', 'yes'], true);
    }
}
