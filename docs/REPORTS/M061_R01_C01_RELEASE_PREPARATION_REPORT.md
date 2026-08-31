# M061-R01-C01 local release-correction report

## Verdict

**STAGING DEPLOYED / VERIFY PASS — AUTHENTICATED ACCEPTANCE CORRECTION REQUIRED**

The dependency gap and false whole-runtime baseline assumption identified by the approved `backup-erp` diagnosis are corrected. The artifact is dependency-closed, the verified staging exception is explicit, and the installer replaced only the exact already-installed R01 state after a separate approval. Compare, preflight, dry-run, apply and independent verify all passed on canonical `backup-erp`. The approved authenticated acceptance attempt passed the available staff/browser and anonymous-login-boundary checks, but exposed an unavailable Mobile API route and lacked an approved external-customer session plus representative project/invoice fixtures. End-to-end M061 acceptance is therefore not claimed.

## Frozen inputs

- Branch: `codex/m061-customer-portal-trust`.
- Runtime content commit: `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a`.
- Corrected artifact: `/private/tmp/m061-r01-c01-customer-trust-repair-805cf3d.tar.gz`.
- Artifact SHA-256: `076d0b75eb9b8d0704217c53982b12b12e8cd1d8ce6525ca50670b427e9d2463`.
- Contents: 29 runtime files, 8 support files and 3 manifests; 40 regular files total.
- Runtime manifest SHA-256: `d8c8ab6097ce8951da0cb826ed50e7387c235379ec38507a4e682b1de180b821`.
- Support manifest SHA-256: `d3094ee745e8f921a3b8cc081b48c32a506d076b23835f1442ea31cb62f30e1a`.
- Verified staging-baseline manifest SHA-256: `569aadfa4b30f6185b574df4fc0738fb3f7c88d88ce025e5a47666148d8d7310`.

## Corrected dependency and baseline

- `custom/cpkanban/lib/projectsync.lib.php` is now an artifact runtime file; its frozen SHA-256 is `86522f6cf9c7e1bcd35982958e8a425f3e5eb824556614973c4fbff413507e4e` and its staging baseline is `ABSENT`.
- `custom/chinaplanetcustomercenter/projects.php` retains the frozen M061 release content SHA-256 `7c608f7dc7b129fa9aeaad2d727bb4776adbb41a03f4c3aea97a3331723af307`; its staging baseline is the diagnosed live pre-M054 SHA-256 `0e3878ffcc3122ea13f8e5b409a7dfebef50cb46673f2dca4134b8ebd5fb5cc4`.
- The package test verifies the projects baseline against known historical Git commit `01d405e80f30598cd81e22f59683ca998c9f366d` and limits the live-only absent override to the canonical CP Kanban library.
- The helper labels this honestly as `M060-runtime-plus-verified-pre-M054-projects`; it no longer calls the entire prestate one M060 commit.

## Restricted tooling

- Helper SHA-256: `083aae31075689409697237c1fe1ea5b049c428564db28b6e8edbda12c521976`.
- Sudoers SHA-256: `67f8c143d001e190454f4754747dfb50c4b872498ec3d08814bba64888a40a94` (unchanged seven fixed commands).
- Installer SHA-256: `c004d2e166d845d574b411ba33180d1304f2eeb10f1bf07e1ae2b23025611707`.
- The installer supports a clean installation or an upgrade only from the exact old helper SHA-256 `0c438629997a7a388ebecf5b303fd555f9b33771608e28379e3db8b1838aa2d7` plus the exact current sudoers state. Unknown, partial, linked or metadata-drifted states fail closed.
- The prior reviewed helper and sudoers are copied into the installer-private directory before replacement and are restored if installation does not reach its verified completion marker.

## Local verification

- Helper and installer shell syntax: PASS.
- PHP lint for all 33 PHP files in the runtime/support manifests: PASS.
- Release helper contract: PASS; seven fixed commands, C01 pins, old-state upgrade guard and production exclusion.
- Release package test: PASS; exact 40-file allowlist, 29 runtime blobs, 8 support paths, 29 baseline entries and artifact checksum.
- M061 operational consistency and M010/M011/M017/M020/M024/M031/M032/M033/M054/M056 source regressions: PASS.
- Mobile app: 12 Vitest files / 39 tests PASS; TypeScript typecheck PASS; Expo lint PASS.
- Isolated MariaDB repair runtime: not repeated in this local correction because no isolated MariaDB service was available; the repair runtime code and its packaged test are unchanged from accepted commit `805cf3d6...`.
- `git diff --check`: PASS before review.

## Independent review and environment boundary

All reviewers inspected the same frozen commit `90f23d4d13b2e164eb75963431d7d10f57830955` and local artifact without editing files or using network/server access.

