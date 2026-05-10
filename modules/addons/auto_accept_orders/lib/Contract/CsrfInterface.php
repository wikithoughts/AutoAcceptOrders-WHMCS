<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Contract;

/**
 * Wraps CSRF token generation and validation for testability.
 */
interface CsrfInterface
{
    /**
     * Generate a new CSRF token for the current session.
     *
     * @return string
     */
    public function generate(): string;

    /**
     * Validate a submitted CSRF token.
     *
     * @param string $token
     * @return bool
     */
    public function validate(string $token): bool;
}
