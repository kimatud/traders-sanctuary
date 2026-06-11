# Traders Sanctuary Real Stability Repair — 2026-06-02

This build focuses on stopping dashboard lockups and resource/API calls from blocking app navigation.

## Main fixes

1. Public resources no longer wait for Firebase auth headers.
   - `get_study_materials.php`
   - `list_certificates.php`
   - public testimonials
   - public announcements/header notifications

2. `apiFetch()` now supports `skipAuth` and `soft` calls.
   - Public resources use `skipAuth: true`.
   - Non-critical resources use short timeouts.
   - Token attachment is capped so it cannot hang the UI.

3. App boot now prioritizes verified cached sessions after the fast PHP session check.
   - If `/api/me.php?fast=1` is slow, the app can still open from a verified cached session.
   - Firebase server-session restoration is capped so it cannot keep the app on a loader forever.

4. Dashboard no longer gets trapped in `Loading Dashboard...` when auth fails to restore.
   - It shows a controlled session refresh screen instead of an infinite spinner.

5. `api/data.php` no longer calls session/token bootstrapping for public fast reads.
   - This removes the root cause of public resources waiting on backend auth refresh.

6. Mobile and desktop scroll safety added.
   - PTJ modals
   - account manager
   - log-trade panel
   - analytics dashboard
   - admin console
   - auth modal

## Validation

- All PHP files passed `php -l` syntax checks.
- Main JavaScript module passed `node --check`.

## Deployment note

After uploading this build, clear browser cache or test in incognito first. Old sessionStorage from previous builds may still contain stale state, but this build uses a bumped cache version so old cached sessions are ignored.
