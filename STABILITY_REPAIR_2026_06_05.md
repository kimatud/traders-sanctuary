# Traders Sanctuary Stability Repair — 2026-06-05

This build focuses on sign-in responsiveness, dashboard boot reliability, and PTJ stability.

## Fixed
- Login no longer waits for the PHP session bridge before opening the app.
- Email/password and Google sign-in now open the dashboard immediately after Firebase confirms identity.
- The PHP session bridge now runs in the background and has a shorter timeout.
- PTJ preload is scheduled after the UI has painted instead of competing with the sign-in render.
- Heavy dashboard sections, especially Premium Trading Journal, mount after the first dashboard paint.
- The full-page mutation observer no longer scans the entire React dashboard repeatedly.
- The back-to-top scroll handler no longer uses `document.body.innerText` on every scroll.
- Dashboard auxiliary resources are delayed slightly after dashboard paint.
- Trading notes load after the initial dashboard render instead of during first paint.

## Validation
- Main JavaScript module passed syntax check.
- All PHP files passed `php -l` syntax checks.

## Upload path
Upload the full contents to `/var/www/html`.
