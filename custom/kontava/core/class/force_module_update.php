<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Ce script doit être appelé depuis Dolibarr (utilisateur connecté)
define('NOLOGIN', 0); // On veut un utilisateur loggé
define('NOCSRFCHECK', 0); // On veut la protection CSRF
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);

// require '../../../main.inc.php'; // Chemin relatif depuis /htdocs/custom/kontava/core/class/
// require_once __DIR__ . '/../../../../main.inc.php';
// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Debugging output to help identify session issues
file_put_contents(__DIR__ . '/kontava_debug.log', print_r([
    'session_id' => session_id(),
    'user' => $user,
    '_COOKIE' => $_COOKIE,
    '_SERVER' => $_SERVER['REQUEST_URI']
], true), FILE_APPEND);
global $user, $db;

if (!defined('DOL_DOCUMENT_ROOT')) {
    echo json_encode(array('success' => false, 'message' => 'DOL_DOCUMENT_ROOT non défini. Vérifiez le chemin de main.inc.php.'));
    exit;
}

try {
    // if (!($user->admin)) {
    //     http_response_code(403);
    //     throw new Exception('Accès refusé : admin requis.');
    // }

    // Correction du chemin d'inclusion ("module" -> "modules")
    // require_once DOL_DOCUMENT_ROOT . '/custom/kontava/core/modules/modKontava.class.php';
    dol_include_once('/kontava/core/modules/modKontava.class.php', 'modKontava');

    if (!class_exists('modKontava')) {
        throw new Exception('Classe modKontava non trouvée');
    }
    $module = new modKontava($db);
    if (!method_exists($module, 'forceModuleUpdate')) {
        throw new Exception('Méthode forceModuleUpdate non trouvée');
    }
    $res = $module->forceModuleUpdate();
    if ($res > 0) {
        echo json_encode(array('success' => true, 'message' => 'Mise à jour du module effectuée avec succès.'));
    } else {
        $msg = (property_exists($module, 'error') && !empty($module->error)) ? $module->error : 'Erreur lors de la mise à jour du module.';
        throw new Exception($msg);
    }
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'message' => $e->getMessage()));
}