# M070 — Local Clean Composite Source Reconstruction Report

## Report control

- **Mission:** M070 — Clean Composite Source Reconstruction
- **Mission status received:** APPROVED
- **Report status:** LOCAL_COMPLETED — INDEPENDENT SECURITY/QA PASS
- **Implementation owner:** Agent 007 Central
- **Dolibarr branch:** `codex/m070-clean-composite`
- **Dolibarr base:** `057af0f205f819c685869b3ee6aa1aea31ea549e` plus immutable M069 evidence through `4309634566601c79845aad00437708a60a969c90`
- **React branch/base:** `codex/m070-clean-composite` / `cb639518a784ce709e4a45eee0bd55b0a477c57a`
- **WordPress branch/base:** `codex/m070-clean-composite` / `fa34257fe9dfc276d85c16dae70b409224465c3c`
- **Result commits:** Created only after the reviewed report freeze; exact SHAs are recorded in the Agent 007 final handoff
- **Environment used:** Isolated local Git worktrees only
- **Staging/Production touched:** No

## Business outcome

The local candidate now joins the accepted M062/Production baseline, the final M061 Staging behavior with a narrowly documented customer-safe unit projection correction, the accepted public-site Staging commit, the canonical WordPress planner, and the corresponding Mobile source without merging M061/M068 wholesale. The result is traceable through deterministic manifests and preserves all ownership, privacy, quotation-review, and protected-worktree boundaries.

## Scope reconciliation

- **Completed:** 327-path Dolibarr runtime/source map; 37 exact M061 runtime files plus two M070 customer-unit security corrections; M061 functional tests/migration source/Mobile source; accepted Logistics upload boundary; M062/Alexandria preservation; React `df5ae8b` overlay; canonical WordPress 1.9.4; complete cross-repository manifests.
- **Not completed by design:** authenticated runtime acceptance, Mobile API HTTP 500 diagnosis, M071 quotation lifecycle, M072 release/version controller, M073 isolated Staging rebuild.
- **Scope corrections:** The first independent Security/QA snapshot found that M061 exposed `fk_unit` as `#<database-id>` in web and Mobile invoice lines. M070 now joins Dolibarr `c_units` and returns only short label, label, code, or `null`; the rejected snapshot is not releasable.

## Source selection and file evidence

| Area | Selection | Complete evidence |
|---|---|---|
| Dolibarr runtime | Accepted main with explicit M041/M053/M054 successor provenance, 37 exact M061 paths plus two M070 security corrections, accepted M039 Logistics upload boundary | `M070_DOLIBARR_RUNTIME_SOURCE_MAP.tsv` |
| Customer Mobile App | M061 final source overlay over accepted main; no permanent-worktree copy | `M070_MOBILE_SOURCE_MANIFEST.tsv` |
| React/Node | Production `cb639518` plus accepted Staging `df5ae8b` | `M070_REACT_SOURCE_MANIFEST.tsv` |
| WordPress planner | Canonical clean `fa34257`, version 1.9.4; duplicate copy excluded | `M070_WORDPRESS_SOURCE_MANIFEST.tsv` |
| Added M070 governance/tests | Mission, report, deterministic manifest builder, composite-source regression | Git local candidate diff |

The runtime map records Production SHA, Staging SHA, chosen canonical source path, selected SHA, source commit, and disposition for every M069 union path. It marks the four exact residue paths excluded and maps the 54 expanded deployed duplicates back to canonical symlink targets rather than treating them as new source files.

The six canonical source successors whose bytes differ from both runtime inventories are explicitly attributed to their accepted owners: M054 project-sync ownership, M053 module dependency separation, and M041 customer-safe shipping notes. Local Mobile parity documentation is attributed to `M070_LOCAL_COMPOSITE`, never to a historical commit whose bytes do not match.

## Preserved and excluded behavior

- M061 financial clarity, invoice details, public payment-note boundary, customer document registry/read model, staff-only no-send email workflow, operational truth, Logistics repair behavior, Mobile DTO/screens, and final UI contrast are present as source.
- M062 port normalization, one Alexandria search destination, exact Old Port/TMT rate identities, separate history series, and customer-safe public booking token behavior remain from current main.
- CP Kanban continues to own `custom/cpkanban/lib/projectsync.lib.php`; the Customer Center file is only a compatibility include.
- React retains public rate → selected rate → review request, Arabic/French search aliases, legal/consent routes, Customer Center links, and secure Node ownership.
- WordPress remains the headless catalog/packing/price-policy owner. Its request endpoint was not used and no WooCommerce order was created.
- Failed M068-R04 code and all M071 behavior are absent. The retained M068 additive schema/configuration state is documented only and was not read, changed, or imported.

