# Traders Sanctuary real stability repair — 2026-06-02

## Root causes fixed

1. Public dashboard resources were starting PHP sessions.
   - PHP locks the session file per user while a request is running.
   - On dashboard boot, several calls run together: testimonials, announcements, study materials, header notifications, testimonial status, notes.
   - Because public reads were starting the same PHP session, requests could queue behind each other and time out.
   - This caused: `Some dashboard resources could not load`, then later `Loading Dashboard...`.

2. The frontend `apiFetch()` was attaching Firebase auth headers too broadly.
   - Simple PHP resources were waiting for Firebase token resolution.
   - Slow token syncing made harmless resources behave like protected auth calls.

3. Admin role could still display as member from stale frontend/session data.
   - The frontend now force-normalizes configured admin emails.
   - The backend admin user list also force-normalizes configured admin emails.
   - Session cache version was bumped so old member-role cache is rejected.

## Important changed files

- `index.html`
  - Removed automatic Firebase auth header attachment from general API calls.
  - Added strict admin email role normalization.
  - Shortened soft dashboard resource timeouts.
  - Made dashboard resources fail quietly instead of blocking the workspace.
  - Added role dropdown support for member/premium/admin.

- `api/config.php`
  - Added `TS_SKIP_SESSION_START` support so public endpoints can avoid PHP session locking.

- `api/data.php`
  - Public fast reads now skip session startup before loading config.
  - Public reads include testimonials and announcements.
  - Admin profile merge now forces configured admin emails to role `admin`.

- `get_study_materials.php`
  - Now skips PHP session startup.
  - Keeps the endpoint public and fast.

## Validation

- JavaScript module syntax check passed with `node --check`.
- All PHP files passed `php -l`.

## Deployment note

After uploading this build, clear browser cache/session storage or test in an incognito window because older builds stored a bad member-role cache in `sessionStorage`.
