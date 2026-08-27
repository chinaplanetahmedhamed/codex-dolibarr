#!/usr/bin/env bash
set -Eeuo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
source_helper="$root/deploy/tools/chinaplanetos_backup_erp_m062_r03_public_search.sh"
artifact=${M062_R03_ARTIFACT:-/private/tmp/m062-public-search-dcd24dd.tar.gz}
test_root=$(mktemp -d /private/tmp/m062-r03-helper-test.XXXXXX)
trap 'rm -rf "$test_root"' EXIT
mkdir -p "$test_root/bin" "$test_root/inbox" "$test_root/target/chinaplanetshippingprices/class" "$test_root/backups" "$test_root/state"
cp "$source_helper" "$test_root/chinaplanetos-backup-erp-m062-ports.test"
cp "$artifact" "$test_root/inbox/m062-public-search-dcd24dd.tar.gz"
git -C "$root" show dcd24ddd99ab5668c2ace3ccddd580abec24b472^:custom/chinaplanetshippingprices/class/PublicShippingRateService.class.php >"$test_root/target/chinaplanetshippingprices/class/PublicShippingRateService.class.php"
cp "$root/custom/chinaplanetshippingprices/class/ShippingPriceService.class.php" "$test_root/target/chinaplanetshippingprices/class/ShippingPriceService.class.php"
chmod 0644 "$test_root/inbox/m062-public-search-dcd24dd.tar.gz" "$test_root/target/chinaplanetshippingprices/class/"*.php

cat >"$test_root/bin/docker" <<'SH'
#!/usr/bin/env bash
set -eu
case "$1" in
  inspect)
    last=${!#}
    if [[ "$*" == *'.Mounts'* ]]; then echo 'erp-staging_erp-staging_custom|true'; exit 0; fi
    case "$last" in
      erp-staging-dolibarr-1) echo 'true|erp-staging|dolibarr' ;;
      erp-staging-mariadb-1) echo 'true|erp-staging|mariadb' ;;
      erp-staging-mailpit-1) echo 'true|erp-staging|mailpit' ;;
      *) exit 1 ;;
    esac
    ;;
  volume) echo "local|${M062_R03_TEST_ROOT}/target" ;;
  port)
    case "$2:$3" in
      erp-staging-dolibarr-1:80/tcp) echo '127.0.0.1:18088' ;;
      erp-staging-mailpit-1:1025/tcp) echo '127.0.0.1:11025' ;;
      erp-staging-mailpit-1:8025/tcp) echo '127.0.0.1:18025' ;;
      *) exit 1 ;;
    esac
    ;;
  cp) exit 0 ;;
  exec)
    case "${*: -1}" in
      */test_m043_public_rate_token.php) echo 'M043_RATE_TOKEN=PASS encrypted=YES ttl=600 projection=ALLOWLIST' ;;
      */test_m062_public_alexandria_group.php) echo 'M062_PUBLIC_ALEXANDRIA_GROUP=PASS option=ONE cards=THREE booking=EXACT' ;;
      *) exit 0 ;;
    esac
    ;;
  *) exit 1 ;;
esac
SH
cat >"$test_root/bin/curl" <<'SH'
#!/usr/bin/env bash
printf '200'
SH
cat >"$test_root/bin/flock" <<'SH'
#!/usr/bin/env bash
exit 0
SH
chmod 0755 "$test_root/bin/docker" "$test_root/bin/curl" "$test_root/bin/flock" "$test_root/chinaplanetos-backup-erp-m062-ports.test"

helper="$test_root/chinaplanetos-backup-erp-m062-ports.test"
baseline='ce821a27a26bbde6915f07ee4c37925a528db8d393b200ece15332b4b13b11de'
release='189bee8d2bc6bf47c8898dcb90efb8dc7658b7816ac2fecaee988d53edb95cb4'
target="$test_root/target/chinaplanetshippingprices/class/PublicShippingRateService.class.php"

run_helper() {
  M062_R03_TEST_MODE=1 M062_R03_TEST_ROOT="$test_root" M062_R03_FAULT=${1:-} "$helper" "$2"
}

if run_helper APPLY_STATE_PUBLISH apply >"$test_root/apply-fault.log" 2>&1; then echo 'M062_R03_FAILURES=FAIL REASON=APPLY_STATE_FAULT_ACCEPTED' >&2; exit 1; fi
[[ $(sha256sum "$target" | awk '{print $1}') == "$baseline" ]]
[[ ! -e "$test_root/state/m062-r03.state" ]]
grep -Fq 'M062_R03_STAGING_AUTO_ROLLBACK=PASS' "$test_root/apply-fault.log" || { cat "$test_root/apply-fault.log" >&2; exit 1; }

run_helper '' apply >"$test_root/apply-pass.log" 2>&1
[[ $(sha256sum "$target" | awk '{print $1}') == "$release" ]]
grep -Fxq 'STATE_STATUS=DEPLOYED' "$test_root/state/m062-r03.state"

for fault in ROLLBACK_LINT ROLLBACK_HTTP ROLLBACK_STATE_PUBLISH; do
  if run_helper "$fault" rollback >"$test_root/rollback-${fault}.log" 2>&1; then echo "M062_R03_FAILURES=FAIL REASON=${fault}_ACCEPTED" >&2; exit 1; fi
  [[ $(sha256sum "$target" | awk '{print $1}') == "$release" ]]
  grep -Fxq 'STATE_STATUS=DEPLOYED' "$test_root/state/m062-r03.state"
  grep -Fq 'M062_R03_STAGING_ROLLBACK_REAPPLY=PASS' "$test_root/rollback-${fault}.log"
done

run_helper '' rollback >"$test_root/rollback-pass.log" 2>&1
[[ $(sha256sum "$target" | awk '{print $1}') == "$baseline" ]]
grep -Fxq 'STATE_STATUS=ROLLED_BACK' "$test_root/state/m062-r03.state"
echo 'M062_R03_FAILURES=PASS APPLY_STATE=RESTORED ROLLBACK_LINT=REAPPLIED ROLLBACK_HTTP=REAPPLIED ROLLBACK_STATE=REAPPLIED FINAL_ROLLBACK=PASS'
