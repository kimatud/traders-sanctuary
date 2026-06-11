# Traders Sanctuary — Night Upgrade Quality Pass

Date: 2026-06-07
Base build: theme contrast cleanup build

## Upgrade focus
This package is a stability-safe polish build prepared before tomorrow's larger upgrades. It avoids changing the working backend flow and focuses on visible quality, mobile behavior, and theme consistency.

## Improved areas

1. Landing page polish
- Stronger premium card depth.
- Cleaner testimonial card spacing.
- Better testimonial controls for many testimonials.
- Improved section title contrast in both light and dark mode.
- Hover polish on feature cards.

2. Member dashboard
- Member Command Center actions are forced into a compact grouped layout for member, premium, and admin roles.
- Light mode Analytics Dashboard button remains visible.
- Mobile command buttons stack cleanly.
- Status cards keep consistent spacing.

3. Certificates
- Certificate previews and submitted certificate images are capped to small clean preview areas.
- Submitted certificate cards inherit the active theme.
- Certificate images are lazy-loaded and decoded asynchronously.

4. Admin dashboard
- Admin cards, tabs, forms, and selected states receive stronger theme-safe contrast.
- Hover behavior is more polished without changing data logic.

5. Premium Trading Journal and analytics
- Tables receive app-like row spacing and safer responsive overflow handling.
- Table headers and rows are more readable in both themes.
- Form controls are more consistent.

6. General quality
- Images are lazy-loaded where possible.
- Wide tables get responsive scrolling on smaller screens.
- The upgrade is mostly CSS/runtime polish, so it does not disturb working login, payment, notes, or certificate backend logic.

## Testing checklist for tomorrow
- Hard refresh after upload.
- Check landing page in light and dark mode.
- Log in as member and confirm command links stay grouped.
- Log in as premium/admin and confirm extra command links are grouped.
- Check admin dashboard selected tab visibility in dark mode.
- Upload/preview certificate and confirm preview remains small.
- Open PTJ/analytics and confirm tables remain readable.
