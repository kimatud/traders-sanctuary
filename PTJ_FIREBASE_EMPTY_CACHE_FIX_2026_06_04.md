# PTJ Firebase Empty Cache Fix — 2026-06-04

This build fixes the Premium Trading Journal rendering all zero stats after deployment.

Root cause:
- `list_journal` returned the local `/api/_data` cache immediately when `TS_FAST_USER_READS` was enabled.
- After redeploy, that local cache can be empty even though the user's real PTJ trades are still in Firebase.
- The frontend also tried browser Firestore REST reads first; Firestore rules can deny those reads with 403, so PTJ fell through to an empty state.

Fixes:
- `api/data.php?action=list_journal` now only uses local-fast mode when local rows actually exist.
- If local cache is empty, the endpoint must attempt Firebase before returning an empty result.
- `api/data.php?action=list_trading_accounts` now follows the same rule for accounts.
- PTJ frontend now requests `fresh=1` from the backend first for journal entries/accounts.
- Existing browser-local cached PTJ rows still show immediately while Firebase refreshes.
- Browser testimonial direct Firestore probing was reduced to avoid noisy 403 console spam; backend remains the authority.

Validation:
- PHP syntax passed for `api/data.php`.
- Inline JavaScript syntax passed with `node --check`.
