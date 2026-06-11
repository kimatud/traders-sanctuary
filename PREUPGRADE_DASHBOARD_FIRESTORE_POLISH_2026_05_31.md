# Pre-upgrade dashboard / Firestore polish

- Restored testimonials to use Firestore as the primary source again, with local cache as fallback.
- Added fresh testimonial action aliases used by the frontend so old local-fast behavior does not mask Firestore data.
- Kept remote timeouts short so slow Firestore/network calls do not freeze the dashboard.
- Reworked the logged-in dashboard opening area into a stricter, lighter command section.
- Hid the heavy Activity Hub intro block so the useful sections start sooner.
- Re-applied a modern red logout button with confirmation and instant local logout behavior.
- Re-applied logo bounce on the actual image element so it remains visible even if container animation is overridden.
