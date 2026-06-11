# Traders Sanctuary - Codes Ready Bottom Controls Stability Fix

Date: 2026-06-11

## Fixes included

1. Mobile bottom spacer removed
- Removed the artificial bottom padding previously reserved for floating controls.
- The RTT and quick-action buttons now float over the app instead of creating a blank bottom band.
- Old PTJ/mobile FAB stacks are hidden/removed so the final two-button stack is the only active mobile quick action set.

2. Return-to-top button stabilized
- Replaced display toggling with a stable class-based visibility controller.
- Removed the forced always-visible RTT behavior that caused blinking/flickering.
- RTT now appears only after scrolling down and stays on the bottom-left.
- RTT scrolls the main window plus dashboard/modal scroll containers back to the top.

3. Mobile floating buttons retained
- Mobile signed-in users still get two floating buttons on the right:
  - Blue analytics/network-bars button -> View Analytics Dashboard
  - Green plus button -> Log New Trade
- Buttons no longer overlap the RTT button.

4. Analytics dashboard close button fixed on mobile
- The analytics close button is now placed in the top-right on mobile, matching the rest of the modal/page close pattern.
- Removed the bottom-floating close placement from the analytics dashboard.

## Validation
- JavaScript syntax check: passed
- PHP syntax check: passed
- CSS brace check: passed
- ZIP integrity: passed after packaging
