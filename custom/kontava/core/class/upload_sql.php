<?php
// Simple handler pour upload .sql vers documents/kontava/temp
// Inclut main de Dolibarr
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
    // pas connecté -> rediriger
    header('Location: '.$redir.'upload_err=' . urlencode('Utilisateur non authentifié'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: '.$redir);
    exit;
}

if (empty($_FILES['sqlfile']) || $_FILES['sqlfile']['error'] !== UPLOAD_ERR_OK) {
    header('Location: '.$redir.'upload_err=' . urlencode('Aucun fichier reçu ou erreur d\'upload'));
    exit;
}

$originalName = $_FILES['sqlfile']['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($ext !== 'sql') {
    header('Location: '.$redir.'upload_err=' . urlencode('Extension non autorisée'));
    exit;
}

// Déterminer dossier documents (utilise DOL_DATA_ROOT si disponible)
if (defined('DOL_DATA_ROOT')) {
    $dataRoot = rtrim(DOL_DATA_ROOT, '/\\');
} else {
    // main.inc.php est inclus, mais fallback au parent du htdocs
    $dataRoot = realpath(DOL_DOCUMENT_ROOT . '/..') . '/documents';
}

$targetDir = $dataRoot . '/kontava/importDB';

// Créer le dossier si besoin
if (!is_dir($targetDir)) {
    if (!@mkdir($targetDir, 0755, true)) {
        header('Location: '.$redir.'upload_err=' . urlencode('Impossible de créer le dossier cible'));
        exit;
    }
}

$targetFile = $targetDir . '/' . basename($originalName);

// Déplacer le fichier uploadé
if (!move_uploaded_file($_FILES['sqlfile']['tmp_name'], $targetFile)) {
    header('Location: '.$redir.'upload_err=' . urlencode('Impossible de déplacer le fichier'));
    exit;
}

// Succès
header('Location: '.$redir.'upload_ok=1');
exit;