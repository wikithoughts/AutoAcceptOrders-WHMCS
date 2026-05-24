<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Tests\Stub;

use AutoAcceptOrders\Contract\CsrfInterface;

/**
 * Deterministic CSRF stub for tests.
 *
 * Always generates 'test-token' and validates any token that equals 'test-token'.
 */
class StubCsrf implements CsrfInterface
{
    public const TOKEN = 'test-token';

    public function generate(): string
    {
        return self::TOKEN;
    }

    public function validate(string $token): bool
    {
        return $token === self::TOKEN;
    }
}
