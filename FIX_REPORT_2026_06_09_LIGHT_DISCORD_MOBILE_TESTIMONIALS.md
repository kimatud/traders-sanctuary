# Fix Report — Light Discord Card + Mobile Testimonials

Date: 2026-06-09

## Fixed

1. **Premium Discord / Join Premium card in light mode**
   - Added a strict light-mode-only CSS skin for `.user-dashboard-callout-premium`.
   - Improved contrast for headings, body copy, warning text, and the Discord button.
   - Preserved the existing dark-mode design by avoiding any `html.dark` / `body.dark` overrides.

2. **Landing page testimonials on mobile**
   - Added mobile viewport guards to prevent horizontal overflow.
   - Forced testimonial grid, carousel frame, slides, and cards to respect `max-width: 100%`.
   - Reduced mobile card padding, avatar size, quote sizing, and controls spacing.
   - Changed mobile stats from a tall stacked layout into compact fitted cards.
   - Added smaller breakpoints for very narrow screens.

## Files changed

- `index.html`

## Patch ID

- `ts-light-premium-discord-and-mobile-testimonials-final-2026-06-09`
