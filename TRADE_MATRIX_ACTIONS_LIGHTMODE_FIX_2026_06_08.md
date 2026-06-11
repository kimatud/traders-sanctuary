# Trade Matrix Actions + Light Mode Fix

Updated file: `index.html`

## What changed
- Kept the 7-column Trade Matrix layout: Date, Trade, Direction, Performance, Risk, Status, Actions.
- Contained the Actions column so View, Edit, and Delete no longer hang outside the board.
- Rebalanced the desktop grid after removing Execution Notes so that the freed space is actually used.
- Converted action buttons into equal-width fitted tabs inside the Actions column.
- Added extra light-mode contrast rules for rows, headers, text, pills, metrics, risk blocks, and action buttons.
- Kept dark mode in the navy/obsidian family so row backgrounds match the site better.

## Not changed
- Firebase/auth/API logic
- Trade loading
- View/Edit/Delete handlers
- Export CSV handler
- PHP backend files

## Checks
- JavaScript module syntax check passed for `app_module.js`.
- PHP lint passed on bundled PHP files.
