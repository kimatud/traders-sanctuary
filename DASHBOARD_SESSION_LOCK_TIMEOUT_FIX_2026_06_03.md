# Dashboard Session Lock Timeout Fix — 2026-06-03

## Root cause fixed
The dashboard was opening several resource loaders at the same time: study materials, header notifications, testimonial status, announcement alerts, trading notes, and the background session bridge.

The background session bridge used PHP sessions while doing slower Firebase/profile work. On shared hosting, that can lock the PHP session file. Other dashboard GET endpoints then queue behind it until the frontend timeout fires, causing messages like:

- Study materials unavailable: The server took too long to respond.
- Header notifications unavailable: The server took too long to respond.
- Testimonial status unavailable.
- Announcement alerts unavailable.
- Trading notes unavailable.

## Changes made
1. `api/session_from_id_token.php`
   - No longer starts the PHP session before Firebase/profile verification.
   - Starts/writes/closes the PHP session only at the final moment after profile data is ready.
   - This prevents the background bridge from blocking dashboard resource endpoints.

2. `api/data.php`
   - Authenticated dashboard GET requests with a Firebase bearer token now skip PHP session start.
   - Public fast reads still skip PHP sessions.

3. `api/config.php`
   - Added a stateless Firebase bearer fast path for sessionless dashboard GET reads.
   - `current_user_or_null()` can now identify the user from the Firebase bearer token without `accounts:lookup` or session locking.
   - `current_id_token_or_null()` safely prefers the browser Firebase bearer token without writing to `$_SESSION` when the request is intentionally sessionless.

4. `index.html`
   - Soft resource loaders now use longer soft timeouts.
   - Non-critical soft fallbacks use `console.debug()` instead of noisy `console.warn()`.
   - The dashboard remains usable even when an optional resource is unavailable.

## Verified locally
- `api/data.php?action=list_announcements&fast=1` returns JSON immediately.
- `api/data.php?action=has_testimonial&fast=1` works with a Firebase bearer-style token without opening PHP session.
- `api/data.php?action=list_trading_notes&fast=1` works with a Firebase bearer-style token without opening PHP session.
- All PHP files pass `php -l` syntax checks.

## Notes
The browser warnings below are not the dashboard resource bug:

- `Permissions-Policy header: Unrecognized feature: interest-cohort` is an old/deprecated browser policy warning.
- `Cross-Origin-Opener-Policy policy would block window.closed` usually comes from Firebase/Google popup auth behavior and does not stop the dashboard resource endpoints from loading.
