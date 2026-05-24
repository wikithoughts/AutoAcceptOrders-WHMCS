<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Stub;

use AutoAcceptOrders\Contract\ClockInterface;

/**
 * Deterministic clock for tests.
 */
class FrozenClock implements ClockInterface
{
    /** @var string */
    private $now;

    public function __construct(string $now = '2026-05-10 12:00:00')
    {
        $this->now = $now;
    }

    public function now(): string
    {
        return $this->now;
    }

    public function minutesAgo(int $minutes): string
    {
        $ts = strtotime($this->now) - ($minutes * 60);
        return date('Y-m-d H:i:s', $ts);
    }

    public function setNow(string $now): void
    {
        $this->now = $now;
    }
}
