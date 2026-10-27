# Four-file Boards live release

User explicitly approved publication on 2026-10-28 Asia/Shanghai. Source commit064b9e0; no core/schema/permission changes. `release.py` is a privileged production helper, not a local build command. Its main actions require current exact target/source checks and approved deployment scope. It preserves current M116 sources and uses the same shared release lock; `apply` runs fresh root-private database/code backup first, then fail-closed before-hash checks. Failed apply restores code only and never restores the DB. Rollback quarantines the new dependency after callers are restored.

Local tests: `python3 deploy/releases/boards-live-20261028/test_release.py`, `node deploy/tests/boards-live/sync.test.cjs`, `node deploy/tests/boards-live/pointer.test.cjs`, `sh deploy/tests/m116/run.sh`. Physical touch/office-scale load are not certified; selected-board 2-second polling is not push. Runtime files use normal versioned asset URLs.

Deployment pending. Do not infer apply from package creation. Use retained actual receipt to reconcile any uncertain execution before retry.
