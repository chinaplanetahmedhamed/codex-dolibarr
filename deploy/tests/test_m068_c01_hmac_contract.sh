#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
helper="$root/deploy/tools/chinaplanetos_backup_erp_m068_c01_hmac.sh"
config="$root/deploy/tools/m068_c01_portal_hmac_config.php"
sudoers="$root/deploy/config/codex-deploy-backup-erp-m068-c01-hmac.sudoers"
installer="$root/deploy/tools/install_backup_erp_m068_c01_hmac_helper.sh"
manifest="$root/deploy/releases/m068-c01-hmac-configuration.sha256"
fail() { echo "M068_C01_CONTRACT=FAIL REASON=$1" >&2; exit 1; }
sha256() { shasum -a 256 "$1" | awk '{print $1}'; }

bash -n "$helper"
bash -n "$installer"
php -l "$config" >/dev/null
[[ $(wc -l <"$sudoers" | tr -d ' ') -eq 5 ]] || fail 'SUDOERS_COUNT'
for command in preflight apply verify rollback status; do
  [[ $(grep -Fxc "codex-deploy ALL=(root) NOPASSWD: /usr/local/sbin/chinaplanetos-backup-erp-m068-c01-hmac $command" "$sudoers") -eq 1 ]] || fail "SUDOERS_${command}"
done
! grep -Eq '(/bin/(ba)?sh|docker|php|\*|SETENV:)' "$sudoers" || fail 'SUDOERS_BROAD_SURFACE'

grep -Fq "const M068_C01_SECRET_NAME = 'CHINAPLANET_PORTAL_INVITATION_HMAC_KEY'" "$config"
grep -Fq 'random_bytes(48)' "$config"
grep -Fq "array('check', 'apply', 'verify', 'rollback')" "$config"
grep -Fq "require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php'" "$config"
grep -Fq 'dolDecrypt($stored)' "$config"
grep -Fq "strncmp(\$stored, 'dolcrypt:', 9)" "$config"
grep -Fq "const M068_C01_SECRET_NOTE = 'M068-C01 owned server-side portal invitation HMAC secret'" "$config"
! grep -Eq 'sha256=|hash\(|hash_equals' "$config" || fail 'VALUE_DIGEST_PRESENT'
grep -Fq 'SELECT GET_LOCK(' "$config"
grep -Fq 'SELECT RELEASE_LOCK(' "$config"
grep -Fq 'FOR UPDATE' "$config"
[[ $(grep -Fc 'm068C01AcquireLock($db, $entity);' "$config") -eq 2 ]] || fail 'MUTATION_ADVISORY_LOCK_COUNT'
[[ $(grep -Fc 'm068C01Begin($db);' "$config") -eq 2 ]] || fail 'MUTATION_TRANSACTION_COUNT'
[[ $(grep -Fc 'm068C01Inspect($db, $entity, true)' "$config") -eq 4 ]] || fail 'LOCKED_MUTATION_INSPECTION_COUNT'
! grep -Eq '\$db->(begin|commit|rollback)\(\) === false' "$config" || fail 'DOLIDB_INTEGER_ZERO_GAP'
grep -Fq "if (!\$db->begin())" "$config"
grep -Fq "if (!\$db->commit())" "$config"
grep -Fq "if (!\$db->rollback())" "$config"
[[ $(grep -c 'dolibarr_set_const' "$config") -eq 1 ]] || fail 'MORE_THAN_EXACT_CONSTANT_WRITE'
[[ $(grep -c 'dolibarr_del_const' "$config") -eq 1 ]] || fail 'MORE_THAN_EXACT_CONSTANT_DELETE'
! grep -Fq 'CHINAPLANET_M068_C01' "$config" || fail 'SECOND_CONSTANT_PRESENT'
! grep -Fq 'entity IN (' "$config" || fail 'FOREIGN_ENTITY_LOOKUP_GAP'
! grep -Eq '(echo|fwrite|print_r|var_dump).*\$secret' "$config" || fail 'SECRET_OUTPUT_PATH'
! grep -Fq '$argv[2]' "$config" || fail 'SECRET_ARGUMENT_PATH'
! grep -Eq 'putenv|export[[:space:]]+CHINAPLANET_PORTAL_INVITATION_HMAC_KEY' "$config" "$helper" || fail 'SECRET_EXPORT_PATH'

