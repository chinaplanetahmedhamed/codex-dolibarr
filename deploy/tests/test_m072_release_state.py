#!/usr/bin/env python3

import copy
import hashlib
import json
import pathlib
import tempfile
import unittest
from unittest import mock

ROOT = pathlib.Path(__file__).resolve().parents[2]
import sys
sys.path.insert(0, str(ROOT / "deploy/lib"))

import chinaplanetos_release as release_module  # noqa: E402
from chinaplanetos_release import Component, ReleaseController, ReleaseError, record_bootstrap_failure  # noqa: E402


def digest(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


class Harness:
    def __init__(self, root: pathlib.Path, failures=None, invalid_states=None):
        self.root = root
        self.failures = failures or {}
        self.invalid_states = invalid_states or {}
        self.calls = []
        self.release = {"schemaVersion": 1, "releaseId": "M072-R01", "components": []}
        for component_id in ("dolibarr", "react", "wordpress", "mobile"):
            component = {
                "id": component_id,
                "commit": "a" * 40,
                "artifact": f"{component_id}.tar.gz",
                "artifactSha256": digest(component_id.encode()),
                "manifest": f"{component_id}.tsv",
                "manifestSha256": digest((component_id + "-manifest").encode()),
                "layout": "dolibarr.layout.tsv" if component_id == "dolibarr" else "",
                "layoutSha256": digest(b"dolibarr-layout") if component_id == "dolibarr" else "",
                "adapter": f"{component_id}-adapter",
                "adapterSha256": digest((component_id + "-adapter").encode()),
                "schemaPolicy": "additive-retain-on-code-rollback" if component_id == "dolibarr" else "none",
                "configPolicy": "external-retain",
                "dataPolicy": "retain",
            }
            self.release["components"].append(component)
            for folder, name, content in (
                ("artifacts", component["artifact"], component_id.encode()),
                ("manifests", component["manifest"], (component_id + "-manifest").encode()),
                ("adapters", component["adapter"], (component_id + "-adapter").encode()),
            ):
                path = root / folder / name
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(content)
            if component_id == "dolibarr":
                path = root / "manifests/dolibarr.layout.tsv"
                path.write_bytes(b"dolibarr-layout")
        self.target = {"schemaVersion": 1, "environment": "staging", "components": ["dolibarr", "react", "wordpress", "mobile"]}

    def run(self, component: Component, action: str):
        self.calls.append((component.component_id, action))
        failure = self.failures.get((component.component_id, action))
        if failure:
            return {"ok": False, "component": component.component_id, "action": action, "reason": failure, "recovery": f"RECOVER:{component.component_id}:{action}".upper()}
        invalid_state = self.invalid_states.get((component.component_id, action))
        return {
            "ok": True,
            "component": component.component_id,
            "action": action,
            "codeState": invalid_state or ("MATCHED" if action != "rollback" else "RESTORED"),
            "schemaState": "ADDITIVE_RETAINED" if component.component_id == "dolibarr" else "UNCHANGED",
            "configState": "UNCHANGED",
            "dataState": "UNCHANGED",
        }

    def controller(self):
        return ReleaseController(
            release=copy.deepcopy(self.release), target=copy.deepcopy(self.target),
            artifacts_root=self.root / "artifacts", manifests_root=self.root / "manifests",
            adapter_root=self.root / "adapters", state_root=self.root / "state", runner=self.run,
            now=lambda: 1770000000, request_id=lambda: "1" * 32, enforce_metadata=False,
        )


class ReleaseStateTest(unittest.TestCase):
    def test_all_actions_are_separate_and_status_does_not_call_an_adapter(self):
        with tempfile.TemporaryDirectory() as raw:
            harness = Harness(pathlib.Path(raw))
            controller = harness.controller()
            self.assertTrue(controller.run("compare")["ok"])
            calls_after_compare = list(harness.calls)
            status = controller.run("status")
            self.assertTrue(status["ok"])
            self.assertEqual(harness.calls, calls_after_compare)
            self.assertEqual({action for _, action in calls_after_compare}, {"compare"})

    def test_checksum_failure_stops_before_any_adapter(self):
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            harness = Harness(root)
            (root / "artifacts/react.tar.gz").write_bytes(b"tampered")
            with self.assertRaisesRegex(ReleaseError, "ARTIFACT_CHECKSUM_MISMATCH"):
                harness.controller().run("preflight")
            self.assertEqual(harness.calls, [])

    def test_apply_failure_rolls_back_only_applied_components_and_records_terminal_state(self):
        with tempfile.TemporaryDirectory() as raw:
            harness = Harness(pathlib.Path(raw), {("wordpress", "apply"): "INJECTED_APPLY_FAILURE"})
            with self.assertRaisesRegex(ReleaseError, "WORDPRESS_INJECTED_APPLY_FAILURE"):
                harness.controller().run("apply")
            self.assertEqual(harness.calls[:4], [("dolibarr", "preflight"), ("react", "preflight"), ("wordpress", "preflight"), ("mobile", "preflight")])
            self.assertEqual(harness.calls[-3:], [("wordpress", "rollback"), ("react", "rollback"), ("dolibarr", "rollback")])
            state = json.loads((pathlib.Path(raw) / "state/release-state.json").read_text())
            self.assertEqual(state["status"], "FAILED_TERMINAL")
            self.assertEqual(state["components"]["dolibarr"]["schemaState"], "ADDITIVE_RETAINED")

    def test_invalid_apply_state_rolls_back_the_current_component(self):
        with tempfile.TemporaryDirectory() as raw:
            harness = Harness(pathlib.Path(raw), invalid_states={("dolibarr", "apply"): "unsafe-state"})
            with self.assertRaisesRegex(ReleaseError, "ADAPTER_STATE_INVALID"):
                harness.controller().run("apply")
            self.assertEqual(harness.calls[-1], ("dolibarr", "rollback"))
            state = json.loads((pathlib.Path(raw) / "state/release-state.json").read_text())
            self.assertEqual(state["status"], "FAILED_TERMINAL")

    def test_rollback_failure_is_explicit_and_never_claims_success(self):
        with tempfile.TemporaryDirectory() as raw:
            harness = Harness(pathlib.Path(raw), {("react", "rollback"): "INJECTED_ROLLBACK_FAILURE"})
            with self.assertRaisesRegex(ReleaseError, "REACT_INJECTED_ROLLBACK_FAILURE"):
                harness.controller().run("rollback")
            state = json.loads((pathlib.Path(raw) / "state/release-state.json").read_text())
            self.assertEqual(state["status"], "FAILED_RECOVERY_REQUIRED")

    def test_target_and_policy_mismatches_fail_closed(self):
        with tempfile.TemporaryDirectory() as raw:
            harness = Harness(pathlib.Path(raw))
            harness.target["components"].reverse()
            with self.assertRaisesRegex(ReleaseError, "TARGET_COMPONENT_SET_MISMATCH"):
                harness.controller()
            harness.target["components"].reverse()
            harness.release["components"][0]["dataPolicy"] = "drop"
            with self.assertRaisesRegex(ReleaseError, "DATA_POLICY_INVALID"):
                harness.controller()

    def test_incomplete_component_set_is_rejected(self):
        with tempfile.TemporaryDirectory() as raw:
            harness = Harness(pathlib.Path(raw))
            harness.release["components"].pop()
            harness.target["components"].pop()
            with self.assertRaisesRegex(ReleaseError, "COMPONENT_SET_INVALID"):
                harness.controller()

    def test_audit_failure_after_pending_is_rewritten_to_terminal(self):
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            harness = Harness(root)
            original = release_module.append_audit
            calls = {"count": 0}

            def fail_once(path, value):
                calls["count"] += 1
                if calls["count"] == 1:
                    raise OSError("injected audit failure")
                return original(path, value)

            with mock.patch.object(release_module, "append_audit", side_effect=fail_once):
                with self.assertRaisesRegex(ReleaseError, "STATE_STORAGE_FAILED"):
                    harness.controller().run("compare")
            request = json.loads((root / "state/requests" / ("1" * 32 + ".json")).read_text())
            self.assertEqual(request["status"], "FAILED_TERMINAL")

    def test_early_checksum_failure_leaves_terminal_request_and_state(self):
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            harness = Harness(root)
            (root / "artifacts/react.tar.gz").write_bytes(b"tampered")
            with self.assertRaisesRegex(ReleaseError, "ARTIFACT_CHECKSUM_MISMATCH"):
                harness.controller().run("preflight")
            request = json.loads((root / "state/requests" / ("1" * 32 + ".json")).read_text())
            state = json.loads((root / "state/release-state.json").read_text())
            self.assertEqual(request["status"], "FAILED_TERMINAL")
            self.assertEqual(state["lastChecks"]["preflight"]["status"], "FAIL")

    def test_descriptor_or_target_bootstrap_failure_is_persisted(self):
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            record_bootstrap_failure(root, "preflight", ReleaseError("TARGET_COMPONENT_SET_MISMATCH", "INSTALL_COMPLETE_TARGET_PROFILE"))
            state = json.loads((root / "bootstrap-state.json").read_text())
            requests = list((root / "requests").glob("*.json"))
            self.assertEqual(state["status"], "FAILED_TERMINAL")
            self.assertEqual(state["reason"], "TARGET_COMPONENT_SET_MISMATCH")
            self.assertEqual(len(requests), 1)

    def test_request_collision_preserves_the_preexisting_record(self):
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            harness = Harness(root)
            existing = root / "state/requests" / ("1" * 32 + ".json")
            existing.parent.mkdir(parents=True)
            existing.write_text('{"owner":"preexisting"}\n', encoding="utf-8")
            before = existing.read_bytes()
            with self.assertRaisesRegex(ReleaseError, "REQUEST_COLLISION"):
                harness.controller().run("compare")
            self.assertEqual(existing.read_bytes(), before)
            evidence = [path for path in existing.parent.glob("*.json") if path != existing]
            self.assertEqual(len(evidence), 1)
            self.assertEqual(json.loads(evidence[0].read_text())["collidedRequestId"], "1" * 32)

    def test_terminal_request_write_failure_is_structured_and_rewritten_terminal(self):
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            harness = Harness(root)
            original = release_module.atomic_write_json
            calls = {"request": 0}

            def fail_first_terminal(path, value, mode=0o600):
                if path.parent.name == "requests" and calls["request"] == 0:
                    calls["request"] += 1
                    raise OSError("injected terminal request write failure")
                return original(path, value, mode)

            with mock.patch.object(release_module, "atomic_write_json", side_effect=fail_first_terminal):
                with self.assertRaisesRegex(ReleaseError, "TERMINAL_REQUEST_WRITE_FAILED"):
                    harness.controller().run("compare")
            request = json.loads((root / "state/requests" / ("1" * 32 + ".json")).read_text())
            self.assertEqual(request["status"], "FAILED_TERMINAL")
            self.assertEqual(request["reason"], "TERMINAL_REQUEST_WRITE_FAILED")

    def test_status_verifies_pins_without_running_an_adapter(self):
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            harness = Harness(root)
            controller = harness.controller()
            self.assertTrue(controller.run("compare")["ok"])
            calls_before = list(harness.calls)
            (root / "adapters/react-adapter").write_bytes(b"tampered")
            with self.assertRaisesRegex(ReleaseError, "ADAPTER_CHECKSUM_MISMATCH"):
                controller.run("status")
            self.assertEqual(harness.calls, calls_before)

    def test_adapter_success_rejects_unknown_fields_without_leaking_them(self):
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            harness = Harness(root)

            def leaking_runner(component, action):
                result = dict(harness.run(component, action))
                result.update(secret="must-not-escape", targetPath="/private/runtime")
                return result

            controller = harness.controller()
            controller.runner = leaking_runner
            with self.assertRaisesRegex(ReleaseError, "ADAPTER_RESPONSE_FIELDS_INVALID"):
                controller.run("compare")
            request = (root / "state/requests" / ("1" * 32 + ".json")).read_text()
            self.assertNotIn("must-not-escape", request)
            self.assertNotIn("/private/runtime", request)

    def test_rollback_terminal_write_failure_reports_completed_phase_exactly(self):
        with tempfile.TemporaryDirectory() as raw:
            root = pathlib.Path(raw)
            harness = Harness(root)
            original = release_module.atomic_write_json
            calls = {"request": 0}

            def fail_first_terminal(path, value, mode=0o600):
                if path.parent.name == "requests" and calls["request"] == 0:
                    calls["request"] += 1
                    raise OSError("injected post-rollback terminal failure")
                return original(path, value, mode)

            with mock.patch.object(release_module, "atomic_write_json", side_effect=fail_first_terminal):
                with self.assertRaisesRegex(ReleaseError, "TERMINAL_REQUEST_WRITE_FAILED"):
                    harness.controller().run("rollback")
            request = json.loads((root / "state/requests" / ("1" * 32 + ".json")).read_text())
            self.assertEqual(request["status"], "FAILED_RECOVERY_REQUIRED")
            self.assertEqual(request["rollbackFailures"], ["TERMINALIZATION:TERMINAL_REQUEST_WRITE_FAILED"])
            self.assertNotIn("UNKNOWN_COMPONENT", json.dumps(request))
            self.assertNotIn("PARTIAL_ROLLBACK", json.dumps(request))


if __name__ == "__main__":
    unittest.main()
