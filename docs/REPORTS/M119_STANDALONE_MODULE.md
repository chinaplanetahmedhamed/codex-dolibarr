# M119 standalone Office Expenses — 2026-11-02

Status: local refactor READY_FOR_REVIEW; no activation or deployment.

## Result

Canonical runtime source is `custom/chinaplanetofficeexpenses/`, with descriptor `modChinaPlanetOfficeExpenses` (500600), own translations and default-denied draft/admin rights. Dependency is native Expense Reports only; Operations enablement is not consulted. The native menu retains administrator1/entity1 pilot restrictions. Operations no longer owns an Office Expenses menu entry.

Old PHP class paths and the HTTP entry delegate to canonical implementations. An internal include preserves POST/uploads/native sessions. Canonical assets are used from both routes. Local tests and preview generators target canonical source; the approved original-design preview is regenerated.

Schema bytes, `cpo_oe_*` tables, identifiers, private configuration and receipt paths are unchanged. Module lifecycle passes no domain SQL. The standalone gate runs before storage binding through both entries. Existing native expense rights, pilot scope, CSRF, idempotency and employee isolation remain intact. Financial posting remains disabled.

## Preservation and review

Branch `codex/m119-office-expenses-local`; HEAD remains `1b3f92f3e7f4ef825b8779da809c86dc8b029b13`; changes uncommitted. Pre-existing edits were preserved in canonical source and a source-only sibling snapshot `m119-standalone-before`, with42 hashes. The dirty permanent checkout on `codex/m084-dashboard-repair` was not overwritten or merged.

Independent review found no Critical/Important issues. It verified32 existing files byte-identical, only entry/Page asset path/MenuSync namespace changed, zero missing files, and15 legacy class shims. Its README ownership/stale-status finding was corrected. No unrelated work was reset/cleaned.

## Verification

- 15 PHP suites passed:184 scenarios plus16 menu assertions, including8 standalone cases against the native24.0.1 module base. Two native auth/session cases explicitly skipped.
- 12 synthetic HTTP scenarios passed: Operations disabled, standalone disabled/native-only-right denial through both routes, canonical/legacy same-key replay without duplication, ownership/currency/CSRF/menu controls. The temporary loopback server and synthetic data were stopped/removed. This is not real native login acceptance.
- Artifact suite5 passed, including RED→GREEN refusal to package relocation shims under the old fixed contract; legacy helper29 and runtime-preparation9 passed.
- PHP lint67 files passed; modified Python parsed; diff whitespace check passed.
- Static original-design preview regenerated from canonical classes; still synthetic/read-only.

Local CLI is PHP8.3.21, native library source24.0.1. Matching PHP8.2.32/MariaDB and actual module activation/session acceptance were not rerun. No real config/database/account/permission/ledger was touched.

## Remaining release gates

1. Verify target module ID500600 is free; verify native module/session/permission lifecycle on the matching disposable runtime, including standalone disabled and Operations disabled.
2. Prepare a complete pinned package containing the standalone module and compatibility files. The legacy M119 builder refuses shim commits before output; historical pinned artifacts remain valid.
3. Prepare scoped installer/rollback and reconcile only the old Office Expenses menu row, preserving unrelated menus and current records. No real module activation or rights grant occurred here.
4. Verify matching MariaDB/private filesystem and actual multipart/native-session acceptance for the new route before backup rollout. Staff/production rollout and posting remain separate.

Local source rollback can use the owned snapshot after checking for subsequent work. Deployed pilot/data were unchanged; no database rollback is required.
