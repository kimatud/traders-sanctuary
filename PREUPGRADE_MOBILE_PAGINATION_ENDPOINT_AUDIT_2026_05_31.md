# Pre-upgrade Mobile Pagination + Endpoint Audit — 2026-05-31

## Fixes added

- Forced mobile pagination controls to remain horizontal and scroll sideways when needed instead of stacking vertically.
- Added a Discord joined-state cleanup so the premium Discord reminder disappears after a member clicks a Discord join/access link.
- Kept the actual Premium Discord card available lower in the workspace.
- Added runtime pagination protection for dynamically rendered tables and admin dashboard sections.

## Static endpoint scan

All PHP endpoints referenced by the frontend exist in the package:

- /api/data.php
- /api/google_start_v4.php
- /api/login_v4.php
- /api/logout.php
- /api/me.php
- /api/register.php
- /api/reset_password.php
- /api/session_from_id_token.php
- delete_announcement_file.php
- upload_announcement_file.php
- delete_certificate.php
- delete_study_material.php
- get_study_materials.php
- list_certificate_requests.php
- list_certificates.php
- review_certificate_request.php
- submit_certificate_request.php
- upload_certificate.php
- upload_study_material.php
- upload_trade_screenshot.php

## Local smoke results

- Public/session check endpoint responds cleanly.
- Public certificate list endpoint responds cleanly.
- Protected endpoints return a clean JSON session response instead of fatal PHP/server errors when not logged in.
- PHP syntax lint passed.
- Inline JavaScript syntax check passed.
- Local image/CSS/script asset reference check passed.

## Live-server note

Logged-in button workflows that depend on Firebase Auth, Firestore permissions, email sending, or admin sessions still need a live-domain browser pass after upload because those depend on production credentials, cookies, domain restrictions, and Firestore rules.
