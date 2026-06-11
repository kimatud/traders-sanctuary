# Screenshot-confirmed Member Dashboard + Certificate Hotfix — 2026-06-07

This patch targets the issues visible in the screenshots:

1. Member Command Center links
   - Forces the member command actions to stay compact and grouped on desktop.
   - Prevents individual action pills from stretching/scattering across the hero.
   - Keeps mobile layout stacked and full-width.

2. Certificate upload error
   - Removed duplicate multipart file submission. The same certificate was being appended as both `certificate` and `certificate_file`, which doubled the upload payload and could exceed the server post limit.
   - The backend still accepts fallback file names, but the frontend now sends only one actual file.
   - Certificate images are now compressed more aggressively before upload.
   - Oversized GIFs and uncompressible images are rejected client-side with a clearer message.

3. Certificate preview size
   - Member preview, admin preview, and pending request previews are capped to small clean cards.
   - Images use `object-fit: contain` so certificates remain readable without taking over the page.

4. Server upload readiness
   - Added safe mod_php upload directives to `.htaccess`.
   - Existing `.user.ini` remains for PHP-FPM style hosting.

5. Testimonials carousel controls
   - The previous dots were already replaced in the prior build with arrows, progress, and count controls. This build keeps that design.

Validation:
- Hotfix runtime script syntax checked with Node.
- PHP certificate endpoints linted successfully.
