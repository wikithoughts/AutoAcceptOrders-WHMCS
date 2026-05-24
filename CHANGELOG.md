# Changelog

## 1.2.0 — 2026-05-10

### Added

- **Admin page overhaul.** Filter log entries by trigger, status, and order ID; per-page selector (10/25/50/100); windowed pagination.
- **Reprocess PENDING action.** Each PENDING log row now shows a **Reprocess** button in the admin page. Clicking it re-fires `AcceptOrder` for that order. The POST action is CSRF-protected.
- **Stale PENDING auto-reclaim.** If a log row has been stuck in `PENDING` for more than 15 minutes, the next hook fire automatically reclaims it instead of being silently blocked by the unique index.
- **Verbose logging.** New `verbose_logging` config field (yes/no, default off). When enabled, successful acceptances are also logged to Utilities > Module Log alongside the existing failure log.
- **Role-rename robustness.** If the "Full Administrator" role has been renamed or translated, the module now falls back to locating an admin whose role is explicitly granted the "Accept Orders" permission before giving up.
- **GitHub Actions CI.** Full pipeline (php -l, PHPUnit, PHPStan level 5, php-cs-fixer) runs on PHP 7.4, 8.0, 8.1, and 8.2 for every push and pull request.
- **Service-class refactor.** Core logic extracted into `lib/` under the `AutoAcceptOrders\` namespace for testability. All WHMCS entry-point functions are retained as thin dispatchers.
- **PHPUnit 9 test suite.** 56 tests covering ConfigRepository, AdminRepository, LogRepository, OrderRepository, AutoAcceptService, hook handlers, and the admin OutputController.
- **PHPStan level 5, php-cs-fixer PSR-12, .editorconfig.**
- **`declare(strict_types=1)`** on all module PHP files.
- **`composer.json`** with phpunit, phpstan, and php-cs-fixer as dev dependencies.

### Changed

- Trigger names (`InvoicePaid`, `FreeOrder`) and admin output behavior documented in README under **Data Model** and **Admin Page** sections.
- `_activate()` now enforces PHP 7.4+ and WHMCS 8.0+ at runtime and returns a friendly error if requirements are not met.
- Upgrade path adds an idempotent dedup of `mod_autoaccept_logs (order_id, trigger_hook)` pairs (keeps newest row) before any future schema changes.
- Admin output rewritten as `OutputController` + Smarty-less template; removed hardcoded 15-row limit.

### Fixed

- **Orphaned PENDING rows no longer permanently block re-processing.** Stale PENDING rows (>15 min) are automatically reclaimed on the next hook fire.

---

## 1.1.0 — 2026-04-23

### Fixed

- **Preserve audit logs on deactivate.** `mod_autoaccept_logs` is no longer dropped when the module is deactivated. The table is only created on activation and is left untouched on deactivation.
- **Multi-order invoice handling.** `InvoicePaid` now processes *all* pending orders linked to an invoice, not just the most recent one. Invoices with multiple attached orders (e.g. renewals, upgrades, addons) are handled correctly.
- **Free-order amount comparison.** Replaced `number_format(...) === '0.00'` string comparison with a floating-point tolerance check (`abs($amount) < 0.005`) to correctly handle any floating-point representation of zero stored by WHMCS.
- **Full Administrator required.** Removed the fallback that allowed any active admin account (regardless of role) to be used for `AcceptOrder`. The module now logs a clear error in Utilities > Module Log when no Full Administrator is available, rather than silently using an account that may lack the necessary permissions.

### Added

- **Duplicate-acceptance protection.** A unique database index on `(order_id, trigger_hook)` prevents two concurrent hook firings from both calling `AcceptOrder` for the same order. The first hook atomically claims a log row via `INSERT IGNORE`; any subsequent hook for the same order exits early.
- **`auto_accept_orders_upgrade()`.** Idempotently adds the unique index to existing installs when upgrading from 1.0.0.
- **MIT License.**

---

## 1.0.0 — 2026-04-20

- Initial release.
