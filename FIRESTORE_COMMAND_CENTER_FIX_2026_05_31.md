# Firestore + Command Center Fix — 2026-05-31

Applied fixes:
- Announcements now read Firestore first and local JSON only as cache/fallback.
- Admin announcement saves return the saved item immediately and update the admin list optimistically.
- Firestore public reads now stop once the first populated namespace is found, improving speed while preserving legacy fallback paths.
- Testimonial status fresh-check no longer short-circuits to local cache when Firestore has the user's previous testimonial.
- Testimonial submission is now protected on the backend: one testimonial per user/email, returning a 409 if duplicate.
- Testimonial documents use a deterministic user-based id for future duplicate prevention.
- Dashboard alert count now loads from announcements instead of staying at zero.
- Member Command Center status cards use two columns to save space.
- Log New Trade action is clearer and routes/focuses users toward the PTJ trade logger.
- Added a visible certificates section instead of jumping to the hidden activity hub.
- Added spacing above the Premium Discord card.

Validation:
- PHP lint passed for all PHP files.
- Inline JavaScript syntax check passed.
- Required UI markers and backend duplicate-testimonial logic are present.
