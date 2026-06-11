# Dashboard Endpoint/Auth Stability Fix — 2026-06-03

## What was fixed

1. `apiData()` now attaches the active Firebase ID token for all authenticated `/api/data.php` calls.
   - This protects dashboard, PTJ, profile, admin, role, payment, and user-management endpoints when the PHP session is stale, locked, or delayed.
   - It also retries 401/403 responses with a fresh token.

2. Public dashboard resources stay lightweight.
   - Public calls such as testimonials and announcements remain no-auth fast reads so they do not slow dashboard boot.

3. Dashboard resource endpoints now use root-safe paths.
   - `/get_study_materials.php`
   - `/list_certificates.php`
   This avoids broken relative endpoint paths if the app is opened from a nested route or cached browser path.

4. Study materials and certificates remain soft-load resources.
   - If those resources are empty/slow, the dashboard should keep opening instead of showing the blocking resource error.

## Endpoints checked locally

- `/get_study_materials.php` → HTTP 200 JSON array
- `/list_certificates.php` → HTTP 200 JSON array
- `/api/data.php?action=list_announcements&fast=1` → HTTP 200 JSON
- `/api/data.php?action=public_testimonials&fast=1` → HTTP 200 JSON
- `/api/ping.php` → HTTP 200 JSON

## Validation

- All PHP files passed `php -l` syntax validation.
- The updated package is ready to upload over the current site files.

## Important deployment note

After uploading this build, clear browser cache or do a hard refresh. If Cloudflare/host caching is enabled, purge the cache so the browser loads the updated `index.html` and not the older dashboard script.
