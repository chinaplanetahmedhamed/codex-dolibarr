#!/usr/bin/env python3
"""Read-only M085 baseline evidence. No credentials, DB data, writes or restarts."""
import hashlib
import json
from pathlib import Path
import subprocess
import sys

TARGETS = {
    'staging': ('erp-staging-dolibarr-1', 'erp-staging_erp-staging_custom'),
    'production': ('vision_erp', 'vision-os_dolibarr_custom'),
}
FILES = [
    'class/PublicWebsiteRequestService.class.php',
    'class/PublicRequestLifecycleService.class.php',
    'class/PublicRequestQuotationService.class.php',
    'api/class/api_chinaplanetoperations.class.php',
    'class/api_chinaplanetoperations.class.php',
    'operations/public_requests.php',
    'langs/ar_SA/chinaplanetoperations.lang',
    'langs/en_US/chinaplanetoperations.lang',
]

def main():
    if len(sys.argv) != 2 or sys.argv[1] not in TARGETS:
        raise SystemExit('Usage: m085_readonly_pdf_baseline.py staging|production')
    environment = sys.argv[1]
    container, volume = TARGETS[environment]
    inspection = json.loads(subprocess.check_output([
        'docker', 'inspect', '--format', '{{json .Mounts}}', container], text=True))
    mounts = [m for m in inspection if m.get('Destination') == '/var/www/html/custom']
    if len(mounts) != 1 or mounts[0].get('Name') != volume or mounts[0].get('Type') != 'volume':
        raise SystemExit('TARGET_MOUNT_MISMATCH')
    expected = Path('/var/lib/docker/volumes') / volume / '_data'
    if Path(mounts[0]['Source']) != expected or expected.is_symlink():
        raise SystemExit('TARGET_PATH_MISMATCH')
    root = expected / 'chinaplanetoperations'
    result = {'environment': environment, 'container': container, 'volume': volume, 'readOnly': True, 'files': {}}
    for relative in FILES:
        path = root / relative
        if not path.exists():
            result['files'][relative] = {'state': 'absent'}
            continue
        if path.is_symlink() or not path.is_file() or not path.resolve().is_relative_to(root.resolve()):
            raise SystemExit('UNEXPECTED_FILE_TYPE')
        data = path.read_bytes()
        result['files'][relative] = {'sha256': hashlib.sha256(data).hexdigest(), 'bytes': len(data)}
        if relative == 'class/PublicWebsiteRequestService.class.php':
            result['catalogV2'] = b'CATALOG_CONTRACT_VERSION' in data
        if relative == 'api/class/api_chinaplanetoperations.class.php':
            result['pdfEndpointAlreadyPresent'] = b'website-pdf-requests' in data
    runtime = subprocess.check_output(['docker', 'exec', container, 'php', '-r',
        'echo json_encode(array("php"=>PHP_VERSION,"post_max_size"=>ini_get("post_max_size"),"memory_limit"=>ini_get("memory_limit")));'], text=True)
    result['runtime'] = json.loads(runtime)
    print(json.dumps(result, indent=2))

if __name__ == '__main__':
    main()
