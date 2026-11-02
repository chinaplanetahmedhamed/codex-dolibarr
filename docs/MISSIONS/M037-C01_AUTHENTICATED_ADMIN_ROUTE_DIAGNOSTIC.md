> **Governance supersession — 2026-11-03:** Legacy agent assignments and delegation in this record are inactive under [the Official AI Governance](https://github.com/chinaplanetahmedhamed/codex-dolibarr/blob/main/docs/governance/AI_GOVERNANCE_2026-11-03.md). Names and dated reviews below are retained as historical attribution only. They grant no current authority or reusable deployment approval. Work must record a new assignment before resumption. Technical/security/data/backup safeguards remain in force.

# M037-C01 — Authenticated Admin Route Diagnostic

## Mission control

- **Mission ID:** M037-C01
- **Parent mission:** M037
- **Type:** CORRECTION
- **Status:** COMPLETED
- **Architect / implementation owner:** Agent 007
- **Required reviewers:** Security, QA
- **Target:** Canonical `backup-erp` staging only
- **Production:** Forbidden
- **Ahmed approval:** Approved in the active task with `ok do it` after the read-only diagnostic scope was stated
- **Approval date:** 2026-07-24

## Objective

Identify the exact safe failure class behind the M037 authenticated `admin_dashboard` route failure before another staging apply is allowed.

## Current context

- Corrected M037 artifact `c0be577c…` at runtime commit `58b3ca3` passed canonical staging preflight.
- Its apply failed at `AUTHENTICATED_ADMIN_ROUTE ROUTE=admin_dashboard`.
- Automatic code and database rollback passed, staging HTTP health returned `200`, and M037 state is `APPLY_FAILED_ROLLED_BACK`.
- The existing helper suppressed all unclassified route output and therefore did not distinguish Dolibarr bootstrap failure, early exit, empty output, contaminated output, or child-process failure.
- The reported staging admin row meets the expected `admin=1`, `fk_soc IS NULL`, `statut=1` shape, so a whole-system update is not justified by current evidence.

## Approved scope

1. Capture and classify parent Dolibarr bootstrap output without printing its contents.
2. Distinguish role-query failure, role-candidate counts, child-render failure, empty output, expected-marker contamination, and unclassified output.
3. Log only bounded reason codes, counts, exit code, output byte count, and SHA-256; never output usernames, HTML, SQL, credentials, or customer data.
4. Add a current-runtime `admin_dashboard` diagnostic to M037 `preflight`.
5. Require the same diagnostic before any future `apply`, so a caller cannot bypass the correction gate.
6. Keep the existing fixed sudo command surface; no new privileged command is introduced.
7. Build and checksum-pin a new clean M037 artifact/helper revision and run `preflight` only on canonical staging.

## Out of scope

- Running M037 `apply`, `verify`, or rollback during this correction.
- Changing Dolibarr users, roles, permissions, module state, schema, customer data, Compose, containers, volumes, ports, proxy, DNS, or documents.
- Copying or printing staging database contents.
- Updating the whole Dolibarr system.
- Any production access or mutation.

## Affected files

- `deploy/tests/test_m037_authenticated_session.php`
- `deploy/tools/chinaplanetos_backup_erp_m037_full_release.sh`
- `deploy/tools/install_backup_erp_m037_full_release_helper.sh`
- `deploy/tests/test_m037_backup_erp_full_release.php`
- M037/M037-C01 mission, report, changelog, and release-evidence records as required

## Security and data handling

- Test user selection remains derived from Dolibarr entity sharing and role/permission checks.
- User identity stays in the child process standard input and is never printed or passed in argv/environment.
- Diagnostic output is restricted to fixed reason codes, aggregate counts, exit status, byte length, and SHA-256.
- No staging business row or configuration value is changed.

## Success criteria

- Local PHP/Bash/static contract tests pass.
- The artifact remains an exact regular-file allowlist with no secret/runtime data.
- Security and QA reviews pass the frozen revision.
- The fixed helper installer accepts only the currently installed helper hash and the new helper hash.
- Canonical staging `preflight` returns either:
  - exact diagnostic PASS plus normal preflight PASS; or
  - a safe, actionable failure reason with byte count and SHA-256.
- No `apply` is executed and production is not accessed.

## Rollback

No application or database rollback is required because the correction run is read-only. The root helper can be rotated back only through a separately checksum-pinned installer if required; no application runtime file is changed by `preflight`.

## Product parity

- **Admin ERP:** No product behavior change; diagnostic tooling only.
- **Customer Portal:** Not applicable; no route or data-contract change.
- **Mobile App:** Not applicable; no API, navigation, status, or design change.

## Review record

| Role | Result | Date | Evidence |
|---|---|---|---|
| Security | PASS | 2026-07-24 | Safe aggregate diagnostics, least-privilege temporary candidate access, metadata-warning guards, stdin-only identity, pre-mutation gate, unchanged sudo surface |
| QA | PASS | 2026-07-24 | 14 positive routes plus external denial, candidate access/classifier contract, M029–M037 regressions, metadata-free 71-entry artifact, exact hashes |
| Architect | APPROVED | 2026-07-24 | Scope limited to read-only staging diagnosis |
| Ahmed | APPROVED | 2026-07-24 | `ok do it` |

## Frozen release candidate

- Runtime artifact commit: `b4fc34ac5d5f7beb78aec297d9c4afad45b416e7`
- Release tooling commit: `aaf1e33`
- Artifact: `m037-full-b4fc34a.tar.gz`
- Artifact SHA-256: `5f6bc33af781ac20898d78e25e9dc011a3dd2451fa061c52e8697202bf6d39f1`
- Artifact entries: `71` regular files (`52` runtime, `17` support, `2` manifests)
- Helper SHA-256: `4d242b85cdc87df74ab62819df4f69e34f823a06f12c33706687a347c172fb16`
- Installer SHA-256: `ac922de4b7068645d8297a0695e21019663bb6ab134d1d7f91b62bd00934cf8a`
- Sudoers SHA-256: `815e0040fce5ebe2fa77f840a3811e3733db995c3bf4360c39fa105c4d4deab1`
- Currently installed helper accepted for rotation only: `ab1377b9ad117227f6ac2e53e02fac540766d2558ac1eccf7f88811e1fd03e6b`
- Remaining gate: upload, checksum verification, root helper rotation, and canonical staging `preflight` only.

## Packaging correction — 2026-07-24

- The first M037-C01 preflight stopped before the admin diagnostic with `ARTIFACT_ALLOWLIST_MISMATCH`.
- Linux tar reported unsupported macOS `LIBARCHIVE.xattr.com.apple.provenance` metadata from the local archive.
- No application, schema, permission, database, document, container, or production mutation occurred.
- The same 71 committed files were rebuilt as metadata-free `ustar`; no runtime/source file changed.
- The helper now rejects any tar listing or extraction warning before comparing the exact allowlist.

## Candidate-access correction — 2026-07-24

- The metadata-free artifact passed validation, and preflight advanced to the current-runtime admin diagnostic.
- The diagnostic stopped with `CLASS=UNCLASSIFIED`, exit `1`, output bytes `111`, and SHA-256 `12eb352c…`; no raw output or identity was printed.
- The evidence is consistent with PHP being unable to open the root-created temporary test script as `www-data`; application and database state were not involved or changed.
- Only the temporary candidate root, `deploy`, and `deploy/tests` directories receive traverse permission, and only the authenticated test script receives read permission.
- The helper also classifies unreadable script, permission, PHP fatal/parse/warning, and Dolibarr technical-error output without printing raw content.

## Completion report

Create `docs/REPORTS/M037-C01_COMPLETION_REPORT.md` with commit/artifact/helper hashes, local tests, reviewer results, installer evidence, exact staging preflight output, no-apply evidence, production non-access, result, and next correction if required.

## Final result — 2026-07-24

- The final read-only preflight reached the authenticated admin test and returned `CLASS=PHP_FATAL_ERROR`, exit `255`, output bytes `296`, output SHA-256 `2eecfcb9…`.
- A local reproduction ran the same committed test with `proc_open` disabled at the same candidate-path shape. It returned exit `255`, the exact fatal signature `Call to undefined function proc_open()`, and `304` bytes; the eight-byte difference equals the `/private/tmp` versus `/tmp` path-prefix difference.
- Root cause: the M037 authenticated-route test harness depends on `proc_open()`, which is unavailable in canonical staging PHP. The admin row, Dolibarr route, and application runtime were not reached by this failing check.
- M037-C01 achieved its diagnostic objective and is complete. Parent M037 remains `CORRECTION_REQUIRED`.
- Required follow-up: a separately approved M037-C02 must replace the `proc_open` child launcher with a one-time, opaque, file-backed context handoff executed by the fixed staging helper. Enabling `proc_open` globally is not recommended.
- Final server evidence: `STATE_STATUS=APPLY_FAILED_ROLLED_BACK`, staging HTTP `200`, `APPLY_EXECUTED=NO`, and production not accessed.
