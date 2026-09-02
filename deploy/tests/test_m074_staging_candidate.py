#!/usr/bin/env python3
"""Execute the exact read-only M074 staging candidate checks from frozen bytes."""

from __future__ import annotations

import os
import pathlib
import re
import shutil
import subprocess
import tarfile
import tempfile


ROOT = pathlib.Path(__file__).resolve().parents[2]
HELPER = ROOT / "deploy/tools/chinaplanetos_backup_erp_m074_qc_preflight.sh"
ARTIFACT = ROOT / "deploy/releases/m074-r07/dolibarr.tar.gz"
EXPECTED_TESTS = (
    "deploy/tests/test_m061_customer_documents_contract.php",
    "deploy/tests/test_m070_customer_unit_projection.php",
    "deploy/tests/test_m068_lifecycle_source.php",
    "deploy/tests/test_m068_portal_invitation_security_source.php",
    "deploy/tests/test_m068_portal_currency_unit.php",
    "deploy/tests/test_m071_catalog_order_v2_source.php",
    "deploy/tests/test_m071_country_contract.php",
    "deploy/tests/test_customer_operation_reports_security.php",
    "deploy/tests/test_m017_mobile_api_data.php",
    "deploy/tests/test_m017_mobile_api_security.php",
    "deploy/tests/test_m032_admin_customer_responsive.php",
    "deploy/tests/test_m074_qc_contract.php",
)


def require(condition: bool, reason: str) -> None:
    if not condition:
        raise SystemExit("M074_STAGING_CANDIDATE=FAIL REASON=" + reason)


source = HELPER.read_text(encoding="utf-8")
match = re.search(r"STATIC_TESTS = \(\n(.*?)\n\)", source, re.DOTALL)
require(match is not None, "STATIC_TEST_LIST_ABSENT")
tests = tuple(re.findall(r'"([^"]+)"', match.group(1)))
require(tests == EXPECTED_TESTS, "STATIC_TEST_LIST_CHANGED")
php = shutil.which("php")
require(php is not None, "PHP_ABSENT")

with tempfile.TemporaryDirectory(prefix="m074-staging-candidate-") as temporary:
    candidate = pathlib.Path(temporary) / "candidate"
    candidate.mkdir()
    with tarfile.open(ARTIFACT, "r:gz") as archive:
        member_names = set()
        for member in archive.getmembers():
            pure = pathlib.PurePosixPath(member.name)
            require(member.isfile(), "ARTIFACT_TYPE_FORBIDDEN")
            require(not pure.is_absolute() and ".." not in pure.parts, "ARTIFACT_PATH_UNSAFE")
            member_names.add(member.name)
        require("custom/chinaplanetoperations/qc/app.php" in member_names, "LIVE_PROBE_TARGET_ABSENT")
        archive.extractall(candidate)

    customer_center = candidate / "custom/chinaplanetcustomercenter"
    os.symlink("../chinaplanetlogistics", customer_center / "chinaplanetlogistics")
    os.symlink("../cpkanban", customer_center / "cpkanban")

    php_files = sorted((candidate / "custom").rglob("*.php")) + sorted((candidate / "deploy").rglob("*.php"))
    require(bool(php_files), "PHP_ALLOWLIST_EMPTY")
    for path in php_files:
        result = subprocess.run((php, "-l", str(path)), stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
        require(result.returncode == 0, "PHP_LINT_" + path.relative_to(candidate).as_posix())

    environment = os.environ.copy()
    for name in ("M017_DOL_DOCUMENT_ROOT", "M017_CREDENTIAL_FILE", "M017_API_BASE"):
        environment.pop(name, None)
    environment["M074_DOL_DOCUMENT_ROOT"] = str(candidate)
    for relative in tests:
        path = candidate / relative
        require(path.is_file() and not path.is_symlink(), "TEST_ABSENT_" + relative)
        result = subprocess.run((php, str(path)), env=environment, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
        if result.returncode != 0:
            print(result.stdout, end="")
        require(result.returncode == 0, "TEST_FAILED_" + relative)

print(f"M074_STAGING_CANDIDATE=PASS PHP_FILES={len(php_files)} TESTS={len(tests)} DB_INTEGRATION=DISABLED")
