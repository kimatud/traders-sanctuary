# Auth Sign-in/Sign-up 400 Fix — 2026-06-01

Fixed the sign-in/sign-up regression where Firebase/client auth failures could stop the flow before the backend session fallback had a chance to open the account.

Changes:
- Added resilient backend fallback for email/password sign-in.
- Added resilient backend fallback for email/password sign-up.
- If Firebase creates a new account but the PHP session bridge fails, the app now tries backend login to open the session instead of leaving the user stuck.
- Expected auth validation failures now return clean JSON without browser-hostile HTTP 400 responses.
- Kept session timeout and previous mobile/dashboard fixes intact.

Validation:
- PHP lint passed across all PHP files.
- Main module syntax check passed after import stripping.
