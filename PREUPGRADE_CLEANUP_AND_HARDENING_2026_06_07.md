# Traders Sanctuary Pre-Upgrade Cleanup and Hardening — 2026-06-07

This package is the stability baseline to deploy before starting feature upgrades.

## What changed in this cleanup

1. Added a clear source-of-truth note to `index.html`. The live app currently runs from the large inline module inside `index.html`; `module.js` and `app_module.js` are reference/back-up files unless `index.html` is changed to import them.
2. Locked `api/firebase_auth_probe.php` behind either an admin session or `DIAGNOSTICS_TOKEN`, matching the other diagnostics endpoints.
3. Coinbase was fully removed in the M-PESA-only build. Manual Safaricom M-PESA Buy Goods Till verification is the only premium payment path.
4. Added `api/preupgrade_smoke_check.php` for a quick admin/token-only health check before upgrades.
5. Added `.gitignore` so secrets, runtime logs, and uploaded user/admin files are not accidentally committed.
6. Removed the filled `api/env.local.php` from the distributable zip. Keep the real one only on the VPS/server. Use `api/env.local.php.example` as the template.

## Deployment notes

Upload this package over the current live files, but do not delete the live server's existing `api/env.local.php` if it already contains your real keys. This zip intentionally excludes the filled local secrets file.

After uploading, open these as admin or with `?token=YOUR_DIAGNOSTICS_TOKEN`:

- `/deployment_check.php`
- `/api/preupgrade_smoke_check.php`
- `/api/performance_check.php`
- `/api/data.php?action=admin_firestore_diagnostics`

## Upgrade gate

Begin upgrades only after these five tests pass:

1. Login opens the dashboard immediately.
2. Admin email opens admin tools, not member-only tools.
3. PTJ loads real trades instead of zeros.
4. Study materials, announcements, testimonials, notifications, and trading notes load without timeout banners.
5. Mobile manage accounts and log-new-trade screens scroll and save correctly.

## Remaining non-blocking technical debt

- Tailwind CDN is still present to avoid design regressions in this stability build. Compile Tailwind before the next polished production release.
- Several runtime UI guard scripts still exist. They are safer now, but the long-term upgrade should move those fixes into proper component styles instead of DOM patch scripts.
