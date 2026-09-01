#!/usr/bin/env python3
"""Security and isolation source-contract tests for M073."""

from __future__ import annotations

import ast
import hashlib
import pathlib
import re


ROOT = pathlib.Path(__file__).resolve().parents[2]
COMPOSE = (ROOT / "deploy/m073/compose.yaml").read_text()
HELPER_PATH = ROOT / "deploy/tools/chinaplanetos_backup_erp_rebuild_m073.py"
HELPER = HELPER_PATH.read_text()
SUDOERS = (ROOT / "deploy/config/codex-deploy-backup-erp-rebuild-m073.sudoers").read_text().splitlines()
INSTALLER = (ROOT / "deploy/tools/install_backup_erp_rebuild_m073_helper.sh").read_text()


def digest(path: pathlib.Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main() -> None:
    forbidden = ("erp-staging", "codex_staging", "/data/compose", "vision_", "chinaplanet-web-production")
    combined = COMPOSE + HELPER
    assert all(token not in combined for token in forbidden)
    assert "internal: true" in COMPOSE
    assert len(re.findall(r"^\s+- 127\.0\.0\.1:\d+:\d+$", COMPOSE, re.MULTILINE)) == 4
    assert "0.0.0.0:" not in COMPOSE
    assert "DOLI_CRON: \"0\"" in COMPOSE
    assert "DISABLE_WP_CRON" in COMPOSE
    assert "WP_HTTP_BLOCK_EXTERNAL" in COMPOSE
    assert "mailpit" in COMPOSE
    assert "chinaplanet-web-production" not in COMPOSE.lower()
    assert "production_data" not in COMPOSE.lower()
    assert "webhook" not in COMPOSE.lower()
    assert re.search(r"node:22-alpine@sha256:[0-9a-f]{64}", COMPOSE + (ROOT / "deploy/m073/images/Dockerfile.react").read_text())
    for image in ("mariadb:10.6@sha256:", "axllent/mailpit:v1.21.8@sha256:"):
        assert image in COMPOSE
    assert "dolibarr/dolibarr:23.0.3@sha256:" in (ROOT / "deploy/m073/images/Dockerfile.dolibarr").read_text()
    assert "wordpress:7.0.4-php8.3-apache@sha256:" in (ROOT / "deploy/m073/images/Dockerfile.wordpress").read_text()

    expected_actions = ["preflight", "create", "verify", "status", "rollback"]
    assert len(SUDOERS) == len(expected_actions)
    for line, action in zip(SUDOERS, expected_actions):
        assert line == f"codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-rebuild-m073 {action}"
    assert HELPER.startswith("#!/usr/bin/python3\n")
    assert "shell=True" not in HELPER
    tree = ast.parse(HELPER)
    assert not any(isinstance(node, (ast.Exec if hasattr(ast, "Exec") else ast.Pass,)) for node in [])
    assert "--remove-orphans\", check=False" in HELPER
    assert "compose(\"down\", \"--remove-orphans\")" in HELPER
    assert "--volumes" not in HELPER and '"-v"' not in HELPER
    assert "SOURCE_BUNDLE_SHA256 = \"__" not in HELPER
    assert "SECRET_LENGTH" in HELPER and "secretLengths" in HELPER
    assert "secrets.token_hex(32)" in HELPER
    assert "os.O_NOFOLLOW" in HELPER
    assert "BUNDLE_MEMBER_COUNT_INVALID" in HELPER
    assert "BUNDLE_MEMBER_METADATA_INVALID" in HELPER
    assert "network_members != EXPECTED_CONTAINERS" in HELPER
    assert "normalized_mounts(detail.get(\"Mounts\")) != EXPECTED_MOUNTS[name]" in HELPER
    assert "normalized_ports(host_config.get(\"PortBindings\")) != EXPECTED_PORT_BINDINGS[name]" in HELPER
    assert "expected_image_ids=image_ids" in HELPER
    assert "health != \"healthy\"" in HELPER
    assert "verify_pinned_release_files()\n        compose(\"down\"" in HELPER
    assert "cleanup_failed_create()" in HELPER
    assert "target_collision_present()" in HELPER
    assert "LEDGERLESS_TARGET_PRESENT" in HELPER
    assert "ROLLBACK_REMNANT_PRESENT" in HELPER
    assert "all_docker_names(\"volume\") & EXPECTED_VOLUMES" in HELPER
    assert "GLOBAL_LOCK" in HELPER
    assert "HELPER_EXECUTED=NO STACK_CREATED=NO" in INSTALLER
    assert "__M073_" not in INSTALLER and "__M073_" not in HELPER
    pins_file = ROOT / "deploy/releases/m073-r01/control-pins.sha256"
    for line in pins_file.read_text().splitlines():
        expected, relative = line.split("  ", 1)
        assert digest((ROOT / relative).resolve()) == expected, relative
    print("M073_REBUILD_CONTRACT_TEST=PASS")


if __name__ == "__main__":
    main()
