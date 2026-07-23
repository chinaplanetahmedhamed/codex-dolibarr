# M037-C01 — Authenticated Admin Route Diagnostic

## Mission control

- **Mission ID:** M037-C01
- **Parent mission:** M037
- **Type:** CORRECTION
- **Status:** IN_PROGRESS
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
| Security | PENDING | — | Frozen helper/test revision required |
| QA | PENDING | — | Local tests and exact artifact required |
| Architect | APPROVED | 2026-07-24 | Scope limited to read-only staging diagnosis |
| Ahmed | APPROVED | 2026-07-24 | `ok do it` |

## Completion report

Create `docs/REPORTS/M037-C01_COMPLETION_REPORT.md` with commit/artifact/helper hashes, local tests, reviewer results, installer evidence, exact staging preflight output, no-apply evidence, production non-access, result, and next correction if required.
