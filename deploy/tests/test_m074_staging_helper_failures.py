#!/usr/bin/env python3
"""Fault and containment tests for the real M074-R10 file/state primitives."""

from __future__ import annotations

import hashlib
import importlib.machinery
import importlib.util
import contextlib
import datetime as dt
import io
import os
import pathlib
import tempfile
import unittest.mock


ROOT = pathlib.Path(__file__).resolve().parents[2]
HELPER = ROOT / "deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh"
loader = importlib.machinery.SourceFileLoader("m074_helper", str(HELPER))
spec = importlib.util.spec_from_loader(loader.name, loader)
helper = importlib.util.module_from_spec(spec)
loader.exec_module(helper)


def require(condition: bool, reason: str) -> None:
    if not condition:
        raise SystemExit("M074_STAGING_HELPER_FAILURES=FAIL REASON=" + reason)


require(helper.schema_diagnostic(b"M074_SCHEMA=FAIL REASON=RIGHT_INSTALL_FAILED\n") == "RIGHT_INSTALL_FAILED", "SCHEMA_REASON_REJECTED")
require(helper.schema_diagnostic(b"SQL syntax error near customer\n") == "SCHEMA_DIAGNOSTIC_INVALID", "RAW_SCHEMA_ERROR_ACCEPTED")
require(helper.schema_diagnostic(b"M074_SCHEMA=FAIL REASON=RIGHT_INSTALL_FAILED\nsecond line\n") == "SCHEMA_DIAGNOSTIC_INVALID", "MULTILINE_SCHEMA_ERROR_ACCEPTED")
require(helper.schema_diagnostic(b"M074_SCHEMA=FAIL REASON=UNKNOWN_STAGE\n") == "SCHEMA_DIAGNOSTIC_INVALID", "UNKNOWN_SCHEMA_REASON_ACCEPTED")
require(helper.schema_diagnostic(b"x" * 193 + b"\n") == "SCHEMA_DIAGNOSTIC_INVALID", "OVERSIZED_SCHEMA_ERROR_ACCEPTED")

schema_candidate = type("SchemaCandidate", (), {"remote": "/tmp/m074-r10-candidate-test"})()
with unittest.mock.patch.object(helper, "run", return_value=type("Result", (), {
        "returncode": 2,
        "stdout": b"M074_SCHEMA_CHECK=MIGRATION_REQUIRED REASON=RIGHTS_MISSING\n",
        "stderr": b"",
})()) as schema_run:
    code, output, diagnostic = helper.schema_command(schema_candidate, "check")
require((code, output, diagnostic) == (2, "M074_SCHEMA_CHECK=MIGRATION_REQUIRED REASON=RIGHTS_MISSING", ""),
        "SCHEMA_CHECK_RESPONSE_INVALID")
schema_args = schema_run.call_args.args[0]
require(schema_args[-10:] == [
    helper.TARGET_CONTAINER, "php", "-d", "display_errors=0", "-d", "log_errors=0", "-d", "error_reporting=0",
    schema_candidate.remote + "/" + helper.MIGRATION, "check",
], "SCHEMA_PHP_DIAGNOSTIC_ISOLATION_MISSING")


