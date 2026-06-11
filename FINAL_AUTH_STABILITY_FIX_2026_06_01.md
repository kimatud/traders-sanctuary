# Final auth stability fix - 2026-06-01

This build streamlines sign-in/sign-up and prevents the previous fast-client session from opening the wrong dashboard state.

## Fixed
- Sign-up no longer opens the dashboard before agreement acceptance.
- Agreement-required users are always routed to `/agreement` until accepted.
- Immediate post-login/post-signup forced refresh was removed to stop bounce/logout loops.
- Login now waits for the verified backend session before opening the dashboard, so admin users keep admin role instead of briefly entering as member.
- Old cached `client_fast` member sessions are invalidated using a new session cache version.
- Protected pages do not render dashboard content for missing users while the session is being restored.
- Login/sign-up still preserve existing-user reset-password messaging.

## Note
Full live confirmation still depends on Firebase Auth, Firestore rules, cookies and hosting behavior on the deployed domain.
