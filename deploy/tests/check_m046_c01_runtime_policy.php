<?php
/** Non-installable, read-only M046-C01 runtime policy checker. */

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);

$documentRoot = getenv('M046_C01_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') {
    fwrite(STDERR, "M046_C01_POLICY=FAIL REASON=DOL_DOCUMENT_ROOT_REQUIRED\n");
    exit(1);
}
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/master.inc.php')) {
    fwrite(STDERR, "M046_C01_POLICY=FAIL REASON=DOL_DOCUMENT_ROOT_INVALID\n");
    exit(1);
}
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '18088';
require $documentRoot.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php';

$entity = (int) $conf->entity;
$constantName = 'CHINAPLANETSP_AUTO_TRANSLATION_ENABLED';
$sql = "SELECT rowid,name,entity,value,type,visible,note,tms FROM ".MAIN_DB_PREFIX."const";
$sql .= " WHERE entity=".$entity." AND name='".$db->escape($constantName)."'";
$res = $db->query($sql);
if (!$res) {
    fwrite(STDERR, "M046_C01_POLICY=FAIL REASON=CONSTANT_READ_FAILED\n");
    exit(1);
}
$row = $db->fetch_object($res);
$storedEnabled = 0;
$storedRowHash = 'ABSENT';
if ($row) {
    $storedEnabled = empty($row->value) ? 0 : 1;
    $canonical = array(
        'rowid'=>(int) $row->rowid,
        'name'=>(string) $row->name,
        'entity'=>(int) $row->entity,
        'value'=>(string) $row->value,
        'type'=>$row->type === null ? null : (string) $row->type,
        'visible'=>(int) $row->visible,
        'note'=>$row->note === null ? null : (string) $row->note,
        'tms'=>$row->tms === null ? null : (string) $row->tms,
    );
    $storedRowHash = hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

$providerCalls = 0;
$translator = new ShippingNoteTranslationService(
    function () use (&$providerCalls) { $providerCalls++; throw new RuntimeException('transport must not run'); },
    array(
        'CHINAPLANETSP_TRANSLATION_ENDPOINT'=>'https://api.deepseek.com/chat/completions',
        'CHINAPLANETSP_TRANSLATION_MODEL'=>'deepseek-v4-flash',
        'CHINAPLANETSP_TRANSLATION_API_KEY'=>'checker-only-unused',
    ),
    function () { throw new RuntimeException('resolver must not run'); }
);
$result = $translator->translate('M046-C01 policy checker');
$effectiveEnabled = ShippingNoteTranslationService::automaticTranslationEnabled() ? 1 : 0;
if ($effectiveEnabled !== 0 || $providerCalls !== 0 || ($result['error'] ?? '') !== 'translation_disabled' || !empty($result['attempted'])) {
    fwrite(STDERR, "M046_C01_POLICY=FAIL REASON=POLICY_GATE_FAILED\n");
    exit(1);
}

echo 'M046_C01_POLICY=PASS ENTITY='.$entity.' STORED_ENABLED='.$storedEnabled.' STORED_ROW_SHA256='.$storedRowHash.' EFFECTIVE_ENABLED=0 PROVIDER_CALLS=0'."\n";
