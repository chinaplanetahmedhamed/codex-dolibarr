<?php
/** Preserve and restore one local admin dashboard layout around browser QA. */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M036_LAYOUT_BACKUP=FAIL REASON=CLI_ONLY\n"); exit(1); }
define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOREQUIREAJAX', 1); define('NOCSRFCHECK', 1);
$root = getenv('M036_DOL_DOCUMENT_ROOT');
if (!$root) $root = '/var/www/html';
$_SERVER['DOCUMENT_ROOT'] = rtrim($root, '/'); $_SERVER['SERVER_NAME'] = 'localhost'; $_SERVER['SERVER_PORT'] = '80';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
global $db, $conf;

$action = isset($argv[1]) ? (string) $argv[1] : '';
$login = getenv('M036_LAYOUT_LOGIN') ?: 'admin';
$backupPath = '/private/tmp/chinaplanetos-m036-dashboard-layout-backup.json';
$dashboardKey = 'admin_operations';

function m036LayoutFail($message) { throw new RuntimeException($message); }
try {
    $entity = (int) $conf->entity;
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE login='".$db->escape($login)."' AND entity IN (0,".$entity.") AND statut=1 AND admin=1 LIMIT 1");
    $userRow = $res ? $db->fetch_object($res) : null;
    if (!$userRow) m036LayoutFail('ADMIN_USER_NOT_FOUND');
    $userId = (int) $userRow->rowid;

    if ($action === 'backup') {
        if (is_file($backupPath)) m036LayoutFail('BACKUP_ALREADY_EXISTS');
        $rows = array();
        $sql = "SELECT widget_key,position,widget_size,visible,date_creation FROM ".MAIN_DB_PREFIX."cpcc_dashboard_layout WHERE entity=".$entity." AND fk_user=".$userId." AND dashboard_key='".$db->escape($dashboardKey)."' ORDER BY position,rowid";
        $res = $db->query($sql); if (!$res) m036LayoutFail('BACKUP_QUERY_'.$db->lasterror());
        while ($row = $db->fetch_object($res)) $rows[] = array('key'=>(string)$row->widget_key,'position'=>(int)$row->position,'size'=>(string)$row->widget_size,'visible'=>(int)$row->visible,'date_creation'=>(string)$row->date_creation);
        $payload = json_encode(array('entity'=>$entity,'user_id'=>$userId,'dashboard_key'=>$dashboardKey,'rows'=>$rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($backupPath, $payload, LOCK_EX) === false) m036LayoutFail('BACKUP_WRITE_FAILED');
        @chmod($backupPath, 0600);
        echo 'M036_LAYOUT_BACKUP=PASS rows='.count($rows)."\n";
        exit(0);
    }

    if ($action === 'restore') {
        if (!is_file($backupPath)) m036LayoutFail('BACKUP_NOT_FOUND');
        $payload = json_decode((string) file_get_contents($backupPath), true);
        if (!is_array($payload) || (int)($payload['entity'] ?? 0) !== $entity || (int)($payload['user_id'] ?? 0) !== $userId || ($payload['dashboard_key'] ?? '') !== $dashboardKey || !is_array($payload['rows'] ?? null)) m036LayoutFail('BACKUP_INVALID');
        $db->begin();
        $delete = "DELETE FROM ".MAIN_DB_PREFIX."cpcc_dashboard_layout WHERE entity=".$entity." AND fk_user=".$userId." AND dashboard_key='".$db->escape($dashboardKey)."'";
        if (!$db->query($delete)) { $db->rollback(); m036LayoutFail('RESTORE_DELETE_'.$db->lasterror()); }
        foreach ($payload['rows'] as $row) {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_dashboard_layout(entity,fk_user,dashboard_key,widget_key,position,widget_size,visible,date_creation) VALUES(".$entity.",".$userId.",'".$db->escape($dashboardKey)."','".$db->escape((string)$row['key'])."',".(int)$row['position'].",'".$db->escape((string)$row['size'])."',".(!empty($row['visible'])?1:0).",'".$db->escape((string)$row['date_creation'])."')";
            if (!$db->query($sql)) { $db->rollback(); m036LayoutFail('RESTORE_INSERT_'.$db->lasterror()); }
        }
        $db->commit();
        if (!unlink($backupPath)) m036LayoutFail('BACKUP_REMOVE_FAILED');
        echo 'M036_LAYOUT_RESTORE=PASS rows='.count($payload['rows'])."\n";
        exit(0);
    }

    if ($action === 'status') {
        $res = $db->query("SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."cpcc_dashboard_layout WHERE entity=".$entity." AND fk_user=".$userId." AND dashboard_key='".$db->escape($dashboardKey)."'");
        $row = $res ? $db->fetch_object($res) : null;
        echo 'M036_LAYOUT_STATUS rows='.($row ? (int)$row->total : -1).' backup='.(is_file($backupPath)?'yes':'no')."\n";
        exit(0);
    }
    m036LayoutFail('ACTION_REQUIRED_backup_restore_status');
} catch (Throwable $e) {
    fwrite(STDERR, 'M036_LAYOUT_BACKUP=FAIL REASON='.$e->getMessage()."\n");
    exit(1);
}
