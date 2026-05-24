# Auto Accept Orders (WHMCS Addon)

Automatically accepts eligible WHMCS orders when:
- an invoice is paid (`InvoicePaid`), or
- checkout completes for a free order (`ShoppingCartCheckoutComplete`).

Duplicate-acceptance is prevented by an atomic database claim: before calling `AcceptOrder`, the module inserts a unique log row (keyed on `order_id + trigger_hook`). If a concurrent hook fires for the same order, the insert is silently ignored and the second hook exits without calling the API.

## Requirements

- PHP 7.4+
- WHMCS 8.0+

## Installation

1. Upload the `auto_accept_orders` folder to:
   - `modules/addons/`
2. Confirm these files exist:
   - `modules/addons/auto_accept_orders/auto_accept_orders.php`
   - `modules/addons/auto_accept_orders/hooks.php`

## Activate in WHMCS

1. In WHMCS Admin, go to **Setup > Addon Modules**.
2. Find **Auto Accept Orders** and click **Activate**.
3. Configure (see Configuration section below).
4. Save changes.

## Configuration

| Field | Type | Description |
|---|---|---|
| **Enable** | Yes/No | Turn the module on or off. |
| **Admin Username** | Text | Optional — see Admin Username section below. |
| **Verbose Logging** | Yes/No | When on, successful acceptances are also logged to **Utilities > Module Log**. Default: off. |

## Admin Username

- If **Admin Username** is provided and matches an active **Full Administrator**, it is used for `localAPI('AcceptOrder')`.
- If the configured username is blank or invalid, the module falls back to the first active **Full Administrator** (by lowest admin ID).
- If the Full Administrator role has been renamed or translated, the module additionally checks for a role with the explicit **Accept Orders** permission before giving up.
- If no qualifying administrator is found, order acceptance is skipped and an error is recorded in **Utilities > Module Log** under `auto_accept_orders`.

> **Note:** A Full Administrator account (or an account with the Accept Orders permission) is required. The module will not fall back to limited-permission admin accounts, as they may lack the permissions needed to accept orders.

## Admin Page

The addon exposes a status page at **Setup > Addon Modules > Auto Accept Orders > Configure**.

**Filters:** narrow down log entries by trigger, status (Pending / OK / Error), or order ID.

**Pagination:** choose 10, 25, 50, or 100 rows per page.

**Reprocess:** PENDING rows show a **Reprocess** button. Clicking it re-fires `AcceptOrder` for that order and updates the log row. The action is CSRF-protected.

## Data Model

The module stores acceptance attempts in `mod_autoaccept_logs`:

| Column | Type | Description |
|---|---|---|
| `id` | INT AUTO_INCREMENT | Primary key |
| `order_id` | INT UNSIGNED | WHMCS order ID |
| `trigger_hook` | VARCHAR(50) | `'InvoicePaid'` or `'FreeOrder'` (see note below) |
| `status_response` | TEXT | `'PENDING'` while in-flight; JSON API response after finalization |
| `created_at` | DATETIME | Claim timestamp |

**Trigger naming:** The `trigger_hook` column stores `'InvoicePaid'` for orders accepted via the `InvoicePaid` WHMCS hook, and `'FreeOrder'` for orders accepted via the `ShoppingCartCheckoutComplete` hook. The `FreeOrder` label is intentional — it describes the semantic trigger (a zero-amount order), not the hook name.

## Stale PENDING Rows

If the server crashes between claim and finalize, a log row can be left with `status_response = 'PENDING'` permanently, which would block future re-processing via the unique index.

The module handles this automatically: if a row has been stuck in PENDING for more than **15 minutes**, the next hook fire for the same order will reclaim it and re-attempt `AcceptOrder`.

For immediate recovery, use the **Reprocess** button on the admin page.

## Notes

- **Deactivating the module preserves `mod_autoaccept_logs`** so your audit trail is not lost on toggle.
- The free-order hook always returns an empty array to avoid interrupting the checkout flow.
- Rows in `PENDING` state indicate a claim that was made but not finalized. They are automatically reclaimed after 15 minutes, or can be reprocessed manually from the admin page.

## Upgrading

1. Upload the new files to `modules/addons/auto_accept_orders/`.
2. In WHMCS Admin, go to **Setup > Addon Modules**.
3. Click **Upgrade** next to **Auto Accept Orders**.

The upgrade function runs automatically and is idempotent — it is safe to run multiple times.

### 1.0.0 → 1.1.0

Adds a unique index on `(order_id, trigger_hook)` to the existing `mod_autoaccept_logs` table. If the table contains duplicate `(order_id, trigger_hook)` pairs from prior double-fires, the `ALTER TABLE` will fail. Resolve by deduplicating those rows in the database before re-running the upgrade.

### 1.1.0 → 1.2.0

The upgrade deduplicates any existing `(order_id, trigger_hook)` pairs automatically (keeping the newest row per pair) before applying any schema changes. No new columns are added. The dedup step is idempotent and safe to re-run.

## Development

Requires PHP 7.4+ and Composer.

```bash
# Install dev dependencies
composer install

# Run tests
composer test

# Run static analysis
composer stan

# Check code style (dry run)
composer cs

# Fix code style
composer cs-fix
```

**CI:** GitHub Actions runs the full pipeline (`php -l`, PHPUnit, PHPStan, php-cs-fixer) on PHP 7.4, 8.0, 8.1, and 8.2 for every push and pull request.

Note: `vendor/` is gitignored and should not be committed.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full history.
