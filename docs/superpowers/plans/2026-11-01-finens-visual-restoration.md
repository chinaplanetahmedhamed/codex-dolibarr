# Finens visual restoration implementation plan

> For agentic workers: execute inline with superpowers:executing-plans; use independent code/visual review. Preserve all staged work.

**Goal:** Restore the supplied Finens design, rather than invent a new visual identity, while retaining exact scoped draft-only behavior.

**Architecture:** Keep domain, store and native writer untouched. Rebuild the employee page/CSS from reference header/sidebar/navbar/expense register/modal patterns. Add local UI-only JavaScript for accessible drawers/dialogs, and allowlisted GET presentation filters in the controller. Reuse the same renderer for static previews. Vendor Cairo and Font Awesome assets with licenses, no runtime CDNs.

**Spec:** Ahmed's explicit correction: the design must remain the original simple Finens experience. Visual authority is the sanitized pinned reference's includes/header.php, sidebar.php, navbar.php, footer.php, expenses.php and assets/css/style.css. Arabic is original; English mirrors the same world. No production data or original application execution.

**Tech stack:** Existing PHP/BCMath, local CSS/JS/fonts, loopback synthetic HTTP fixture, browser visual checks.

## Tasks

- [x] Capture a static source-template reconstruction with original Tailwind/style/font assets and synthetic content, plus the current local screen. Never run original auth/database/seeding code.
- [x] Restore dark256px sidebar, white navbar, actual rendered source typography (system-sans; declared Cairo locally available), indigo/rose/emerald palette,16px cards, compact expense table, filter row, quick-action modal, mobile drawer and bottom navigation. Keep unavailable native balances/finance actions out of displayed claims.
- [x] Preserve current fields, decimal strings, office/identity checks, CSRF/retries/error retention. Implement functional own-record filters/navigation and accessible modal keyboard behavior. Add meaningful filter/navigation regressions; no visual unit tests that merely assert CSS strings.
- [x] Serve assets only from an explicit fixture allowlist; retain strict self-only CSP with local script/font directives. Update static previews to the shared renderer, explicitly read-only.
- [x] Verify Arabic/English desktop/mobile, reference versus corrected screenshots, form open/save, search/filter behavior, drawer/Escape/focus, overflow and original-currency formatting. Run68 scenarios and lint; independent code/visual review; preserve isolated staging. Full mobile amount/currency columns can require horizontal scrolling; disclosed at requested stop milestone.

## Resumed mobile usability

Keep the same Finens table and shell. On phones, freeze the amount column at the logical trailing edge, allow the currency to wrap without breaking/truncating the decimal value, and provide localized controls for the remaining horizontal details. Desktop column order and all accounting/context logic stay intact.

- [x] Reproduce clipped original money before editing; prepare normal/maximum-value/mixed-currency synthetic visual fixtures.
- [x] Add mobile-only frozen money column plus accessible horizontal-scroll controls and overflow state; keep amounts as exact strings. Fix cached asset loading with content-hash URLs.
- [x] Verify complete value/currency bounds at320/375/433 CSSpx, both directions and both scroll ends, expanded quote evidence, keyboard controls, empty state, resize without reload and desktop regression.
- [x] Run69 regression scenarios,20 PHP syntax checks, JS/diff checks and independent review; save screenshots/report and preserve staged work without commit/push/deploy.