grep -Fq "TARGET_CONTAINER='erp-staging-dolibarr-1'" "$helper"
grep -Fq "TARGET_CONTAINER='erp-staging-dolibarr-1'" "$installer"
grep -Fq "docker inspect --format '{{.State.Running}}' \"\$TARGET_CONTAINER\"" "$installer"
grep -Fq 'docker exec -i "$TARGET_CONTAINER" php -l /dev/stdin <"$path"' "$installer"
! grep -Fq 'php -l "$private_config"' "$installer" || fail 'INSTALLER_HOST_PHP_LINT'
grep -Fq '[[ $lint_rc -eq 255 ]] && return 1' "$installer"
grep -Fq "die 'CONFIG_RUNTIME_INVALID'" "$installer"
grep -Fq 'CONFIG_LINT=CONTAINER_PHP' "$installer"
grep -Fq "DB_CONTAINER='erp-staging-mariadb-1'" "$helper"
grep -Fq "MAILPIT_CONTAINER='erp-staging-mailpit-1'" "$helper"
grep -Fq "DB_NAME='dolibarr_db'" "$helper"
grep -Fq "BASELINE_RELEASE='M062-R04'" "$helper"
grep -Fq "M068_PREFLIGHT_HELPER_SHA='11b3e0e9e566be2722bc831e097ab67d04d93a6e4f708b4795494407df71940a'" "$helper"
grep -Fq "PREDECESSOR_HELPER_SHA='752df39e6f9f7f679feba22afa08c3a94b4ccdfe00055984d6c327c5ef677e54'" "$installer"
grep -Fq 'mariadb-dump' "$helper"
grep -Fq 'DATABASE_RESTORE_CHECK_MISMATCH' "$helper"
grep -Fq 'DATABASE_BACKUP_CONTAINS_HMAC_KEY' "$helper"
grep -Fq 'DB_BACKUP_VALIDATED=1' "$helper"
grep -Fq 'discard_unvalidated_database_backup' "$helper"
grep -Fq 'MYSQL_PWD="$pass" exec "$dump"' "$helper"
grep -Fq -- '-u root "$db"' "$helper"
grep -Fq 'information_schema.routines' "$helper"
grep -Fq 'information_schema.triggers' "$helper"
grep -Fq 'information_schema.events' "$helper"
grep -Fq 'if [ "$kind" = B ]' "$helper"
grep -Fq 'STATE_SECRET_PRESTATE=ABSENT' "$helper"
grep -Fq 'CUSTOMER_DATA_OUTPUT=NO' "$helper"
grep -Fq 'COMMANDS=preflight,apply,verify,rollback,status' "$helper"
! grep -Eq '(vision_erp|vision_db|/root/vision-os|erp\.visiontrading\.biz)' "$helper" "$installer" || fail 'PRODUCTION_IDENTIFIER'
! grep -Eq '(request\.json|eval |source |\. \$)' "$helper" "$installer" || fail 'ARBITRARY_EXECUTION_SURFACE'
! grep -Eq 'case "\$1" in[^\n]*(compare|dry-run)' "$helper" || fail 'UNAPPROVED_COMMAND'
! grep -Eq 'STATE_(SECRET_VALUE|SECRET_SHA|HMAC_KEY|DIGEST)=' "$helper" || fail 'SECRET_STATE_FIELD'

helper_sha=$(sha256 "$helper")
config_sha=$(sha256 "$config")
sudoers_sha=$(sha256 "$sudoers")
grep -Fq "readonly HELPER_SHA='$helper_sha'" "$installer" || fail 'INSTALLER_HELPER_PIN'
grep -Fq "readonly CONFIG_SHA='$config_sha'" "$installer" || fail 'INSTALLER_CONFIG_PIN'
grep -Fq "readonly SUDOERS_SHA='$sudoers_sha'" "$installer" || fail 'INSTALLER_SUDOERS_PIN'
grep -Fq "readonly CONFIG_SHA='$config_sha'" "$helper" || fail 'HELPER_CONFIG_PIN'
grep -Fq 'HELPER_EXECUTED=NO' "$installer"
! grep -Eq '(^|[[:space:]])"?\$TARGET_HELPER"?[[:space:]]+(preflight|apply|verify|rollback|status)' "$installer" || fail 'INSTALLER_EXECUTES_HELPER'

[[ $(wc -l <"$manifest" | tr -d ' ') -eq 4 ]] || fail 'MANIFEST_COUNT'
expected_paths=$(printf '%s\n' \
  deploy/config/codex-deploy-backup-erp-m068-c01-hmac.sudoers \
  deploy/tools/chinaplanetos_backup_erp_m068_c01_hmac.sh \
  deploy/tools/install_backup_erp_m068_c01_hmac_helper.sh \
  deploy/tools/m068_c01_portal_hmac_config.php | LC_ALL=C sort)
actual_paths=$(awk 'NF==2 && $1 ~ /^[a-f0-9]{64}$/ {print $2}' "$manifest" | LC_ALL=C sort)
[[ "$actual_paths" == "$expected_paths" ]] || fail 'MANIFEST_ALLOWLIST'
(cd "$root" && shasum -a 256 -c deploy/releases/m068-c01-hmac-configuration.sha256 >/dev/null) || fail 'MANIFEST_HASH'

echo "M068_C01_CONTRACT=PASS COMMANDS=5 PHP_COMMANDS=4 EXACT_CONSTANT_COUNT=1 TARGET=ERP_STAGING_ONLY SERVER_GENERATION=YES FULL_DATABASE_BACKUP=YES RESTORE_TEST=YES SECRET_INPUT=NONE SECRET_OUTPUT=NONE CHECKSUM_PINNED=YES MANIFEST_FILES=4 HELPER_SHA=$helper_sha CONFIG_SHA=$config_sha SUDOERS_SHA=$sudoers_sha INSTALLER_EXECUTES_HELPER=NO SERVER_ACCESSED=NO PRODUCTION_ACCESSED=NO"
