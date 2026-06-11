# Live Test Checklist Before Upgrades

Use this after deploying the pre-upgrade package.

## Browser hard refresh

1. Open the site.
2. Press Ctrl + F5 once.
3. Open DevTools > Console.
4. Confirm there is no `Unexpected token`, `RefreshCw is not defined`, or endless red 500-loop.

## Auth and dashboard

- Log in with your admin email.
- The dashboard should appear immediately after Firebase login.
- Backend/session sync may continue quietly, but the page should remain clickable.
- Log out and log back in once.

## Admin role

- Confirm admin tools are visible.
- Open `/api/data.php?action=admin_firestore_diagnostics` while logged in as admin.
- If it fails, repair Firestore/backend role sync before upgrades.

## Data loading

Check these sections:

- PTJ trades and analytics
- Study materials
- Announcements
- Testimonials
- Header notifications
- Trading notes
- Certificate requests

No section should stay at `0` forever unless the database is genuinely empty.

## Mobile

Test in responsive/mobile view:

- Manage accounts must scroll.
- Log new trade must scroll.
- Partial TP fields must be reachable.
- Floating buttons should not cover form save buttons.
- Data table pagination should remain horizontal.

## Upgrade decision

If all above pass, the site is ready for upgrades. If any fail, fix that area first.
