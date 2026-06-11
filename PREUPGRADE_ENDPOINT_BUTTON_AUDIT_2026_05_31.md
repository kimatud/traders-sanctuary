# Pre-upgrade endpoint/button audit — 2026-05-31

Static package checks completed:
- All frontend PHP endpoint references in `index.html` have matching files in the package.
- PHP lint passes across all PHP files.
- Inline JavaScript syntax check passes.
- Local asset reference check passes.
- Public/non-auth endpoints were smoke-tested with PHP's built-in server.

Functional hardening added in this build:
- `list_announcements` now returns local/cache data immediately unless `fresh=1` is requested.
- `public_testimonials` remains local/cache-first.
- `list_journal` now returns local/cache data immediately unless `fresh=1` is requested.
- `list_trading_accounts` now returns local/session profile accounts immediately unless `fresh=1` is requested.
- Frontend idle logout and backend session timeout now both use 60 minutes.
- Backend remote HTTP timeout reduced to 5 seconds and connect timeout to 2 seconds for faster failover.

Important limitation:
- Button click accuracy was statically checked by verifying all referenced endpoints/assets/scripts exist and parse correctly. A real browser/live-server pass is still required for authenticated UI-only behaviors because Firebase/Auth/Firestore require the deployed domain and live credentials.
