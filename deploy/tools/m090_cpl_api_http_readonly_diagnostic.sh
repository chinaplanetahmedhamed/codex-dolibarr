#!/usr/bin/env bash
set -Eeuo pipefail
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

[[ $# -eq 1 && "$1" == '--confirm-read-only' ]] || { echo 'HTTP_DIAGNOSTIC=FAIL ARGUMENTS' >&2; exit 2; }
[[ $(id -u) -eq 0 && $(hostname) == 'srv1011043' ]] || { echo 'HTTP_DIAGNOSTIC=FAIL HOST_OR_USER' >&2; exit 2; }

probe() {
  local label="$1" url="$2" response status body
  response=$(curl --silent --show-error --max-time 15 --write-out '\n%{http_code}' "$url")
  status=${response##*$'\n'}
  body=${response%$'\n'*}
  printf 'PROBE=%s STATUS=%s\n' "$label" "$status"
  printf '%s' "$body" | python3 -c '
import hashlib, json, re, sys
raw = sys.stdin.buffer.read(65536)
print("BODY_BYTES=%d BODY_SHA256=%s" % (len(raw), hashlib.sha256(raw).hexdigest()))
try:
    data = json.loads(raw)
except (ValueError, UnicodeDecodeError):
    print("BODY_FORMAT=NON_JSON")
    sys.exit(0)
print("BODY_FORMAT=JSON")
values = []
def gather(obj):
    if isinstance(obj, dict):
        for key, value in obj.items():
            if key.lower() in ("error", "message", "status", "code", "title", "exception") and isinstance(value, (str, int)):
                values.append(str(value))
            elif isinstance(value, (dict, list)):
                gather(value)
    elif isinstance(obj, list):
        for value in obj[:10]: gather(value)
gather(data)
sample = " ".join(values).lower()
categories = {"AUTH": r"unauthori[sz]ed|authentication|api.key|token|forbidden|permission", "DB": r"database|sql|unknown column|table.*exist|query", "ROUTE": r"route|method|api class|not implemented|not found", "PHP": r"fatal|exception|typeerror|undefined|include|require", "CONFIG": r"module|disabled|configuration|entity"}
matched = [name for name, pattern in categories.items() if re.search(pattern, sample)]
print("ERROR_CATEGORIES=%s" % (",".join(matched) if matched else "NONE"))
'
}

echo 'HTTP_DIAGNOSTIC_SCOPE=UNAUTHENTICATED_API_STATUS_AND_SANITIZED_ERROR_CATEGORIES'
probe CPL_SHIPMENTS 'https://erp.visiontrading.biz/api/index.php/chinaplanetlogistics/shipments'
probe OPERATIONS_CONTROL 'https://erp.visiontrading.biz/api/index.php/chinaplanetoperations/website-requests'
echo 'HTTP_DIAGNOSTIC=PASS READ_ONLY'