- DevOps/VPS: **PASS**. Reverified exact artifact structure/hashes, 26 M060 hashes plus the historical projects hash and two `ABSENT` states, dependency closure, staging-only fixed targets, exact seven-command surface and old-helper upgrade guard.
- Security: **PASS**. Reverified regular-file allowlist, absence of traversal/link/device entries, checksum and metadata controls, fail-closed unknown/partial-state handling, private prior-state recovery, no production target and no customer/data/email authority expansion.
- QA: **PASS**. Repeated helper/package tests, 33/33 packaged PHP lints, 20 ERP regression files, 12/12 Mobile files with 39/39 tests, typecheck, lint and `git diff --check`; independently matched all runtime and support blobs.
- Non-blocking recommendation: add an executable fault-injection harness for installer failure/restore paths when an isolated root test environment is available. The present review covered those paths statically; staging installation will be a separate exact gate.
- Local artifact/source changed only. No file was uploaded, no staging helper was replaced, no release command ran, no database row/event/email changed, and production was not accessed.

## Next gate

The upload/helper-install, planning, apply and independent verify gates are complete. The attempted authenticated acceptance is recorded below with a `CORRECTION_REQUIRED` verdict. A separate local correction must restore the Mobile API dependency/route and prepare non-production customer-owned project and invoice fixtures plus a dedicated external-customer staging session. Any correction deployment and repeated acceptance require new exact approvals. Production remains outside this Mission approval.

## Separately approved staging upload and installation — 2026-08-20

Ahmed approved the exact previously announced next gate with `ok , do it`: upload the four pinned C01 files to canonical `backup-erp` and replace only the known old M061-R01 helper. The gate completed successfully:

- Inbox artifact SHA-256: `076d0b75eb9b8d0704217c53982b12b12e8cd1d8ce6525ca50670b427e9d2463`.
- Inbox/helper and installed-helper SHA-256: `083aae31075689409697237c1fe1ea5b049c428564db28b6e8edbda12c521976`.
- Inbox/installed sudoers SHA-256: `67f8c143d001e190454f4754747dfb50c4b872498ec3d08814bba64888a40a94`.
- Inbox installer SHA-256: `c004d2e166d845d574b411ba33180d1304f2eeb10f1bf07e1ae2b23025611707`.
- Installer result: `M061_R01_INSTALL=PASS`, exact prior helper recognized, no deploy, no database change and no production access.
- Installed helper metadata: `root:root 0755`, size `34812`.
- Installed sudoers metadata: `root:root 0440`, size `699`; `visudo` passed.
- Restricted `sudo -l`: exactly seven fixed M061-R01 commands.

No `compare`, `preflight`, `dry-run`, `apply`, `verify`, `rollback` or `status` command ran. The next gate is a separate approval for staging `compare`, `preflight` and `dry-run` only.

## Separately approved staging compare, preflight and dry-run — 2026-08-20

Ahmed approved the exact previously announced `backup-erp`-only gate with `ok , do it`. Execution was conditional and stopped between gates to validate each result:

- Installed-helper identity recheck: `root:root 0755`, size `34812`, SHA-256 `083aae31075689409697237c1fe1ea5b049c428564db28b6e8edbda12c521976`; the fixed seven-command surface remained available.
- Compare: **PASS**, release `M061-R01-C01`, runtime commit `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a`, artifact SHA-256 `076d0b75eb9b8d0704217c53982b12b12e8cd1d8ce6525ca50670b427e9d2463`, `DRIFT_COUNT=0`.
- Preflight: **PASS**. The preflight-generated plan reported `CANDIDATES=0`, `AMBIGUOUS=1`, `AMBIGUOUS_IDS=1`, and data SHA-256 `78c584bfea6a1389a1fe54f69ae51fd99d732b434b840057e861cc115bd78457`.
- Final independent dry-run: **PASS**. Retained plan SHA-256 `5e228b7ad4426a88345fd08544ae0833eb8ce5bce74108e5d202f1ae17a29717`; plan-file SHA-256 `e3387bc8003340abe13df87ec3fd760cf8264cce8bcdf94b2c0a61b36889cd4c`; identical data SHA-256; `CANDIDATE_IDS=none`; `AMBIGUOUS_IDS=1`.
- No eligible shipment row exists for automatic repair in the retained plan. The one impossible or otherwise ambiguous record remains excluded and unchanged for manual review.
- All three gates reported no database change and no production access; planning reported no email. `apply`, `verify`, `rollback`, and `status` were not invoked.

The retained dry-run is planning evidence only. A new exact approval is required before staging `apply` and `verify`; production still requires an independent release Mission and approval.

## Separately approved staging apply and verify — 2026-08-21