## Database, accounting, configuration, permissions, and privacy

- **Database/schema/data:** No database access or mutation. `m061_r02_customer_center_schema.php` is source-only.
- **Accounting:** No posting, balance, payment, quotation, or currency mutation. Invoice line unit presentation now resolves a public label/code rather than displaying an internal database ID.
- **Configuration/module state:** No read or change; no helper or migration ran.
- **Permissions/customer visibility:** Restored accepted source retains entity/`fk_soc`, explicit publication, staff permission, CSRF, server-owned recipient/sender, and private-note/file boundaries.
- **Public integration:** React calls the secure Node layer; no browser credential or direct ERP call was introduced.

## Automated verification

| Verification | Result |
|---|---|
| M069 evidence SHA-256 freeze | PASS |
| M061 overlay | PASS — 37 exact runtime hashes + 2 pinned M070 security corrections |
| Customer invoice unit projection | PASS — web/Mobile internal ID absent; label/code fallback is null-safe |
| M069-C04 residue absence | PASS |
| M068-only source absence | PASS — all 21 paths added by rejected `32e82ff` are checked |
| CP Kanban project-sync ownership and symlinks | PASS |
| Manifest input safety and provenance | PASS — tracked index only; exact repository heads; no unstaged/untracked inputs; local composite content labelled explicitly |
| Changed PHP syntax | PASS |
| M054 ownership regression | PASS |
| M010/M017/M020/M031/M056/M061 source regressions | PASS |
| M062 normalization/importer/current-data negative fixtures/Alexandria/shipping privacy | PASS; database-backed current-data audit remains for M073 because no local database runtime was available or authorized |
| React lint | PASS |
| React secure-server tests | PASS — 57/57 |
| React UI/helper tests | PASS — 64/64 |
| React build | PASS |
| Mobile TypeScript + Expo lint | PASS |
| Mobile Vitest | PASS — 12 files, 39 tests |
| WordPress PHP tests + worker smoke + PHP syntax | PASS; PHP 8.4 emitted the pre-existing `fgetcsv` escape deprecation only |
| `git diff --check` and manifest reproducibility | PASS before final freeze |

## Language, direction, mobile, and runtime acceptance boundary

Source and automated contracts cover Arabic/English/French public content, Arabic RTL and EN/FR LTR, Customer Center Arabic/English, keyboard/focus labels, 360/390px-responsive rules, and separate Alexandria terminal history. No browser/runtime environment was started and no live or authenticated business workflow was exercised under M070. Full visual/runtime acceptance belongs to the isolated M073 stack and must not be inferred from tests or build success.

## Known risks and next gates

1. M061's Staging Mobile API HTTP 500 and missing positive external-customer/payment-note/document fixtures remain explicit M073 blockers.
2. The M061 additive schema and retained M068 schema/configuration state must be reconciled in the isolated M073 database copy; this local source reconstruction applies neither.
3. React `df5ae8b` deliberately retains the pre-M071 fail-safe behavior that excludes incomplete packing from calculation/submission. M071 must later implement mixed complete/incomplete quote-list behavior under the frozen v2 contract.
4. Footer version metadata and unified release tooling remain M072.
5. The database-backed M062 current-data audit could not connect to an inactive local Dolibarr database; its deterministic negative fixture passed. No database was started, opened, or changed, and the real-data audit remains an M073 runtime gate.

## Deployment boundary and rollback

- Push/upload/helper/server/database/configuration/email/business actions: **NONE**.
- Staging and Production: **UNCHANGED**.
- Rollback before commit: discard only the isolated M070 worktrees after preserving evidence.
- Rollback after local commit: create a new local branch from the pinned bases; do not reset or clean permanent worktrees.

## Review record

| Role | Result | Evidence |
|---|---|---|
| Security | PASS | No actionable finding on frozen Dolibarr `7690968a...`, React `ad9e6994...`, WordPress `05ec2b03...` trees |
| QA | PASS | Frozen manifests, exclusions, provenance, syntax, and complete local test evidence verified |
| Architect | ACCEPTED_LOCAL | Both independent verdicts PASS; local commits authorized by the approved M070 scope |
| Ahmed | Local implementation approved | No Staging/Production authority |
| First frozen snapshot | CORRECTION_REQUIRED | Rejected after Security/QA findings; no commit created |

## Final disposition

`LOCAL_COMPLETE_REVIEWED`. Security and QA passed the same immutable source candidate. Only local commits may now be created; M071, Staging, and Production each remain separate exact approval gates.
