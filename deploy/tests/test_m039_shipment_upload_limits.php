<?php
/** Synthetic, database-free acceptance checks for M039. */

function m039Assert($condition, $message)
{
    if (!$condition) throw new RuntimeException('FAIL '.$message);
    print 'PASS '.$message.PHP_EOL;
}

function getDolGlobalInt($name)
{
    return $name === 'MAIN_UPLOAD_DOC' ? 2048 : 0;
}

function dol_sanitizeFileName($value)
{
    return preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) $value);
}

function m039RemoveTree($path)
{
    if (!is_dir($path)) return;
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') continue;
        $child = $path.DIRECTORY_SEPARATOR.$name;
        if (is_dir($child) && !is_link($child)) m039RemoveTree($child);
        else unlink($child);
    }
    rmdir($path);
}

$repo = getenv('M039_REPO_ROOT');
if (!is_string($repo) || $repo === '') $repo = dirname(__DIR__, 2);
$repo = rtrim($repo, '/');
$tmpRoot = sys_get_temp_dir().'/m039-'.bin2hex(random_bytes(6));
define('DOL_DATA_ROOT', $tmpRoot);
define('MAIN_DB_PREFIX', 'llx_');

require_once $repo.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

try {
    $staffLimit = cpcc_portal_staff_upload_max_bytes();
    m039Assert($staffLimit === 200 * 1024 * 1024, 'staff shipment upload limit is exactly 200 MiB');
    m039Assert(cpcc_portal_upload_max_bytes() === 2048 * 1000, 'customer upload keeps the configured Dolibarr limit');

    $config = file_get_contents($repo.'/custom/chinaplanetlogistics/.htaccess');
    foreach (array(
        'php_value upload_max_filesize 200M',
        'php_value post_max_size 210M',
        'php_value memory_limit 1024M',
        'php_value max_execution_time 900',
        'php_value max_input_time 900',
    ) as $directive) {
        m039Assert(substr_count((string) $config, $directive) === 1, 'module configuration contains '.$directive);
    }

    $card = file_get_contents($repo.'/custom/chinaplanetlogistics/card.php');
    m039Assert(substr_count((string) $card, 'cpcc_portal_staff_upload_max_bytes()') === 1, 'only the internal shipment-card upload action requests the staff allowance');

    mkdir($tmpRoot.'/chinaplanetlogistics/ship-1', 0700, true);
    $exactPath = $tmpRoot.'/exact.pdf';
    $exactHandle = fopen($exactPath, 'w+b');
    fwrite($exactHandle, "%PDF-1.4\n");
    ftruncate($exactHandle, $staffLimit);
    fclose($exactHandle);
    clearstatcache(true, $exactPath);

    $exact = cpcc_portal_validate_shipment_upload('exact.pdf', $exactPath, $staffLimit, $staffLimit);
    m039Assert(!empty($exact['ok']) && (int) $exact['size'] === $staffLimit, 'allowed PDF at exactly 200 MiB passes staff size and MIME policy');

    $customer = cpcc_portal_validate_shipment_upload('exact.pdf', $exactPath, $staffLimit);
    m039Assert(empty($customer['ok']) && $customer['error'] === 'File size is not allowed.', 'customer default does not inherit the staff allowance');

    $overPath = $tmpRoot.'/over.pdf';
    $overHandle = fopen($overPath, 'w+b');
    fwrite($overHandle, "%PDF-1.4\n");
    ftruncate($overHandle, $staffLimit + 1);
    fclose($overHandle);
    clearstatcache(true, $overPath);

    $over = cpcc_portal_validate_shipment_upload('over.pdf', $overPath, $staffLimit + 1, $staffLimit);
    m039Assert(empty($over['ok']) && $over['error'] === 'File size is not allowed.', 'one byte above 200 MiB is rejected');

    $iniError = cpcc_portal_store_uploaded_shipment_file(
        1,
        cpcc_portal_shipment_doc_root(1),
        'large.pdf',
        '',
        0,
        UPLOAD_ERR_INI_SIZE,
        $staffLimit
    );
    m039Assert(empty($iniError['ok']) && $iniError['error'] === 'File exceeds the 200 MB upload limit.', 'PHP size rejection reports the exact staff limit safely');

    $customerError = cpcc_portal_store_uploaded_shipment_file(
        1,
        cpcc_portal_shipment_doc_root(1),
        'large.pdf',
        '',
        0,
        UPLOAD_ERR_INI_SIZE
    );
    m039Assert(empty($customerError['ok']) && $customerError['error'] === 'File exceeds the allowed upload size.', 'customer PHP size rejection does not claim the staff allowance');

    print 'M039 shipment upload limit tests passed'.PHP_EOL;
} finally {
    m039RemoveTree($tmpRoot);
}
