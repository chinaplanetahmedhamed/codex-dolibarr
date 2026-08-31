# M061-UI01 — Section email action contrast

## Authority

- Approved by Ahmed Hamed on 2026-08-25: `fixe the font color to be readable , make it like a button`.
- Scope: local Customer Center source correction and proportional local verification only.
- Staging deployment, production access, real email and business-data mutation are not authorized.

## Problem

The staff-only section email action was styled as a pale control, but the Admin ERP shell's more specific generic link rule overrode its text color. On the dark Customer Center hero, the result looked like a low-contrast text link rather than a clear action button.

## Exact correction

- Add Dolibarr's standard `button` class to the financial statement, invoice and project email actions.
- Scope the action style to the Customer Center page header and enforce a gold background with dark navy text so the Admin ERP link rule cannot override it.
- Preserve the existing permission, URL, recipient, preview, send and Agenda behavior.
- Keep a minimum 44px action height, a visible keyboard focus ring, hover/focus feedback and full-width phone behavior below 700px.
- Extend the focused source contract to cover all three button classes, enforced contrast and keyboard focus visibility.

## Acceptance

- PHP lint and the focused section-email, responsive and service tests must pass.
- Desktop and 390x844 local visual checks must confirm the intended colors and button shape with no page-wide overflow.
- No email may be sent and no staging or production resource may be changed.

## Completion evidence

Recorded in `docs/REPORTS/M061_UI01_COMPLETION_REPORT.md`.
