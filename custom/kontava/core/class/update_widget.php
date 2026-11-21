<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// Trouver la racine du projet et inclure main.inc.php
// $main_inc_path = __DIR__ . '/../../../../main.inc.php';
// if (!file_exists($main_inc_path)) {
//     die("Erreur : main.inc.php non trouvé à " . $main_inc_path . " (__DIR__=" . __DIR__ . ")");
// }
// require_once $main_inc_path;
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

// Inclure le fichier contenant la fonction updateWidgetState()
require_once __DIR__ . '/kontava_function.php';

// 🔹 Vérification du token CSRF
$csrf_token = isset($_POST['token']) ? $_POST['token'] : '';
if ($csrf_token !== $_SESSION['newtoken']) {
    die("Erreur CSRF : Token invalide.");
}

// 🔹 Récupération et sécurisation des données
$box_id = isset($_POST['box_id']) ? (int)$_POST['box_id'] : 0;
$zone = isset($_POST['zone']) ? (int)$_POST['zone'] : 0;
$fk_user = isset($_POST['fk_user']) ? (int)$_POST['fk_user'] : 0;
$unfold = isset($_POST['unfold']) ? (int)$_POST['unfold'] : 1;

if ($box_id && $fk_user) {
    if (updateWidgetState($box_id, $zone, $fk_user, $unfold)) {
        echo "OK";
    } else {
        echo "Erreur lors de la mise à jour";
    }
} else {
    echo "Données invalides";
}
?>

