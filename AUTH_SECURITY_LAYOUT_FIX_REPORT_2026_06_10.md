# Auth Security + Small Laptop Layout Fix — 2026-06-10

## Fixed

### 1. Refresh / hard-refresh privacy issue
Previous behavior could restore the last signed-in user because Firebase Auth persistence and the PHP session were not fully cleared during logout/idle logout.

Changes made:
- Added Firebase `signOut(clientAuth)` to the logout flow.
- Added `browserSessionPersistence` so auth is not kept as long-lived local persistence.
- Added a logout lock in `localStorage` to prevent `/api/me.php` or Firebase auto-restore from resurrecting the previous user after logout.
- Logout now clears the fast session cache and PTJ preload memory.
- Boot auth now checks the logout lock before accepting server/Firebase cached users.
- Login/register/Google login clears the logout lock only after a real new sign-in starts.

### 2. Auth page on tablets and small laptops
Previous behavior kept tablet/small laptop widths in a mobile-like stacked layout, forcing the user to scroll before reaching the login area.

Changes made:
- Added a final responsive auth override from `740px` to `1180px`.
- Forces the same desktop-style two-column shell for tablets and small laptops.
- Compresses brand copy, proof cards, input spacing, and form padding to fit the viewport.
- Hides non-critical brand proof sections on narrower tablets or short-height laptops so the form stays visible.

### 3. Light-mode Join Premium Discord card alignment
Previous behavior aligned light-mode text left and placed the button awkwardly.

Changes made:
- Centered all light-mode card content.
- Kept the red warning pill above.
- Forced the Join Premium Discord button below the warning and centered.
- Dark mode was not changed.

## Validation
- JavaScript syntax check: passed.
- PHP syntax check: passed.
- CSS brace balance check: passed.
- ZIP integrity: passed after packaging.
