# Study Material Description Fix — 2026-06-11

## Fixed
- Study material cards now prefer the description submitted during upload.
- The public listing endpoint normalizes description fields from uploaded metadata before falling back.
- Folder-scanned files now try to match uploaded metadata by `fileName`, `originalName`, and `fileUrl` basename.
- The exposed phrase `Existing study material from the study materials folder.` is not used as a public description.
- If no uploaded description exists, the UI now uses a clean generic resource message instead of exposing folder structure.

## Validation
- PHP syntax checked.
- JavaScript syntax checked from extracted inline scripts.
- ZIP integrity checked.
