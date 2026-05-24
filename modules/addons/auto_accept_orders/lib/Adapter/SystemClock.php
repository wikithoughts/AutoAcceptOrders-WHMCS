<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Adapter;

use AutoAcceptOrders\Contract\ClockInterface;

/**
 * ClockInterface implementation using the system clock.
 */
class SystemClock implements ClockInterface
{
    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function minutesAgo(int $minutes): string
    {
        return date('Y-m-d H:i:s', time() - ($minutes * 60));
    }
}
