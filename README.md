# Auto Accept Orders (WHMCS Addon)

Automatically accepts eligible WHMCS orders when:
- an invoice is paid (`InvoicePaid`), or
- checkout completes for a free order (`ShoppingCartCheckoutComplete`).

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
   - **Admin Username**: optional
4. Save changes.

## Admin Username Fallback Logic

- If **Admin Username** is provided and matches an active **Full Administrator**, it is used for `localAPI('AcceptOrder')`.
- If it is blank or invalid, the module falls back to the first active **Full Administrator**.
- If no active Full Administrator is found, it falls back to the first active admin account.

## Notes

- The module logs API outcomes in `mod_autoaccept_logs`.
- Free-order hook returns an empty array to avoid checkout flow interruption.
