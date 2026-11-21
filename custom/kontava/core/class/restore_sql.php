<?php
// Simple restore endpoint for Kontava widget
// require_once __DIR__ . '/../../../../main.inc.php'; // adjust path if needed
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php'; //Pour utiliser la fonction setEventMessages()


// On nettoie tout buffer de sortie avant de commencer
while (ob_get_level()) ob_end_flush();

// ---------------------------
// 1️⃣ HEADERS POUR LE STREAMING
// ---------------------------
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // pour Nginx — sinon le flux serait tamponné

// ---------------------------
// 2️⃣ DÉCLARATION DE LA FONCTION sendProgress()
// ---------------------------
function sendProgress($progress, $status, $log = '', $meta = array())
{
    global $globalLog;

    if ($log) $globalLog .= strip_tags($log)."\n"; // On garde une version propre sans HTML

    $payload = array_merge([
        'progress' => $progress, // nombre (0 à 100)
        'status'   => $status,   // texte court affiché au-dessus de la barre
        'log'      => $log       // texte affiché dans la console/log
    ], is_array($meta) ? $meta : array());

    echo json_encode($payload) . "\n";

    // Envoi immédiat au navigateur (sans attendre la fin du script)
    @ob_flush();
    @flush();
}
function saveLog($backupDir)
{
    global $globalLog;
    $timestamp = date('Ymd_His');
    if (!is_dir($backupDir)) dol_mkdir($backupDir);

    $logfile = $backupDir . '/restore-' . $timestamp . '.log';
    file_put_contents($logfile, $globalLog);
    return $logfile;
}

//Je crée une fonction de restauration car utilisée plein de fois, en particulier pour rollback en cas de pb de migration
function dolibarr_restore_database($db, $binary, $args, $filepath, $passDb)
{
    //Je supprime TOUTES les table avant de restaurer car ça foutait la merde sans ça, mais je sais pas pourquoi !
    $resql = $db->query("SHOW TABLES");
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $tablename = array_values((array)$obj)[0];
            $db->query("DROP TABLE IF EXISTS `$tablename`");
        }
    } else {
        return -1;
    }
    // assemble command string (will use shell to perform input redirection)
    $cmd = escapeshellcmd($binary) . ' ' . implode(' ', $args) . ' < ' . escapeshellarg($filepath);
    $execOutput = '';

    // set password in env to avoid -p on command line
    if (!empty($passDb)) {
        putenv('MYSQL_PWD=' . $passDb);
    }
    dol_syslog("Command to restore backup: ".$cmd);
    // $execReturn = 0;
    //On wipe toute les table de la SB existante car sinon la restauration pose problème
    // // execute (capture output and return)
    exec($cmd . ' 2>&1', $out, $ret); //EXECUTION DE LA RESTAURATION !
    $execOutput = implode("\n", $out);
    unset($passDb);
    if (!empty($passDb)) {
        putenv('MYSQL_PWD=');
    }
    return [$ret,$execOutput];
}

global $user, $conf, $db, $langs, $globalLog;

//On initialise le global log vide
$globalLog='';
// Prepare redirect URL and final status to stream to client
$redirect = $_SERVER['HTTP_REFERER'] ?? '/';

/* determine target dir like widget */
if (defined('DOL_DATA_ROOT')) {
    $dataRoot = rtrim(DOL_DATA_ROOT, '/\\');
} else {
    $dataRoot = realpath(DOL_DOCUMENT_ROOT . '/..') . '/documents';
}
$targetDir = $dataRoot . '/kontava/importDB';
$backupDir = $targetDir . '/backup';

if (empty($user) || ! $user->admin) {
    // unauthorized
    sendProgress(
        0,
        '',
        $langs->transnoentitiesnoconv('restoreNonAdmin'),
        [ 'status_type' => 'error', 'final' => false ]);
    $logfile=saveLog($backupDir);//On sauve le log dans un fichier
    sendProgress(0,'','',[ 'status_type' => 'error', 'final' => true ]);
    exit;
}
    
    // basic token check (uses Dolibarr token mechanism)
