# M119 — Original Finens design restoration checkpoint

Status: saved local milestone at Ahmed's requested stop boundary; no deployment or financial posting.

## Original versus previous local design

The sanitized reference's `includes/header.php`, `sidebar.php`, `navbar.php`, `footer.php`, `expenses.php`, `assets/css/style.css` and `assets/js/app.js` establish the original visual authority. The earlier local UI replaced this with a navy horizontal header, an always-open two-column form/card list, different fonts/spacing and no original navigation. That was a visual departure from the requested app.

The corrected local staff interface now follows the reference:

- 256px slate900 desktop sidebar with Finens brand, indigo active navigation and a white sticky company navbar.
- Slate50 background; original indigo, rose and emerald tokens;16px-radius panels, source-like spacing/type scale, compact filter row and tabular register.
- Original Font Awesome6.5.1 solid icons, self-hosted assets/licenses. The source declares Cairo but its Tailwind `font-sans` body overrides that declaration; browser comparison confirmed the actual system-sans stack, which the restored default matches. Cairo is retained as the original declared face, without a runtime Google Fonts dependency.
- Rose quick-entry dialog instead of the permanent side form; mobile dark navigation drawer and bottom bar with raised central add button. Native dialogs support Escape, focus return and explicit keyboard wrap.
- Arabic RTL and English LTR share the same renderer/style. Static previews now use this renderer too, rather than continuing to display the earlier design.

The sidebar only exposes available own-draft views: overview, expenses and advances/returns. Search, date/type filters and reset operate on the already-authorized own drafts. Advance saves redirect to the advance register. No source auth/database/treasury code was copied or executed. Domain money, store, native writer and posting gates remain unchanged.

## Honest scope differences

The app shows draft totals separated by original currency, own-draft counts and entity base currency. It does not invent a native safe balance, show a negative cash debit for a draft, promise that saving deducts cash, or expose unimplemented payroll/admin/payments. Category/tax mappings, native runtime acceptance, financial posting and other existing gates remain pending. Original field layout is adapted for mandatory original currency, office, source reference and dated FX evidence.

## Visual evidence

Evidence is outside source under `../m119-preview/finens-evidence/`; open `../m119-preview/finens-comparison.html` through the existing loopback static server for side-by-side pairs.

- `reference-{ar,en}-{desktop,mobile}.jpg`: static synthetic reconstruction of reference template classes, not execution of original Finens. English is a translation of the Arabic-only source template, not an upstream English screen. This is source-grounded qualitative comparison, not a pixel-perfect golden or proof of original live behavior.
- `restored-{ar,en}-{desktop,mobile}.jpg`: actual local employee interface with synthetic stored drafts.
- `dialog-en-mobile.jpg`, `drawer-en-mobile.jpg`: quick-entry and navigation states. A browser translation-widget overlay appears in one capture.
- `metrics.json`, `mobile-final-check.json`: matched actual viewports1600×1111 CSSpx and433×938 CSSpx. Browser override requested1440/390 but scaled them; no horizontal **page** overflow occurred. Sidebar measured256px on both desktop versions and is hidden on mobile. Background and computed system-sans world match.

Original mobile tables use horizontal scrolling. The restored register preserves that pattern: source documents/purpose can extend the table, and full amount/currency plus trailing office/status columns may require horizontal scrolling. Mobile row counters are hidden and type labels shortened to reduce this; there is no whole-page overflow. This remains a disclosed usability limitation, not a claim that every column fits the phone width.

## Functional verification and review

The browser submitted an over-precision amount `1.001`, received validation in the reopened dialog, and retained the same submission key and field values. Correcting it to `82.35 EGP` saved exactly one synthetic draft with success/redirect; the existing `700.10 EGP` draft stayed intact. Search by the synthetic receipt reference returned only the matching row. Mobile drawer/quick-add, conditional return-reference requirements, Escape/focus return and keyboard wrapping were verified.

Independent code review reproduced a new malformed-filter rendering defect: controller400 returned before form defaults existed, producing warnings/empty retry key. A warning-as-error render regression reproduced it RED; initialization was moved before early returns; all14 controller scenarios pass GREEN. Independent visual review confirmed the source-like desktop shell and readable dialog/drawer, and identified mobile icon/column differences. The icon was restored; horizontal-table visibility remains documented above.

Verified total: **68 scenarios** (16 domain/store +13 native drafts +14 employee controller +9 synthetic HTTP +8 native identity +5 capabilities +3 provenance).20 scoped PHP files pass syntax checks; local JS passes `node --check`; diff whitespace checks pass. HTTP tests now scope POST fields to the draft form, exercise malformed-filter rendering and verify binary local icon-font delivery/asset allowlisting. No full native HTTP acceptance is implied by these synthetic tests.

Work remains isolated on `codex/m119-office-expenses-local`. Preserve existing staged work; no commits, pushes, production/security changes or deployment were made.