with tempfile.TemporaryDirectory(prefix="m074-helper-failures-") as raw:
    sandbox = pathlib.Path(raw)
    helper.TARGET_ROOT = sandbox / "target"
    helper.BACKUP_ROOT = sandbox / "backups"
    helper.DB_BACKUP_ROOT = sandbox / "db-backups"
    helper.STATE_ROOT = sandbox / "state"
    helper.STATE_FILE = helper.STATE_ROOT / "state.json"
    helper.PLAN_FILE = helper.STATE_ROOT / "plan.json"
    for directory in (helper.TARGET_ROOT, helper.BACKUP_ROOT, helper.DB_BACKUP_ROOT, helper.STATE_ROOT):
        directory.mkdir(mode=0o700)

    # Local tests cannot own files as root, so preserve every other metadata/hash check.
    def local_validate(path: pathlib.Path, expected: str | None = None) -> None:
        info = path.lstat()
        require(path.is_file() and not path.is_symlink() and (info.st_mode & 0o777) == 0o600, "LOCAL_ROOT_FILE_METADATA")
        if expected:
            require(helper.sha256_file(path) == expected, "LOCAL_ROOT_FILE_HASH")

    helper.validate_root_file = local_validate

    # macOS lacks Linux renameat2; emulate the two operations to exercise the
    # controller's compare-and-swap decision logic locally.
    def local_renameat2(old_fd: int, old: str, new_fd: int, new: str, flags: int) -> None:
        if flags == helper.RENAME_NOREPLACE:
            try:
                os.stat(new, dir_fd=new_fd, follow_symlinks=False)
            except FileNotFoundError:
                os.rename(old, new, src_dir_fd=old_fd, dst_dir_fd=new_fd)
                return
            raise FileExistsError(new)
        if flags == helper.RENAME_EXCHANGE:
            swap = ".exchange-" + os.urandom(6).hex()
            os.rename(old, swap, src_dir_fd=old_fd, dst_dir_fd=old_fd)
            try:
                os.rename(new, old, src_dir_fd=new_fd, dst_dir_fd=old_fd)
                os.rename(swap, new, src_dir_fd=old_fd, dst_dir_fd=new_fd)
            except Exception:
                try: os.rename(swap, old, src_dir_fd=old_fd, dst_dir_fd=old_fd)
                except OSError: pass
                raise
            return
        raise OSError("unsupported rename flags")

    helper.renameat2 = local_renameat2
    uid = os.getuid(); gid = os.getgid()
    original = b"original-runtime"
    deployed = b"deployed-runtime"
    target = helper.TARGET_ROOT / "chinaplanetoperations" / "class" / "Example.php"
    target.parent.mkdir(parents=True)
    target.write_bytes(original); target.chmod(0o644)
    snapshot = {"files": [{"path": "chinaplanetoperations/class/Example.php", "state": "PRESENT",
                           "sha256": hashlib.sha256(original).hexdigest(), "uid": uid, "gid": gid, "mode": 0o644}], "layout": []}
    deployed_snapshot = {"files": [{"path": "chinaplanetoperations/class/Example.php", "state": "PRESENT",
                                    "sha256": hashlib.sha256(deployed).hexdigest(), "uid": uid, "gid": gid, "mode": 0o644}], "layout": []}
    backup = helper.write_backup(None, snapshot, "before")
    root_fd = os.open(helper.TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY)
    try:
        helper.atomic_write_target(root_fd, "chinaplanetoperations/class/Example.php", deployed, uid, gid, 0o644)
    finally:
        os.close(root_fd)
    helper.restore_snapshot(*backup, allowed_snapshot=deployed_snapshot)
    require(target.read_bytes() == original, "REAL_RESTORE_FAILED")

    # Compatibility overlay files use the same CAS backup/restore path while unmanaged files remain exact.
    overlay_root = helper.TARGET_ROOT / "chinaplanetcustomercenter/cpkanban"
    overlay_root.mkdir(parents=True)
    overlay_target = overlay_root / "index.php"; overlay_extra = overlay_root / "extra.txt"
    overlay_target.write_bytes(b"overlay-old"); overlay_target.chmod(0o644)
    overlay_extra.write_bytes(b"preserve"); overlay_extra.chmod(0o640)
    overlay_snapshot = {"files": [{"path": "chinaplanetcustomercenter/cpkanban/index.php", "state": "PRESENT",
                                   "sha256": hashlib.sha256(b"overlay-old").hexdigest(), "uid": uid, "gid": gid, "mode": 0o644},
                                  {"path": "chinaplanetcustomercenter/cpkanban/managed/new.php", "state": "ABSENT"}],
                        "layout": [{"path": "chinaplanetcustomercenter/cpkanban", "state": "OVERLAY_DIRECTORY",
                                    "expected": "../cpkanban", "actual": "NON_SYMLINK_DIRECTORY",
                                    "tree": helper.directory_tree_snapshot("chinaplanetcustomercenter/cpkanban")}]}
    overlay_deployed = {"files": [{"path": "chinaplanetcustomercenter/cpkanban/index.php", "state": "PRESENT",
                                    "sha256": hashlib.sha256(b"overlay-new").hexdigest(), "uid": uid, "gid": gid, "mode": 0o644},
                                   {"path": "chinaplanetcustomercenter/cpkanban/managed/new.php", "state": "PRESENT",
                                    "sha256": hashlib.sha256(b"managed-new").hexdigest(), "uid": uid, "gid": gid, "mode": 0o644}]}
    overlay_backup = helper.write_backup(None, overlay_snapshot, "overlay-before")
    overlay_fd = os.open(helper.TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY)
    try:
        helper.atomic_write_target(overlay_fd, "chinaplanetcustomercenter/cpkanban/index.php", b"overlay-new", uid, gid, 0o644)
        (overlay_root / "managed").mkdir(mode=0o755)
        (overlay_root / "managed/new.php").write_bytes(b"managed-new")
        (overlay_root / "managed/new.php").chmod(0o644)
    finally:
        os.close(overlay_fd)
    helper.restore_snapshot(*overlay_backup, allowed_snapshot=overlay_deployed)
    require(overlay_target.read_bytes() == b"overlay-old" and overlay_extra.read_bytes() == b"preserve" and not (overlay_root / "managed").exists(),
            "OVERLAY_ROLLBACK_NOT_EXACT")

    # Rollback cleanup must not follow an intermediate parent swapped to an external symlink.
    outside_race = sandbox / "outside-race"; (outside_race / "sub").mkdir(parents=True)
    race_root = overlay_root / "race"; (race_root / "sub").mkdir(parents=True)
    race_item = {"path": "chinaplanetcustomercenter/cpkanban", "state": "OVERLAY_DIRECTORY",
                 "expected": "../cpkanban", "actual": "NON_SYMLINK_DIRECTORY",
                 "tree": helper.directory_tree_snapshot("chinaplanetcustomercenter/cpkanban")}
    # The approved prestate did not contain the managed race parents.
    race_item["tree"] = [entry for entry in race_item["tree"] if entry["path"] not in ("race", "race/sub")]
    race_allowed = {"files": [{"path": "chinaplanetcustomercenter/cpkanban/race/sub/file.php", "state": "PRESENT"}]}
    real_directory_tree_snapshot = helper.directory_tree_snapshot
    swapped = False
    def swap_parent_after_snapshot(relative):
        nonlocal_tree = real_directory_tree_snapshot(relative)
        global swapped
        if not swapped:
            race_root.rename(overlay_root / "race-held")
            race_root.symlink_to(outside_race, target_is_directory=True)
            swapped = True
        return nonlocal_tree
    helper.directory_tree_snapshot = swap_parent_after_snapshot
    race_blocked = False
    try:
        helper.remove_managed_overlay_directories(race_item, race_allowed)
    except (helper.ReleaseError, OSError):
        race_blocked = True
    helper.directory_tree_snapshot = real_directory_tree_snapshot
    require(race_blocked and (outside_race / "sub").is_dir(), "ROLLBACK_PARENT_SYMLINK_RACE_ESCAPED_TARGET")
    race_root.unlink(); (overlay_root / "race-held").rename(race_root)
    (race_root / "sub").rmdir(); race_root.rmdir()

    # Production live checks bind unmanaged overlay entries to the approved prestate.
    overlay_candidate = type("OverlayCandidate", (), {})()
    overlay_candidate.runtime_rows = [{"runtime_target": "custom/chinaplanetcustomercenter/cpkanban/index.php",
                                       "sha256": hashlib.sha256(b"overlay-new").hexdigest(), "mode": "644"}]
    overlay_candidate.layout = {"custom/chinaplanetcustomercenter/cpkanban": "../cpkanban"}
    overlay_target.write_bytes(b"overlay-new")
    with unittest.mock.patch.object(helper.pwd, "getpwnam", return_value=type("Pw", (), {"pw_uid": uid})()), \
         unittest.mock.patch.object(helper.grp, "getgrnam", return_value=type("Gr", (), {"gr_gid": gid})()), \
         unittest.mock.patch.object(helper, "schema_status", return_value="READY"), \
         unittest.mock.patch.object(helper, "http_status", return_value=200):
        helper.live_checks(overlay_candidate, overlay_snapshot)
        overlay_extra.write_bytes(b"unmanaged-drift")
        drift_blocked = False
        try:
            helper.live_checks(overlay_candidate, overlay_snapshot)
        except helper.ReleaseError:
            drift_blocked = True
        require(drift_blocked, "LIVE_CHECK_ACCEPTED_UNMANAGED_OVERLAY_DRIFT")
    overlay_extra.write_bytes(b"preserve")
    overlay_target.write_bytes(b"overlay-old")

    # Restore must reject an unknown concurrent value and leave it untouched.
    attacker = b"concurrent-unknown-runtime"
    target.write_bytes(attacker)
    blocked = False
    try:
        helper.restore_snapshot(*backup, allowed_snapshot=deployed_snapshot)
    except helper.ReleaseError:
        blocked = True
    require(blocked and target.read_bytes() == attacker, "ROLLBACK_CAS_RACE_ACCEPTED")
    target.write_bytes(original)

    # A swapped parent symlink must never be followed by the fd-relative reader.
    outside = sandbox / "outside"; outside.mkdir(); (outside / "secret.php").write_bytes(b"outside")
    (helper.TARGET_ROOT / "unsafe").symlink_to(outside, target_is_directory=True)
    target_fd = os.open(helper.TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY)
    blocked = False
    try:
        try:
            helper.read_file_at(target_fd, "unsafe/secret.php")
        except OSError:
            blocked = True
    finally:
        os.close(target_fd)
    require(blocked, "SYMLINK_PARENT_FOLLOWED")

    # Preflight plan is bound to exact baseline, schema and database identity.
    plan = helper.plan_payload(snapshot, "MIGRATION_REQUIRED_RIGHTS_MISSING", "a" * 64)
    helper.atomic_json(helper.PLAN_FILE, plan)
    require(len(helper.require_plan(snapshot, "MIGRATION_REQUIRED_RIGHTS_MISSING", "a" * 64)) == 64, "PLAN_NOT_ACCEPTED")
    mismatch = False
    try:
        helper.require_plan({"files": [], "layout": []}, "MIGRATION_REQUIRED_RIGHTS_MISSING", "a" * 64)
    except helper.ReleaseError:
        mismatch = True
    require(mismatch, "PLAN_BASELINE_DRIFT_ACCEPTED")

    # An unbound diagnostic helper cannot preflight/apply; the exact reviewed
    # remote baseline passes and any drift remains fail closed.
    reviewed_baseline = helper.EXPECTED_BASELINE_SHA
    require(reviewed_baseline == "39693423340f0e954a789a06cf65c229dab41beb72a7dde27d952449c8529094", "REVIEWED_BASELINE_PIN_MISMATCH")
    helper.EXPECTED_BASELINE_SHA = "UNBOUND_DIAGNOSTIC_ONLY"
    baseline_blocked = False
    try:
        helper.require_expected_baseline(snapshot)
    except helper.ReleaseError:
        baseline_blocked = True
    require(baseline_blocked, "UNBOUND_BASELINE_ACCEPTED")
    helper.EXPECTED_BASELINE_SHA = helper.canonical_sha(snapshot)
    require(helper.require_expected_baseline(snapshot) == helper.canonical_sha(snapshot), "PINNED_BASELINE_REJECTED")
    baseline_drift = False
    try:
        helper.require_expected_baseline({"files": [], "layout": []})
    except helper.ReleaseError:
        baseline_drift = True
    require(baseline_drift, "UNAPPROVED_BASELINE_DRIFT_ACCEPTED")
    helper.EXPECTED_BASELINE_SHA = reviewed_baseline

    # A terminal state invalidates every preflight created at or before its update time.
    plan_created = dt.datetime.fromisoformat(plan["created_at"])
    stale_blocked = False
    try:
        helper.require_plan(snapshot, "MIGRATION_REQUIRED_RIGHTS_MISSING", "a" * 64, plan_created)
    except helper.ReleaseError:
        stale_blocked = True
    require(stale_blocked, "STALE_PLAN_ACCEPTED_AFTER_TERMINAL_STATE")

    # Pending apply reconciles the old code and returns a terminal state; it cannot begin a new apply.
    root_fd = os.open(helper.TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY)
    try:
        helper.atomic_write_target(root_fd, "chinaplanetoperations/class/Example.php", deployed, uid, gid, 0o644)
    finally:
        os.close(root_fd)
    database = helper.DB_BACKUP_ROOT / "backup.sql"; database.write_bytes(b"synthetic"); database.chmod(0o600)
    database_sha = helper.sha256_file(database)
    saved = []
    real_preserve_state = helper.preserve_state
    helper.preserve_state = lambda current, status, **fields: saved.append((status, {**current, **fields})) or {"status": status, **current, **fields}
    state = {"status": "PENDING_APPLY_CODE", "request_id": "request", "code_backup": str(backup[0]),
             "code_backup_sha256": backup[1], "prestate": str(backup[2]), "prestate_sha256": backup[3],
             "database_backup": str(database), "database_backup_sha256": database_sha}
    result = helper.reconcile_pending(state, deployed_snapshot)
    require(result == "APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED" and target.read_bytes() == original, "PENDING_APPLY_NOT_RESTORED")
    require(saved[-1][0] == "APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED", "PENDING_APPLY_NOT_TERMINAL")

    # Pending rollback re-applies the safety backup and returns without another rollback.
    root_fd = os.open(helper.TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY)
    try:
        helper.atomic_write_target(root_fd, "chinaplanetoperations/class/Example.php", deployed, uid, gid, 0o644)
    finally:
        os.close(root_fd)
    reapply = helper.write_backup(None, deployed_snapshot, "reapply")
    target.write_bytes(original)
    rollback_state = {"status": "PENDING_ROLLBACK", "reapply_code_backup": str(reapply[0]),
                      "reapply_code_backup_sha256": reapply[1], "reapply_prestate": str(reapply[2]),
                      "reapply_prestate_sha256": reapply[3], "code_backup": str(backup[0]),
                      "code_backup_sha256": backup[1], "prestate": str(backup[2]), "prestate_sha256": backup[3]}
    rollback_state["database_identity"] = "a" * 64
    real_database_identity = helper.database_identity
    real_live_checks = helper.live_checks
    helper.database_identity = lambda candidate: "a" * 64
    helper.live_checks = lambda candidate, baseline_snapshot: None
    result = helper.reconcile_pending(rollback_state, deployed_snapshot, object())
    require(result == "ROLLBACK_FAILED_REAPPLIED_STATE_DEPLOYED" and target.read_bytes() == deployed, "PENDING_ROLLBACK_NOT_REAPPLIED")
    require(saved[-1][0] == "DEPLOYED" and saved[-1][1]["last_rollback_result"] == "FAILED_REAPPLIED", "REAPPLY_STATE_NOT_VERIFIABLE")
    helper.database_identity = real_database_identity
    helper.live_checks = real_live_checks

    # A crash-created restore-check database is recorded and removed by reconciliation.
    dropped = []
    real_drop_validation_database = helper.drop_validation_database
    helper.drop_validation_database = dropped.append
    result = helper.reconcile_pending({"status": "PENDING_APPLY_PREPARE", "request_id": "r",
                                       "validation_database": "m074_restore_check_1_deadbeef"})
    require(result == "APPLY_FAILED_NO_MUTATION" and dropped == ["m074_restore_check_1_deadbeef"], "RESTORE_CHECK_DATABASE_NOT_RECONCILED")
    helper.drop_validation_database = real_drop_validation_database

    # Recovery-required state retains every backup and identity field.
    provenance_state = dict(state); provenance_state["database_identity"] = "a" * 64
    real_restore = helper.restore_snapshot
    helper.restore_snapshot = lambda *args, **kwargs: (_ for _ in ()).throw(RuntimeError("fault"))
    failed = False
    try:
        helper.reconcile_pending(provenance_state, deployed_snapshot)
    except RuntimeError:
        failed = True
    require(failed and saved[-1][0] == "APPLY_FAILED_RECOVERY_REQUIRED", "RECOVERY_FAILURE_NOT_TERMINAL")
    for key in ("code_backup", "code_backup_sha256", "prestate", "prestate_sha256", "database_backup", "database_backup_sha256", "database_identity"):
        require(saved[-1][1].get(key) == provenance_state[key], "RECOVERY_PROVENANCE_LOST_" + key.upper())
    helper.restore_snapshot = real_restore
    helper.preserve_state = real_preserve_state

    # Deployed verify/no-op/rollback identity binding fails closed.
    helper.require_saved_database_identity({"database_identity": "a" * 64}, "a" * 64)
    mismatch = False
    try: helper.require_saved_database_identity({"database_identity": "a" * 64}, "b" * 64)
    except helper.ReleaseError: mismatch = True
    require(mismatch, "DATABASE_IDENTITY_DRIFT_ACCEPTED")

    # Identity drift immediately before schema apply stops and restores code.
    target.write_bytes(original)
    mutation_states = []
    real_apply_candidate = helper.apply_candidate
    real_database_identity = helper.database_identity
    real_release_snapshot = helper.release_snapshot
    real_save_state = helper.save_state
    def fake_apply_candidate(candidate, baseline):
        root_fd = os.open(helper.TARGET_ROOT, os.O_RDONLY | os.O_DIRECTORY)
        try: helper.atomic_write_target(root_fd, "chinaplanetoperations/class/Example.php", deployed, uid, gid, 0o644, baseline["files"][0])
        finally: os.close(root_fd)
    helper.apply_candidate = fake_apply_candidate
    helper.database_identity = lambda candidate: "b" * 64
    helper.release_snapshot = lambda candidate: deployed_snapshot
    helper.save_state = lambda status, **fields: mutation_states.append(status) or {"status": status, **fields}
    drift_blocked = False
    try:
        helper.execute_apply_mutation(object(), snapshot, "a" * 64, {"request_id": "r"}, *backup)
    except helper.ReleaseError:
        drift_blocked = True
    require(drift_blocked and target.read_bytes() == original, "IDENTITY_DRIFT_DID_NOT_RESTORE_CODE")
    require(mutation_states == ["APPLY_FAILED_AUTO_ROLLED_BACK_SCHEMA_RETAINED"], "IDENTITY_DRIFT_STATE_INVALID")
    helper.apply_candidate = real_apply_candidate
    helper.database_identity = real_database_identity
    helper.release_snapshot = real_release_snapshot
    helper.save_state = real_save_state

    # rollback command cleans PENDING_APPLY_PREPARE before any unrelated full-environment check.
    command_dropped = []
    real_prepare_roots = helper.prepare_roots
    real_acquire_lock = helper.acquire_lock
    real_load_state = helper.load_state
    real_check_environment = helper.check_environment
    real_drop_validation_database = helper.drop_validation_database
    real_preserve_state = helper.preserve_state
    helper.prepare_roots = lambda: None
    helper.acquire_lock = lambda: os.open(sandbox / "command.lock", os.O_RDWR | os.O_CREAT, 0o600)
    helper.load_state = lambda: {"status": "PENDING_APPLY_PREPARE", "validation_database": "m074_restore_check_2_cafebabe"}
    helper.check_environment = lambda: (_ for _ in ()).throw(RuntimeError("unrelated runtime unavailable"))
    helper.drop_validation_database = command_dropped.append
    helper.preserve_state = lambda state, status, **fields: {"status": status, **state, **fields}
    output = io.StringIO()
    with contextlib.redirect_stdout(output): helper.command_rollback()
    require(command_dropped == ["m074_restore_check_2_cafebabe"] and "NEW_ROLLBACK_STARTED=NO" in output.getvalue(),
            "ROLLBACK_COMMAND_CLEANUP_BLOCKED_BY_ENVIRONMENT")
    helper.prepare_roots = real_prepare_roots
    helper.acquire_lock = real_acquire_lock
    helper.load_state = real_load_state
    helper.check_environment = real_check_environment
    helper.drop_validation_database = real_drop_validation_database
    helper.preserve_state = real_preserve_state

    # Validation DB cleanup rejects every name outside the exact M074 prefix.
    blocked = False
    try: helper.drop_validation_database("customer_production")
    except helper.ReleaseError: blocked = True
    require(blocked, "VALIDATION_DATABASE_PREFIX_NOT_ENFORCED")

    # HTTP checks install a redirect-denying handler and return the 302 itself.
    handlers = []
    class FakeOpener:
        def open(self, request, timeout):
            raise helper.urllib.error.HTTPError(request.full_url, 302, "redirect", {}, None)
    def fake_build_opener(*args):
        handlers.extend(args); return FakeOpener()
    with unittest.mock.patch.object(helper.urllib.request, "build_opener", fake_build_opener):
        require(helper.http_status("http://127.0.0.1:18088/") == 302, "REDIRECT_STATUS_NOT_RETURNED")
    redirect_handlers = [item for item in handlers if isinstance(item, helper.urllib.request.HTTPRedirectHandler)]
    proxy_handlers = [item for item in handlers if isinstance(item, helper.urllib.request.ProxyHandler)]
    require(len(redirect_handlers) == 1 and redirect_handlers[0].redirect_request(None, None, 302, "redirect", {}, "https://production.invalid/") is None,
            "HTTP_REDIRECT_HANDLER_MISSING")
    require(len(proxy_handlers) == 1 and proxy_handlers[0].proxies == {}, "HTTP_PROXY_NOT_DISABLED")

    # The status command reflects absent/ready plans and recovery-required state.
    real_prepare_roots = helper.prepare_roots
    helper.prepare_roots = lambda: None
    helper.PLAN_FILE.unlink(missing_ok=True); helper.STATE_FILE.unlink(missing_ok=True)
    output = io.StringIO()
    with contextlib.redirect_stdout(output): helper.command_status()
    require("PREFLIGHT_PLAN=ABSENT" in output.getvalue() and "APPLY_AVAILABLE=NO" in output.getvalue(), "STATUS_ABSENT_PLAN_MISLEADING")
    helper.atomic_json(helper.PLAN_FILE, helper.plan_payload(snapshot, "MIGRATION_REQUIRED_RIGHTS_MISSING", "a" * 64))
    output = io.StringIO()
    with contextlib.redirect_stdout(output): helper.command_status()
    require("PREFLIGHT_PLAN=READY" in output.getvalue() and "APPLY_AVAILABLE=YES" in output.getvalue(), "STATUS_READY_PLAN_NOT_AVAILABLE")
    recovery = helper.state_payload("APPLY_FAILED_RECOVERY_REQUIRED", request_id="r", schema_failure_reason="RIGHT_INSTALL_FAILED")
    helper.atomic_json(helper.STATE_FILE, recovery)
    output = io.StringIO()
    with contextlib.redirect_stdout(output): helper.command_status()
    require("APPLY_AVAILABLE=NO" in output.getvalue() and "APPLY_REASON=RECOVERY_REQUIRED" in output.getvalue() and "SCHEMA_FAILURE_REASON=RIGHT_INSTALL_FAILED" in output.getvalue(), "STATUS_RECOVERY_STATE_MISLEADING")
    helper.atomic_json(helper.STATE_FILE, helper.state_payload("APPLY_FAILED_RECOVERY_REQUIRED", request_id="r", schema_failure_reason="database leaked"))
    invalid_state_blocked = False
    try: helper.load_state()
    except helper.ReleaseError: invalid_state_blocked = True
    require(invalid_state_blocked, "UNSAFE_SCHEMA_REASON_ACCEPTED_IN_STATE")
    helper.prepare_roots = real_prepare_roots

