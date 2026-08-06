#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_m044_palestine_release.sh"
sudoers="$root/deploy/config/codex-deploy-m044-palestine-release.sudoers"

bash -n "$helper"
grep -Fq "readonly RUNTIME_COMMIT='bea5c9798f2001473a8458a54b1e31821843875b'" "$helper"
grep -Fq "readonly ARTIFACT_SHA='ce65bc0ce331ead157d4a09ea814bdaef7faf6f1277a1d7a1f54cfb1ab7b5cbd'" "$helper"
grep -Fq "TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "TARGET_CONTAINER='vision_erp'" "$helper"
grep -Fq "TARGET_ROOT='/var/lib/docker/volumes/vision-os_dolibarr_custom/_data'" "$helper"
grep -Fq "COUNTRY_SNAPSHOT_REMOTE=\"/tmp/m044-country-snapshot-\$\$.json\"" "$helper"
grep -Fq "M044_SNAPSHOT_PATH=\"\$COUNTRY_SNAPSHOT_REMOTE\"" "$helper"
grep -Fq 'if restore_code && restore_database' "$helper"
grep -Fq 'MUTATED=1; apply_code; configure_gateway; apply_country' "$helper"
grep -Fq 'SECRET_REMOTE="/tmp/m043-secrets-$$"' "$helper"
! grep -Fq 'trap '\''rc=$?' "$helper"
grep -Fq 'production-compare' "$sudoers"
grep -Fq 'production-preflight' "$sudoers"
grep -Fq 'production-apply' "$sudoers"
grep -Fq 'production-verify' "$sudoers"
grep -Fq 'production-rollback' "$sudoers"
grep -Fq 'production-status' "$sudoers"
[[ $(grep -o '/usr/local/sbin/chinaplanetos-m044-palestine-release' "$sudoers" | wc -l | tr -d ' ') -eq 12 ]]
! grep -Eq 'NOPASSWD: (ALL|/bin/(ba)?sh|/usr/bin/(ba)?sh|/usr/bin/docker|/usr/bin/sudo)' "$sudoers"
[[ $(sha256sum "$helper" | awk '{print $1}') == 'd0d747bb445414848310824ef18c6ac6ef7317ecdd2dfe8c07030626369f5549' ]]
[[ $(sha256sum "$sudoers" | awk '{print $1}') == '6fab772137a2ebffe824bbd23e8b7d82a1775a98c07abc3a9affd29e0c8099c4' ]]

echo 'M044_RELEASE_HELPER_TEST=PASS COMMANDS=12 STAGING_AND_PRODUCTION=PINNED'
