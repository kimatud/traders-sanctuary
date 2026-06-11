# Traders Sanctuary hard-refresh full-app stability repair — 2026-06-07

## Main problems corrected

1. Login opened too slowly and could appear stuck on the auth page.
   - Email/password and Google login now open the workspace immediately after Firebase confirms the user.
   - PHP/Firebase session bridging now continues quietly in the background instead of blocking the dashboard.
   - Admin email protection remains in place so `denniskimatu4028@gmail.com` is normalized to `admin` client-side while backend profile sync completes.

2. Dashboard became unresponsive after login.
   - Several old runtime watchdogs were repeatedly scanning the whole dashboard and forcing inline styles.
   - Infinite dashboard/log-button/PTJ unlock intervals were converted to short finite boot checks.
   - MutationObservers that watched `class` and `style` changes were changed to child-node observation only, preventing self-triggering style loops.

3. PTJ/study/announcement resources could sit loading or show zeros.
   - Browser Firestore REST fallback now has a hard timeout and cannot hang the UI.
   - PTJ journal/account loaders now use shorter soft timeouts instead of 30–60 second blocking requests.
   - PTJ still uses local/preloaded rows first, then hydrates Firebase data after login.
   - The “no trades” message no longer implies the data is permanently missing.

4. Backend Firestore reads could time out on shared hosting.
   - User collection reads now cap Firestore path/token attempts.
   - Collection-group recovery searches are capped so one missing namespace cannot freeze `api/data.php`.
   - Trading-account loading is limited to the most relevant paths first.
   - Account merge logic was corrected so duplicate account IDs merge predictably.

5. Hard refresh / stale deployment behavior.
   - HTML cache is set to no-store/no-cache so users get the repaired app immediately after deployment.
   - PHP endpoints remain no-store.

## Validation performed

- JavaScript syntax check passed for `module.js`, `app_module.js`, and the inline module inside `index.html`.
- PHP syntax check passed for every `.php` file in the project.
- The repaired `index.html` was rebuilt with the patched module content.

## Deployment notes

Upload the whole package to `/var/www/html` and replace existing files. Keep the `api/_data` folder writable by the web server. After upload, open the site in a browser and hard refresh once using Ctrl+F5.

Recommended quick checks after deployment:

1. Visit `/api/ping.php` — should return quickly.
2. Login using the admin email — it should route directly to dashboard/admin access without hanging.
3. Open PTJ — cached/preloaded trades should appear quickly; Firebase hydration continues after.
4. Visit `/api/data.php?action=firebase_data_diagnostics&fresh=1` while logged in if PTJ still shows zero rows; it will show which Firebase paths are readable.
