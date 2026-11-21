<?php

// Load Dolibarr
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

global $langs, $user, $conf, $db;
// Vérification token CSRF
$token = GETPOST('token', 'alpha');
if (empty($token) || function_exists('newToken') && $token !== newToken()) {    // header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

// Lecture paramètres
$logfile = GETPOST('logfile', 'alpha');
if (!$logfile) {
    echo json_encode(['status' => 'error', 'message' => 'No logfile specified']);
    exit;
}

// Security: forbid directory traversal
$logfile = preg_replace('/[^a-zA-Z0-9_\-\.\/]/', '', $logfile);

// Full path (Dolibarr documents folder)
$fullpath = $logfile; //$logfile contient déjà le chemin complet !

if (!file_exists($fullpath)) {
    echo json_encode(['status' => 'error', 'message' => 'Fichier log introuvable.']);
    exit;
}

// On vérifie que l’email peut théoriquement partir en s'assurant que le SMTP est configuré
if (empty($conf->global->MAIN_MAIL_SMTP_PORT) || empty($conf->global->MAIN_MAIL_SMTP_SERVER) || empty($conf->global->MAIN_MAIL_SMTPS_ID) || empty($conf->global->MAIN_MAIL_SMTPS_PW)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Vous n\'avez pas configuré vos envois d\'emails. Vous devez utiliser une méthode SMTP. Pour ne pas risquer de perdre votre bouton log en raffraichissant la fenêtre, le plus simple est d\'ouvrir une autre fenêtre de votre Kontava, puis allez dans Configuration>Emails, puis cliquez sur Modifier pour y configurer votre méthode d\'envoi SMTP.Ensuite revenez ici pour envoyer le log !'
    ]);
    exit;
}


//On détermine le nom du client 
$nom_client = $conf->global->MAIN_INFO_SOCIETE_NOM ?? 'Client inconnu';

$subject = "[".$nom_client."] Rapport d'erreur de restauration";
$to = "contact@kontava.fr";

$log_content = file_get_contents($fullpath);

// Assemble email
$message = "Bonjour,\n\n";
$message .= "L'utilisateur ".$nom_client." a rencontré une erreur lors d'une restauration DB.\n";
$message .= "Log ci-joint.\n\n";
$message .= "Cordialement,\nKontava\n";

require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';

$mail = new CMailFile(
    $subject,
    $to,
    $conf->global->MAILING_EMAIL_FROM ?: 'noreply@localhost',
    $message,
    array($fullpath),           // Fichiers en pièces jointes
     array('text/plain'),
    array(basename($fullpath)),
    '',     // CC
    '',     // BCC
    0,      // Delivery receipt
    0,      // Not HTML
    '',     // errors_to
    '',     // css
    '',     // trackid
    '',     // moreinheader
    'kontava-restore',
    $conf->global->MAIN_MAIL_EMAIL_FROM ?: 'noreply@localhost'
);

if ($mail->sendfile()) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Problème inconnu lors de l envoi']);
}
exit;
?>