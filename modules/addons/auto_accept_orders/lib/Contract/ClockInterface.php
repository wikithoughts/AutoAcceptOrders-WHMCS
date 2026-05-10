<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Contract;

/**
 * Provides the current time so tests can freeze or control it.
 */
interface ClockInterface
{
    /**
     * Current datetime as a MySQL-formatted string.
     *
     * @return string  e.g. "2026-05-10 14:00:00"
     */
    public function now(): string;

    /**
     * Datetime N minutes in the past.
     *
     * @param int $minutes
     * @return string
     */
    public function minutesAgo(int $minutes): string;
}
