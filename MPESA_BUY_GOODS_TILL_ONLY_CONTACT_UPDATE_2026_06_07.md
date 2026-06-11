# M-PESA Buy Goods Till-only contact update — 2026-06-07

This build keeps Safaricom M-PESA Buy Goods Till as the only public payment destination.

## Updated payment display

- Public payment method now reads: Safaricom M-PESA Buy Goods Till.
- Public payment instruction now reads: Pay via Safaricom M-PESA Buy Goods Till `6723191`, then submit the M-PESA confirmation code for admin verification.
- Removed the public M-PESA phone/payment number from the premium payment configuration response.
- Users still enter their own phone used for payment when submitting payment proof, so admin verification still works.

## Contact update

- Emergency/payment support contact now displays as `0797671000`.
- The previous personal M-PESA phone contact has been removed from public project files and is not exposed in the payment screen.

## Backend constants

```php
define('MPESA_PAYMENT_METHOD', 'Safaricom M-PESA Buy Goods Till');
define('MPESA_TILL_NUMBER', '6723191');
define('MPESA_PHONE_NUMBER', '');
define('SUPPORT_EMERGENCY_CONTACT', '0797671000');
```

## Deployment note

Upload over the current site, but keep your live `api/env.local.php` untouched if it exists on the VPS.
