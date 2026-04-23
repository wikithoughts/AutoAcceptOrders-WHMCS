# Auto Accept Orders (WHMCS Addon)

Automatically accepts eligible WHMCS orders when:
- an invoice is paid (`InvoicePaid`), or
- checkout completes for a free order (`ShoppingCartCheckoutComplete`).

Duplicate-acceptance is prevented by an atomic database claim: before calling `AcceptOrder`, the module inserts a unique log row (keyed on `order_id + trigger_hook`). If a concurrent hook fires for the same order, the insert is silently ignored and the second hook exits without calling the API.

## Installation

1. Upload the `auto_accept_orders` folder to:
   - `modules/addons/`
2. Confirm these files exist:
   - `modules/addons/auto_accept_orders/auto_accept_orders.php`
   - `modules/addons/auto_accept_orders/hooks.php`

## Activate in WHMCS

1. In WHMCS Admin, go to **Setup > Addon Modules**.
2. Find **Auto Accept Orders** and click **Activate**.
3. Configure:
   - **Enable**: turn the module on
   - **Admin Username**: optional (see below)
4. Save changes.

## Admin Username

- If **Admin Username** is provided and matches an active **Full Administrator**, it is used for `localAPI('AcceptOrder')`.
- If it is blank or invalid, the module falls back to the first active **Full Administrator** (by lowest admin ID).
- If no active Full Administrator is found, order acceptance is skipped and an error is recorded in **Utilities > Module Log** under `auto_accept_orders`.

> **Note:** A Full Administrator account is required. The module will not fall back to limited-permission admin accounts, as they may lack the permissions needed to accept orders.

## Notes

- The module logs API outcomes in `mod_autoaccept_logs`. Rows in PENDING state indicate a claim that was made but not finalized (e.g. due to a crash) — these are safe to inspect and can be cleared manually if needed.
- Free-order hook always returns an empty array to avoid interrupting the checkout flow.
- **Deactivating the module preserves `mod_autoaccept_logs`** so your audit trail is not lost on toggle.

## Upgrading

1. Upload the new files to `modules/addons/auto_accept_orders/`.
2. In WHMCS Admin, go to **Setup > Addon Modules**.
3. Click **Upgrade** next to **Auto Accept Orders**.

The upgrade function runs automatically and is idempotent — it is safe to run multiple times.

### 1.0.0 → 1.1.0

Adds a unique index on `(order_id, trigger_hook)` to the existing `mod_autoaccept_logs` table. If the table contains duplicate `(order_id, trigger_hook)` pairs from prior double-fires, the `ALTER TABLE` will fail with a duplicate-key error and the upgrade will report the error message. Resolve by deduplicating those rows in the database before re-running the upgrade.
