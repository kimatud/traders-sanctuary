# PTJ Strict User Isolation + Auth Small-Laptop Fix — 2026-06-10

## What was fixed

### 1. PTJ showing same trades for all users
This patch changes PTJ from permissive ownership matching to strict ownership matching.

A journal/trade row is now visible only when one of these is true:
- `userId`, `uid`, `ownerId`, `firebaseUid`, `createdBy`, or `memberUid` exactly matches the signed-in Firebase UID.
- `email`, `userEmail`, `memberEmail`, or `createdByEmail` exactly matches the signed-in email.
- The Firestore document path proves it came from `/users/{currentUid}/...`, `/user_data/{currentUid}/...`, or `/members/{currentUid}/...`.

Markerless local/cache-only PTJ rows are no longer trusted. This prevents old/stale browser or backend cache rows from one account appearing in another user's journal.

### 2. PTJ save ownership stamping
Every saved PTJ row is now force-stamped on the backend with:
- `userId`
- `uid`
- `userEmail`

The frontend also stamps the same fields before saving.

### 3. Browser preload cache safety
The sign-in preload no longer publishes cached journal rows before ownership is verified. This avoids showing someone else's old cached trades while the backend is still loading.

### 4. Small laptop auth page
For screens between 861px and 1099px, the auth page now uses a compact desktop two-column layout instead of falling into the long mobile-style scroll layout.

## Validation
- PHP syntax check passed.
- JavaScript syntax check passed.
- CSS brace check passed.

## Important deployment note
After uploading this build, users who previously saw leaked/stale PTJ rows may need one hard refresh once. The new code will stop displaying unowned/markerless rows.
