# M116 customer cash-book UI correction — production completion

Status: **LIVE, VERIFIED**. The customer statement now offers five presentations of the same simple cash ledger, uses the normal Dolibarr top menu, and shows finance-authorized Balance/Due on customer and project cards. Due is exactly max(0, -cash balance), the funds needed to cover recorded spending. Currency positions remain separate. No invoice, overdue, commitment or automatic payment behavior was added.

Owner's conditional approval: user Sentinel_1a4dad14fd2481919908d260c588934b, “this if finished push to live”, supplied in trusted parent delegation. Remaining acceptance was completed before apply. Existing saved root connection approval and single-writer release scope were retained. Boards owner confirmed no concurrent production writes; its realtime/touch trial remained isolated.

## Actual release receipts

- Runtime code: f6fefab780d14dd863ca8c2a3857dfa421e87fbb, comprising935881f and the native print-contrast fix. Base abfed8b atop previously deployed1b60285. Local branch codex/m116-statement-presentations. No Git push/merge performed.
- Target: https://erp.visiontrading.biz, srv1011043, vision_erp. Existing Dolibarr24.0.1/PHP8.2.7/BCMath image retained. No container replacement/restart or runtime/config/security change.
- Artifact SHA2569f04bd1ee392913dcb899d7c1148d42771a2afbe75ab111eef5f8ca454a00737; manifest SHA256595ebb029fe0fb7b97bad19562a5d5a127eb34d00dc30579bebf030705989f1c.
- Reviewed installed helper/root/m116_ui_release.py SHAb02ff485b98a171f41a869549dc25f1a107d26d5938d037ca4c127d222d8ab12.
- Applied2026-10-27T12:55:31Z (20:55:31Asia/Shanghai). Independent file/schema/backup/health verification12:56:50Z. Final server readonly acceptance13:05:37Z (21:05:37Asia/Shanghai).
- Nine runtime files, six replacements and three new dependencies; explicit allowlist in deploy/releases/m116-ui/manifest.json. All93 preexisting source pins verified with this reviewed overlay; new files individually pinned. Native core preserved.
- No schema migration, flags, permissions/grants, credentials, business records or historical finances changed. No test identities/records, supplier/forwarder code, realtime/touch trial or customer-portal exposure deployed.

## Backup and rollback

Fresh private backup/root/chinaplanetos-m116-ui-20261027 contains six original files plus uid/gid/mode and full database dump18530351bytes. Dump SHA25659ebd8aab1d4f3d9a7aed0487f9d1ce8b3697b74f88dbd5d290ec883875d0687. Readback, completion marker and hashes passed; backup bytes remained server-side.

Rollback command: `python3 /root/m116_ui_release.py rollback`. It refuses newer source drift, validates backups, restores all existing callers before quarantining new dependencies, and retains all database/business writes. There is no database restore or schema mutation path. Atomic-write failure cleanup and symlink-safe receipt publication were independently reviewed and failure-tested. Rollback was not needed or executed. Never repeat apply on existing state.

## Verification

| Area | Evidence/outcome |
|---|---|
| Full regression | deploy/tests/m116/run.sh exit0; exact amount20, service29, hardening8, canonical scope/audit/rights/export/concurrency, workspace28 and actual renderer harness passed. SQLite concurrency is not claimed as a new native locking test. |
| New summaries |19 actual assertions: positive/zero/negative, separate currencies, reversal, project/card attribution once, unallocated, invalid IDs/list limits, no-finance response. Request IDs now use a separate counter so assertion totals are not inflated. |
| Presentation/card tests | Five modes, escaping, scoped spending, receipt/spending reversals, partial details/residuals. Card success/denial, exact currencies, mismatched scope, stale detached DOM,41-card/20-scope batches, disabled flag pass. |
| Release recovery |10 unittest cases pass, including fsync/chown/chmod/rename failure recovery, symlink receipt victim unchanged, backup corruption and dependency-safe rollback. |
| Native isolated | Existing synthetic native24.0.2/PHP8.3.21 session, native top menu and customer/project card pixels, five390px layouts, normal contextual draft/save/post and negative shortfall roll-up, native HTTP10 authorization/routes/export checks pass. No users/grants/passwords changed. |
| Print PDF | Captured rendered native DOM rendered with WeasyPrint70; one page, exact amounts/FX/allocations/Due verified, PNG reviewed. Low-contrast print totals corrected before the pinned release. |
| Live desktop/mobile | All five modes at1728px and390px; normal top menu and no account overflow. Customer/project Balance+Due, customer/project/card statement navigation and existing Boards/list/card/attachment controls pass. No business test writes. |
| Financial preservation | Canonical customer projection digest identical before/after:24d1ddeb726932f3fbdc2dce8f0410ff458fa628a0359b2d61bea3b56b39b3da. Live server checks confirm project+unallocated conservation and exact Due. |
| Finance ACL/export | Existing native non-finance identity evaluated server-side is denied by summary service. Actual XLSX2650bytes is valid ZIP/OpenXML, includes Due and contains no formula cells. No separate non-finance live login or grants were created. |

Sanitized local receipts: /Users/mac/Documents/Codex/2026-10-26/task-4/release-m116-ui/live-verify-receipt.json, live-readonly-acceptance.json and live-browser-results.json. Private live screenshot stays local under private-live; it is excluded from Git/artifacts and is not shared/uploaded. No Library uploads.

## Nonblocking limits and coordination

- Browser XLSX capture timed out, although the actual server generator and OpenXML checks passed. Native OS Print/Save dialog remains unverified; the separately rendered PDF does not claim that OS action.
- Selected live card had no attachment files. Existing attachment controls rendered; no binary attachment download/upload test was performed.
- Production posting/reversal/reallocation tests were intentionally not performed. They passed isolated synthetic/service tests; no live financial test transactions are authorized.
- Realtime/touch and M117 remain separate; neither is released. Current live Boards JS is00a29efd921b87ed185f32d5d87902d738ac0a7ee9e8ebc73b8c9241ec8e73a3. Any later merge must preserve this baseline.
- Final notification to Boards-owner thread01a16d4d-b009-71d0-8231-07beb60c6039 was **DENIED** by automatic approval review for missing direct user authorization to message that specific thread. No retry, indirect delivery or bypass was attempted. Earlier sole-writer hold had succeeded; deployment itself is complete.
- M117 reported a separate local MySQL8889 FD_SETSIZE issue after its test clone. This release performed no MySQL restart, FLUSH TABLES or configuration change; production verification was unaffected.

Verified user entry: https://erp.visiontrading.biz/custom/chinaplanetoperations/tasks/account.php?customer=3316&view=statement . The Display selector switches styles; normal cash semantics and authorization apply in each view. Admin ERP/internal responsive mobile implemented. Customer Center/native Mobile App exposure not added.
