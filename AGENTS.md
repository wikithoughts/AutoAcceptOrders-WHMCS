# AutoAcceptOrders-WHMCS — agent contract

WHMCS addon module that auto-accepts qualifying orders. **Public repo** (`wikithoughts/AutoAcceptOrders-WHMCS`): unlimited Actions minutes, direct push to `main` allowed.

## Layout

- `modules/addons/auto_accept_orders/` — the module: `auto_accept_orders.php` (config/admin UI), `hooks.php` (WHMCS hook registration), `autoload.php`, `lib/` (logic)
- `tests/` — PHPUnit (WHMCS APIs stubbed under `tests/Stub`, excluded from phpstan)
- `vendor/` — composer output; never edit. `composer.lock` is deliberately untracked.

## Commands (verified — PHP 8.5 via Homebrew; ensure `/opt/homebrew/bin` on PATH)

```bash
composer install
vendor/bin/phpunit              # 56 tests
vendor/bin/phpstan analyse      # level 5, phpstan.neon.dist
```

Both must pass before any push — CI runs the same matrix (PHP 8.1+; 7.4/8.0 dropped 2026-05).

## Deployment

Copy `modules/addons/auto_accept_orders/` into a WHMCS installation's `modules/addons/` and activate in the WHMCS admin. No build step.
