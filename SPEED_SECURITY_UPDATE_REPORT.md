# Traders Sanctuary Speed + Security Pass — 2026-05-31

## Main changes
- Removed private hardcoded secrets from `api/config.php`.
- Added environment/local-secret loading through `api/env.local.php` or server environment variables.
- Added `api/env.local.php.example` with placeholders only.
- Disabled frontend Firebase credential exposure by replacing the browser API key with a backend-auth placeholder.
- Moved login/register to backend session flow when browser Firebase is disabled.
- Increased session timeout from 10 minutes to 60 minutes on both frontend and backend.
- Added login/register rate limiting using `api/_data/auth_rate_limits.json`.
- Removed verbose Firebase raw error/debug exposure from login responses.
- Made `/api/me.php?fast=1` return the current session quickly for faster app boot.
- Added Brotli/Deflate compression and stronger static cache headers.
- Deferred non-critical third-party scripts: Swiper, html2canvas, jsPDF.
- Added preconnect hints for major CDNs/fonts.
- Protected `api/_data`, log/json files, and local env files from public access.
- Protected diagnostics/deployment checks behind admin session or `DIAGNOSTICS_TOKEN`.
- Added `api/performance_check.php` to verify config, writable folders, session settings, and PHP capability.
- Added local fallback endpoints for announcement uploads/deletes when browser Firebase Storage is disabled.

## Required live server setup
Create `api/env.local.php` on the live server by copying `api/env.local.php.example`, then fill in the real values:

- `FIREBASE_WEB_API_KEY`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `SMTP_PASSWORD`
- `SUBSCRIPTION_CRON_SECRET`
- `DIAGNOSTICS_TOKEN`

Do not share or re-zip the filled `api/env.local.php` file.

## Folder names preserved
- `funded certificates`
- `study materials`
- `trade_screenshots`
- `announcement_files`
- `api/_data`

## Validation performed
- PHP lint passed for all PHP files.
- Inline JavaScript syntax check passed.
- No hardcoded Google OAuth secret, SMTP password, cron secret, or Firebase API key remained in the package.
