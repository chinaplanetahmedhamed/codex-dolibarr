# Boards backup release — installed and verified

Current verified release: cumulative Boards application `7bb0686`, support `a6e23d1`, delivery `7f4eec3`, protected workflow [41522847329](https://github.com/chinaplanetahmedhamed/codex-dolibarr/actions/runs/41522847329) successful.45runtime paths/seven additive tables/customer option/six flags; original data/access,35dependencies and M119R13 preserved. Private backups/state `/root/boards-backup-release-20261103-retry1`; no production changes. See `INSTALL.md` and `docs/REPORTS/M107_C02_BACKUP_BOARDS_INSTALLATION.md` for current scope, receipts, guarded rollback and acceptance limits. Do not replayapply on existing state.

The following is the retained historical preparation checkpoint. Ahmed subsequently authorized the cumulative backup-only install after the old-source mismatch was explained and offered a dedicated existing administrative session. Its missing-prerequisite/NOTSTARTED/R11 wording below is historical and superseded by the verified report above.

---

# Backup-only Boards scrolling release — preparation checkpoint

Application candidate: `48856acfd44b333469ed5574e992a0c36293c94c` (base `c2ad887`). Ahmed approved deploying the tested scrolling correction to **backup-erp only**, for his inspection. Production and Office Expenses changes are excluded.

## Verified boundary (2026-11-02 UTC)

- Restricted SSH reached `srv1011043`, UID1002, using the existing key and strict host checking. Exact eight-file reads through the canonical staging custom-volume path returned PermissionError. Those files are **unreadable**, not absent.
- Installed `/usr/local/sbin/chinaplanetos-backup-erp-deploy` is root:root0755, SHA256 `48fdc68f0e07f2a9fa3c5875d13170527551f8b390b76bcbb0fca11533bd183f`. It is the current Office Expenses R11 helper; it was not invoked or changed for Boards. No dedicated `/usr/local/sbin/chinaplanetos-backup-erp-boards-scroll` exists.
- Public backup assets were compared with the application candidate's base. `card_board.js` and `card_board.css` match. **`boards.js`, `card_operation.js` and `boards.css` differ substantially**, and the new geometry URL returns404. The current Boards assets predate the production workspaces/sound/polling baseline. A404 is not proof of host-side absence.
- Therefore the candidate's eight full files must **not** be copied onto backup. The current PHP/source hashes must be obtained first, located against repository source, and the scrolling correction ported as a minimal patch preserving the backup's behavior and dependencies. No unrelated hierarchy, sound, finance or realtime release is implied.
- GitHub environment `backup-erp` was inspected without changes. The workflow must continue to select that environment and `backup-erp-deployment` concurrency, honoring any current gates. No new workflow was dispatched. Concurrent Office Expenses run41482124847 was observed in progress and subsequently completed successfully at head `78efe9a5fca48d26602f0b6060d1b5a9bdf81990`; its helper/request/state were not reused.

Raw non-secret asset comparisons and diffs are in the sibling task evidence directory `boards-scroll-20261102/backup-release/` outside this repository. No remote application, helper, request, configuration, database, grant, service, proxy or document writes were performed by this preparation.

## Concrete missing prerequisite

An authorized administrative **read-only** snapshot of the fixed eight paths and canonical staging metadata is needed after the shared deployment queue is idle. The prepared `readonly_preflight.py` takes no arguments, checks the exact host/account/resources/mounts/ports, rejects symlinks and oversized/changing files, and outputs only hashes and metadata. It does not install a helper, run PHP, read configuration or records, acquire credentials, change permissions, create a remote file, or execute a deployment. Six isolated tests pass, including rejection of an unexpected extra published Mailpit port found by independent review. It deliberately refuses this local Mac/restricted account.

Do not treat the restricted rejection as permission to switch to root. Use only a separately authorized existing administrative session or an approved bounded reader. No password, new credential or sudoers grant is requested here. The script is prepared locally; **it has not been uploaded or executed on the server**.

## Next release steps after reconciliation

1. Obtain and verify the bounded snapshot and current sole-writer hold. Reconcile exact PHP/source/runtime pins and locate the existing dependency closure; stop on unknown source rather than overwrite it.
2. Port only the scrolling/menu correction onto the current backup source and rerun affected tests, complete matching-version Arabic/English desktop/mobile rendered checks, and independently review the deployment implementation.
3. Build a reproducible immutable allowlisted artifact and manifest. Create a separate checksum-pinned Boards deployment path; do not replace/reuse the Office helper, its `request.json`, previous production helper, or a generic newest-artifact selector. Any new security-sensitive command grant or protected-environment approval remains separately gated.
4. Require exact before-hashes/absence, canonical resource/image/mount guards, exclusive shared deployment ownership and environment gates. Make a fresh root-private code backup recording existence/content/hash/UID/GID/mode before apply. Test rollback restoration and partial-failure behavior before executing it remotely.
5. Apply only reconciled files, lint in the target runtime, verify hashes independently, and perform authenticated non-destructive board/card/menu acceptance. Rollback on failure restores only the exact saved code/existence and preserves all current business data. No database restore or schema change is necessary.

Deployment status: **NOT STARTED — source reconciliation and dedicated permitted deployment path blocked.** Earlier local20 checks still apply to48856ac only; they are not acceptance of a port onto the older backup source. Physical touchscreen acceptance remains unverified.
