#!/usr/bin/env python3
"""Fail-closed state engine for the ChinaPlanetOS unified release controller."""

from __future__ import annotations

import fcntl
import hashlib
import json
import os
import pathlib
import re
import subprocess
import tempfile
import time
import uuid
from dataclasses import dataclass
from typing import Callable, Mapping, Sequence

ALLOWED_ACTIONS = ("compare", "preflight", "apply", "verify", "status", "rollback")
READ_ONLY_ACTIONS = ("compare", "preflight", "verify", "status")
RELEASE_ID_RE = re.compile(r"^M\d{3}-R\d{2}$")
COMPONENT_ID_RE = re.compile(r"^[a-z][a-z0-9-]{1,31}$")
FILE_NAME_RE = re.compile(r"^[a-z0-9][a-z0-9._-]{1,127}$")
SHA256_RE = re.compile(r"^[0-9a-f]{64}$")
COMMIT_RE = re.compile(r"^[0-9a-f]{40}$")
ADAPTER_ROOT = pathlib.Path("/usr/local/libexec/chinaplanetos-release")
SAFE_PATH = "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
STATE_STATUSES = {
    "NOT_APPLIED", "APPLY_PENDING", "DEPLOYED_UNVERIFIED", "DEPLOYED_VERIFIED",
    "ROLLBACK_PENDING", "ROLLED_BACK_RETAINED_STATE_REPORTED",
    "FAILED_TERMINAL", "FAILED_RECOVERY_REQUIRED",
}
REQUIRED_COMPONENTS = ("dolibarr", "react", "wordpress", "mobile")


class ReleaseError(RuntimeError):
    def __init__(self, reason: str, recovery: str = "INSPECT_RELEASE_EVIDENCE") -> None:
        super().__init__(reason)
        self.reason = reason
        self.recovery = recovery
        self.recorded = False


@dataclass(frozen=True)
class Component:
    component_id: str
    commit: str
    artifact: str
    artifact_sha256: str
    manifest: str
    manifest_sha256: str
    layout: str
    layout_sha256: str
    adapter: str
    adapter_sha256: str
    schema_policy: str
    config_policy: str
    data_policy: str