$token = GETPOST('token', 'alpha');
if (empty($token) || function_exists('newToken') && $token !== newToken()) {
    // Best-effort check; adapt to your Dolibarr token validation if different
    // Send an immediate error via the streaming API and stop.

    sendProgress(
        0,
        $langs->transnoentitiesnoconv('BadCSRFTokenStatus'),
        $langs->transnoentitiesnoconv('BadCSRFTokenText'),
        [ 'status_type' => 'error', 'final' => false ]);
        $logfile=saveLog($backupDir);//On sauve le log dans un fichier
        sendProgress(0,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
        exit;
    }
       
$fname = basename(GETPOST('file', 'alpha')); // keep only name
if (empty($fname)) {
    sendProgress(
        0,
        $langs->transnoentitiesnoconv('NoFileStatus'),
        $langs->transnoentitiesnoconv('NoFileText'),
        [ 'status_type' => 'error', 'final' => false ]);
        $logfile=saveLog($backupDir);//On sauve le log dans un fichier
        sendProgress(0,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
        exit;
    }
$filepath = $targetDir . '/' . $fname; //On détermine le chemin complet vers le fichier dump uploadé
            
$needDolibarrMigration = GETPOST('need_migration', 'int'); // 0 or 1
if (!in_array($needDolibarrMigration, [0,1])) {
    $needDolibarrMigration = 0;
}

$isKontavaInBackup = GETPOST('is_kontava_in_backup', 'int'); // 0 or 1
if (!in_array($isKontavaInBackup, [0,1])) {
    $isKontavaInBackup = 0;
}

$dolibarrVersionFrom = GETPOST('dolibarr_version_from', 'alpha');
if( empty($dolibarrVersionFrom)) {
    $needDolibarrMigration = 0;
}
$dolibarrVersionTo = GETPOST('dolibarr_version_to', 'alpha');
if( empty($dolibarrVersionTo)) {
    $needDolibarrMigration = 0;
}


// safety checks
if (!is_file($filepath) || strpos(realpath($filepath), realpath($targetDir)) !== 0) {
    sendProgress(
        0,
        $langs->transnoentitiesnoconv('NoFileStatus'),
        $langs->transnoentitiesnoconv('NoFileText'),
        [ 'status_type' => 'error', 'final' => false ]);
    $logfile=saveLog($backupDir);//On sauve le log dans un fichier
    sendProgress(0,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
    exit;
}


// require_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/class/export_sql.php';
dol_include_once('/kontava/core/class/export_sql.php');//PYNC Dolistore rules correction

// -------------------------------------------
// Étape 0 : Je nettoie le répertoire en ne gardant que le dernier dump et le dernier json (s'ils existent) et le dernier log
// -------------------------------------------
// --- Nettoyage JSON ---
sleep(1);
sendProgress(5,$langs->transnoentitiesnoconv('RestoreInitStatus'),'----------------------------------------');//Saut de ligne dans le log

sendProgress(
        5,
        $langs->transnoentitiesnoconv('RestoreInitStatus'),
        $langs->transnoentitiesnoconv('backupFolderCleanupText'));
$jsonFiles = glob($backupDir . '/kontava_preserve_*.json');
if ($jsonFiles !== false && count($jsonFiles) > 1) {
    // Trier par date de modification décroissante
    usort($jsonFiles, function($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    // Le premier (plus récent) à garder
    $latestJson = array_shift($jsonFiles);

    // Supprimer tous les autres
    foreach ($jsonFiles as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

// --- Nettoyage SQL ---
$sqlFiles = glob($backupDir . '/kontava_*.sql');
if ($sqlFiles !== false && count($sqlFiles) > 1) {
    usort($sqlFiles, function($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $latestSql = array_shift($sqlFiles);

    foreach ($sqlFiles as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

// --- Nettoyage Logs ---
$logFiles = glob($backupDir . '/restore-*.log');
if ($logFiles !== false && count($logFiles) > 1) {
    usort($logFiles, function($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    $latestSql = array_shift($logFiles);

    foreach ($logFiles as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

// -------------------------------------------
// Étape 1 : Sauvegarde avant restauration
// -------------------------------------------
sleep(1);
sendProgress(10,$langs->transnoentitiesnoconv('backupCurrentDBStatus'),'----------------------------------------');//Saut de ligne dans le log
sendProgress(
        10,
        $langs->transnoentitiesnoconv('backupCurrentDBStatus'),
        $langs->transnoentitiesnoconv('backupCurrentDBText'));
// $backupPath = 'PENSER A REACTIVER LA SAUVEGARDE AVANT RESTAURATION';
$backupPath = backupDatabase('server');
dol_syslog("Backup avant restauration effectué : $backupPath");
sendProgress(
        15,
        $langs->transnoentitiesnoconv('backupCurrentDBStatus'),
        $langs->transnoentitiesnoconv('backupCurrentDBOverText'));//.' '.$backupPath);
// -------------------------------------------
// Étape 2 : Récupération des données Kontava avant restauration
// -------------------------------------------

$PYNCtest = 1;
$kontavaPreserveData = [];

if (!$isKontavaInBackup && $PYNCtest == 1) {
    sleep(1);
    sendProgress(20,$langs->transnoentitiesnoconv('backupKeyDataStatus'),'----------------------------------------');//Saut de ligne dans le log
    sendProgress(
        20,
        $langs->transnoentitiesnoconv('backupKeyDataStatus'),
        $langs->transnoentitiesnoconv('backupKeyDataText'));
    dol_syslog("Kontava absent du backup — récupération des données locales");

    // --- Constantes essentielles ---
    $constNames = [
        'MAIN_PDF_PROPAL_USE_KONTAVA_SIGNING',
        'TAKEPOS_ENABLE_SUMUP',
        'MAIN_APPLICATION_TITLE',
        'MAIN_HOME',
        'MAIN_THEME',
        'MAIN_MONNAIE',
        'FACTURE_ADDON_PDF',
        'PROPALE_ADDON_PDF',
        'MAIN_SECURITY_HASH_ALGO',
        'CRON_KEY',
        'MAIN_LANDING_PAGE',
        'MAIN_INFO_SOCIETE_NOM',
        'MAIN_INFO_SOCIETE_COUNTRY',
        'MAIN_MAIL_EMAIL_FROM',
        'MAILING_EMAIL_FROM',
        'MAIN_VERSION_LAST_UPGRADE',
        'MAIN_EXTERNAL_SMTP_CLIENT_IP_ADDRESS',
        'MAILING_NO_USING_PHPMAIL',
        'MAIN_SECURITY_HASH_ALGO',
        'MAIN_INFO_SOCIETE_ADDRESS',
        'MAIN_INFO_SOCIETE_ZIP',
        'MAIN_INFO_SOCIETE_TOWN'
    ];

    // Si la liste est vide, on skip
    if (!empty($constNames)) {
        // Échapper et entourer de quotes chaque nom
        $quoted = array_map(function($n) use ($db) {
            return "'" . $db->escape($n) . "'";
        }, $constNames);

        $inList = implode(',', $quoted);

        $sql = "SELECT name, value, note, visible, type, entity 
                FROM ".MAIN_DB_PREFIX."const 
                WHERE name IN ($inList)";

        $resql = $db->query($sql);

        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $kontavaPreserveData['const'][$obj->name] = [
                    'value'   => $obj->value,
                    'note'    => $obj->note,
                    'visible' => $obj->visible,
                    'type'    => $obj->type,
                    'entity'  => $obj->entity
                ];
            }
            sendProgress(
                22,
                $langs->transnoentitiesnoconv('backupKeyDataStatus'),
                $langs->transnoentitiesnoconv('backupKeyConstantText'));

        } else {
            sendProgress(
                22,
                $langs->transnoentitiesnoconv('backupKeyDataStatus'),
                $langs->transnoentitiesnoconv('backupKeyConstantError').$db->lasterror(),
                [ 'status_type' => 'error', 'final' => false ]);
            $logfile=saveLog($backupDir);//On sauve le log dans un fichier
            sendProgress(22,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
            exit;
        }
    }
    // --- Données admin ---
    $sql = "SELECT login, pass_crypted, email FROM ".MAIN_DB_PREFIX."user WHERE rowid = 1";
    $resql = $db->query($sql);
    if ($resql && $obj = $db->fetch_object($resql)) {
        $kontavaPreserveData['admin_user'] = [
            'login' => $obj->login,
            'pass_crypted' => $obj->pass_crypted,
            'email' => $obj->email
        ];
        sendProgress(
            24,
            $langs->transnoentitiesnoconv('backupKeyDataStatus'),
            $langs->transnoentitiesnoconv('backupKeyAdminText'));
    } else {
        sendProgress(
                24,
                $langs->transnoentitiesnoconv('backupKeyDataStatus'),
                $langs->transnoentitiesnoconv('backupKeyAdminError').$db->lasterror(),
                [ 'status_type' => 'error', 'final' => false ]);
            $logfile=saveLog($backupDir);//On sauve le log dans un fichier
            sendProgress(24,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
            exit;
    }
    

    // ---  Table llx_kontava ---
    // $tableName = MAIN_DB_PREFIX . 'kontava';
    $tableName = MAIN_DB_PREFIX . 'konst';
    $hasKontavaTable = false;
    $resql = $db->query("SHOW TABLES LIKE '".$db->escape($tableName)."'");
    $hasKontavaTable = ($resql && $db->num_rows($resql) > 0);

    if ($hasKontavaTable) {
        // $sql = "SELECT * FROM ".$tableName;
        $sql = "SELECT rowid, nom, valeur, type, note FROM ".$tableName;
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                // $kontavaPreserveData['kontava'][] = (array)$obj;
                $kontavaPreserveData['konst'][] = [
                    'nom'    => $obj->nom,
                    'valeur' => $obj->valeur,
                    'type'   => $obj->type,
                    'note'   => $obj->note,
                ];
            }
        }
        sendProgress(
            26,
            $langs->transnoentitiesnoconv('backupKeyDataStatus'),
            $langs->transnoentitiesnoconv('backupKeyTableText'));
    } else {
        sendProgress(
            26,
            $langs->transnoentitiesnoconv('backupKeyDataStatus'),
            $langs->transnoentitiesnoconv('backupKeyTableError').$db->lasterror(),
            [ 'status_type' => 'error', 'final' => false ]);
            $logfile=saveLog($backupDir);//On sauve le log dans un fichier
            sendProgress(26,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
            exit;
    }
    

    // ---  Widgets Kontava (user admin uniquement) ---
    $sql = "SELECT d.file, b.box_order
            FROM ".MAIN_DB_PREFIX."boxes AS b
            JOIN ".MAIN_DB_PREFIX."boxes_def AS d ON b.box_id = d.rowid
            WHERE b.fk_user = 1 AND b.position = 99 AND d.file LIKE '%@kontava'";
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $kontavaPreserveData['widgets'][] = [
                'file' => $obj->file,
                'box_order' => $obj->box_order
            ];
        }
        sendProgress(
            28,
            $langs->transnoentitiesnoconv('backupKeyDataStatus'),
            $langs->transnoentitiesnoconv('backupKeyWidgetText'));
    } else {
        sendProgress(
            28,
            $langs->transnoentitiesnoconv('backupKeyDataStatus'),
            $langs->transnoentitiesnoconv('backupKeyWidgetError').$db->lasterror(),
            [ 'status_type' => 'error', 'final' => false ]);
            $logfile=saveLog($backupDir);//On sauve le log dans un fichier
            sendProgress(28,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
            exit;
    }
    

    // --- Sauvegarde temporaire dans un fichier JSON ---
    $timestamp = date('Ymd_His');   
    if (!is_dir($backupDir)) dol_mkdir($backupDir);

    $tempFile = $backupDir . "/kontava_preserve_{$timestamp}.json";
    file_put_contents($tempFile, json_encode($kontavaPreserveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    dol_syslog("Kontava data sauvegardées dans $tempFile");
    sendProgress(
        30,
        $langs->transnoentitiesnoconv('backupKeyDataStatus'),
        $langs->transnoentitiesnoconv('backupKeyDataJSONText').' '.$tempFile);
}
//POUR TESTER L'ENVOI DE LOG
// $logfile=saveLog($backupDir);//On sauve le log dans un fichier
// sendProgress(30,'TEST LOG','TEST ENVOI DE LOG : '.$logfile,[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
// exit;
// FIN DU TEST DE LOG


// -------------------------------------------
// Étape 3 : Exécuter la restauration de la DB
// -------------------------------------------
$PYNCtest = 1;// 1 actif, 0 = étape inactive pour tester autre chose
if($PYNCtest == 1) {
    sleep(1);
    sendProgress(40,$langs->transnoentitiesnoconv('RestoreInProgressStatus'),'----------------------------------------');//Saut de ligne dans le log
    sendProgress(
        40,
        $langs->transnoentitiesnoconv('RestoreInProgressStatus'),
        $langs->transnoentitiesnoconv('RestoreInProgressText'));
    sendProgress(
        40,
        $langs->transnoentitiesnoconv('RestoreInProgressStatus'),
        $langs->transnoentitiesnoconv('restoreInitialDumpText2'));
    $type = $db->type ?? ($conf->dbtype ?? '');

    $execOutput = '';
    $execReturn = 1;

    if (in_array($type, ['mysql','mysqli'])) {
        $binary = $db->getPathOfRestore() ?: 'mysql';
        // Build args without exposing password on command line: set env MYSQL_PWD
        $host = $conf->global->MAIN_DB_HOST ?? $dolibarr_main_db_host ?? '';
        $port = $conf->global->MAIN_DB_PORT ?? $dolibarr_main_db_port ?? '';
        $userDb = $conf->global->MAIN_DB_USER ?? $dolibarr_main_db_user ?? '';
        $passDb = $conf->global->MAIN_DB_PASS ?? $dolibarr_main_db_pass ?? '';
        $dbname = $conf->global->MAIN_DB_NAME ?? $dolibarr_main_db_name ?? '';

        $args = [];
        if ($dbname) $args[] = escapeshellarg($dbname);
        if ($host) { $args[] = '-h'; $args[] = escapeshellarg($host); }
        if ($port) { $args[] = '-P'; $args[] = escapeshellarg($port); }
        if ($userDb) { $args[] = '-u'; $args[] = escapeshellarg($userDb); }

        // // assemble command string (will use shell to perform input redirection)
        // $cmd = escapeshellcmd($binary) . ' ' . implode(' ', $args) . ' < ' . escapeshellarg($filepath);

        // // set password in env to avoid -p on command line
        // if (!empty($passDb)) {
        //     putenv('MYSQL_PWD=' . $passDb);
        // }
        // dol_syslog("Command to restore backup: ".$cmd);
        // $execReturn = 0;
        // //On wipe toute les table de la SB existante car sinon la restauration pose problème
        // dolibarr_restore_reset_database($db);
        // // // execute (capture output and return)
        // exec($cmd . ' 2>&1', $out, $ret); //EXECUTION DE LA RESTAURATION !
        // $execOutput = implode("\n", $out);
        // $execReturn = $ret;

        //Je lance la restauration
        list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $filepath, $passDb);
    }
    /*elseif (in_array($type, ['pgsql','postgresql'])) {
        $binary = $db->getPathOfRestore() ?: 'psql';
        $host = $conf->global->MAIN_DB_HOST ?? $dolibarr_main_db_host ?? '';
        $port = $conf->global->MAIN_DB_PORT ?? $dolibarr_main_db_port ?? '';
        $userDb = $conf->global->MAIN_DB_USER ?? $dolibarr_main_db_user ?? '';
        $passDb = $conf->global->MAIN_DB_PASS ?? $dolibarr_main_db_pass ?? '';
        $dbname = $conf->global->MAIN_DB_NAME ?? $dolibarr_main_db_name ?? '';

        $args = [];
        if ($host) { $args[] = '-h'; $args[] = escapeshellarg($host); }
        if ($port) { $args[] = '-p'; $args[] = escapeshellarg($port); }
        if ($userDb) { $args[] = '-U'; $args[] = escapeshellarg($userDb); }
        if ($dbname) { $args[] = '-d'; $args[] = escapeshellarg($dbname); }

        $cmd = escapeshellcmd($binary) . ' ' . implode(' ', $args) . ' -f ' . escapeshellarg($filepath);

        if (!empty($passDb)) {
            putenv('PGPASSWORD=' . $passDb);
        }
        exec($cmd . ' 2>&1', $out, $ret);
        $execOutput = implode("\n", $out);
        $execReturn = $ret;
        if (!empty($passDb)) {
            putenv('PGPASSWORD=');
        }
    }*/
    
    else {
        // Unsupported DB type: stream an error and stop
        sendProgress(
            40,
            $langs->transnoentitiesnoconv('RestoreInProgressStatus'),
            $langs->transnoentitiesnoconv('RestoreInProgressDBError'),
            [ 'status_type' => 'error', 'final' => false ]);
        $logfile=saveLog($backupDir);//On sauve le log dans un fichier
        sendProgress(40,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
        exit;
    }
    //POUR TESTER L'ECHEC DE LA RESTAURATION !!
    // $execReturn = 1;
    //FIN DU TEST
    if ($execReturn === 0) {
        // send a final progress message with redirect and success flag
        sendProgress(
            60,
            $langs->transnoentitiesnoconv('RestoreInProgressStatus'),
            $langs->transnoentitiesnoconv('RestoreInProgressSuccess'));
        
        // Reconnexion à la base après restauration
        try {
            $db->close();
            $host = $conf->global->MAIN_DB_HOST ?? $dolibarr_main_db_host ?? '';
            $port = $conf->global->MAIN_DB_PORT ?? $dolibarr_main_db_port ?? '';
            $user_db = $conf->global->MAIN_DB_USER ?? $dolibarr_main_db_user ?? '';
            $pass = $conf->global->MAIN_DB_PASS ?? $dolibarr_main_db_pass ?? '';
            $name = $conf->global->MAIN_DB_NAME ?? $dolibarr_main_db_name ?? '';
            $db = getDoliDBInstance($conf->db->type, $host, $user_db, $pass, $name, $port);
            if (!$db->connected) {
                sendProgress(
                    60,
                    $langs->transnoentitiesnoconv('RestoreInProgressStatus'),
                    'Erreur de reconnexion à la base de données après restauration',
                    [ 'status_type' => 'error', 'final' => false ]);
                $logfile=saveLog($backupDir);//On sauve le log dans un fichier
                sendProgress(60,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
                exit;
            }
            sendProgress(
                65,
                $langs->transnoentitiesnoconv('RestoreInProgressStatus'),
                'Reconnexion à la base de données réussie');
        } catch (Exception $e) {
            sendProgress(
                60,
                $langs->transnoentitiesnoconv('RestoreInProgressStatus'),
                'Erreur de reconnexion : ' . $e->getMessage(),
                [ 'status_type' => 'error', 'final' => false ]);
            $logfile=saveLog($backupDir);//On sauve le log dans un fichier
            sendProgress(60,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
            exit;
        }
    } else {
        $text = $langs->transnoentitiesnoconv('RestoreInProgressError') . ' (code=' . intval($execReturn) . ')';
        // optionally append first 2000 chars of output for debugging
        $text .= ' - ' . substr($execOutput, 0, 2000); //Si on veut limiter le log envoyé, était 400
        // Send final error via the stream but DO NOT include redirect
        sendProgress(
            40,
            $langs->transnoentitiesnoconv('RestoreInProgressStatus'),
            $text,
            [ 'status_type' => 'error', 'final' => false ]);
        sendProgress(40,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
        sendProgress(40,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
        list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
        if ($execReturn === 0) {
            // send a final progress message with redirect and success flag
            sendProgress(40,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
        } else {
            sendProgress(40,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                [ 'status_type' => 'error', 'final' => false ]);
        }
        $logfile=saveLog($backupDir);//On sauve le log dans un fichier
        sendProgress(40,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
        exit;
    }
}


// -------------------------------------------
// Étape 4 : Si $needDolibarrMigration On lance la migration de Dolibarr
// -------------------------------------------
$PYNCtest = 1;
sleep(1);
sendProgress(70,$langs->transnoentitiesnoconv('RestoreMigrationStatus'),'----------------------------------------');//Saut de ligne dans le log
sendProgress(
    70,
    $langs->transnoentitiesnoconv('RestoreMigrationStatus'),
    $langs->transnoentitiesnoconv('RestoreMigrationText'));
if ($needDolibarrMigration == 0 || $PYNCtest == 0) {
    sendProgress(
        80,
        $langs->transnoentitiesnoconv('RestoreMigrationStatus'),
        $langs->transnoentitiesnoconv('NoMigrationText'));
} else {
    sendProgress(
        70,
        $langs->transnoentitiesnoconv('RestoreMigrationStatus'),
        $langs->transnoentitiesnoconv('YesMigrationText'));
        
    // Création du fichier pour forcer la migration
    $unlockFile = $dataRoot . '/upgrade.unlock';
    if (!file_exists($unlockFile)) {
        if (file_put_contents($unlockFile, "Force Dolibarr upgrade at " . date('Y-m-d H:i:s')) !== false) {
            sendProgress(70,
                $langs->transnoentitiesnoconv('RestoreMigrationStatus'),
                'Fichier upgrade.unlock créé pour autoriser la migration');
        } else {
            sendProgress(70,
                $langs->transnoentitiesnoconv('RestoreMigrationStatus'),
                '⚠️ Impossible de créer le fichier upgrade.unlock', ['status_type' => 'error']);
            sendProgress(70,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
            sendProgress(70,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
            list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
            if ($execReturn === 0) {
                // send a final progress message with redirect and success flag
                sendProgress(70,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
            } else {
                sendProgress(70,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                    [ 'status_type' => 'error', 'final' => false ]);
            }
            $logfile=saveLog($backupDir);//On sauve le log dans un fichier
            sendProgress(70,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
            exit;
        }
    }

    //On prépare la migration
    $php_path = trim(shell_exec('which php')) ?: 'php';
    $install_dir = DOL_DOCUMENT_ROOT . '/install';
    $upgrade_script = 'upgrade.php';
    $client_conf = DOL_DOCUMENT_ROOT . '/conf/conf.php'; // Pour que le chemin vers la conf user ne soit pas perdu en CLI
    $dolibarrVersionFrom = preg_replace('/(\.[0-9]+)$/i', '.0', $dolibarrVersionFrom);//Pour les migrations on doit toujours avoir les version mineure à 0
    $dolibarrVersionTo   = preg_replace('/(\.[0-9]+)$/i', '.0', $dolibarrVersionTo);
    
    //On va lister tous les sauts de versions à migrer car si on en lance plusieurs à la fois, des scripts de migration intermédiaires sont oubliés
    $migrationDir = $install_dir . '/mysql/migration/';
    $files = glob($migrationDir . '*.sql');
    sort($files, SORT_NATURAL);

    $migrations = [];
    foreach ($files as $f) {
        if (preg_match('/([0-9]+\.[0-9]+\.[0-9]+)-([0-9]+\.[0-9]+\.[0-9]+)\.sql$/', basename($f), $m)) {// donc on va lister tous les fichiers *sql qui s'écrivent x.y.z-u.v.w.sql
            $migrations[$m[1]] = $m[2]; //On les range dans le tableau [x.y.z => u.v.w]
        }
    }
    $current = $dolibarrVersionFrom; //On commencera la migration de la version courante du dump
    $nbMigrations = 0; //On y calculera le nombre de migrations à effectuer
    while (isset($migrations[$current]) && version_compare($current, $dolibarrVersionTo, '<')) {
        $nbMigrations++;
        $current = $migrations[$current];
    }

    $nbScripts = ($nbMigrations) * 2 + 1;; // 2 scripts (upgrade.php, upgrade2.php) par saut + les script step5.php à la toute fin
    $cptScript = 0; // Compteur global d’avancement
    
    // On lance la migration
    $current = $dolibarrVersionFrom; //On réinitialise notre point de départ de la migration de la version courante du dump

    while (isset($migrations[$current]) && version_compare($current, $dolibarrVersionTo, '<')) {//On parcours toutes les versions From des fichiers SQL et on fait évoluer le current à la version To
        $next = $migrations[$current]; //On définie la version To en cours
        // Liste des scripts : sans step5 sauf pour la dernière migration
        $scripts = ['upgrade.php', 'upgrade2.php'];
        if ($next === $dolibarrVersionTo) {
            $scripts[] = 'step5.php';
        }
        foreach ($scripts as $script) {//Pour chaque migration il y a ces 3 scripts à passer
            $cmd = sprintf(
                'cd %s && DOL_CONF_FILE=%s %s %s %s %s',
                escapeshellarg($install_dir),//cd htdocs/install c'est la méthode décrite dans le wiki et sinon il ne trouve pas ses scripts car fait en chemin relatif
                escapeshellarg($client_conf),//pour passer en variable d'environnement le chemin du conf.php complet. Fonctionne avec une modif de /install/inc.php pour lui faire trouver le $conffile
                escapeshellcmd($php_path),
                escapeshellarg($script),
                escapeshellarg($current),
                escapeshellarg($next)
            );
            $progress = round(70 + ($cptScript * 10 / $nbScripts), 2);
            sendProgress(
                $progress,
                $langs->transnoentitiesnoconv('RestoreMigrationStatus').' script '.$script.' ('.$current.' → '.$next.')',
                "→ Migration via $script ($current → $next)");
            $handle = popen($cmd . ' 2>&1', 'r');
            while (!feof($handle)) {
                $buffer = fgets($handle);
                if ($buffer === false) break;
                
                // Nettoie un peu le log pour éviter les trop gros blocs HTML
                $line = preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $buffer); // retire les couleurs CLI
                $line = trim(strip_tags($buffer)); //Retire le HTML
                if ($line !== '') { //On envoit les logs au fur et à mesure
                    sendProgress(
                        $progress,
                        $langs->transnoentitiesnoconv('RestoreMigrationStatus').' script '.$script.' ('.$current.' → '.$next.')',
                        htmlspecialchars($line));
                    }
            }
            $returnCode = pclose($handle);
            if ($returnCode !== 0) {
                sendProgress(
                    $progress,
                    $langs->transnoentitiesnoconv('RestoreMigrationStatus').' script '.$script.' ('.$current.' → '.$next.')',
                    $langs->transnoentitiesnoconv('RestoreMigrationError') . " (code=$returnCode)",
                    ['status_type'=>'error','final'=>true]);
                sendProgress($progress,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
                sendProgress($progress,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
                list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
                if ($execReturn === 0) {
                    // send a final progress message with redirect and success flag
                    sendProgress($progress,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
                } else {
                    sendProgress($progress,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                        [ 'status_type' => 'error', 'final' => false ]);
                }
                $logfile=saveLog($backupDir);//On sauve le log dans un fichier
                sendProgress($progress,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
                exit;
            }
            $cptScript++; //On incrémente pour mettre à jour un avancement linéaire par script
        }
        $current = $next;
    }

    sendProgress(
                80,
                $langs->transnoentitiesnoconv('RestoreMigrationStatus'),
                $langs->transnoentitiesnoconv('RestoreMigrationSuccess'));
}
// -------------------------------------------
// Étape 5 : Si !$isKontavaInBackup on active le module Kontava
// -------------------------------------------
if ($isKontavaInBackup == 0) {
    sendProgress(
        80,
        $langs->transnoentitiesnoconv('RestoreInitStatus'),
        $langs->transnoentitiesnoconv('NoKontavaInBackupText'));
} else {
    sendProgress(
        80,
        $langs->transnoentitiesnoconv('RestoreInitStatus'),
        $langs->transnoentitiesnoconv('YesKontavaInBackupText'));
}
if ($isKontavaInBackup == 0) {
    
    sleep(1);
    sendProgress(80,$langs->transnoentitiesnoconv('ActivationKontavaModuleStatus'),'----------------------------------------');//Saut de ligne dans le log
    sendProgress(
        80,
        $langs->transnoentitiesnoconv('ActivationKontavaModuleStatus'),
        $langs->transnoentitiesnoconv('ActivationKontavaModuleText'));

    // require_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/modules/modKontava.class.php';
    dol_include_once('/kontava/core/modules/modKontava.class.php', 'modKontava');

    if (!class_exists('modKontava')) {
        sendProgress(
            80,
            $langs->transnoentitiesnoconv('ActivationKontavaModuleStatus'),
            $langs->transnoentitiesnoconv('ActivationKontavaModuleError').' Classe modKontava non trouvée',
            [ 'status_type' => 'error', 'final' => false ]);
        sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
        sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
        list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
        if ($execReturn === 0) {
            // send a final progress message with redirect and success flag
            sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
        } else {
            sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                [ 'status_type' => 'error', 'final' => false ]);
        }
        $logfile=saveLog($backupDir);//On sauve le log dans un fichier
        sendProgress(80,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
        exit;
    } else {
        $module = new modKontava($db);
        if (!method_exists($module, 'forceModuleUpdate')) {
            sendProgress(
                80,
                $langs->transnoentitiesnoconv('ActivationKontavaModuleStatus'),
                $langs->transnoentitiesnoconv('ActivationKontavaModuleError').' Méthode forceModuleUpdate non trouvée',
                [ 'status_type' => 'error', 'final' => false ]);
            sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
            sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
            list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
            if ($execReturn === 0) {
                // send a final progress message with redirect and success flag
                sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
            } else {
                sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                    [ 'status_type' => 'error', 'final' => false ]);
            }
            $logfile=saveLog($backupDir);//On sauve le log dans un fichier
            sendProgress(80,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
            exit;
        } else {
            $res = $module->init(); //Marche pas, fait arrêter le script on dirait
            if ($res > 0) {
                sendProgress(
                    85,
                    $langs->transnoentitiesnoconv('ActivationKontavaModuleStatus'),
                    $langs->transnoentitiesnoconv('ActivationKontavaModuleSuccess'));
                // dolsyslog("Module Kontava activé avec succès", LOG_INFO);
            } else {
                $msg = (property_exists($module, 'error') && !empty($module->error)) ? $module->error : 'Erreur lors de la mise à jour du module.';
                // dolsyslog($msg, LOG_ERR);
                sendProgress(
                    80,
                    $langs->transnoentitiesnoconv('ActivationKontavaModuleStatus'),
                    $langs->transnoentitiesnoconv('ActivationKontavaModuleError').$module->error,
                    [ 'status_type' => 'error', 'final' => false ]);
                sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
                sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
                list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
                if ($execReturn === 0) {
                    // send a final progress message with redirect and success flag
                    sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
                } else {
                    sendProgress(80,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                        [ 'status_type' => 'error', 'final' => false ]);
                }
                $logfile=saveLog($backupDir);//On sauve le log dans un fichier
                sendProgress(80,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
                exit;
            }
        }
    }
}

// -------------------------------------------
// Étape 6 : Si !$isKontavaInBackup Réinjection des données Kontava après restauration
// -------------------------------------------
$PYNCtest = 1;
if (!$isKontavaInBackup && $PYNCtest == 1) {
    dol_syslog("Réinjection des données Kontava post-restauration");
    sleep(1);
    sendProgress(90,$langs->transnoentitiesnoconv('restoreKeyDataStatus'),'----------------------------------------');//Saut de ligne dans le log
    sendProgress(90, 'Réinjection de vos données essentielles', '** On réinjecte dans votre base de données restaurée vos données essentielles. **');
    sendProgress(
            90,
            $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
            $langs->transnoentitiesnoconv('restoreKeyDataText'));

    $latestFile = null;

    // On cherche le dernier fichier de sauvegarde JSON créé
    if (is_dir($backupDir)) {
        $file_json = glob($backupDir . '/kontava_preserve_*.json');
        if (!empty($file_json)) {
            usort($file_json, function ($a, $b) {
                return filemtime($b) <=> filemtime($a);
            });
            $latestFile = $file_json[0];
        }
    }

    if ($latestFile && file_exists($latestFile)) {
        $jsonContent = file_get_contents($latestFile);
        $kontavaPreserveData = json_decode($jsonContent, true);

        if (is_array($kontavaPreserveData)) {
            // --- Réinjection des constantes ---
            if (!empty($kontavaPreserveData['const'])) {
                foreach ($kontavaPreserveData['const'] as $name => $data) {
                    $value   = $db->escape($data['value'] ?? '');
                    $note    = $db->escape($data['note'] ?? '');
                    $visible = (int)($data['visible'] ?? 1);
                    $type    = $db->escape($data['type'] ?? 'string');
                    $entity  = (int)($data['entity'] ?? 1);

                    // On fait un UPSERT pour que la constante soit créée ou mise à jour
                    $sql = "INSERT INTO ".MAIN_DB_PREFIX."const (name, value, note, visible, type, entity)
                            VALUES ('".$db->escape($name)."', '$value', '$note', $visible, '$type', $entity)
                            ON DUPLICATE KEY UPDATE 
                                value = VALUES(value),
                                note = VALUES(note),
                                visible = VALUES(visible),
                                type = VALUES(type),
                                entity = VALUES(entity)";
                    $res = $db->query($sql);
                    if (!$res) {
                        sendProgress(
                            90,
                            $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                            $langs->transnoentitiesnoconv('restoreKeyConstantError').$name.' : '.$db->lasterror());
                        sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
                        sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
                        list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
                        if ($execReturn === 0) {
                            // send a final progress message with redirect and success flag
                            sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
                        } else {
                            sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                                [ 'status_type' => 'error', 'final' => false ]);
                        }
                        $logfile=saveLog($backupDir);//On sauve le log dans un fichier
                        sendProgress(90,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
                        exit;
                    } else {
                        sendProgress(
                            92,
                            $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                            $langs->transnoentitiesnoconv('restoreKeyConstantText').' '.$name);
                    }
                }
            }

            // --- Réinjection user admin ---
            if (!empty($kontavaPreserveData['admin_user'])) {
                $admin = $kontavaPreserveData['admin_user'];
                $sql = "UPDATE ".MAIN_DB_PREFIX."user
                        SET pass_crypted = '".$db->escape($admin['pass_crypted'])."',
                            email = '".$db->escape($admin['email'])."'
                        WHERE rowid = 1";
                $res = $db->query($sql);
                if (!$res) {
                    sendProgress(
                        92,
                        $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                        $langs->transnoentitiesnoconv('restoreKeyAdminError').$db->lasterror());
                    sendProgress(92,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
                    sendProgress(92,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
                    list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
                    if ($execReturn === 0) {
                        // send a final progress message with redirect and success flag
                        sendProgress(92,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
                    } else {
                        sendProgress(92,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                            [ 'status_type' => 'error', 'final' => false ]);
                    }
                    $logfile=saveLog($backupDir);//On sauve le log dans un fichier
                    sendProgress(92,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
                    exit;
                } else {
                    sendProgress(
                        94,
                        $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                        $langs->transnoentitiesnoconv('restoreKeyAdminText'));
                }
            }

            // --- Réinjection de la table llx_kontava ---
            // if (!empty($kontavaPreserveData['kontava'])) {
            //     foreach ($kontavaPreserveData['kontava'] as $row) {
            if (!empty($kontavaPreserveData['konst'])) {
                foreach ($kontavaPreserveData['konst'] as $row) {
                    $cols = array_keys($row);
                    $vals = array_map(function ($v) use ($db) {
                        return isset($v) ? "'".$db->escape($v)."'" : "NULL";
                    }, array_values($row));

                    // $sql = "INSERT INTO ".MAIN_DB_PREFIX."kontava (".implode(',', $cols).")
                    $sql = "INSERT INTO ".MAIN_DB_PREFIX."konst (".implode(',', $cols).")
                            VALUES (".implode(',', $vals).")
                            ON DUPLICATE KEY UPDATE ";

                    $updateParts = [];
                    foreach ($cols as $c) {
                        $updateParts[] = "$c = VALUES($c)";
                    }
                    $sql .= implode(',', $updateParts);

                    $res = $db->query($sql);
                    if (!$res){
                        sendProgress(
                            94,
                            $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                            $langs->transnoentitiesnoconv('restoreKeyTableError').$db->lasterror());
                        sendProgress(94,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
                        sendProgress(94,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
                        list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
                        if ($execReturn === 0) {
                            // send a final progress message with redirect and success flag
                            sendProgress(94,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
                        } else {
                            sendProgress(94,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                                [ 'status_type' => 'error', 'final' => false ]);
                        }
                        $logfile=saveLog($backupDir);//On sauve le log dans un fichier
                        sendProgress(94,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
                        exit;
                    } else {
                        sendProgress(
                            96,
                            $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                            $langs->transnoentitiesnoconv('restoreKeyTableText'));
                    }
                }
            }

            // --- Réinjection des widgets Kontava ---
            if (!empty($kontavaPreserveData['widgets'])) {
                foreach ($kontavaPreserveData['widgets'] as $widget) {
                    $file_widget = $db->escape($widget['file']);
                    $order = $db->escape($widget['box_order']);

                    $sql = "
                        INSERT INTO ".MAIN_DB_PREFIX."boxes (entity, box_id, position, box_order, fk_user)
                        SELECT 1, bdef.rowid, 99, '".$order."', 1
                        FROM ".MAIN_DB_PREFIX."boxes_def AS bdef
                        WHERE bdef.file = '".$file_widget."'
                        AND NOT EXISTS (
                            SELECT 1
                            FROM ".MAIN_DB_PREFIX."boxes AS b
                            WHERE b.box_id = bdef.rowid
                            AND b.fk_user = 1
                            AND b.position = 99
                        )
                    ";

                    $resql = $db->query($sql);
                    if (!$resql) {
                        sendProgress(
                            96,
                            $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                            $langs->transnoentitiesnoconv('restoreKeyWidgetError').$file_widget.' : '.$db->lasterror());
                    } else {
                        sendProgress(
                            98,
                            $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                            $langs->transnoentitiesnoconv('restoreKeyWidgetText').$file_widget);
                    }
                }
            }

            dol_syslog("Réinjection Kontava terminée avec succès depuis $latestFile");
            sendProgress(
                98,
                $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                $langs->transnoentitiesnoconv('restoreKeyDataSuccess'));
        } else {
            sendProgress(
                90,
                $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                $langs->transnoentitiesnoconv('restoreKeyDataJSONError'),
                [ 'status_type' => 'error', 'final' => false ]);
            sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
            sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
            list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
            if ($execReturn === 0) {
                // send a final progress message with redirect and success flag
                sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
            } else {
                sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                    [ 'status_type' => 'error', 'final' => false ]);
            }
            $logfile=saveLog($backupDir);//On sauve le log dans un fichier
            sendProgress(90,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
            exit;
        }
    } else {
        sendProgress(
            90,
                $langs->transnoentitiesnoconv('restoreKeyDataStatus'),
                $langs->transnoentitiesnoconv('restoreKeyDataNoJSONError'),
            [ 'status_type' => 'error', 'final' => false ]);
        sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText'));
        sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpText2'));
        list($execReturn, $execOutput) = dolibarr_restore_database($db, $binary, $args, $backupPath, $passDb);
        if ($execReturn === 0) {
            // send a final progress message with redirect and success flag
            sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpSucessText'));
        } else {
            sendProgress(90,$langs->transnoentitiesnoconv('restoreInitialDumpStatus'),$langs->transnoentitiesnoconv('restoreInitialDumpFailText'),
                [ 'status_type' => 'error', 'final' => false ]);
        }
        $logfile=saveLog($backupDir);//On sauve le log dans un fichier
        sendProgress(90,'','',[ 'status_type' => 'error', 'final' => true, 'logfile' => $logfile ]);
        exit;
    }
}
//On supprime le fichier dump uploadé
if (is_file($filepath)) {
    @unlink($filepath);
}
sleep(1);
setEventMessages($langs->transnoentitiesnoconv('RestoreSuccessText'), null, 'mesgs');
sendProgress(100, $langs->transnoentitiesnoconv('RestoreSuccessStatus'), '** '.$langs->transnoentitiesnoconv('RestoreSuccessText').' **', array(
    'status_type' => 'ok',
    'final' => true,
    'redirect' => $redirect
));
$logfile=saveLog($backupDir);//On sauve le log dans un fichier
exit;
?>