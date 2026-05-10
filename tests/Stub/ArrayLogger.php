<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Stub;

use AutoAcceptOrders\Contract\LoggerInterface;

/**
 * In-memory logger that records all calls for assertion in tests.
 */
class ArrayLogger implements LoggerInterface
{
    /** @var array */
    private $calls = [];

    public function log(string $module, string $action, array $request, $response): void
    {
        $this->calls[] = compact('module', 'action', 'request', 'response');
    }

    public function getCalls(): array
    {
        return $this->calls;
    }

    public function hasCallMatching(string $action): bool
    {
        foreach ($this->calls as $call) {
            if ($call['action'] === $action) {
                return true;
            }
        }
        return false;
    }

    public function count(): int
    {
        return count($this->calls);
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
