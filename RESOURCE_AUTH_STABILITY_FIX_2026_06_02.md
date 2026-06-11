# Resource/Auth Stability Fix - 2026-06-02

This build fixes the resource-loading timeouts and dashboard bounce caused by PHP waiting on slow Firebase/Firestore server calls during dashboard boot.

Key changes:
- Auth session creation now builds the verified PHP session from Firebase token claims + local backend profile first, instead of waiting on multiple Firestore reads.
- Admin role is still protected by configured admin email guard.
- Dashboard resources use fast local/cache reads first instead of blocking on Firestore server requests.
- Study materials endpoint no longer blocks on session restoration.
- Announcement/testimonial dashboard reads return immediately from local cache and remain usable if Firestore is slow.
- Session restore keeps the last verified cached user instead of dropping to Loading Dashboard when PHP/Firebase bridge is delayed.
- Server outbound timeout defaults reduced to avoid long hanging requests.

Notes:
- The Tailwind CDN console message is a production recommendation/warning, not the cause of the dashboard timeout. Replacing Tailwind CDN with a compiled local CSS build should be handled in the upcoming upgrade phase.
