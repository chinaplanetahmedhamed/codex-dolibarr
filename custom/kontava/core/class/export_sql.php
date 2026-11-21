<?php
/**
 * export_sql.php
 *
 * Permet d’exporter la base de données :
 *  - soit en téléchargement direct (mode=download)
 *  - soit en sauvegarde serveur (mode=server)
 */
// Require user and database initialization
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

/**
 * Fonction principale d’export
 * @param string $mode "download" (envoi au navigateur) ou "server" (sauvegarde sur disque)
 * @param string|null $destination Répertoire cible si mode=server
 * @return string|null Retourne le chemin du fichier sauvegardé si mode=server
 */
function backupDatabase($mode = 'download', $destination = null)
{
    global $user, $conf, $dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass, $dolibarr_main_db_name, $dolibarr_main_db_port;

    // Security check
    if (!$user->admin) {
        accessforbidden();
    }

    // Vérif CSRF si appel depuis formulaire
    if ($mode === 'download') {
        if (!isset($_POST['token']) || !isset($_SESSION['token']) || $_POST['token'] !== $_SESSION['token']) {
            http_response_code(403);
            exit('Token de sécurité invalide');
        }
    }
    // Paramètres de connexion à la base de données
    $host = $conf->global->MAIN_DB_HOST ?? $dolibarr_main_db_host ?? '';
    $port = $conf->global->MAIN_DB_PORT ?? $dolibarr_main_db_port ?? '';
    $user_db = $conf->global->MAIN_DB_USER ?? $dolibarr_main_db_user ?? '';
    $pass = $conf->global->MAIN_DB_PASS ?? $dolibarr_main_db_pass ?? '';
    $name = $conf->global->MAIN_DB_NAME ?? $dolibarr_main_db_name ?? '';

    // Vérifier que mysqldump est disponible
    $mysqldumpPath = 'mysqldump';
    
    // Options pour mysqldump
    $extraOptions = array(
        '--protocol=tcp',
        '--single-transaction',
        '--quick',
        '--add-drop-table=TRUE',
        '-c', // --complete-insert
        '-e', // --extended-insert
        '--hex-blob',
        '--default-character-set=utf8',
        '--no-tablespaces'
    );

    // Créer un fichier temporaire pour les credentials (sécurité)
    $tmpDir = sys_get_temp_dir();
    $defaultsFile = tempnam($tmpDir, 'mysqldump_conf_');
    if ($defaultsFile === false) {
        http_response_code(500);
        exit('Impossible de créer un fichier temporaire pour la sauvegarde.');
    }

    $defaultsContent = "[client]\n";
    if (!empty($host)) $defaultsContent .= "host=" . $host . "\n";
    if (!empty($port)) $defaultsContent .= "port=" . $port . "\n";
    if (!empty($user_db)) $defaultsContent .= "user=" . $user_db . "\n";
    if (!empty($pass)) $defaultsContent .= "password=" . $pass . "\n";

    file_put_contents($defaultsFile, $defaultsContent);
    @chmod($defaultsFile, 0600);

    // Construire la commande
    $cmdParts = array();
    $cmdParts[] = $mysqldumpPath;
    $cmdParts[] = '--defaults-extra-file=' . escapeshellarg($defaultsFile);
    foreach ($extraOptions as $opt) $cmdParts[] = $opt;
    if (!empty($host)) $cmdParts[] = '-h' . escapeshellarg($host);
    if (!empty($port)) $cmdParts[] = '-P' . escapeshellarg($port);
    $cmdParts[] = escapeshellarg($name);

    $command = implode(' ', $cmdParts) . ' 2>&1'; // Capturer stderr aussi

    // IMPORTANT : Tester la commande d'abord dans un buffer
    ob_start();

    // Version de Dolibarr
    $dolibarrVersion = defined('DOL_VERSION') ? DOL_VERSION : (isset($conf->global->MAIN_VERSION_LAST_UPGRADE) ? $conf->global->MAIN_VERSION_LAST_UPGRADE : '');

    // En-tête du fichier SQL
    echo "-- Sauvegarde de la base de données Kontava\n";
    echo "-- Date: " . date('Y-m-d H:i:s') . "\n";
    if ($dolibarrVersion !== '') echo "-- Version Dolibarr: " . $dolibarrVersion . "\n";
    echo "-- Host: " . $host . "\n";
    echo "-- Database: " . $name . "\n\n";

    // Exécuter mysqldump
    $ret = 0;
    passthru($command, $ret);

    // Nettoyer le fichier de credentials
    @unlink($defaultsFile);

    // Vérifier si l'export a réussi
    $output = ob_get_contents();

    if ($ret !== 0) {
        // Erreur pendant l'export
        ob_end_clean();
        http_response_code(500);
        exit('Erreur lors de l\'export de la base de données. Code retour: ' . $ret . "\n\nDétails: " . substr($output, 0, 500). 'Commande = '. $command.' host='.$host.' port='.$port.' user='.$user_db.' base='.$name);
    }

    // Vérifier que l'output contient bien du SQL (pas juste des erreurs)
    if (strlen($output) < 100 || strpos($output, 'CREATE TABLE') === false) {
        ob_end_clean();
        http_response_code(500);
        exit('Le fichier de sauvegarde semble vide ou invalide. Vérifiez les permissions de la base de données.');
    }

    // Tout est OK, envoyer les headers et le contenu
    $now = dol_print_date(dol_now(), 'dayhourlog');
    $filename = 'kontava_' . $conf->db->name . '_' . $dolibarrVersion .'_' . $now . '.sql';

    if ($mode === 'server') {
        // Mode serveur → on enregistre dans /documents/kontava/importDB/backup/
        $backupDir = $destination ?: DOL_DATA_ROOT . '/kontava/importDB/backup/';
        if (!file_exists($backupDir)) dol_mkdir($backupDir);
        $filePath = $backupDir . $filename;
        $output = ob_get_clean(); // 🔥 capture et efface le buffer sinon tout le SQL s'affiche dans le log du widget
        file_put_contents($filePath, $output);
        return $filePath;
    } else {
        // Mode téléchargement → on envoie au navigateur
        $output = ob_get_clean(); // 🔥 idem, on ferme proprement le buffer
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: ' . strlen($output));
        header('Pragma: no-cache');
        header('Cache-Control: no-cache, must-revalidate');
        echo $output;
        exit;
    }
    // Envoyer le contenu
    // ob_end_flush();//
}

// Si ce fichier est appelé directement via le navigateur :
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $mode = $_POST['mode'] ?? 'download';
    backupDatabase($mode);
}
?>
