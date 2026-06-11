# PTJ User Isolation + Light Discord Warning Fix — 2026-06-09

## What was fixed

### 1. PTJ user data isolation
The PTJ could show or save journal data under the wrong user when the browser had a fresh Firebase bearer token but PHP still held an older session. This is especially risky after switching users/devices or after a stale PHP session survives while Firebase auth changes.

Fixes applied:
- `api/config.php`
  - `current_user_or_null()` now checks the incoming Firebase bearer token against the active PHP session.
  - If the bearer UID/email differs from the PHP session UID/email, the backend replaces the stale session with the bearer-token user before PTJ reads/writes.
- `api/data.php`
  - Added `journal_row_matches_user()` and `filter_journal_rows_for_user()`.
  - `list_journal` now filters cached and Firebase rows so rows explicitly owned by another UID/email are not returned.
- `index.html`
  - PTJ now prefers the live Firebase auth UID over the PHP/session profile ID when building the PTJ identity/cache key.
  - PTJ now filters journal rows on the frontend too, so any row with another user’s `userId`, `uid`, `ownerId`, `firebaseUid`, `createdBy`, or email owner field is rejected before display.

### 2. Light-mode Premium Discord warning pill
The red warning background in the Premium Discord card was too wide. It now behaves like a fitted warning pill:
- `display: inline-flex`
- width auto
- max-width capped
- reduced padding
- wraps naturally without stretching across the full card

Dark mode was not changed.

## Validation
- PHP syntax check passed across all PHP files.
- Inline/local JavaScript syntax check passed with `node --check`.
- ZIP integrity verified after packaging.

## Important live test
After upload, test with two different users:
1. Log in as User A and add/confirm PTJ trades.
2. Log out fully.
3. Log in as User B in the same browser.
4. Confirm User B does not see User A’s PTJ trades.
5. Add a User B trade, then confirm User A does not see it after logging back in.
