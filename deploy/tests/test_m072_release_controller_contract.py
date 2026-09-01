#!/usr/bin/env python3

import hashlib
import pathlib
import re
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]
CONTROLLER = ROOT / "deploy/tools/chinaplanetos_release_controller.py"
LIBRARY = ROOT / "deploy/lib/chinaplanetos_release.py"
INSTALLER = ROOT / "deploy/tools/install_chinaplanetos_release_controller.sh"
SUDOERS = ROOT / "deploy/config/codex-deploy-chinaplanetos-release.sudoers"


class ControllerContractTest(unittest.TestCase):
    def test_restricted_action_surface_is_exact(self):
        lines = SUDOERS.read_text().splitlines()
        self.assertEqual(len(lines), 6)
        for action in ("compare", "preflight", "apply", "verify", "status", "rollback"):
            self.assertEqual(lines.count(f"codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-release-controller {action}"), 1)
        source = LIBRARY.read_text()
        self.assertIn('ALLOWED_ACTIONS = ("compare", "preflight", "apply", "verify", "status", "rollback")', source)
        self.assertNotIn("shell=True", source)
        self.assertIn('env={"PATH": SAFE_PATH, "LANG": "C", "LC_ALL": "C"}', source)

    def test_controller_has_lock_pins_terminal_requests_and_independent_states(self):
        source = LIBRARY.read_text()
        for marker in (
            "fcntl.LOCK_EX | fcntl.LOCK_NB", 'f"{kind}_CHECKSUM_MISMATCH"',
            "FAILED_RECOVERY_REQUIRED", "FAILED_TERMINAL",
            "codeState", "schemaState", "configState", "dataState", "atomic_write_json", "append_audit",
            "schema_policy", "rollbackFailures", "verify_pinned_json", "DESCRIPTOR_CHECKSUM_MISMATCH",
            "REQUIRED_COMPONENTS", "record_bootstrap_failure", "/proc/self/fd/", "pass_fds",
        ):
            self.assertIn(marker, source)
        self.assertIn("if action == \"status\"", source)
        self.assertLess(source.index('if action == "status"'), source.index('request = {'))

    def test_installer_pins_every_installed_file_and_never_installs_a_release_profile(self):
        installer = INSTALLER.read_text()
        for name, path in (
            ("CONTROLLER_SHA", CONTROLLER), ("LIBRARY_SHA", LIBRARY), ("SUDOERS_SHA", SUDOERS),
        ):
            match = re.search(rf"readonly {name}='([0-9a-f]{{64}})'", installer)
            self.assertIsNotNone(match, name)
            self.assertEqual(match.group(1), hashlib.sha256(path.read_bytes()).hexdigest())
        self.assertIn("visudo -cf /etc/sudoers", installer)
        self.assertIn("rollback_install", installer)
        self.assertIn("flock -n 9", installer)
        self.assertIn("GENERATION_DIR", installer)
        self.assertIn('mv -Tf "$link_stage" "$TARGET_LINK"', installer)
        self.assertIn("validate_generation", installer)
        self.assertIn("fsync_path /usr/local/sbin", installer)
        self.assertIn('fsync_path "${STATE_ROOT%/*}"', installer)
        self.assertIn('fsync_path "${directory%/*}"', installer)
        self.assertIn("RELEASE_PROFILE_INSTALLED=NO", installer)
        self.assertNotIn("release.json", installer)
        self.assertNotIn("target.json", installer)

    def test_source_contains_no_environment_target_or_production_mutator(self):
        joined = "\n".join(path.read_text() for path in (CONTROLLER, LIBRARY, INSTALLER, SUDOERS))
        for forbidden in ("erp-staging", "vision_erp", "vision_db", "backup-erp.visiontrading.biz", "erp.visiontrading.biz"):
            self.assertNotIn(forbidden, joined)
        self.assertNotIn("docker compose", joined)
        self.assertNotIn("docker exec", joined)


if __name__ == "__main__":
    unittest.main()
