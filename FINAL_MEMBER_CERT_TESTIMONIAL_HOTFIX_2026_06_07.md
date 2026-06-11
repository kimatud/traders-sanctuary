# Final Member Command, Certificate, and Testimonials Hotfix — 2026-06-07

## Fixed

### 1. Member Command Center navlinks
- Added final desktop/mobile CSS override scoped to `.command-center-hero`.
- Member role links no longer stretch across the hero or appear scattered.
- Desktop keeps links compact and grouped.
- Mobile stacks links full-width for clean tapping.

### 2. Certificate preview too large
- Member certificate preview is capped to a compact card.
- Admin manual upload preview is capped to a compact card.
- Admin pending request thumbnails are also capped.
- Images use `object-fit: contain` so certificates remain readable without stretching the page.

### 3. Certificate submit error: “Please choose your certificate image”
- Added `name="certificate"` to the member and admin file inputs.
- Client now sends both `certificate` and `certificate_file` multipart fields for compatibility.
- Client compresses large certificate images before upload to reduce PHP `post_max_size` failures.
- PHP endpoints now accept `certificate`, `certificate_file`, or any uploaded file fallback.
- PHP error message now explains if the image did not reach the server because of upload/post limits.
- Added `.user.ini` with safer upload/post limits for shared hosting.

### 4. Certificate approval reliability
- Approval endpoint remains authenticated/admin-only.
- Upload requests now save consistently into the pending folder and JSON queue.
- Admin pending previews are smaller and easier to scan before approving/rejecting.

### 5. Landing page testimonials controls
- Replaced one-dot-per-testimonial controls with compact previous/next arrows, progress bar, and count.
- This avoids ugly dot overflow when there are many testimonials.

## Validation
- Main inline JavaScript parsed successfully with Node.
- All PHP files passed `php -l` syntax validation.

## Deployment note
Upload this package over the live site. Keep the live `api/env.local.php` in place if your server already has one, because this package should not be used to overwrite private production secrets.
