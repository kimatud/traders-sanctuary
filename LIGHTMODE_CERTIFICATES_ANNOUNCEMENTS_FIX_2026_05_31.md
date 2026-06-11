# Light Mode, Certificates, and Announcements Fix - 2026-05-31

Applied fixes:
- Stabilized the light-mode Member Command Center `Log New Trade` button so it stays clean, readable, and undistorted.
- Added stronger spacing between the Member Command Center hero and the following Premium Discord/dashboard cards.
- Redesigned the certificates section as a sleek certificate-only gallery with no card descriptions.
- Added formal certificate-sharing wording for funded members who are willing to share their certificates.
- Made announcement deletion update the admin list and client-side dashboard cache immediately.
- Added backend deleted-announcement tombstones so stale Firestore/cache rows do not reappear after deletion.
- Preserved Firestore-first announcement reads, with local cache only as fallback.

Validation:
- PHP lint passed.
- Inline JavaScript syntax check passed.
