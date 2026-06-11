# Deep Dashboard Stability Fix - 2026-06-03

## Main root causes addressed

1. Dashboard boot was still allowed to wait on server session restoration. On shared hosting, slow Firebase/session bridge requests could delay the UI and make unrelated resource loaders appear broken.
2. Optional dashboard resources were still treated too loudly in a few admin/member loaders. These now fail silently or fall back to local cache instead of showing the global dashboard resource error.
3. Browser Firestore fallback reads could create extra blocked/slow requests. The frontend now treats the PHP API/local cache as the source of truth for dashboard reads.
4. Several upload/list endpoint calls used relative paths. They now use root-safe absolute paths so `/dashboard`, `/admin`, and deep routes all hit the same PHP files.
5. `get_study_materials.php` used to include the full backend config. It now avoids PHP sessions/Firebase entirely and returns immediately from local JSON/folder scan.

## Files changed

- `index.html`
  - Faster auth boot with immediate cached/Firebase client user fallback.
  - Background server session refresh cannot block dashboard rendering.
  - Root-safe endpoint paths.
  - Soft resource loaders no longer trigger dashboard-level errors.
  - Browser Firestore public fallback removed from announcement notifications.

- `api/me.php`
  - Fast bearer-token mode skips PHP session locking.
  - Safe guard added so stateless fast responses do not write to `$_SESSION`.

- `get_study_materials.php`
  - Removed dependency on `api/config.php`.
  - No PHP session, no Firebase, no outbound requests.
  - Always returns JSON with HTTP 200, even when the folder/cache is empty.

## Validation performed

- PHP syntax checked across all PHP files.
- Main module JavaScript syntax checked with Node.
- Local endpoint smoke tests passed:
  - `/get_study_materials.php`
  - `/list_certificates.php`
  - `/api/data.php?action=list_announcements&fast=1`
  - `/api/data.php?action=public_testimonials&fast=1`
  - `/api/ping.php`
  - `/api/me.php?fast=1`