Ahmed continued from the exact announced apply/verify gate with `continiue`. The restricted helper was rechecked against its reviewed SHA-256 before execution.

- Apply: **PASS**; request `m061-r01-20260821T061643Z-20c457318419`; release `M061-R01-C01`; runtime commit `805cf3d6e739ecb6c220e1b07ce6e78bb0346d7a`; artifact SHA-256 `076d0b75eb9b8d0704217c53982b12b12e8cd1d8ce6525ca50670b427e9d2463`; plan SHA-256 `5e228b7ad4426a88345fd08544ae0833eb8ce5bce74108e5d202f1ae17a29717`.
- Code backup: `/root/backup-erp-code-backups/m061-before-805cf3d-m061-r01-20260821T061643Z-20c457318419.tar.gz`; SHA-256 `623b81be3c357dd278b2bda95c0ba763b8cdfec4ab052ea910cd3ab7be572daf`.
- Full database backup: `/root/backup-erp-db-backups/m061-before-805cf3d-m061-r01-20260821T061643Z-20c457318419.sql`; SHA-256 `a8917af9a1215e3e8656da786e0ee5850c825128b08cd07b4c47b73dd8a753b8`; the helper's isolated restore/fingerprint validation passed before deployment continued.
- The helper's apply summary uses conservative `DATABASE_CHANGED=YES` semantics. Because the retained plan had zero candidates, there was no shipment-row correction and no per-row repair event; ambiguous record `1` remained excluded and unchanged. Customer-visible events remained zero and email remained disabled.
- Independent verify: **PASS**, durable `STATE=DEPLOYED`, exact code/artifact/plan match, `CANDIDATES=0`, `AMBIGUOUS=1`, no verify-command database mutation, no email, and no production access.
- `rollback` and `status` were not invoked. At deployment time, authenticated acceptance remained `PENDING_EXTERNAL_GATE`; the later acceptance attempt and superseding verdict are recorded below.

This is verified staging deployment evidence, not production authority or end-to-end customer acceptance.

## Approved authenticated staging acceptance attempt — 2026-08-23

Ahmed approved authenticated M061 acceptance on `backup-erp` only with `نعم، نفّذ القبول المصادق لـM061 على backup-erp فقط، دون الإنتاج.` The inspection used existing authenticated staging sessions and read-only navigation; no login secret was copied or transmitted.

### Evidence that passed

- Existing `backup-erp` admin sessions were available in Chrome and the in-app browser. The external QA account `qa_customer_a` exists and is linked to `QA Customer A`, but no approved external-customer session was available.
- Authenticated staff pages for Customer Center, shipment list/detail, projects, invoices and documents rendered in Arabic and English at `1280px` and `390px`. The inspected pages had document/body widths equal to the viewport with no page-wide horizontal overflow.
- The shipment detail for staging record `1` displayed customer-facing operational dates as `YYYY-MM-DD`, the ordered journey, current/next state and container count. The already-reported ambiguous future actual-arrival record remained unchanged and was not treated as an automatic-repair candidate.
- Both QA customers currently have zero projects and zero approved invoices. Their safe empty states rendered correctly, including `لا توجد مشاريع / No projects found.` and `لا توجد فواتير معتمدة.`
- Anonymous requests with forged `socid=2` and `token=forged` against projects, documents, shipment detail and a direct shipment-file download all returned the Dolibarr login page. The direct-download response was HTML, not the requested file.
- No form was submitted, no project-card sync action was clicked, no file was downloaded through an authenticated customer route, no account/password/token was created or changed, no email was sent and no production host was accessed.

### Blocking gaps and failure

- The correct Mobile API base documented by the app, `/api/index.php/chinaplanetcustomercenter/mobile/v1`, returned HTTP `501` with `API not found (failed to include API file)`. The Dolibarr API subsystem itself is active: `/api/index.php/status` returned the expected unauthenticated HTTP `401`. Web/Mobile project parity therefore cannot pass on this staging runtime.
- The only available browser identity was the internal admin. Local `customer-agent` credentials are explicitly local-only and were not reused on staging. Without an approved external-customer staging session, ownership-forgery checks under an authenticated external identity, customer-only navigation visibility and direct-download authorization cannot be accepted.
- The staging dataset has no customer-owned project and no approved invoice for either QA customer. Eight-stage project progress, the active-project `<100%` rule, invoice due/overdue semantics and the no-lines customer/staff split cannot be demonstrated with the current data.

### Acceptance verdict

**CORRECTION_REQUIRED.** Staff web rendering and the anonymous login boundary pass, but authenticated external-customer security, representative business-data behavior and live Mobile API parity remain blocking. This verdict authorizes no correction deployment and no production action.
