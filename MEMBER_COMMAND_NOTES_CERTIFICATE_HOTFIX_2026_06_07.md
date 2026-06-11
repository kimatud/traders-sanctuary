# Member Command, Notes, and Certificate Hotfix — 2026-06-07

## Fixed

### 1. Member Command Center navlinks spacing
- Added a final desktop override so command links stay compact, left-aligned, and close together.
- Mobile still stacks the links full-width for tap comfort.

### 2. Certificate preview too large
- Certificate preview is now capped to a small card.
- Desktop preview max width is about 360px with a contained image.
- Mobile preview remains responsive but no longer fills the whole screen.

### 3. Certificate submit says no image selected
- Certificate submit now reads the selected file from React state and also falls back to the real DOM file input.
- The file input accepts PNG, JPG, JPEG, and WEBP.
- Submit uses `FormData.append('certificate', selectedFile, filename)` so the PHP endpoint receives the correct `$_FILES['certificate']` item.

### 4. Notes not loading upon login
- Trading notes now hydrate immediately from local cache, then refresh from backend.
- Added retry hydration at 0.9s, 2.5s, and 5.5s after login to handle slow session/Firebase sync.
- Successful remote loads are cached for faster next-login display.

### 5. Note update turns created date invalid
- Edit now preserves the original `createdAt` value.
- Note rows are normalized before rendering.
- Dates now use safe formatting and display `N/A` instead of `Invalid Date` if bad data exists.

## Validation
- Inline JavaScript scripts parsed successfully with `node --check`.
- All PHP files passed `php -l` syntax checks.
