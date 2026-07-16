<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';

$sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."user_rights(entity,fk_user,fk_id)";
$sql .= " SELECT ".(int)$conf->entity.",u.rowid,500401 FROM ".MAIN_DB_PREFIX."user u";
$sql .= " WHERE u.statut=1 AND u.fk_soc>0";
if (!$db->query($sql)) throw new RuntimeException($db->lasterror());
print "External customer read access ensured.\n";
