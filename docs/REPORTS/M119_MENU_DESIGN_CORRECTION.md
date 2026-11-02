# M119 menu and notice correction — 2026-11-03

Ahmed explicitly requested removal of the repeated notice and a complete menu/dropdown design check. This is a bounded UI correction, with no accounting, membership, data, schema or permission changes.

Actual native employee-modal inspection reproduced a235px control with a300px popup extending beyond the dialog. Dolibarr desktop CSS applies min-width300px with !important to opened Select2 lists. The module override must defeat that specific native rule while keeping the real Select2 width, values, labels and events. The fix is scoped to this module, including legacy, company users, workspace filters and modal controls.

The repeated page-level draft/payment explainer is removed from the six section layouts. Contextual financial disabled-state, opening-reconciliation and confirmation messages are retained at their relevant actions. User-entered notes are unchanged.

Acceptance pending: authenticated Arabic/English Egypt/China menus, desktop/phone and modal/filter popups; native keyboard/search and disabled states; final backup-ERP release and independent verification. Customer Center/Mobile App not applicable. Production excluded.
