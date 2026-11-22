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

## Released to backup and production — 2026-11-23 Asia/Shanghai

- Source `4ed67298f3d0a6ec6ed8c930c7a1ef1f4dcf9590`, [PR #63](https://github.com/chinaplanetahmedhamed/codex-dolibarr/pull/63). Five files deployed on backup first, then live. Exact target hashes match each other and the Mac/source overlay. This is M140 captured baseline plus the bounded M141 overlay.
- Both targets passed pinned identity/custom volume/proxy/config/predecessor checks, five-file source backup and archive readback, PHP lint, 49 native permission/persistence cases against temporary tables, native existing Board 3 employee picker comparison, external-actor denial, anonymous login requirement and HTTPS served-asset hashes. Independent verification repeated the checks after application. Native Dolibarr is 24.0.1.
- Live picker: five active internal employees from eight active internal users. Backup picker: one active internal employee, reflecting its intentionally isolated account state; no user activation/authentication or classification was copied. Native query IDs exactly matched the service response.
- Initial metadata expectation assumed a production Compose label. Fresh inspection confirmed the same pinned live container/image/custom volume and route, with no Compose label. A later strict equality guard caught Docker mount array ordering; all values/config/proxy were unchanged. Comparison now normalizes order while rejecting changed fields/paths.
- One production code application automatically restored all five predecessor files after the HTTPS guard encountered the predecessor at a previously cached fixed diagnostic URL (`CF-Cache-Status: HIT`). Recovery completed successfully. The corrected guard checks the actual new mtime-versioned URL emitted by `cpo_asset`; the same immutable app artifact then applied and independently verified. No cache/security setting was disabled or changed.
- Final controller: `/root/m141-release-r4.py`, SHA-256 `512bb78ec332b7924bdc1996b16ee1bc7b93fe559b0778e9fe72ea3015d72ae2`. Artifact: `229979a962af27eeb7934aa36e1c321014a2a1cc6951f66aa770553ce8629f2a`. [Sanitized receipts](../evidence/m141-release-2026-11-23/release.json) include exact backups/hashes. Rollback is the pinned controller with `backup rollback` or `live rollback`; it restores code only and refuses subsequent source drift.
- Existing signed-in production Board page rendered in Chrome. A native AX/viewport mismatch opened a card instead of the offscreen Share control; it was dismissed without submitting changes. Subsequent user activity interrupted further UI control. Full native signed-in member-dialog acceptance remains unrecorded; synthetic EN/AR/phone acceptance and deployed native service/HTTP evidence are separate. No real member was added/removed as a test.
- No persistent business record, financial record, schema/module setting, configuration, access right, proxy or service was changed. Source backup files were retained. M141 is deployed and server-side verified on both environments; the explicit dialog UAT limit above remains.

## Shared employee administrator follow-up — 2026-11-23

Ahmed reported Moaz and Rina missing. A bounded live profile read confirmed Moaz is an active internal employee administrator stored at native entity0; the initial picker incorrectly required entity1. Rina is already an active entity1 employee and was present in the deployed service response. No account reclassification is needed.

The follow-up follows existing `InternalTaskAuthorizationService::isInternalActive`: General Board staff options include current-entity employees or native entity0 administrators, with active/internal/Employee checks retained. Ordinary entity0 users, inactive/global external users and users/admins in other nonzero entities remain denied. The member identity join also recognizes native shared administrators so a successfully added person remains visible/removable. Customer/supplier picker eligibility retains its prior exact-entity behavior.

Regression: initial added shared-admin fixture failed, then 72 SQLite cases and 67 native MySQL temporary-table cases passed; full workspace shared-member rendering was checked on SQLite (native temporary table repeated-reference limit is retained). This source correction is pending follow-up deployment; the preceding five-file release evidence remains historical. No real memberships or native profiles were altered for testing.
