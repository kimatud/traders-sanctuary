# M-PESA-only premium payment flow — 2026-06-07

This build removes the Coinbase/Crypto payment path completely and keeps Safaricom M-PESA Buy Goods Till verification as the only premium payment method.

## What changed

- Removed `api/create_checkout.php`.
- Removed `api/coinbase_webhook.php`.
- Removed Coinbase configuration constants from `api/config.php`.
- Updated frontend payment wording in `index.html`, `module.js`, and `app_module.js` to say Safaricom M-PESA Buy Goods Till verification only.
- Kept the existing manual payment submission flow:
  - User pays to Buy Goods Till `6723191`.
  - User submits the M-PESA confirmation code and phone used for payment.
  - Admin verifies or rejects the request from the Payments panel.
  - Verified users get premium access for `30` days.

## Active payment settings

The active settings are in `api/config.php`:

```php
define('PREMIUM_DAYS', 30);
define('PREMIUM_PRICE_KES', '3000');
define('MPESA_PAYMENT_METHOD', 'Safaricom M-PESA Buy Goods Till');
define('MPESA_BUSINESS_NAME', 'Traders Sanctuary');
define('MPESA_TILL_NUMBER', '6723191');
define('MPESA_PHONE_NUMBER', '');
define('SUPPORT_EMERGENCY_CONTACT', '0797671000');
define('MPESA_ADMIN_NOTE', 'Pay via Safaricom M-PESA Buy Goods Till Number 6723191, then submit the M-PESA confirmation code for admin verification.');
```

If the Till Number changes later, update both `MPESA_TILL_NUMBER` and `MPESA_ADMIN_NOTE` in `api/config.php`.

## Live test after deployment

1. Log in as a member.
2. Open the Premium page.
3. Confirm it shows Safaricom M-PESA Buy Goods Till only.
4. Submit a test payment proof with a fake code.
5. Log in as admin and check the Payments panel.
6. Reject the fake proof or delete it from `api/_data/manual_payments.json` if needed.
