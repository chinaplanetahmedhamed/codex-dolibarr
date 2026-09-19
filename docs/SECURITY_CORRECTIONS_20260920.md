# Approved bounded correction — 2026-09-20

Ahmed authorized: “ok do the corrections and test, then push to live when pass”.

Scope: ERP-01 Kanban move POST and fail-closed session CSRF checks; ERP-02 existing active same-entity destination, entity-scoped card update, unchanged state/log on invalid moves or failed audit. Existing internal staff permissions remain unchanged. No core, database schema, module configuration, business record migration, WordPress, or mobile contract changes. Customer Center remains read-only for moves.

Source baseline aaeb5d1, carrying accepted M085 application 7d0ca96. Production/staging Kanban fingerprints must match inspected source before deploy. Deploy only the two runtime files through a fixed scoped helper with checksum, backup, PHP lint, health, rollback, and authenticated positive/negative acceptance. No generic custom-volume replacement.

Parity: internal desktop/phone board affected; Customer Center write denial tested; mobile API unchanged. Tests/review/deployment pending.

## Local acceptance

Independent Security and QA reviewers PASS source and targeted regressions. React lint, full server tests, UI tests and build pass. Actual local editor login/reload, content save, forced logout503 visible error, retry and logout/reload pass at1440px and390px. Native Dolibarr23.0.3 synthetic staff drag/drop persists after reload; GET, missing/wrong CSRF, inactive/missing/foreign columns, no-access staff and customer writes denied.44 domain assertions and32 portal-hardening assertions pass. Evidence/screenshots reside privately at wordpress/outputs/security-assessment-2026-09-20/corrections. Staging/production acceptance remains pending.
