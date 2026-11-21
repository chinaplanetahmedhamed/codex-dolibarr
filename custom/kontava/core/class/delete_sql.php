<?php
// Handler pour suppression de fichier .sql dans documents/kontava/importDB
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


$returnUrl = GETPOST('returnurl', 'alpha');
if (empty($returnUrl)) {
    $returnUrl = '/custom/kontava/kontavaindex.php';
}
// Si l'URL contient déjà un "?"
if (strpos($returnUrl, '?') !== false) {
    $redir = $returnUrl . '&';
} else {
    $redir = $returnUrl . '?';
}

if (empty($user->id)) {
    header('Location: '.$redir.'upload_err=' . urlencode('Utilisateur non authentifié'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: '.$redir);
    exit;
}

// Vérification du token simple (main.inc.php fait déjà un contrôle central si nécessaire)
// On attend 'token' envoyé par le formulaire
if (function_exists('newToken')) {
    if (empty($_POST['token'])) {
        header('Location: '.$redir.'upload_err=' . urlencode('Jeton CSRF manquant'));
        exit;
    }
    // Tentative de vérification basique avec la valeur en session si présente
    if (!empty($_SESSION['newtoken']) && !hash_equals($_SESSION['newtoken'], $_POST['token'])) {
        header('Location: '.$redir.'upload_err=' . urlencode('Jeton CSRF invalide'));
        exit;
    }
}

if (empty($_POST['file'])) {
    header('Location: '.$redir.'upload_err=' . urlencode('Nom de fichier manquant'));
    exit;
}

$basename = basename($_POST['file']); // protège contre la traversée de répertoires

// Déterminer dossier documents
if (defined('DOL_DATA_ROOT')) {
    $dataRoot = rtrim(DOL_DATA_ROOT, '/\\');
} else {
    $dataRoot = realpath(DOL_DOCUMENT_ROOT . '/..') . '/documents';
}
$targetDir = $dataRoot . '/kontava/importDB';
$targetFile = $targetDir . '/' . $basename;

if (!is_file($targetFile)) {
    header('Location: '.$redir.'upload_err=' . urlencode('Fichier introuvable'));
    exit;
}

if (!is_writable($targetFile)) {
    header('Location: '.$redir.'upload_err=' . urlencode('Fichier non supprimable (permissions)'));
    exit;
}

if (!@unlink($targetFile)) {
    header('Location: '.$redir.'upload_err=' . urlencode('Impossible de supprimer le fichier'));
    exit;
}

// Succès
header('Location: '.$redir.'delete_ok=1');
exit;
?>