<?php

declare(strict_types=1);

namespace AutoAcceptOrders\Adapter;

use AutoAcceptOrders\Contract\CsrfInterface;

/**
 * CsrfInterface implementation for production WHMCS installs.
 *
 * Prefers WHMCS\CSRF\Token (available WHMCS 7.7+).
 * Falls back to a session-based token for older installs.
 */
class WhmcsCsrf implements CsrfInterface
{
    public function generate(): string
    {
        if (class_exists('WHMCS\CSRF\Token')) {
            return \WHMCS\CSRF\Token::generate();
        }

        return $this->sessionToken();
    }

    public function validate(string $token): bool
    {
        if (class_exists('WHMCS\CSRF\Token')) {
            try {
                \WHMCS\CSRF\Token::check($token);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }

        $expected = $this->sessionToken();
        return hash_equals($expected, $token);
    }

    private function sessionToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['aao_csrf'])) {
            $_SESSION['aao_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['aao_csrf'];
    }
}