def sha256_file(path: pathlib.Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def sha256_fd(fd: int) -> str:
    digest = hashlib.sha256()
    os.lseek(fd, 0, os.SEEK_SET)
    while True:
        chunk = os.read(fd, 1024 * 1024)
        if not chunk:
            break
        digest.update(chunk)
    os.lseek(fd, 0, os.SEEK_SET)
    return digest.hexdigest()


def _safe_file(path: pathlib.Path, *, expected_mode: int | None = None, root_owned: bool = False) -> None:
    try:
        stat = path.lstat()
    except FileNotFoundError as exc:
        raise ReleaseError("FILE_ABSENT", f"INSTALL_PINNED_FILE:{path.name}") from exc
    if path.is_symlink() or not path.is_file():
        raise ReleaseError("FILE_TYPE_INVALID", f"REINSTALL_PINNED_FILE:{path.name}")
    if expected_mode is not None and stat.st_mode & 0o777 != expected_mode:
        raise ReleaseError("FILE_MODE_INVALID", f"RESTORE_MODE:{path.name}:{expected_mode:o}")
    if root_owned and (stat.st_uid != 0 or stat.st_gid != 0):
        raise ReleaseError("FILE_OWNER_INVALID", f"RESTORE_ROOT_OWNERSHIP:{path.name}")


def _safe_directory(path: pathlib.Path, *, expected_mode: int | None = None, root_owned: bool = False) -> None:
    try:
        stat = path.lstat()
    except FileNotFoundError as exc:
        raise ReleaseError("DIRECTORY_ABSENT", f"INSTALL_PRIVATE_DIRECTORY:{path.name}") from exc
    if path.is_symlink() or not path.is_dir():
        raise ReleaseError("DIRECTORY_TYPE_INVALID", f"RESTORE_PRIVATE_DIRECTORY:{path.name}")
    if expected_mode is not None and stat.st_mode & 0o777 != expected_mode:
        raise ReleaseError("DIRECTORY_MODE_INVALID", f"RESTORE_DIRECTORY_MODE:{path.name}:{expected_mode:o}")
    if root_owned and (stat.st_uid != 0 or stat.st_gid != 0):
        raise ReleaseError("DIRECTORY_OWNER_INVALID", f"RESTORE_ROOT_OWNERSHIP:{path.name}")


def _safe_directory_chain(path: pathlib.Path) -> None:
    current = path
    while True:
        try:
            stat = current.lstat()
        except FileNotFoundError as exc:
            raise ReleaseError("DIRECTORY_CHAIN_ABSENT", f"RESTORE_DIRECTORY_CHAIN:{path}") from exc
        if current.is_symlink() or not current.is_dir() or stat.st_uid != 0 or stat.st_gid != 0 or stat.st_mode & 0o022:
            raise ReleaseError("DIRECTORY_CHAIN_UNSAFE", f"RESTORE_ROOT_DIRECTORY_CHAIN:{path}")
        if current == current.parent:
            break
        current = current.parent


def load_json(path: pathlib.Path, *, expected_mode: int | None = None, root_owned: bool = False) -> dict:
    _safe_file(path, expected_mode=expected_mode, root_owned=root_owned)
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise ReleaseError("JSON_INVALID", f"REINSTALL_PINNED_FILE:{path.name}") from exc
    if not isinstance(value, dict):
        raise ReleaseError("JSON_ROOT_INVALID", f"REINSTALL_PINNED_FILE:{path.name}")
    return value


def verify_pinned_json(config_root: pathlib.Path, name: str) -> dict:
    value_path = config_root / f"{name}.json"
    pin_path = config_root / f"{name}.sha256"
    _safe_file(value_path, expected_mode=0o600, root_owned=True)
    _safe_file(pin_path, expected_mode=0o600, root_owned=True)
    try:
        pin = pin_path.read_text(encoding="ascii").strip()
    except (OSError, UnicodeError) as exc:
        raise ReleaseError("DESCRIPTOR_PIN_INVALID", f"REINSTALL_PINNED_DESCRIPTOR:{name}") from exc
    if not SHA256_RE.fullmatch(pin) or sha256_file(value_path) != pin:
        raise ReleaseError("DESCRIPTOR_CHECKSUM_MISMATCH", f"REINSTALL_PINNED_DESCRIPTOR:{name}")
    return load_json(value_path, expected_mode=0o600, root_owned=True)


def validate_release(value: Mapping[str, object]) -> tuple[str, tuple[Component, ...]]:
    if value.get("schemaVersion") != 1:
        raise ReleaseError("RELEASE_SCHEMA_INVALID", "INSTALL_SUPPORTED_RELEASE_DESCRIPTOR")
    release_id = str(value.get("releaseId", ""))
    if not RELEASE_ID_RE.fullmatch(release_id):
        raise ReleaseError("RELEASE_ID_INVALID", "INSTALL_SUPPORTED_RELEASE_DESCRIPTOR")
    raw_components = value.get("components")
    if not isinstance(raw_components, list) or [str(item.get("id", "")) if isinstance(item, dict) else "" for item in raw_components] != list(REQUIRED_COMPONENTS):
        raise ReleaseError("COMPONENT_SET_INVALID", "REBUILD_COMPLETE_RELEASE_DESCRIPTOR")
    components: list[Component] = []
    seen: set[str] = set()
    for raw in raw_components:
        if not isinstance(raw, dict):
            raise ReleaseError("COMPONENT_INVALID", "REBUILD_COMPLETE_RELEASE_DESCRIPTOR")
        component_id = str(raw.get("id", ""))
        if not COMPONENT_ID_RE.fullmatch(component_id) or component_id in seen:
            raise ReleaseError("COMPONENT_ID_INVALID", "REBUILD_COMPLETE_RELEASE_DESCRIPTOR")
        seen.add(component_id)
        commit = str(raw.get("commit", ""))
        artifact = str(raw.get("artifact", ""))
        artifact_sha = str(raw.get("artifactSha256", ""))
        manifest = str(raw.get("manifest", ""))
        manifest_sha = str(raw.get("manifestSha256", ""))
        layout = str(raw.get("layout", ""))
        layout_sha = str(raw.get("layoutSha256", ""))
        adapter = str(raw.get("adapter", ""))
        adapter_sha = str(raw.get("adapterSha256", ""))
        policies = {
            "schema": str(raw.get("schemaPolicy", "")),
            "config": str(raw.get("configPolicy", "")),
            "data": str(raw.get("dataPolicy", "")),
        }
        if not COMMIT_RE.fullmatch(commit):
            raise ReleaseError("COMPONENT_COMMIT_INVALID", f"REBUILD_COMPONENT:{component_id}")
        if not FILE_NAME_RE.fullmatch(artifact) or not FILE_NAME_RE.fullmatch(manifest):
            raise ReleaseError("COMPONENT_FILE_NAME_INVALID", f"REBUILD_COMPONENT:{component_id}")
        if component_id == "dolibarr":
            if not FILE_NAME_RE.fullmatch(layout) or not SHA256_RE.fullmatch(layout_sha):
                raise ReleaseError("COMPONENT_LAYOUT_INVALID", "REBUILD_COMPONENT:dolibarr")
        elif layout or layout_sha:
            raise ReleaseError("COMPONENT_LAYOUT_UNEXPECTED", f"REBUILD_COMPONENT:{component_id}")
        if not FILE_NAME_RE.fullmatch(adapter) or not adapter.startswith(component_id + "-"):
            raise ReleaseError("COMPONENT_ADAPTER_INVALID", f"REBUILD_COMPONENT:{component_id}")
        if not all(SHA256_RE.fullmatch(value) for value in (artifact_sha, manifest_sha, adapter_sha)):
            raise ReleaseError("COMPONENT_PIN_INVALID", f"REBUILD_COMPONENT:{component_id}")
        if policies["schema"] not in ("none", "additive-retain-on-code-rollback"):
            raise ReleaseError("SCHEMA_POLICY_INVALID", f"REVIEW_COMPONENT:{component_id}")
        if policies["config"] not in ("none", "external-retain", "external-restore"):
            raise ReleaseError("CONFIG_POLICY_INVALID", f"REVIEW_COMPONENT:{component_id}")
        if policies["data"] not in ("none", "retain", "restore-approved-backup-only"):
            raise ReleaseError("DATA_POLICY_INVALID", f"REVIEW_COMPONENT:{component_id}")
        components.append(Component(
            component_id, commit, artifact, artifact_sha, manifest, manifest_sha,
            layout, layout_sha, adapter, adapter_sha,
            policies["schema"], policies["config"], policies["data"],
        ))
    return release_id, tuple(components)


def validate_target(value: Mapping[str, object], components: Sequence[Component]) -> str:
    if value.get("schemaVersion") != 1:
        raise ReleaseError("TARGET_SCHEMA_INVALID", "INSTALL_REVIEWED_TARGET_PROFILE")
    environment = str(value.get("environment", ""))
    if environment not in ("staging", "production"):
        raise ReleaseError("TARGET_ENVIRONMENT_INVALID", "INSTALL_REVIEWED_TARGET_PROFILE")
    target_components = value.get("components")
    if target_components != [component.component_id for component in components]:
        raise ReleaseError("TARGET_COMPONENT_SET_MISMATCH", "INSTALL_COMPLETE_TARGET_PROFILE")
    return environment


def atomic_write_json(path: pathlib.Path, value: Mapping[str, object], mode: int = 0o600) -> None:
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if path.parent.is_symlink():
        raise ReleaseError("STATE_PARENT_INVALID", "RESTORE_PRIVATE_STATE_DIRECTORY")
    payload = (json.dumps(value, sort_keys=True, separators=(",", ":")) + "\n").encode("utf-8")
    fd, raw_path = tempfile.mkstemp(prefix=f".{path.name}.", dir=path.parent)
    temporary = pathlib.Path(raw_path)
    try:
        os.fchmod(fd, mode)
        with os.fdopen(fd, "wb", closefd=True) as handle:
            handle.write(payload)
            handle.flush()
            os.fsync(handle.fileno())
        os.replace(temporary, path)
        directory_fd = os.open(path.parent, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))
        try:
            os.fsync(directory_fd)
        finally:
            os.close(directory_fd)
    finally:
        if temporary.exists():
            temporary.unlink()


