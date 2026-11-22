# M141 — General Board staff membership

Owner: Ahmed Hamed. Implementation: Codex. Date: 2026-11-23 Asia/Shanghai.
Branch: `codex/general-board-staff`.

Scope: the existing Operations General Board member/card sharing picker and server validation. Use native active, same-entity, internal users marked Employee; reject customer/supplier/external and non-employee accounts. Preserve customer/supplier workspace sharing, existing grants, native rights, CSRF, optimistic versions and last-manager protection. Existing excluded members must remain removable without granting access again.

Routes: `custom/chinaplanetoperations/tasks/boards.php` and `boards_api.php`; service: `InternalTaskBoardsService`; records: native `user`, existing `cpo_board_member` and `cpo_card_grant`. No schema, module activation, financial changes or production deployment authorized by this request.

Plan: reproduce with synthetic SQL fixtures; filter the existing picker and validate writes in the owning service; explain staff-only selection in the existing AR/EN dialog; verify positive/negative and removal/persistence cases. Record actual results below.

Status: implemented and verified locally; not published or deployed to backup/production.

## Verified 2026-11-23

- Reproduced missing employee filtering with the real service SQL: initial synthetic test failed because non-employee internal accounts were offered.
- Added the native `employee=1` predicate only for General Boards, alongside existing exact entity, active status and absent third-party linkage checks. Customer/supplier workspace internal-user eligibility remains unchanged. The same eligibility applies to Board and individual-card grants.
- Native manager/edit rights, server-side eligibility, optimistic Board versions, transaction rollback and last-manager guard remain effective. Stored grants for inactive/former/external users remain explicitly removable; no existing memberships were automatically removed or user classifications changed.
- `deploy/tests/test_m141_general_board_staff.php`: **53 SQLite** permission/persistence checks passed. **49 MySQL 5.7 session-temporary-table** cases passed using the existing isolated Mac connection. The customer-project compatibility subsection runs on SQLite because MySQL 5.7 cannot reopen one temporary table in nested queries. No app/business tables were mutated.
- Initial separate-database test preparation was denied by the lab account's existing privileges. No grant/root workaround was attempted; the supported session-temporary-table approach succeeded instead.
- Existing local lab administrator authenticated via native login. General Board API staff-only metadata and translated EN/AR labels passed with required session tokens. An initial tokenless GET returned 403; the normal token-bearing request passed. No real membership/form changes were submitted.
- Actual current Boards JS/CSS rendered in a synthetic-only loopback browser fixture: English and Arabic sharing dialogs show staff wording, staff options and legacy member removal; Arabic 390×844 layout showed the complete form and Save/Cancel without clipping. This is UI fixture evidence, separate from native authenticated HTTP/service evidence. Native Chrome was in active user use and was left undisturbed.
- PHP lint on both changed PHP application files and the regression test, JS syntax and `git diff --check` passed.
- The five application/translation files were copied to the isolated Mac runtime only after each predecessor matched Git HEAD. Original files and hashes are private under `/Users/mac/.codex/m141-general-board-staff-20261123/`. Running Mac remains `http://127.0.0.1:18121/`; canonical implementation is this repository.

## Existing baseline test failures

The following old suites were run on both the candidate and the immutable captured live code. They have the same failures in both environments; this change does not claim a green legacy suite:

- `test_m058_source_contract.php`: module-version expectation, exact-entity source expectation, active-relationship/polyglot document validation expectation.
- `test_m059_source_contract.php`: direct Team Tasks menu/module-version expectation.
- `test_m058_ui_contract.php`: old embedded drawer conflict/cover/close source expectation.

## Operational limit

“Staff” follows the existing native Dolibarr Employee flag, not an inferred name or a new staff registry. If a technical/test/customer identity is incorrectly marked as an active internal employee, its native user classification needs a separate explicit review. This change does not silently reclassify or deactivate accounts. No schema, financial record, native right, customer portal, backup-ERP or production mutation was made.


## Release authorization — 2026-11-23

Ahmed explicitly requested “push to live and sync to backup as well”. This authorizes the tested five-file Operations overlay on `vision_erp` / `vision-os_dolibarr_custom` and `erp-staging-dolibarr-1` / `erp-staging_erp-staging_custom`, including current identity/predecessor checks, scoped source backup, code-only apply, functional verification and data-preserving rollback. Database/configuration/rights/user classification, services/proxy and unrelated source remain outside the release. Earlier local-only status is historical; remote release results are pending below.
