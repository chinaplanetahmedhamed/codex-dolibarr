# M119 standalone source release — 2026-11-02

Ahmed requested “push the new module”; target remains the established backup-erp trial. Production and financial posting are excluded. This checkpoint supersedes earlier uncommitted/local-only statements only for source publication and verified release preparation.

## Scope

Standalone `custom/chinaplanetofficeexpenses/`, native login/default-denied rights, audited branch assignment and fresh native permissions, Egypt/China and own/branch record isolation, private receipts, legacy route/class compatibility and original-style local design previews. Native accounts 5–8 and groups 4–7 already exist; [activation plan](M119_STAFF_BRANCH_ACTIVATION_PLAN.json) records the exact four intended roles. Real finance grants/memberships are not activated.

Dolibarr core is unchanged. Existing draft/receipt schema identities and storage locations are retained; the access schema is additive and dormant. The module lifecycle does not install/drop business tables. Admin ERP: implemented source, runtime acceptance pending. Customer Center: not applicable, external users denied. Mobile App: no API added; responsive web evidence is in the earlier implementation report.

## Package and checks

[Standalone builder](../../deploy/releases/m119/build_standalone_artifact.py) uses a fixed dependency-closed allowlist for descriptor, translations, routes, classes, assets and all three schema manifests/SQL sources. Immutable full Git commit only; symlinks and missing files are rejected before output. Deterministic archive plus per-file checksum manifest; no credentials, database snapshots, preview/reference/test tools or financial native bridge. Metadata explicitly marks code-only, staff activation false, installer absent and posting false.

The historical builder stays bound by its old default contract and rejects relocation shims. Historical artifact tests use source `8b14b9e9c609ed296cb18368f576bbf86fffadbb`; Actions fetches that history. A separate standalone test verifies local dependencies/schema hashes, deterministic bytes, exact archive contents and missing/symlink migration refusal. Actions also builds and retains the real current-commit package only after matching-runtime checks pass.

Fresh local verification: 21 safe suites passed, zero failed, including 21 staff-access scenarios, 13 synthetic HTTP scenarios, 2 new standalone-package tests, 5 historical artifact cases, 9 preparation cases and 29 historical-helper cases. PHP lint: 73 files passed. JavaScript syntax, Python/JSON parsing and diff whitespace passed. Local PHP 8.4.7/native library 24.0.1; the local Docker daemon is absent. Matching PHP 8.2.32/MariaDB 10.6.27/Linux checks run through the existing `m119-check` Actions option. Five real-MariaDB cases were added for additive access migration, fresh persisted audit/scoping, stale revision/revoke, audit rollback and lost commit acknowledgement; their results must come from Actions, not this Mac.

## Staging boundary and rollback

The installed restricted helper SHA `845e497f2828df927ce14e5431d180a747c62bae264fbcee244183696cb967d9` accepts only the historical Operations pilot/source/package. It cannot install the standalone descriptor, access schema or new role activation. The old `m119-r01` deploy action must not be used for this release. No new helper, schema, finance grant, module/configuration activation or remote application write was performed in this release preparation.

Remaining staging work: a matching narrowly scoped installer with exact package pins, backup/restore preflight and migration/module/menu/role readback, then authenticated four-user positive/negative branch/owner/CSRF/receipt and Arabic/English desktop/phone acceptance. Usable staff credentials still require native administrator delivery; no passwords were put in source/chat or emailed. Account/group creation and code publication alone are not isolation acceptance.

Source rollback is a new revert commit of the scoped change. Future runtime rollback must first disable staff mode and restore the reviewed pilot code/config/menu, preserving all accounts, operational records, private receipts, additive schema and audit history. Do not delete financial/business data for rollback.

Branch: `codex/m119-office-expenses-local`. Commit/run identifiers are recorded in the final handoff and subsequent check evidence.

## Confirmed publication and matching CI

Source commit `1b88b8869e700ad54a441124e980105ca6313cfa` was pushed to the existing branch and verified with remote `ls-remote`. [Actions 41338709389](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41338709389) completed successfully for exactly that commit. PHP 8.2.32/native 24.0.1/MariaDB 10.6.27 checks passed: 23 real MariaDB cases, including all five new access-storage failure/recovery cases; 7 actual Linux/www-data filesystem cases; 21 staff-access scenarios; complete CLI/HTTP suites with `FAILED_SUITES=0`. Two credential-free identity auth/session cases remain intentionally skipped and do not establish real staff login acceptance.

The complete 46-entry standalone archive SHA256 is `3f3254ae3f1fb4bd186b8d37afb7f3b7e31826291a18dad24599dcabf417fd1e`. Independent Mac and GitHub runner builds produced that same digest. [Download code package and manifest](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41338709389/artifacts/13096675548) (Actions retention 14 days). Local copy: `../m119-staging-release/standalone-1b88b88/` relative to this repository root. This is a code archive, not a native module-install wizard or activation script.

After publication, a fresh same-origin authenticated browser GET of the standalone users route still returned 404; the native administrator users page remained authenticated. The available Playwright request context transport did not support its cookies method, so readback used a same-origin browser fetch instead. No deployment attempted, remote code or database changed, or permission/helper boundary bypassed. The matching installer/activation remains the concrete blocker; the current requested source push and matching checks are complete.
