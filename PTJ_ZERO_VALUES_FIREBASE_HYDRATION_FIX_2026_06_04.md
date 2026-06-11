# PTJ Zero Values Firebase Hydration Fix - 2026-06-04

## Root cause
The backend endpoint `/api/data.php?action=list_journal&fresh=1` was already returning Firebase journal rows, but the Premium Trading Journal frontend could still remain at zero when the first dashboard boot happened before the Firebase ID token/session was fully ready, or when a soft/cached empty response won the race.

## Fixes applied
- Added a robust PTJ Firebase hydration sequence that tries:
  1. `/api/data.php?action=list_journal&fresh=1&sync=1`
  2. `/api/data.php?action=list_journal&fresh=1`
  3. `/api/data.php?action=list_journal`
  4. `apiData('list_journal')`
  5. direct browser Firestore collection reads as a final fallback.
- Prevented empty/late responses from overwriting real cached trades.
- Added auth-token-ready retries after sign-in.
- Added timed retries at 1.5s and 6s if PTJ still has zero trades.
- Fixed direct Firestore fallback calls to use `remote:true` for journal and trading accounts.
- Added a visible PTJ Firebase status pill so the dashboard shows whether trades loaded or are still hydrating.
- Added console logging: `PTJ loaded X trades from ...`.

## Validation
- Main module syntax check passed with `node --check`.
- `api/data.php` syntax check passed with `php -l`.
