#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

[[ $# -eq 1 && "$1" == '--confirm-read-only' ]] || { echo 'DIAGNOSTIC=FAIL ARGUMENTS' >&2; exit 2; }
[[ $(id -u) -eq 0 && $(hostname) == 'srv1011043' ]] || { echo 'DIAGNOSTIC=FAIL HOST_OR_USER' >&2; exit 2; }

readonly backup='/root/cpl-api-backups/cpl_api_pre_apply_20260924_161647.tar.gz'
readonly container='vision_erp'

echo 'DIAGNOSTIC_SCOPE=CPL_API_SYNTAX_BACKUP_AND_SANITIZED_FATAL_MARKERS'
if [[ -f "$backup" && ! -L "$backup" ]]; then
  stat -c 'BACKUP=%U:%G %a %s %n' "$backup"
  sha256sum "$backup" | awk '{print "BACKUP_SHA256=" $1}'
  tar -tzf "$backup" | awk '
    /^chinaplanetlogistics\/(class\/api_chinaplanetlogistics\.class\.php|api\/class\/api_chinaplanetlogistics\.class\.php|core\/modules\/modChinaPlanetLogistics\.class\.php)$/ { print "BACKUP_MEMBER=" $0 }
  '
else
  echo 'BACKUP=ABSENT_OR_INVALID'
fi

docker exec "$container" php -l /var/www/html/custom/chinaplanetlogistics/class/api_chinaplanetlogistics.class.php
docker exec "$container" php -l /var/www/html/custom/chinaplanetlogistics/api/class/api_chinaplanetlogistics.class.php
docker exec "$container" php -l /var/www/html/custom/chinaplanetlogistics/core/modules/modChinaPlanetLogistics.class.php

docker logs --since '2026-09-24T16:15:00Z' --tail 5000 "$container" 2>&1 | python3 -c '
import re, sys
count = 0
for line in sys.stdin:
    if not re.search(r"PHP (Fatal|Parse) error|Uncaught (Error|Exception|TypeError)", line, re.I):
        continue
    count += 1
    kind = "PARSE" if "Parse" in line else "FATAL"
    path = re.search(r"/var/www/html/([A-Za-z0-9_./-]+)(?::| on line )(\d+)", line)
    if path:
        print("ERROR_MARKER=%s FILE=%s LINE=%s" % (kind, path.group(1), path.group(2)))
    else:
        print("ERROR_MARKER=%s FILE=UNAVAILABLE" % kind)
print("ERROR_MARKER_COUNT=%d" % count)
'
echo 'DIAGNOSTIC=PASS READ_ONLY'
