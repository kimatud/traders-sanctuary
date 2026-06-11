# Member Dashboard, Notes, Certificates and Share Experience Fixes — 2026-06-07

## Fixed areas

### 1. Member Command Center alignment
- Added a final dashboard CSS layer that aligns the command center action links into equal, clean columns on desktop.
- Mobile keeps a one-column stacked layout for tap-friendly controls.

### 2. Trading notes instant refresh
- Removed the fast-empty trading notes read that returned an empty list after save.
- `save_trading_note` now returns the saved note item with a stable note id.
- The frontend now updates the trading notes table immediately after adding/updating/deleting a note, then refreshes from the server in the background.

### 3. Certificate submission UX
- Member certificate upload now shows an image preview before submission.
- Admin manual certificate upload now shows an image preview before upload.
- Certificate endpoints now attach Firebase auth headers and retry with fresh auth when needed, reducing silent 401/403 failures after login.

### 4. Certificate approval action response
- Admin approval/rejection now uses authenticated API calls with longer timeout windows.
- Approval/rejection buttons should show a working state and then return a success/error message instead of appearing dead.

### 5. Share Experience forever-loading
- The testimonial panel no longer remains stuck on Loading when the backend status check is unavailable.
- If status cannot be confirmed, the form opens and server-side uniqueness still protects against duplicate testimonials.

## Files changed
- `index.html`
- `api/data.php`

## Validation
- PHP lint passed for all PHP files.
- Main inline module JavaScript passed `node --check` syntax validation.
