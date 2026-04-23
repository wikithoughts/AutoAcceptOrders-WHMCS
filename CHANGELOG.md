# Changelog

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

## 1.0.0 — 2026-04-20

- Initial release.
