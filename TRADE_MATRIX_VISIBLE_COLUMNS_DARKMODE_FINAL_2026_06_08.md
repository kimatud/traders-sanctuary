# Trade Matrix Visible Columns + Dark Mode Palette Fix

Changed file: `index.html`

## What was fixed
- Removed the grey trade-row background from the desktop Trade Matrix.
- Replaced it with a darker premium navy/glass palette that matches the site dark mode.
- Forced the desktop Trade Matrix to fit inside the available page width instead of clipping the Actions column.
- Rebalanced all desktop columns using a fixed layout:
  - Date
  - Trade
  - Direction
  - Execution Notes
  - Performance
  - Risk Details
  - Status
  - Actions
- Made the Actions column compact with three visible icon buttons, so View/Edit/Delete no longer overflow out of the screen.
- Tightened long account names, long notes, R/P/L values, and risk details with ellipsis/clamping where needed.
- Preserved the existing React handlers for View, Edit, Delete, filtering, sorting, pagination, CSV export, Firebase loading, and account logic.

## Safety
This is a UI/layout override only. Backend files, Firebase/API logic, auth, upload handlers, and PHP data endpoints were not changed.
