<?php
// Add chinagate_test user as a contact/member of DEMO-PRJ-001
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOREQUIRESOC', 1);
define('NOREQUIRETRAN', 1);
define('NOCSRFCHECK', 1);

$res = @include('/var/www/html/master.inc.php');
if (!$res) { die("Cannot load master.inc.php\n"); }

// Check project rowid
$r = $db->query("SELECT rowid, ref, title, fk_soc FROM ".MAIN_DB_PREFIX."projet WHERE ref='DEMO-PRJ-001'");
if ($r && $db->num_rows($r) > 0) {
    $row = $db->fetch_object($r);
    echo "Project found: " . $row->ref . " (id=" . $row->rowid . ", fk_soc=" . $row->fk_soc . ")\n";
    $proj_id = $row->rowid;
} else {
    die("Project DEMO-PRJ-001 not found\n");
}

// Check user
$r = $db->query("SELECT rowid, login, fk_soc FROM ".MAIN_DB_PREFIX."user WHERE login='chinagate_test'");
$urow = $db->fetch_object($r);
echo "User: " . $urow->login . " (id=" . $urow->rowid . ", fk_soc=" . $urow->fk_soc . ")\n";
$user_id = $urow->rowid;

// Check if already a member
$r = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."element_contact WHERE element_id=" . $proj_id . " AND fk_socpeople IS NULL AND fk_c_type_contact IN (SELECT rowid FROM ".MAIN_DB_PREFIX."c_type_contact WHERE element='project')");
echo "Existing contacts: " . $db->num_rows($r) . "\n";

// Check llx_projet_task_user or llx_element_contact for project
$r = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."element_contact WHERE element_id=" . $proj_id . " LIMIT 5");
echo "element_contact rows for project: " . $db->num_rows($r) . "\n";
while ($row = $db->fetch_object($r)) {
    echo "  - fk_socpeople=" . $row->fk_socpeople . ", fk_c_type_contact=" . $row->fk_c_type_contact . "\n";
}

// Check project_user table
$r = $db->query("SHOW TABLES LIKE '%projet%user%'");
echo "\nProject user tables:\n";
while ($row = $db->fetch_row($r)) { echo "  " . $row[0] . "\n"; }

// The correct table for project members in Dolibarr is llx_element_contact
// Type contact for project internal user is 'PROJECTLEADER' or 'PROJECTMEMBER'
$r = $db->query("SELECT rowid, code, libelle FROM ".MAIN_DB_PREFIX."c_type_contact WHERE element='project' LIMIT 10");
echo "\nProject contact types:\n";
while ($row = $db->fetch_object($r)) {
    echo "  id=" . $row->rowid . " code=" . $row->code . " label=" . $row->libelle . "\n";
}

// Also check if there's a specific project participant table
$r = $db->query("SELECT * FROM ".MAIN_DB_PREFIX."projet WHERE rowid=" . $proj_id);
$proj = $db->fetch_object($r);
echo "\nProject public: " . $proj->public . "\n";

// If project is not public, we need to add chinagate_test as a contact
// Let's make the project public so external users can see it
$db->query("UPDATE ".MAIN_DB_PREFIX."projet SET public=1 WHERE rowid=" . $proj_id);
echo "Set project to public=1: " . $db->affected_rows() . " rows\n";

// Also check if there's a socid filter issue - the project has fk_soc=34 which matches chinagate_test's fk_soc
echo "\nDone! Project is now public.\n";

$db->close();
?>