def create_json(path: pathlib.Path, value: Mapping[str, object], mode: int = 0o600) -> None:
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if path.parent.is_symlink():
        raise ReleaseError("STATE_PARENT_INVALID", "RESTORE_PRIVATE_STATE_DIRECTORY")
    payload = (json.dumps(value, sort_keys=True, separators=(",", ":")) + "\n").encode("utf-8")
    try:
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, mode)
    except FileExistsError as exc:
        raise ReleaseError("REQUEST_COLLISION", "RETRY_WITH_SECURE_REQUEST_ID") from exc
    try:
        os.write(fd, payload)
        os.fsync(fd)
    finally:
        os.close(fd)
    directory_fd = os.open(path.parent, os.O_RDONLY | getattr(os, "O_DIRECTORY", 0))
    try:
        os.fsync(directory_fd)
    finally:
        os.close(directory_fd)


def append_audit(path: pathlib.Path, value: Mapping[str, object]) -> None:
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    if path.parent.is_symlink():
        raise ReleaseError("AUDIT_PARENT_INVALID", "RESTORE_PRIVATE_STATE_DIRECTORY")
    payload = (json.dumps(value, sort_keys=True, separators=(",", ":")) + "\n").encode("utf-8")
    fd = os.open(path, os.O_WRONLY | os.O_APPEND | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    try:
        os.write(fd, payload)
        os.fsync(fd)
    finally:
        os.close(fd)


def default_component_state(component: Component) -> dict[str, str]:
    return {
        "commit": component.commit,
        "codeState": "NOT_INSPECTED",
        "schemaState": "NOT_INSPECTED",
        "configState": "NOT_INSPECTED",
        "dataState": "NOT_INSPECTED",
    }


class ReleaseController:
    def __init__(
        self,
        *,
        release: Mapping[str, object],
        target: Mapping[str, object],
        artifacts_root: pathlib.Path,
        manifests_root: pathlib.Path,
        adapter_root: pathlib.Path,
        state_root: pathlib.Path,
        runner: Callable[[Component, str], Mapping[str, object]],
        now: Callable[[], int] = lambda: int(time.time()),
        request_id: Callable[[], str] = lambda: uuid.uuid4().hex,
        enforce_metadata: bool = True,
    ) -> None:
        self.release_id, self.components = validate_release(release)
        self.environment = validate_target(target, self.components)
        self.artifacts_root = artifacts_root
        self.manifests_root = manifests_root
        self.adapter_root = adapter_root
        self.state_root = state_root
        self.runner = runner
        self.now = now
        self.request_id = request_id
        self.enforce_metadata = enforce_metadata
        self.state_file = state_root / "release-state.json"
        self.audit_file = state_root / "audit.jsonl"
        self.requests_root = state_root / "requests"

    def verify_pins(self) -> None:
        directories = (
            (self.artifacts_root, 0o700),
            (self.manifests_root, 0o700),
            (self.adapter_root, 0o755),
        )
        for directory, mode in directories:
            if self.enforce_metadata:
                _safe_directory_chain(directory)
            _safe_directory(directory, expected_mode=mode if self.enforce_metadata else None, root_owned=self.enforce_metadata)
        for component in self.components:
            checks = (
                (self.artifacts_root / component.artifact, component.artifact_sha256, "ARTIFACT", 0o600),
                (self.manifests_root / component.manifest, component.manifest_sha256, "MANIFEST", 0o644),
                (self.adapter_root / component.adapter, component.adapter_sha256, "ADAPTER", 0o755),
            )
            for path, expected, kind, mode in checks:
                _safe_file(path, expected_mode=mode if self.enforce_metadata else None, root_owned=self.enforce_metadata)
                if sha256_file(path) != expected:
                    raise ReleaseError(f"{kind}_CHECKSUM_MISMATCH", f"RESTORE_PINNED_{kind}:{component.component_id}")
            if component.layout:
                layout_path = self.manifests_root / component.layout
                _safe_file(layout_path, expected_mode=0o644 if self.enforce_metadata else None, root_owned=self.enforce_metadata)
                if sha256_file(layout_path) != component.layout_sha256:
                    raise ReleaseError("LAYOUT_CHECKSUM_MISMATCH", f"RESTORE_PINNED_LAYOUT:{component.component_id}")

    def _base_state(self) -> dict:
        return {
            "schemaVersion": 1,
            "releaseId": self.release_id,
            "environment": self.environment,
            "status": "NOT_APPLIED",
            "updatedAt": self.now(),
            "components": {component.component_id: default_component_state(component) for component in self.components},
            "lastChecks": {},
        }

    def _load_state(self) -> dict:
        if not self.state_file.exists():
            return self._base_state()
        state = load_json(self.state_file, expected_mode=0o600 if self.enforce_metadata else None, root_owned=self.enforce_metadata)
        if state.get("releaseId") != self.release_id or state.get("environment") != self.environment:
            raise ReleaseError("STATE_RELEASE_MISMATCH", "RECONCILE_PREVIOUS_RELEASE_STATE")
        if set(state.get("components", {})) != {component.component_id for component in self.components}:
            raise ReleaseError("STATE_COMPONENT_SET_MISMATCH", "RECONCILE_PREVIOUS_RELEASE_STATE")
        if state.get("status") not in STATE_STATUSES or not isinstance(state.get("lastChecks", {}), dict):
            raise ReleaseError("STATE_STATUS_INVALID", "RECONCILE_PREVIOUS_RELEASE_STATE")
        expected = {component.component_id: component for component in self.components}
        for component_id, component_state in state["components"].items():
            if not isinstance(component_state, dict) or component_state.get("commit") != expected[component_id].commit:
                raise ReleaseError("STATE_COMPONENT_INVALID", "RECONCILE_PREVIOUS_RELEASE_STATE")
            for key in ("codeState", "schemaState", "configState", "dataState"):
                if not re.fullmatch(r"[A-Z][A-Z0-9_]{1,63}", str(component_state.get(key, ""))):
                    raise ReleaseError("STATE_VALUE_INVALID", "RECONCILE_PREVIOUS_RELEASE_STATE")
        return state

    def _save_state(self, state: dict, status: str) -> None:
        state["status"] = status
        state["updatedAt"] = self.now()
        atomic_write_json(self.state_file, state)

    def _adapter(self, component: Component, action: str) -> Mapping[str, object]:
        try:
            result = self.runner(component, action)
        except ReleaseError:
            raise
        except Exception as exc:
            raise ReleaseError("ADAPTER_EXECUTION_FAILED", f"INSPECT_ADAPTER:{component.component_id}:{action}") from exc
        if not isinstance(result, Mapping) or result.get("component") != component.component_id or result.get("action") != action:
            raise ReleaseError("ADAPTER_RESPONSE_INVALID", f"INSPECT_ADAPTER:{component.component_id}:{action}")
        common_keys = {"ok", "component", "action"}
        if result.get("ok") is not True:
            if set(result) != common_keys | {"reason", "recovery"}:
                raise ReleaseError("ADAPTER_RESPONSE_FIELDS_INVALID", f"INSPECT_ADAPTER:{component.component_id}:{action}")
            reason = str(result.get("reason", "UNKNOWN_FAILURE"))
            recovery = str(result.get("recovery", f"INSPECT_ADAPTER:{component.component_id}:{action}"))
            if not re.fullmatch(r"[A-Z0-9_:-]{1,160}", reason) or not re.fullmatch(r"[A-Z0-9_:.\-/]{1,240}", recovery):
                raise ReleaseError("ADAPTER_ERROR_UNSAFE", f"INSPECT_ADAPTER:{component.component_id}:{action}")
            raise ReleaseError(f"{component.component_id.upper().replace('-', '_')}_{reason}", recovery)
        state_keys = {"codeState", "schemaState", "configState", "dataState"}
        if set(result) != common_keys | state_keys:
            raise ReleaseError("ADAPTER_RESPONSE_FIELDS_INVALID", f"INSPECT_ADAPTER:{component.component_id}:{action}")
        return {key: result[key] for key in ("ok", "component", "action", "codeState", "schemaState", "configState", "dataState")}

    @staticmethod
    def _merge_component_state(state: dict, component: Component, result: Mapping[str, object]) -> None:
        current = state["components"][component.component_id]
        for key in ("codeState", "schemaState", "configState", "dataState"):
            value = str(result.get(key, current[key]))
            if not re.fullmatch(r"[A-Z][A-Z0-9_]{1,63}", value):
                raise ReleaseError("ADAPTER_STATE_INVALID", f"INSPECT_ADAPTER:{component.component_id}")
            current[key] = value

    def _record_terminal(self, request: dict, state: dict, state_status: str) -> None:
        try:
            atomic_write_json(self.requests_root / f"{request['requestId']}.json", request)
        except OSError as exc:
            raise ReleaseError("TERMINAL_REQUEST_WRITE_FAILED", "REPAIR_PRIVATE_STATE_STORAGE") from exc
        try:
            self._save_state(state, state_status)
        except OSError as exc:
            raise ReleaseError("TERMINAL_STATE_WRITE_FAILED", "REPAIR_PRIVATE_STATE_STORAGE") from exc
        try:
            append_audit(self.audit_file, request)
        except OSError as exc:
            raise ReleaseError("TERMINAL_AUDIT_WRITE_FAILED", "REPAIR_PRIVATE_STATE_STORAGE") from exc

    def _record_unowned_terminal(self, request: dict, state: dict, failure: ReleaseError) -> None:
        collided_request_id = request["requestId"]
        for _ in range(3):
            evidence = dict(request)
            evidence.update(
                requestId=uuid.uuid4().hex,
                status="FAILED_TERMINAL",
                reason=failure.reason,
                recovery=failure.recovery,
                finishedAt=self.now(),
            )
            if failure.reason == "REQUEST_COLLISION":
                evidence["collidedRequestId"] = collided_request_id
            try:
                create_json(self.requests_root / f"{evidence['requestId']}.json", evidence)
            except ReleaseError as collision:
                if collision.reason == "REQUEST_COLLISION":
                    continue
                raise
            try:
                self._save_state(state, "FAILED_TERMINAL")
                append_audit(self.audit_file, evidence)
            except OSError as exc:
                raise ReleaseError("FAILURE_EVIDENCE_WRITE_FAILED", "REPAIR_PRIVATE_STATE_STORAGE") from exc
            return
        raise ReleaseError("FAILURE_EVIDENCE_COLLISION", "REPAIR_SECURE_REQUEST_ID_SOURCE")

    def run(self, action: str) -> dict:
        if action not in ALLOWED_ACTIONS:
            raise ReleaseError("COMMAND_NOT_ALLOWED", "USE_EXACT_APPROVED_ACTION")
        state = self._load_state()
        if action == "status":
            self.verify_pins()
            return {"ok": True, "action": action, "releaseId": self.release_id, "environment": self.environment, "state": state}

        request = {
            "requestId": self.request_id(),
            "releaseId": self.release_id,
            "environment": self.environment,
            "action": action,
            "status": "PENDING",
            "startedAt": self.now(),
        }
        if not re.fullmatch(r"[0-9a-f]{32}", request["requestId"]):
            raise ReleaseError("REQUEST_ID_INVALID", "RETRY_WITH_SECURE_REQUEST_ID")
        apply_attempted: list[Component] = []
        rollback_completed: list[Component] = []
        rollback_current: Component | None = None
        request_recorded = False
        try:
            create_json(self.requests_root / f"{request['requestId']}.json", request)
            request_recorded = True
            append_audit(self.audit_file, request)
            self.verify_pins()
            if action in ("compare", "preflight", "verify"):
                results = []
                for component in self.components:
                    result = self._adapter(component, action)
                    self._merge_component_state(state, component, result)
                    results.append(dict(result))
                state["lastChecks"][action] = {"status": "PASS", "at": self.now()}
                next_status = "DEPLOYED_VERIFIED" if action == "verify" and state["status"] == "DEPLOYED_UNVERIFIED" else state["status"]
                self._save_state(state, next_status)
                request.update(status="PASS", finishedAt=self.now())
                self._record_terminal(request, state, next_status)
                return {"ok": True, "action": action, "releaseId": self.release_id, "results": results}

            if action == "apply":
                for component in self.components:
                    self._adapter(component, "preflight")
                self._save_state(state, "APPLY_PENDING")
                for component in self.components:
                    apply_attempted.append(component)
                    result = self._adapter(component, "apply")
                    self._merge_component_state(state, component, result)
                    self._save_state(state, "APPLY_PENDING")
                self._save_state(state, "DEPLOYED_UNVERIFIED")
            elif action == "rollback":
                self._save_state(state, "ROLLBACK_PENDING")
                for component in reversed(self.components):
                    rollback_current = component
                    result = self._adapter(component, "rollback")
                    self._merge_component_state(state, component, result)
                    rollback_completed.append(component)
                    rollback_current = None
                    self._save_state(state, "ROLLBACK_PENDING")
                self._save_state(state, "ROLLED_BACK_RETAINED_STATE_REPORTED")

            request.update(status="PASS", finishedAt=self.now())
            self._record_terminal(request, state, state["status"])
            return {"ok": True, "action": action, "releaseId": self.release_id, "state": state}
        except Exception as raw_failure:
            failure = raw_failure if isinstance(raw_failure, ReleaseError) else ReleaseError("STATE_STORAGE_FAILED", "REPAIR_PRIVATE_STATE_STORAGE")
            rollback_failures: list[str] = []
            if action == "apply" and apply_attempted:
                for component in reversed(apply_attempted):
                    try:
                        result = self._adapter(component, "rollback")
                        self._merge_component_state(state, component, result)
                    except ReleaseError as rollback_failure:
                        rollback_failures.append(f"{component.component_id}:{rollback_failure.reason}")
            if action == "rollback":
                if rollback_current is not None:
                    rollback_failures.append(f"{rollback_current.component_id}:{failure.reason}")
                elif len(rollback_completed) == len(self.components):
                    rollback_failures.append(f"TERMINALIZATION:{failure.reason}")
                elif rollback_completed:
                    rollback_failures.append(f"STATE_AFTER:{rollback_completed[-1].component_id}:{failure.reason}")
                    rollback_failures.append("PARTIAL_ROLLBACK:RECONCILIATION_REQUIRED")
                else:
                    rollback_failures.append(f"ROLLBACK_START:{failure.reason}")
            if action in READ_ONLY_ACTIONS:
                state["lastChecks"][action] = {"status": "FAIL", "at": self.now(), "reason": failure.reason, "recovery": failure.recovery}
                terminal = state["status"]
                request_terminal = "FAILED_TERMINAL"
            else:
                terminal = "FAILED_RECOVERY_REQUIRED" if rollback_failures else "FAILED_TERMINAL"
                request_terminal = terminal
            request.update(
                status=request_terminal,
                reason=failure.reason,
                recovery=failure.recovery,
                rollbackFailures=rollback_failures,
                finishedAt=self.now(),
            )
            if not request_recorded:
                try:
                    self._record_unowned_terminal(request, state, failure)
                except ReleaseError as evidence_failure:
                    evidence_failure.recorded = True
                    raise evidence_failure from failure
                failure.recorded = True
                raise failure
            try:
                self._record_terminal(request, state, terminal)
            except ReleaseError as terminal_failure:
                terminal_failure.recorded = True
                raise terminal_failure from failure
            failure.recorded = True
            raise failure


def subprocess_runner(adapter_root: pathlib.Path) -> Callable[[Component, str], Mapping[str, object]]:
    def run(component: Component, action: str) -> Mapping[str, object]:
        adapter = adapter_root / component.adapter
        adapter_fd = -1
        try:
            adapter_fd = os.open(adapter, os.O_RDONLY | os.O_NOFOLLOW)
            stat = os.fstat(adapter_fd)
            if not os.path.isfile(f"/proc/self/fd/{adapter_fd}") or stat.st_uid != 0 or stat.st_gid != 0 or stat.st_mode & 0o777 != 0o755:
                raise ReleaseError("ADAPTER_METADATA_INVALID", f"RESTORE_PINNED_ADAPTER:{component.component_id}")
            if sha256_fd(adapter_fd) != component.adapter_sha256:
                raise ReleaseError("ADAPTER_CHECKSUM_MISMATCH", f"RESTORE_PINNED_ADAPTER:{component.component_id}")
            completed = subprocess.run(
                [f"/proc/self/fd/{adapter_fd}", action],
                check=False,
                capture_output=True,
                text=True,
                timeout=900,
                env={"PATH": SAFE_PATH, "LANG": "C", "LC_ALL": "C"},
                pass_fds=(adapter_fd,),
            )
        except (OSError, subprocess.TimeoutExpired) as exc:
            raise ReleaseError("ADAPTER_EXECUTION_FAILED", f"INSPECT_ADAPTER:{component.component_id}:{action}") from exc
        finally:
            if adapter_fd >= 0:
                os.close(adapter_fd)
        lines = completed.stdout.splitlines()
        if len(lines) != 1 or len(lines[0]) > 8192:
            raise ReleaseError("ADAPTER_OUTPUT_INVALID", f"INSPECT_ADAPTER:{component.component_id}:{action}")
        try:
            result = json.loads(lines[0])
        except json.JSONDecodeError as exc:
            raise ReleaseError("ADAPTER_OUTPUT_INVALID", f"INSPECT_ADAPTER:{component.component_id}:{action}") from exc
        if completed.returncode != 0 and isinstance(result, dict) and result.get("ok") is True:
            raise ReleaseError("ADAPTER_EXIT_MISMATCH", f"INSPECT_ADAPTER:{component.component_id}:{action}")
        return result
    return run


def run_cli(argv: Sequence[str]) -> int:
    if len(argv) != 2 or argv[1] not in ALLOWED_ACTIONS:
        raise ReleaseError("EXACTLY_ONE_COMMAND_REQUIRED", "USE_EXACT_APPROVED_ACTION")
    if os.geteuid() != 0:
        raise ReleaseError("ROOT_REQUIRED", "USE_RESTRICTED_SUDOERS_COMMAND")
    expected_path = pathlib.Path("/usr/local/sbin/chinaplanetos-release-controller")
    if pathlib.Path(argv[0]) != expected_path:
        raise ReleaseError("HELPER_PATH_INVALID", "REINSTALL_PINNED_CONTROLLER")

    config_root = pathlib.Path("/etc/chinaplanetos-release")
    state_root = pathlib.Path("/var/lib/chinaplanetos-release")
    state_root.mkdir(mode=0o700, parents=True, exist_ok=True)
    _safe_directory(config_root, expected_mode=0o700, root_owned=True)
    _safe_directory(state_root, expected_mode=0o700, root_owned=True)
    _safe_directory_chain(config_root)
    _safe_directory_chain(state_root)
    lock_path = state_root / "controller.lock"
    lock_fd = os.open(lock_path, os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    try:
        fcntl.flock(lock_fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError as exc:
        os.close(lock_fd)
        raise ReleaseError("LOCK_CONTENDED", "WAIT_FOR_ACTIVE_REQUEST_TO_FINISH") from exc

    try:
        try:
            release = verify_pinned_json(config_root, "release")
            target = verify_pinned_json(config_root, "target")
            controller = ReleaseController(
                release=release,
                target=target,
                artifacts_root=pathlib.Path("/var/lib/chinaplanetos-release/artifacts"),
                manifests_root=config_root / "manifests",
                adapter_root=ADAPTER_ROOT,
                state_root=state_root,
                runner=subprocess_runner(ADAPTER_ROOT),
            )
            print(json.dumps(controller.run(argv[1]), sort_keys=True, separators=(",", ":")))
            return 0
        except ReleaseError as failure:
            if not failure.recorded:
                record_bootstrap_failure(state_root, argv[1], failure)
                failure.recorded = True
            raise
    finally:
        fcntl.flock(lock_fd, fcntl.LOCK_UN)
        os.close(lock_fd)


def record_bootstrap_failure(state_root: pathlib.Path, action: str, failure: ReleaseError) -> None:
    request_id = uuid.uuid4().hex
    timestamp = int(time.time())
    record = {
        "requestId": request_id,
        "releaseId": "UNKNOWN",
        "environment": "UNKNOWN",
        "action": action,
        "status": "FAILED_TERMINAL",
        "reason": failure.reason,
        "recovery": failure.recovery,
        "startedAt": timestamp,
        "finishedAt": timestamp,
    }
    atomic_write_json(state_root / "requests" / f"{request_id}.json", record)
    atomic_write_json(state_root / "bootstrap-state.json", record)
    append_audit(state_root / "audit.jsonl", record)
