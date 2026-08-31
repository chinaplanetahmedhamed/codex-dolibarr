<?php

/**
 * Build deterministic, metadata-free M070 source manifests.
 *
 * This is a local read-only inventory tool. It does not contact a server,
 * inspect configuration or business data, or mutate a runtime/database.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$root = dirname(__DIR__, 2);
$reactRoot = $argv[1] ?? '';
$wordpressRoot = $argv[2] ?? '';

function m070Fail($message)
{
    fwrite(STDERR, "M070_MANIFEST=FAIL REASON=".$message."\n");
    exit(1);
}

function m070Path($value)
{
    $real = realpath($value);
    if ($real === false || !is_dir($real)) {
        m070Fail('SOURCE_ROOT_INVALID');
    }
    return $real;
}

function m070ReadTsv($file)
{
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        m070Fail('EVIDENCE_UNREADABLE');
    }
    $header = fgetcsv($handle, 0, "\t", '"', "\\");
    if ($header !== array('relative_path', 'file_type', 'mode', 'uid', 'gid', 'size', 'sha256')) {
        m070Fail('EVIDENCE_HEADER_INVALID');
    }
    $rows = array();
    while (($row = fgetcsv($handle, 0, "\t", '"', "\\")) !== false) {
        if (count($row) !== 7 || isset($rows[$row[0]])) {
            m070Fail('EVIDENCE_ROW_INVALID');
        }
        $rows[$row[0]] = array_combine($header, $row);
    }
    fclose($handle);
    return $rows;
}

function m070ReadShaList($file)
{
    $rows = array();
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: array() as $line) {
        if (!preg_match('/^([a-f0-9]{64})  ([^\\0]+)$/D', $line, $match)) {
            m070Fail('SHA_LIST_INVALID');
        }
        $rows[$match[2]] = $match[1];
    }
    return $rows;
}

function m070SafeSourcePath($runtimePath)
{
    $maps = array(
        'custom/chinaplanetcustomercenter/chinaplanetlogistics/' => 'custom/chinaplanetlogistics/',
        'custom/chinaplanetcustomercenter/cpkanban/' => 'custom/cpkanban/',
    );
    foreach ($maps as $expanded => $canonical) {
        if (strpos($runtimePath, $expanded) === 0) {
            return array($canonical.substr($runtimePath, strlen($expanded)), 'COMPATIBILITY_SYMLINK_TARGET');
        }
    }
    return array($runtimePath, 'DIRECT');
}

function m070WriteRows($file, array $header, array $rows)
{
    usort($rows, function ($left, $right) {
        return strcmp($left[0], $right[0]);
    });
    $handle = fopen($file, 'wb');
    if ($handle === false) {
        m070Fail('OUTPUT_UNWRITABLE');
    }
    fwrite($handle, implode("\t", $header)."\n");
    foreach ($rows as $row) {
        foreach ($row as $value) {
            if (strpos((string) $value, "\t") !== false || strpos((string) $value, "\n") !== false) {
                m070Fail('OUTPUT_VALUE_INVALID');
            }
        }
        fwrite($handle, implode("\t", $row)."\n");
    }
    fflush($handle);
    fclose($handle);
}

function m070GitValue($repo, $arguments)
{
    $command = 'git -C '.escapeshellarg($repo).' '.$arguments;
    $output = array();
    $status = 0;
    exec($command, $output, $status);
    if ($status !== 0) {
        return null;
    }
    return rtrim(implode("\n", $output), "\r\n");
}

function m070AssertRepository($repo, $expectedHead)
{
    $head = m070GitValue($repo, 'rev-parse HEAD');
    if ($head !== $expectedHead) {
        m070Fail('GIT_HEAD_MISMATCH:'.basename($repo));
    }
    $status = m070GitValue($repo, 'status --porcelain --untracked-files=all');
    if ($status === null) {
        m070Fail('GIT_STATUS_FAILED:'.basename($repo));
    }
    foreach ($status === '' ? array() : explode("\n", $status) as $row) {
        if (substr($row, 0, 2) === '??' || substr($row, 1, 1) !== ' ') {
            m070Fail('GIT_UNSTAGED_OR_UNTRACKED:'.basename($repo));
        }
    }
}

function m070GitFiles($repo)
{
    $command = 'git -C '.escapeshellarg($repo).' ls-files --cached -z';
    $raw = shell_exec($command);
    if (!is_string($raw)) {
        m070Fail('GIT_FILE_LIST_FAILED');
    }
    $files = array_values(array_filter(explode("\0", $raw), 'strlen'));
    sort($files, SORT_STRING);
    return $files;
}

function m070GitIndexBlob($repo, $relative)
{
    $value = m070GitValue($repo, 'ls-files -s -- '.escapeshellarg($relative));
    if (!is_string($value) || !preg_match('/^[0-9]+ ([a-f0-9]+) 0\t/', $value, $match)) {
        m070Fail('GIT_INDEX_BLOB_MISSING:'.$relative);
    }
    return $match[1];
}

function m070GitCommitBlob($repo, $commit, $relative)
{
    if ($commit === '') {
        return null;
    }
    return m070GitValue($repo, 'rev-parse '.escapeshellarg($commit.':'.$relative).' 2>/dev/null');
}

function m070GitChangedFiles($repo, $baseCommit, $overlayCommit)
{
    $command = 'git -C '.escapeshellarg($repo).' diff --name-only -z '.escapeshellarg($baseCommit).' '.escapeshellarg($overlayCommit);
    $raw = shell_exec($command);
    if (!is_string($raw)) {
        m070Fail('GIT_OVERLAY_LIST_FAILED');
    }
    return array_fill_keys(array_values(array_filter(explode("\0", $raw), 'strlen')), true);
}

function m070RepositoryRows($repo, $baseCommit, $overlayCommit, $kind, $prefix = '')
{
    $overlay = $overlayCommit === '' ? array() : m070GitChangedFiles($repo, $baseCommit, $overlayCommit);
    $rows = array();
    foreach (m070GitFiles($repo) as $relative) {
        if ($prefix !== '' && strpos($relative, $prefix) !== 0) {
            continue;
        }
        if (preg_match('#^(?:node_modules|dist|vendor)/#', $relative)) {
            continue;
        }
        if (preg_match('#^docs/REPORTS/M070_(?:REACT|WORDPRESS|MOBILE)_SOURCE_MANIFEST\\.tsv$#', $relative)) {
            continue;
        }
        $absolute = $repo.'/'.$relative;
        if (!is_file($absolute)) {
            continue;
        }
        $surface = 'support';
        if ($kind === 'react') {
            if (strpos($relative, 'src/') === 0) $surface = 'public-ui';
            elseif (strpos($relative, 'server/') === 0 || strpos($relative, 'api/') === 0) $surface = 'secure-node';
            elseif (strpos($relative, 'docs/') === 0) $surface = 'documentation';
        } elseif ($kind === 'wordpress') {
            if (strpos($relative, 'includes/') === 0 || preg_match('/\\.php$/', $relative)) $surface = 'plugin-runtime';
            if (strpos($relative, 'tests/') === 0) $surface = 'test';
        } elseif ($kind === 'mobile') {
            if (strpos($relative, 'mobile/chinaplanet-customer-app/app/') === 0) $surface = 'mobile-ui';
            elseif (strpos($relative, 'mobile/chinaplanet-customer-app/src/') === 0) $surface = 'mobile-client';
            elseif (strpos($relative, 'mobile/chinaplanet-customer-app/tests/') === 0) $surface = 'test';
            else $surface = 'documentation-support';
        }
        $indexBlob = m070GitIndexBlob($repo, $relative);
        $overlayBlob = isset($overlay[$relative]) ? m070GitCommitBlob($repo, $overlayCommit, $relative) : null;
        $baseBlob = m070GitCommitBlob($repo, $baseCommit, $relative);
        if ($overlayBlob !== null && $indexBlob === $overlayBlob) {
            $sourceCommit = $overlayCommit;
        } elseif ($baseBlob !== null && $indexBlob === $baseBlob) {
            $sourceCommit = $baseCommit;
        } else {
            $sourceCommit = 'M070_LOCAL_COMPOSITE';
        }
        $rows[] = array($relative, $surface, (string) filesize($absolute), hash_file('sha256', $absolute), $sourceCommit);
    }
    return $rows;
}

$reactRoot = m070Path($reactRoot);
$wordpressRoot = m070Path($wordpressRoot);
m070AssertRepository($root, '4309634566601c79845aad00437708a60a969c90');
m070AssertRepository($reactRoot, 'cb639518a784ce709e4a45eee0bd55b0a477c57a');
m070AssertRepository($wordpressRoot, 'fa34257fe9dfc276d85c16dae70b409224465c3c');
$production = m070ReadTsv($root.'/docs/REPORTS/M069_PRODUCTION_RUNTIME_MANIFEST.tsv');
$staging = m070ReadTsv($root.'/docs/REPORTS/M069_BACKUP_ERP_RUNTIME_MANIFEST.tsv');
$m061 = m070ReadShaList($root.'/docs/REPORTS/M069_M061_FINAL_EFFECTIVE_RUNTIME.sha256');

$excluded = array_fill_keys(array(
    'custom/chinaplanetcustomercenter/admin/acceptance_demo.php',
    'custom/chinaplanetcustomercenter/admin/demo/seed_customer_portal_demo.php',
    'custom/chinaplanetcustomercenter/admin/demo/seed_full_order_cycles.php',
    'custom/chinaplanetcustomercenter/admin/demo/seed_operations_sops.php',
), true);
$acceptedStagingSources = array(
    'custom/chinaplanetlogistics/.htaccess' => array(
        'layer' => 'M039_ACCEPTED_STAGING_DEPENDENCY',
        'commit' => '851c231cc96f51d2c3fafcb32c0a172e4f9973d5',
        'sha256' => '45b9e1fbfdb9279beeba83af81ebfc881f3edad9706d54e7ee50806afa169daf',
    ),
);
$acceptedMainSuccessors = array(
    'custom/chinaplanetcustomercenter/core/triggers/interface_99_modChinaPlanetCustomerCenter_CustomerProjectSync.class.php' => array(
        'layer' => 'M054_ACCEPTED_MAIN_PROJECT_SYNC_OWNER',
        'commit' => '87efe25453b9b9a9a0eec95a6386898508076c85',
        'sha256' => '28e6f35da5e8f818686ab567ef0beb0ad5e5266f049d18799a57ae4a803c32a9',
    ),
    'custom/chinaplanetcustomercenter/lib/projectsync.lib.php' => array(
        'layer' => 'M054_ACCEPTED_MAIN_PROJECT_SYNC_COMPATIBILITY',
        'commit' => '87efe25453b9b9a9a0eec95a6386898508076c85',
        'sha256' => '9acf384cc0b0fb4c41cf760bf5d488cf31c17f08097e0164dd0fd0d9bc6da7b0',
    ),
    'custom/cpkanban/board.php' => array(
        'layer' => 'M054_ACCEPTED_MAIN_PROJECT_SYNC_OWNER',
        'commit' => '87efe25453b9b9a9a0eec95a6386898508076c85',
        'sha256' => 'b8f86e74c670cf36ad59535a8a989d7f3fffc04c3611cf4b17d131c92f08f5a6',
    ),
    'custom/chinaplanetshippingprices/class/CustomerShippingBookingService.class.php' => array(
        'layer' => 'M053_ACCEPTED_MAIN_DEPENDENCY_BOUNDARY',
        'commit' => 'c60d8fb2991d80694336851ecadecbde78e5daa8',
        'sha256' => 'a87b85fae568b5186ec8ba01555d17e53ff389ccc6c630cb08d6263b43478cb3',
    ),
    'custom/chinaplanetshippingprices/customer_booking.php' => array(
        'layer' => 'M053_ACCEPTED_MAIN_DEPENDENCY_BOUNDARY',
        'commit' => 'c60d8fb2991d80694336851ecadecbde78e5daa8',
        'sha256' => '492ff119dffaad995a8472ddc603b025c1f538ec460321ffc2d067ab2c0404fd',
    ),
    'custom/chinaplanetshippingprices/index.php' => array(
        'layer' => 'M041_ACCEPTED_MAIN_PUBLIC_NOTES',
        'commit' => 'f0b722183419ca2cfc38cb4939ae6b9d12b6d1fa',
        'sha256' => 'd185b2428645ed6e5c2b03593395a5969b0425c5bd5e2e5c20a7a86f46902fbf',
    ),
);
$m070SecurityCorrections = array(
    'custom/chinaplanetcustomercenter/invoice.php' => array(
        'layer' => 'M070_LOCAL_SECURITY_CORRECTION',
        'commit' => 'M070_LOCAL_COMPOSITE',
        'sha256' => '3276a410394ac693515cc82319e0c11ab60e625982e083d50c01ae000dbfdc0c',
    ),
    'custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php' => array(
        'layer' => 'M070_LOCAL_SECURITY_CORRECTION',
        'commit' => 'M070_LOCAL_COMPOSITE',
        'sha256' => 'e6c837c299af4b018d85686b3d0bb37e0d0fccf5c4b4d0c53a0b8bf086113607',
    ),
);

$paths = array_unique(array_merge(array_keys($production), array_keys($staging)));
$runtimeRows = array();
foreach ($paths as $runtimePath) {
    $productionSha = $production[$runtimePath]['sha256'] ?? '-';
    $stagingSha = $staging[$runtimePath]['sha256'] ?? '-';
    if (isset($excluded[$runtimePath])) {
        $runtimeRows[] = array($runtimePath, '-', '-', 'EXCLUDED_M069_C04_RESIDUE', '-', $productionSha, $stagingSha, 'ABSENT_FROM_SOURCE_AND_FUTURE_RELEASES');
        continue;
    }
    list($sourcePath, $mapping) = m070SafeSourcePath($runtimePath);
    $absolute = $root.'/'.$sourcePath;
    if (!is_file($absolute)) {
        m070Fail('RUNTIME_SOURCE_MISSING:'.$runtimePath);
    }
    $sourceSha = hash_file('sha256', $absolute);
    $sourceLayer = isset($m061[$sourcePath]) ? 'M061_FINAL_EFFECTIVE' : 'M062_ACCEPTED_MAIN';
    $sourceCommit = isset($m061[$sourcePath])
        ? '92854682f9192b47f2bfdf9013b8c4e4dcb9e49e'
        : '057af0f205f819c685869b3ee6aa1aea31ea549e';
    if (isset($acceptedStagingSources[$runtimePath])) {
        $sourceLayer = $acceptedStagingSources[$runtimePath]['layer'];
        $sourceCommit = $acceptedStagingSources[$runtimePath]['commit'];
        if ($sourceSha !== $acceptedStagingSources[$runtimePath]['sha256']) {
            m070Fail('ACCEPTED_STAGING_SOURCE_HASH_MISMATCH:'.$runtimePath);
        }
    }
    if (isset($acceptedMainSuccessors[$sourcePath])) {
        $sourceLayer = $acceptedMainSuccessors[$sourcePath]['layer'];
        $sourceCommit = $acceptedMainSuccessors[$sourcePath]['commit'];
        if ($sourceSha !== $acceptedMainSuccessors[$sourcePath]['sha256']) {
            m070Fail('ACCEPTED_MAIN_SUCCESSOR_HASH_MISMATCH:'.$sourcePath);
        }
    }
    if (isset($m070SecurityCorrections[$sourcePath])) {
        $sourceLayer = $m070SecurityCorrections[$sourcePath]['layer'];
        $sourceCommit = $m070SecurityCorrections[$sourcePath]['commit'];
        if ($sourceSha !== $m070SecurityCorrections[$sourcePath]['sha256']) {
            m070Fail('M070_SECURITY_CORRECTION_HASH_MISMATCH:'.$sourcePath);
        }
    } elseif (isset($m061[$sourcePath]) && $sourceSha !== $m061[$sourcePath]) {
        m070Fail('M061_SOURCE_HASH_MISMATCH:'.$runtimePath);
    }
    $runtimeRows[] = array($runtimePath, $sourcePath, $sourceSha, $sourceLayer, $sourceCommit, $productionSha, $stagingSha, $mapping);
}

m070WriteRows(
    $root.'/docs/REPORTS/M070_DOLIBARR_RUNTIME_SOURCE_MAP.tsv',
    array('runtime_path', 'canonical_source_path', 'selected_sha256', 'source_layer', 'source_commit', 'production_sha256', 'staging_sha256', 'disposition'),
    $runtimeRows
);

$reactRows = m070RepositoryRows(
    $reactRoot,
    'cb639518a784ce709e4a45eee0bd55b0a477c57a',
    'df5ae8bd8e7c352f435cf175e5b411c9c53ecdce',
    'react'
);
m070WriteRows(
    $root.'/docs/REPORTS/M070_REACT_SOURCE_MANIFEST.tsv',
    array('relative_path', 'surface', 'size', 'sha256', 'source_commit'),
    $reactRows
);

$wordpressRows = m070RepositoryRows(
    $wordpressRoot,
    'fa34257fe9dfc276d85c16dae70b409224465c3c',
    '',
    'wordpress'
);
m070WriteRows(
    $root.'/docs/REPORTS/M070_WORDPRESS_SOURCE_MANIFEST.tsv',
    array('relative_path', 'surface', 'size', 'sha256', 'source_commit'),
    $wordpressRows
);

$mobileRows = m070RepositoryRows(
    $root,
    '057af0f205f819c685869b3ee6aa1aea31ea549e',
    '92854682f9192b47f2bfdf9013b8c4e4dcb9e49e',
    'mobile',
    'mobile/chinaplanet-customer-app/'
);
m070WriteRows(
    $root.'/docs/REPORTS/M070_MOBILE_SOURCE_MANIFEST.tsv',
    array('relative_path', 'surface', 'size', 'sha256', 'source_commit'),
    $mobileRows
);

printf(
    "M070_MANIFEST=PASS DOLIBARR_RUNTIME=%d REACT=%d WORDPRESS=%d MOBILE=%d\n",
    count($runtimeRows),
    count($reactRows),
    count($wordpressRows),
    count($mobileRows)
);