source = HELPER.read_text(encoding="utf-8")
require('existing["status"] == "PENDING_APPLY_PREPARE"' in source and 'result = reconcile_pending(existing)' in source and 'NEW_APPLY_STARTED=NO' in source, "APPLY_RECONCILE_STOP_GATE_MISSING")
require('M074_STAGING_STATUS={state[\'status\']}' in source, "STATUS_DOES_NOT_REPORT_ACTUAL_STATE")
require('BACKUPS_REVALIDATED=YES' in source and 'validate_code_backup' in source, "VERIFY_BACKUP_REVALIDATION_MISSING")
require('O_NOFOLLOW' in source and 'RENAME_EXCHANGE' in source and 'RENAME_NOREPLACE' in source, "FD_RELATIVE_CAS_SAFETY_MISSING")
require('validation_database = make_validation_database_name()' in source and 'save_state("PENDING_APPLY_PREPARE", **fields)' in source, "RESTORE_CHECK_STATE_GATE_MISSING")
require('require_saved_database_identity' in source and 'rollback_checks' in source, "VERIFY_OR_ROLLBACK_GATE_MISSING")
require(source.count('database_identity(candidate) != identity') >= 2, "DATABASE_IDENTITY_RECHECKS_MISSING")
require('GENERATION_EXPRESSION' in source and 'CHECK_CLAUSE' in source and 'PARTITION_EXPRESSION' in source, "DATABASE_FINGERPRINT_METADATA_MISSING")
require("case \"$table\" in ''|*[!A-Za-z0-9_]*) exit 1;; esac" in source, "DATABASE_FINGERPRINT_IDENTIFIER_ALLOWLIST_MISSING")

print("M074_STAGING_HELPER_FAILURES=PASS CASES=31")
