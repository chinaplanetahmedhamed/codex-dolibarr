<?php
/** Entity-scoped Customers Portal group/right correction for M068 staging only. */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M068_PORTAL_GROUP=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('check', 'apply', 'verify'), true)) { fwrite(STDERR, "M068_PORTAL_GROUP=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);
$documentRoot = rtrim((string) (getenv('M068_DOL_DOCUMENT_ROOT') ?: '/var/www/html'), '/');
if ($documentRoot === '' || !is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M068_PORTAL_GROUP=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db, $conf;

$entity = (int) $conf->entity;
if ($entity <= 0) { fwrite(STDERR, "M068_PORTAL_GROUP=FAIL REASON=ENTITY_INVALID\n"); exit(1); }
$lockName = 'chinaplanetos:m068:portal-group:'.$entity;
$locked = false;
$releaseLock = static function () use ($db, &$lockName, &$locked) {
    if (!$locked) return true;
    $res = $db->query("SELECT RELEASE_LOCK('".$db->escape($lockName)."') AS released");
    $row = $res ? $db->fetch_object($res) : null;
    if (!$row || (int) $row->released !== 1) return false;
    $locked = false;
    return true;
};
$fail = static function ($reason) use (&$releaseLock) {
    if (!$releaseLock()) $reason = 'LOCK_RELEASE_FAILED_AFTER_'.$reason;
    fwrite(STDERR, 'M068_PORTAL_GROUP=FAIL REASON='.$reason."\n");
    exit(1);
};
$inspect = static function ($forUpdate = false) use ($db, $entity, $fail) {
    $suffix = $forUpdate ? ' FOR UPDATE' : '';
    $groups = array();
    $sql = "SELECT rowid,entity FROM ".MAIN_DB_PREFIX."usergroup WHERE nom='Customers Portal' AND entity IN (0,".$entity.") ORDER BY entity DESC,rowid".$suffix;
    $res = $db->query($sql);
    if (!$res) $fail('GROUP_QUERY_FAILED');
    while ($row = $db->fetch_object($res)) $groups[] = array('id'=>(int)$row->rowid, 'entity'=>(int)$row->entity);
    $active = array_values(array_filter($groups, static function ($row) use ($entity) { return $row['entity'] === $entity; }));
    $global = array_values(array_filter($groups, static function ($row) { return $row['entity'] === 0; }));
    if (count($active) > 1 || count($global) > 1) $fail('GROUP_AMBIGUOUS');
    $canonical = count($active) === 1 ? $active[0] : (count($global) === 1 ? $global[0] : null);

    $rights = array();
    $sql = "SELECT gr.rowid,gr.fk_usergroup,g.entity AS group_entity FROM ".MAIN_DB_PREFIX."usergroup_rights gr INNER JOIN ".MAIN_DB_PREFIX."usergroup g ON g.rowid=gr.fk_usergroup WHERE gr.entity=".$entity." AND gr.fk_id=500201 AND g.nom='Customers Portal' AND g.entity IN (0,".$entity.") ORDER BY gr.rowid".$suffix;
    $res = $db->query($sql);
    if (!$res) $fail('RIGHT_QUERY_FAILED');
    while ($row = $db->fetch_object($res)) $rights[] = array('id'=>(int)$row->rowid, 'group'=>(int)$row->fk_usergroup, 'group_entity'=>(int)$row->group_entity);
    $canonicalRights = 0;
    $noncanonicalRights = array();
    foreach ($rights as $right) {
        if ($canonical && $right['group'] === $canonical['id']) $canonicalRights++;
        else $noncanonicalRights[] = $right;
    }

    $forbidden = array();
    $sql = "SELECT gr.rowid FROM ".MAIN_DB_PREFIX."usergroup_rights gr INNER JOIN ".MAIN_DB_PREFIX."usergroup g ON g.rowid=gr.fk_usergroup WHERE gr.entity=".$entity." AND gr.fk_id=343 AND g.nom='Customers Portal' AND g.entity IN (0,".$entity.") ORDER BY gr.rowid".$suffix;
    $res = $db->query($sql);
    if (!$res) $fail('FORBIDDEN_RIGHT_QUERY_FAILED');
    while ($row = $db->fetch_object($res)) $forbidden[] = (int) $row->rowid;
    return array(
        'active_count'=>count($active), 'global_count'=>count($global), 'canonical'=>$canonical,
        'canonical_rights'=>$canonicalRights, 'noncanonical_rights'=>$noncanonicalRights,
        'forbidden_rights'=>$forbidden,
    );
};
$ready = static function ($state) {
    return $state['canonical'] !== null && $state['canonical_rights'] === 1
        && count($state['noncanonical_rights']) === 0 && count($state['forbidden_rights']) === 0;
};
$describe = static function ($state) use ($entity) {
    $scope = $state['canonical'] === null ? 'ABSENT' : ($state['canonical']['entity'] === $entity ? 'ENTITY' : 'GLOBAL');
    return 'ACTIVE_GROUPS='.$state['active_count'].' GLOBAL_GROUPS='.$state['global_count'].' CANONICAL_SCOPE='.$scope
        .' CANONICAL_RIGHTS='.$state['canonical_rights'].' NONCANONICAL_RIGHTS='.count($state['noncanonical_rights'])
        .' FORBIDDEN_RIGHTS='.count($state['forbidden_rights']);
};

$rightDef = $db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".$entity." AND id=500201 AND module='chinaplanetcustomercenter' AND enabled='1'");
if (!$rightDef || $db->num_rows($rightDef) !== 1) $fail('RIGHT_DEFINITION_INVALID');

if ($argv[1] === 'check' || $argv[1] === 'verify') {
    $state = $inspect(false);
    if ($argv[1] === 'verify') {
        if (!$ready($state)) $fail('VERIFY_NOT_READY');
        echo 'M068_PORTAL_GROUP_VERIFY=PASS '.$describe($state)." MEMBERSHIPS_CHANGED=NO BUSINESS_RECORDS=NONE\n";
        exit(0);
    }
    if ($ready($state)) { echo 'M068_PORTAL_GROUP_CHECK=READY '.$describe($state)."\n"; exit(0); }
    echo 'M068_PORTAL_GROUP_CHECK=REPAIR_REQUIRED '.$describe($state)."\n";
    exit(2);
}

$lockResult = $db->query("SELECT GET_LOCK('".$db->escape($lockName)."',0) AS acquired");
$lockRow = $lockResult ? $db->fetch_object($lockResult) : null;
if (!$lockRow || (int) $lockRow->acquired !== 1) $fail('LOCK_UNAVAILABLE');
$locked = true;
if (!$db->begin()) $fail('TRANSACTION_BEGIN_FAILED');
$state = $inspect(true);
if ($state['canonical'] === null) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."usergroup(nom,note,entity,datec) VALUES('Customers Portal','M068 Customer Center portal access',".$entity.",'".$db->idate(dol_now())."')";
    if (!$db->query($sql)) { if (!$db->rollback()) $fail('ROLLBACK_FAILED_AFTER_GROUP_CREATE'); $fail('GROUP_CREATE_FAILED'); }
    $state = $inspect(true);
    if ($state['canonical'] === null || $state['canonical']['entity'] !== $entity) { if (!$db->rollback()) $fail('ROLLBACK_FAILED_AFTER_GROUP_VERIFY'); $fail('GROUP_CREATE_VERIFY_FAILED'); }
}
if ($state['canonical_rights'] > 1) { if (!$db->rollback()) $fail('ROLLBACK_FAILED_AFTER_DUPLICATE_RIGHT'); $fail('CANONICAL_RIGHT_DUPLICATE'); }
if ($state['canonical_rights'] === 0) {
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."usergroup_rights(fk_usergroup,fk_id,entity) VALUES(".(int)$state['canonical']['id'].",500201,".$entity.")";
    if (!$db->query($sql)) { if (!$db->rollback()) $fail('ROLLBACK_FAILED_AFTER_RIGHT_INSERT'); $fail('CANONICAL_RIGHT_INSERT_FAILED'); }
}
foreach ($state['noncanonical_rights'] as $right) {
    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."usergroup_rights WHERE rowid=".(int)$right['id']." AND entity=".$entity." AND fk_id=500201")) { if (!$db->rollback()) $fail('ROLLBACK_FAILED_AFTER_NONCANONICAL_DELETE'); $fail('NONCANONICAL_RIGHT_DELETE_FAILED'); }
}
foreach ($state['forbidden_rights'] as $rowid) {
    if (!$db->query("DELETE FROM ".MAIN_DB_PREFIX."usergroup_rights WHERE rowid=".(int)$rowid." AND entity=".$entity." AND fk_id=343")) { if (!$db->rollback()) $fail('ROLLBACK_FAILED_AFTER_FORBIDDEN_DELETE'); $fail('FORBIDDEN_RIGHT_DELETE_FAILED'); }
}
$post = $inspect(true);
if (!$ready($post)) { if (!$db->rollback()) $fail('ROLLBACK_FAILED_AFTER_POST_VERIFY'); $fail('POST_REPAIR_VERIFY_FAILED'); }
if (!$db->commit()) { if (!$db->rollback()) $fail('ROLLBACK_FAILED_AFTER_COMMIT'); $fail('TRANSACTION_COMMIT_FAILED'); }
if (!$releaseLock()) $fail('LOCK_RELEASE_FAILED');
echo 'M068_PORTAL_GROUP_APPLY=PASS '.$describe($post)." MEMBERSHIPS_CHANGED=NO USERS_CHANGED=NO BUSINESS_RECORDS=NONE EMAIL_SENT=NO PRODUCTION_ACCESSED=NO\n";
