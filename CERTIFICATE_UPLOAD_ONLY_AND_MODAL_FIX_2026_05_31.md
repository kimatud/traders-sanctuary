# Certificate upload-only and modal fix — 2026-05-31

Changes included:
- Removed member-facing certificate image gallery display from the dashboard.
- Kept member certificate submission for admin approval.
- Improved the certificate submission card and file-picker layout.
- Improved admin certificate manual upload labels and spacing.
- Adjusted COOP header to reduce Firebase popup `window.closed` warnings.
- Rendered the study material/PDF modal through a body portal with higher z-index so it opens in the visible viewport instead of requiring page scrolling.
- Added final responsive CSS for certificate upload controls and modal behavior.

Validation:
- All PHP files lint clean.
- Main inline module syntax check passed with Node.
