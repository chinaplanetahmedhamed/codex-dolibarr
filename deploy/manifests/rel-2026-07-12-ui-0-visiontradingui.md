# REL-2026-07-12-UI-0-VISIONTRADINGUI

Status: `READY_FOR_STAGING`

Scope:

- Add the standalone `visiontradingui` Dolibarr module (ID `500300`).
- Add module-owned design tokens, accessible components and dashboard assets.
- Add a staging-only environment banner guarded by `VISIONTRADING_ENVIRONMENT=staging`.
- Add a standard admin setup page for the default-off dashboard feature flag.

Safety:

- No Dolibarr core changes.
- Module, right and feature flag are off by default.
- No database migration.
- No remote assets, fonts, network calls or copied reference artwork.
- Production deployment is not authorized.

Staging acceptance gates:

1. Module ID collision check passes.
2. PHP lint, JavaScript syntax and `test_visiontradingui.php` pass.
3. Module activates through the standard Dolibarr mechanism.
4. `VISIONTRADINGUI_ENABLE_DASHBOARD` is enabled on isolated staging only.
5. `VISIONTRADING_ENVIRONMENT=staging` produces a visible environment banner.
6. Existing Customer Center, Logistics, CPKanban and Kontava pages remain HTTP 200.
7. Dark/light/system, high contrast, reduced motion, RTL/LTR and 390px checks pass.
8. Customer views expose no supplier cost, margin, internal notes or cross-customer data.

Rollback:

- Disable `VISIONTRADINGUI_ENABLE_DASHBOARD`.
- Disable the `VisionTradingUI` module through Dolibarr.
- Restore/remove only `custom/visiontradingui`; no schema rollback is required.
