#!/usr/bin/env bash
# M084: reviewed read-only production incident evidence. No apply, DB access, or config output.
# Requires an operator already authorized to read Docker metadata/logs on this server.
set -euo pipefail
[[ "${1:-}" == '--read-only' && "$#" == 1 ]] || { echo 'Usage: m084-production-readonly-diagnostics.sh --read-only' >&2; exit 2; }
container=vision_erp
[[ "$(docker inspect --format '{{.Name}} {{.State.Running}}' "$container")" == '/vision_erp true' ]] || { echo 'PRODUCTION_CONTAINER_NOT_RUNNING' >&2; exit 1; }
echo 'M084_DIAGNOSTIC_SCOPE=PHP_VERSION_AND_REDACTED_INCIDENT_ERRORS_ONLY'
docker exec "$container" php -r 'echo "PHP_VERSION=".PHP_VERSION.PHP_EOL;'
# Pipe all input through a redactor. Never output access logs, cookies, credentials, SQL, or raw traces.
docker logs --since '2026-09-15T00:00:00Z' --tail 5000 "$container" 2>&1 | python3 -c '
import re,sys
count=0
for line in sys.stdin:
    if not re.search(r"PHP (?:Fatal|Parse) error|Uncaught (?:Error|TypeError|Exception)|Allowed memory size", line): continue
    if not re.search(r"chinaplanetcustomercenter|chinaplanetlogistics|cpkanban|cpcc_|cpl_|CplBilingual",line): continue
    line=re.sub(r"https?://\S+", "[URL]",line)
    line=re.sub(r"\b(?:client|referer):.*$", "[REQUEST_METADATA]",line)
    line=re.sub(r"\b(?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b.*", "[SQL_REDACTED]",line,flags=re.I)
    line=re.sub(r"\x27[^\x27]*\x27|\x22[^\x22]*\x22", "[QUOTED_VALUE]",line)
    line=re.sub(r"\b(?:password|passwd|token|authorization|cookie)\s*[=:]\s*\S+", "[SECRET_REDACTED]",line,flags=re.I)
    print(line.strip()[:1200]);count+=1
print("MATCHED_INCIDENT_ERRORS="+str(count))
print("NO_MATCH_IS_NOT_PROOF_OF_NO_ERROR; PHP may write to a separate configured error log.")
'
echo 'DATABASE_ACCESSED=NO APPLICATION_FILES_CHANGED=NO DEPLOYED=NO'
