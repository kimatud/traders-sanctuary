# Certificate Upload + Approval Fix — 2026-05-31

Implemented a proper funded certificate submission and approval flow.

## Changes
- Reworked member certificate gallery so certificate images fit with `object-fit: contain` inside a certificate-ratio card instead of being cropped/distorted.
- Added a member-side certificate upload form inside the Certificates section.
- Added `submit_certificate_request.php` for authenticated members to submit certificate images.
- Added admin email notification when a certificate is submitted for approval.
- Added `list_certificate_requests.php` for admin pending certificate review.
- Added `review_certificate_request.php` for admin approve/reject workflow.
- Approved certificates are moved from `funded certificates/pending/` into `funded certificates/` and added to `certificates.json`.
- Rejected certificate files are removed from the pending folder.
- Updated Admin > Certificates with pending submissions, preview image, member name/email, approve, and reject buttons.
- Kept existing manual admin certificate upload/delete functionality.

## Folder requirements
- `funded certificates/`
- `funded certificates/pending/`
- `api/_data/`

## Validation
- PHP lint passed.
- Main module JavaScript syntax check passed.
