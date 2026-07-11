<?php
/**
 * China Planet Kanban - Customer onboarding helper.
 * Idempotently adds external users to the Customers Portal group and sets their landing page to chinaplanetlogistics/board.php.
 */
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../lib/chinaplanetlogistics.lib.php';

if (empty($user->id) || (!$user->hasRight('chinaplanetlogistics', 'admin') && empty($user->admin))) {
    accessforbidden();
}

$langs->loadLangs(array('users', 'admin', 'other'));

function cpk_onboard_get_group($db)
{
    global $conf;
    $entity = isset($conf->entity) ? (int) $conf->entity : 1;
    $name = 'Customers Portal';
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup WHERE nom = '".$db->escape($name)."' AND entity IN (0,".$entity.") ORDER BY entity DESC LIMIT 1";
    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) return (int) $obj->rowid;

    $sql = "INSERT INTO ".MAIN_DB_PREFIX."usergroup (nom, note, entity, datec) VALUES ('".$db->escape($name)."', 'Auto-created by CP Kanban customer onboarding', ".$entity.", '".$db->idate(dol_now())."')";
    if (!$db->query($sql)) return -1;
    return (int) $db->last_insert_id(MAIN_DB_PREFIX.'usergroup');
}

function cpk_onboard_grant_read($db, $groupId)
{
    global $conf;
    $entity = isset($conf->entity) ? (int) $conf->entity : 1;
    $rightId = 500101;
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup_rights WHERE fk_usergroup = ".(int)$groupId." AND fk_id = ".(int)$rightId." AND entity = ".$entity;
    $resql = $db->query($sql);
    if ($resql && ($db->fetch_object($resql))) return 1;
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."usergroup_rights (fk_usergroup, fk_id, entity) VALUES (".(int)$groupId.", ".(int)$rightId.", ".$entity.")";
    return $db->query($sql) ? 1 : -1;
}

function cpk_onboard_user($db, $userId, $groupId)
{
    global $conf;
    $entity = isset($conf->entity) ? (int) $conf->entity : 1;
    $userId = (int) $userId;
    if ($userId <= 0 || $groupId <= 0) return -1;

    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup_user WHERE fk_user = ".$userId." AND fk_usergroup = ".(int)$groupId." AND entity = ".$entity;
    $resql = $db->query($sql);
    if (!($resql && $db->fetch_object($resql))) {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."usergroup_user (fk_user, fk_usergroup, entity) VALUES (".$userId.", ".(int)$groupId.", ".$entity.")";
        if (!$db->query($sql)) return -1;
    }

    $param = 'MAIN_LANDING_PAGE';
    $value = '/custom/chinaplanetlogistics/chinaplanetlogistics/board.php';
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."user_param WHERE fk_user = ".$userId." AND param = '".$db->escape($param)."' AND entity = ".$entity;
    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        $sql = "UPDATE ".MAIN_DB_PREFIX."user_param SET value = '".$db->escape($value)."' WHERE rowid = ".(int)$obj->rowid;
        if (!$db->query($sql)) return -1;
    } else {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."user_param (fk_user, entity, param, value) VALUES (".$userId.", ".$entity.", '".$db->escape($param)."', '".$db->escape($value)."')";
        if (!$db->query($sql)) return -1;
    }
    return 1;
}

$action = GETPOST('action', 'alpha');
$messages = array();
$token = GETPOST('token', 'alpha');
$tokenOk = true;
if (function_exists('checkToken')) {
    $tokenOk = checkToken($token);
} elseif (isset($_SESSION['newtoken'])) {
    $tokenOk = ($token == $_SESSION['newtoken']);
}
if ($action == 'onboard' && $tokenOk) {
    $mode = GETPOST('mode', 'alpha');
    $targetUser = GETPOST('fk_user', 'int');
    $groupId = cpk_onboard_get_group($db);
    if ($groupId <= 0) {
        setEventMessages('Could not create/find Customers Portal group: '.$db->lasterror(), null, 'errors');
    } else {
        cpk_onboard_grant_read($db, $groupId);
        $users = array();
        if ($mode == 'all') {
            $sql = "SELECT rowid, login FROM ".MAIN_DB_PREFIX."user WHERE statut = 1 AND fk_soc > 0";
            $resql = $db->query($sql);
            if ($resql) while ($obj = $db->fetch_object($resql)) $users[(int)$obj->rowid] = $obj->login;
        } elseif ($targetUser > 0) {
            $users[$targetUser] = 'user '.$targetUser;
        }
        $ok = 0; $ko = 0;
        foreach ($users as $uid => $login) {
            $r = cpk_onboard_user($db, $uid, $groupId);
            if ($r > 0) $ok++; else $ko++;
        }
        setEventMessages('Onboarding finished. OK: '.$ok.' / Failed: '.$ko, null, $ko ? 'warnings' : 'mesgs');
    }
}

llxHeader('', 'CP Kanban - Customer Onboarding', '', '', 0, 0, array(), array('/custom/chinaplanetlogistics/chinaplanetlogistics/css/board.css'));
print '<div class="cpk-wrap" dir="rtl">';
print load_fiche_titre('تهيئة العملاء تلقائياً', '', 'user');
print '<div class="cpk-banner">هذه الصفحة تضيف العملاء الخارجيين إلى مجموعة Customers Portal وتضبط صفحة البداية لهم على مركز العميل Kanban. العملية آمنة ويمكن تشغيلها أكثر من مرة.</div>';
print '<div class="cpk-detail-card">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="onboard">';
print '<table class="border centpercent">';
print '<tr><td class="titlefield">النطاق</td><td>';
print '<label><input type="radio" name="mode" value="all" checked> كل المستخدمين الخارجيين النشطين</label><br>';
print '<label><input type="radio" name="mode" value="one"> مستخدم واحد فقط — rowid:</label> <input type="number" name="fk_user" class="flat" style="width:120px">';
print '</td></tr>';
print '<tr><td>ما سيتم ضبطه</td><td><ul><li>إنشاء/استخدام مجموعة Customers Portal</li><li>منح صلاحية chinaplanetlogistics read رقم 500101 للمجموعة</li><li>إضافة المستخدم الخارجي للمجموعة</li><li>تعيين MAIN_LANDING_PAGE = /custom/chinaplanetlogistics/chinaplanetlogistics/board.php</li></ul></td></tr>';
print '</table>';
print '<div class="center" style="margin-top:14px"><input type="submit" class="button" value="تشغيل التهيئة"></div>';
print '</form>';
print '</div></div>';
llxFooter();
