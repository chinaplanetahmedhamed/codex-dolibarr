<style><?php 
	include 'kontava-style.css';
	?>
</style>

<?php
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);
/* Copyright (C) 2004-2017  Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2021  Frédéric France     <frederic.france@netlogic.fr>
 * Copyright (C) 2023 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    kontava/core/boxes/kw_sparexx.php
 * \ingroup kontava
 * \brief   Widget provided by Kontava
 *
 * Put detailed description here.
 */

// include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";
//On Inclut une classe de widget modifiée pour afficher correctement la petite flêche qui tourne
include_once DOL_DOCUMENT_ROOT."/custom/kontava/core/class/modules_boxes.php";


/**
 * Class to manage the box
 *
 * Warning: for the box to be detected correctly by dolibarr,
 * the filename should be the lowercase classname
 */
class kw_importdb extends ModeleBoxesKontava
{
	/**
	 * @var string Alphanumeric ID. Populated by the constructor.
	 */
	public $boxcode = "kw_importdb";

	/**
	 * @var string Box icon (in configuration page)
	 * Automatically calls the icon named with the corresponding "object_" prefix
	 */
	public $boximg = "kontava@kontava";

	/**
	 * @var string Box label (in configuration page)
	 */
	public $boxlabel;

	/**
	 * @var string[] Module dependencies
	 */
	public $depends = array('kontava');

	//PYNC Supprimé en version Dolibarr 20
	// /**
	//  * @var DoliDb Database handler
	//  */
	// public $db;

	// /**
	//  * @var mixed More parameters
	//  */
	// public $param;

	// /**
	//  * @var array Header informations. Usually created at runtime by loadBox().
	//  */
	// public $info_box_head = array();

	// /**
	//  * @var array Contents informations. Usually created at runtime by loadBox().
	//  */
	// public $info_box_contents = array();

	/**
	 * @var string 	Widget type ('graph' means the widget is a graph widget)
	 */
	public $widgettype = 'graph';


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 * @param string $param More parameters
	 */
	public function __construct(DoliDB $db, $param = '')
	{
		global $user, $conf, $langs;
		// Translations
		$langs->loadLangs(array("kontava@kontava","boxes"));

		parent::__construct($db, $param);

		$this->boxlabel = '&nbsp;'. $langs->transnoentitiesnoconv("WidgetNameImportDB");

		$this->param = $param;

		//START_PYNC_MODIF
		$this->db = $db;
		// disable box for such cases
		// if (!empty($conf->global->SOCIETE_DISABLE_CUSTOMERS)) {
		// 	$this->enabled = 0; // disabled by this option
		// }
		// include_once DOL_DOCUMENT_ROOT."/custom/kontava/core/modules/modKontava.class.php";
		// $objMod = new modKontava($db);
		// $result = $objMod->remove();
		// $result = $objMod->init();
		// header("Refresh:0");

		// if (!($user->admin)) {
			$this->enabled = 1; // enabled by default
		// }
		// $this->hidden = !($user->rights->societe->lire && empty($user->socid));
		$this->hidden = !($user->hasRight('societe', 'read'));//PYNC changement de syntaxe avec Dolibarr20
		//END_PYNC_MODIF

		// Condition when module is enabled or not
		// $this->enabled = getDolGlobalInt('MAIN_FEATURES_LEVEL') > 0;
		// Condition when module is visible by user (test on permission)
		// $this->hidden = !$user->hasRight('testd20', 'myobject', 'read');
	}

	/**
	 * Load data into info_box_contents array to show array later. Called by Dolibarr before displaying the box.
	 *
	 * @param int $max Maximum number of records to load
	 * @param int $kontava si =1, alors le widget est appelé par l'accueil du module Kontava
	 * @param int $zone par défaut à 0 pour correspondre à l'acceuil principal, pour l'accueil du module kontava, $zone = 99
	 * @return void
	 */
	public function loadBox($max = 5, $kontava = 0, $zone = 0)
	{
		global $user, $langs, $conf;// PYNC_MODIF was $langs;
		
		// Use configuration value for max lines count
		$this->max = $max;
		include DOL_DOCUMENT_ROOT.'/custom/kontava/core/boxes/kontava_color.php';
		include_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/class/kontava_function.php';
		//Je détecte si l'adresse de mon instance dolibarr est hébergée sur *.kontava.fr
		$host = $_SERVER['HTTP_HOST'];
		if (strpos($host, 'kontava.fr') !== false) {
			// L'instance est hébergée sur kontava.fr
			$kontava_instance = true;
		} else {
			// L'instance n'est pas hébergée sur kontava.fr
			$kontava_instance = false;
		}
		$filename = basename(__FILE__); //On récupère le nom du fichier php
		$idWidget=getWidgetId($filename);//On récupère l'id du widget tel que défini dans la table llx_boxes_def
		
		$dataUnfold = getWidgetState($idWidget, $zone, $user->id); //on reçoit la $zone directement en entrée de la fonction showBox, par défaut 0 pour accueil et sinon 99 pour l'accueil du module Kontava
		$hiddenClass = $dataUnfold ? '' : 'hidden';
		
		$couleurFont = '#FFF' ;
		$this->dragndropColor = $couleurFont;
		$this->backgroundColor = $vert1_hex;
		$dolurlroot=DOL_URL_ROOT;
		$titreDuWidget = <<<HTML
<script src="{$dolurlroot}/custom/kontava/core/boxes/widget.js" defer></script>
<div class="widget-container" data-box-id="{$idWidget}" data-user-id="{$user->id}" data-zone="{$zone}" data-dol-url-root="{$dolurlroot}">
	<div class="widget-title">
		<!-- <a href="/custom/kontava/kontavaindex.php?mainmenu=kontava"> -->
			<img src="{$dolurlroot}/custom/kontava/img/object_kontava.png" alt="Kontava" style="height: 12px;">
		<!-- </a> -->
		&nbsp;
		<a title="{$langs->trans('WidgetNameImportDBToolTip')}" style="text-decoration:none; color: {$couleurFont}; flex-grow: 1;">
			{$langs->trans('WidgetNameImportDBShort')}&nbsp;&nbsp;&nbsp;
		</a>
	</div>
	<i class="widget-arrow fa fa-chevron-down" style="color:{$couleurFont};"></i>
</div>
HTML;
								
								//END_PYNC_MODIF

		$importDBText = <<<HTML
<div class="kontava" style="margin:10px;">
	<span class="kontava">
		{$langs->trans('ImportDBText1')}<br/>
		<b style="color:{$orange_hex};">{$langs->trans('ImportDBAdmin')}</b><br/>
		{$langs->trans('ImportDBText2')}<br/>
		{$langs->trans('ImportDBText3')}<br/><br/>
		{$langs->trans('ImportDBText4')}
	</span>
</div>
HTML;

		// Générer le token CSRF Dolibarr
        $token = '';
        if (function_exists('newToken')) {
            $token = newToken();
        }

        // Déterminer dossier documents (comme dans upload_sql.php)
        if (defined('DOL_DATA_ROOT')) {
            $dataRoot = rtrim(DOL_DATA_ROOT, '/\\');
        } else {
            $dataRoot = realpath(DOL_DOCUMENT_ROOT . '/..') . '/documents';
        }
        $targetDir = $dataRoot . '/kontava/importDB';

        // Rechercher un fichier .sql présent (on prend le premier s'il y en a)
        $existingFile = '';
        if (is_dir($targetDir)) {
            $files = glob($targetDir . '/*.sql');
            if (!empty($files)) {
                // conserver seulement le nom de fichier
                $existingFile = basename($files[0]);
            }
        }
        $backupForm = <<<HTML
			<!-- Export form -->
			<form action="/custom/kontava/core/class/export_sql.php" method="post">
				<input type="hidden" name="token" value="{$token}" />
				<input type="hidden" name="mode" value="download" />
				<button type="submit" class="kontava" title="{$langs->transnoentitiesnoconv('ExportDatabase')}" style="margin:10px; line-height: 3; border-width: 0px;">
					<i class="fa fa-download" aria-hidden="true"></i>&nbsp;Sauvegarder la base de donnée
				</button>
			</form>
			HTML;
		$importLog = '';

        if ($existingFile) {
			// --- START: check if SQL is a Dolibarr backup and extract MAIN_VERSION_LAST_UPGRADE ---
            $filePath = $targetDir . '/' . $existingFile;
            $fileVersion = '';
            if (is_readable($filePath)) {
                $sqlContent = file_get_contents($filePath);
                $fileVersion     = extractDolibarrConstFromSQL($sqlContent, 'MAIN_VERSION_LAST_UPGRADE'); //On récupère la value de la constante MAIN_VERSION_LAST_UPGRADE
                $kontavaEnabled  = extractDolibarrConstFromSQL($sqlContent, 'MAIN_MODULE_KONTAVA'); //On récupère la value de la constante MAIN_MODULE_KONTAVA (si c'est 1, alors le module Kontava était activé lors de la sauvegarde)
                // $hasKontavaTable = (bool)preg_match('/CREATE\s+TABLE\s+[`"]?llx_kontava[`"]?(\s|\(|`)/i', $sqlContent); //Vérifie si la table llx_kontava est présente dans le dump SQL
                $hasKontavaTable = (bool)preg_match('/CREATE\s+TABLE\s+[`"]?llx_konst[`"]?(\s|\(|`)/i', $sqlContent); //Vérifie si la table llx_konst est présente dans le dump SQL
                $isKontavaInBackup = ($kontavaEnabled === '1' && $hasKontavaTable)?1:0; //Considère que c'est une sauvegarde Dolibarr avec Kontava si la constante est à 1 et que la table existe
            }

            // Current Dolibarr version (fallback to empty if not available)
            $currentVersion = defined('DOL_VERSION') ? DOL_VERSION : (isset($conf->global->MAIN_VERSION_LAST_UPGRADE) ? $conf->global->MAIN_VERSION_LAST_UPGRADE : '');
            $needDolibarrMigration = 0; //Pour l'envoyer à restore_sql.php afin de lui dire si on doit où non lancer le script de migration de la DB
            // Build UI depending on discovered file version
            if (empty($fileVersion)) {
                // Not a recognized Dolibarr backup -> show delete button + message on the right
                $msg = htmlspecialchars($langs->transnoentitiesnoconv('ImportDBNoVersion'));
                $uploadForm = <<<HTML
                    <div style="margin:10px; display:flex; align-items:center; justify-content:space-between;">
                        <div style="min-width:0;">
                            <div class="kontava">
                                <span style="white-space: nowrap;">{$langs->trans('FileLoaded')}</span>&nbsp;
                                <strong style="display:inline; overflow-wrap:anywhere; word-break:break-all;">{$existingFile}</strong>
                            </div>
                        </div>

                        <div style="display:inline-flex; gap:8px; align-items:center;">

                            <!-- Delete form -->
                            <form action="/custom/kontava/core/class/delete_sql.php" method="post" style="display:inline;">
								<input type="hidden" name="returnurl" value="{$_SERVER['REQUEST_URI']}"/>
                                <input type="hidden" name="token" value="{$token}" />
                                <input type="hidden" name="file" value="{$existingFile}" />
                                <button type="submit" class="kontava" title="{$langs->trans('DeleteFile')}" style="background:transparent;border:0;color:#f49739;">
                                    <i class="fa fa-trash" style="color:#f49739;" aria-hidden="true"></i>
                                </button>
                            </form>

                            <!-- Message (remplace le bouton Restaurer) -->
                            <div style="max-width:420px;color:#f49739;font-size:0.95em;">{$msg}</div>
                        </div>
                    </div>
HTML;
            } elseif ($currentVersion !== '' && version_compare($fileVersion, $currentVersion, '>')) {
                // File was created for a newer Dolibarr version -> show delete button + warning message on the right
                $msg = htmlspecialchars($langs->transnoentitiesnoconv('ImportDBVersionHigher1').' '.$fileVersion.' '.$langs->transnoentitiesnoconv('ImportDBVersionHigher2').$currentVersion.$langs->transnoentitiesnoconv('ImportDBVersionHigher3'));
                $uploadForm = '
                    <div style="margin:10px; display:flex; align-items:center; justify-content:space-between;">
                        <div style="min-width:0;">
                            <div class="kontava">
                                <span style="white-space: nowrap;">'.$langs->trans('FileLoaded').'</span>&nbsp;
                                <strong style="display:inline; overflow-wrap:anywhere; word-break:break-all;">'.htmlspecialchars($existingFile).'</strong>
                            </div>
                            <div class="kontava" style="font-size:0.6em; margin-top:4px;">'.htmlspecialchars($langs->transnoentitiesnoconv('ImportDBLoadedFileVersion').' '.$fileVersion).'</div>
                        </div>

                        <div style="display:inline-flex; gap:8px; align-items:center;">

                            <!-- Delete form -->
                            <form action="/custom/kontava/core/class/delete_sql.php" method="post" style="display:inline;">
                                <input type="hidden" name="returnurl" value="'.$_SERVER['REQUEST_URI'].'"/>
                                <input type="hidden" name="token" value="'.htmlspecialchars($token).'" />
                                <input type="hidden" name="file" value="'.htmlspecialchars($existingFile).'" />
                                <button type="submit" class="kontava" title="'.$langs->trans('DeleteFile').'" style="background:transparent;border:0;color:#f49739;">
                                    <i class="fa fa-trash" style="color:#f49739;" aria-hidden="true"></i>
                                </button>
                            </form>

                            <!-- Warning message (remplace le bouton Restaurer) -->
                            <div style="max-width:420px;color:#f49739;font-size:0.95em;">'.$msg.'</div>
                        </div>
                    </div>';
            } else { //On autorise la restauration
                if (version_compare($fileVersion, $currentVersion, '<')) {
                    $needDolibarrMigration = 1; //Si la version du backup est strictement antérieure à la version courante, on devra lancer le script de migration après restauration
                }
                // OK to allow restore (fileVersion <= currentVersion)
                $confirm1 = $langs->transnoentitiesnoconv('ImportDBConfirmRestoration1');
                $confirm2 = $langs->transnoentitiesnoconv('ImportDBText4');
                $confirm1_js = json_encode($confirm1);
				$confirm2_js = json_encode($confirm2);
				
				// Préparer les valeurs encodées en JSON utilisées dans le JS inline
				$token_json = json_encode($token);
				$existingFile_json = json_encode($existingFile);
				$needMigration_json = json_encode($needDolibarrMigration);
				$isKontavaInBackup_json = json_encode($isKontavaInBackup);
				$dolibarrVersionFrom = json_encode($fileVersion);
				$dolibarrVersionTo = json_encode($currentVersion);

				$importLog = <<<HTML
					<div id="restore-progress" style="margin-top:10px; display:none; position:relative;">
						<div style="border:1px solid #ccc; border-radius:4px; height:20px; width:100%; position:relative; overflow:hidden;">
							<div id="restore-progress-bar"
								style="background:{$vert1_hex}; height:100%; width:0%; transition:width 0.3s;">
							</div>
							<div id="restore-progress-text"
								style="position:absolute; top:0; left:0; width:100%; height:100%; 
										display:flex; align-items:center; justify-content:center;
										font-size:12px; color:white; font-weight:bold; text-shadow:0 0 2px #000;">
								0%
							</div>
						</div>
						<div id="restore-status" style="margin-top:5px; margin-left:10px; font-size:smaller; color:#333;">En attente...</div>
					</div>

					<div id="restore-log"
						style="display:none; margin-top:10px; max-height:180px; overflow-y:auto;
								background:#fafafa; border:1px solid #ddd; padding:5px;
								font-family:monospace; font-size:12px;
								white-space:pre-wrap; word-break:break-word;">
					</div>
					HTML;
				// $uploadForm = '';
				$uploadForm = <<<HTML
					<div style="margin:10px; display:flex; align-items:center; justify-content:space-between;"> 
						<div style="min-width:0;">
							<div class="kontava">
								<span style="white-space: nowrap;">{$langs->trans('FileLoaded')}</span>&nbsp;
								<strong style="display:inline; overflow-wrap:anywhere; word-break:break-all;">{$existingFile}</strong>
							</div>
							<div class="kontava" style="font-size:0.6em; margin-top:4px;">{$langs->transnoentitiesnoconv('ImportDBLoadedFileVersion')} {$fileVersion}</div>
							<!-- <div class="kontava" style="font-size:0.6em; margin-top:4px;">Need Migration={$needDolibarrMigration}, Is Kontava module enabled={$kontavaEnabled}, Is llx_kontava table in backup={$hasKontavaTable}, Is Kontava in backup={$isKontavaInBackup}</div> -->
						</div>

						<div style="display:inline-flex; gap:8px; align-items:center;">
							<!-- Delete form -->
							<form action="/custom/kontava/core/class/delete_sql.php" method="post" style="display:inline;">
								<input type="hidden" name="returnurl" value="{$_SERVER['REQUEST_URI']}"/>
								<input type="hidden" name="token" value="{$token}" />
								<input type="hidden" name="file" value="{$existingFile}" />
								<input type="hidden" name="need_migration" value="{$needDolibarrMigration}" />
								<input type="hidden" name="is_kontava_in_backup" value="{$isKontavaInBackup}" />
								<button type="submit" class="kontava" title="{$langs->trans('DeleteFile')}" style="background:transparent;border:0;color:#f49739;">
									<i class="fa fa-trash" style="color:#f49739;" aria-hidden="true"></i>
								</button>
							</form>

							<!-- Restore button -->
							<button id="btn-start-restore" type="button" class="kontava" title="{$langs->transnoentitiesnoconv('RestoreDatabase')}" style="line-height:3; border-width:0;">
								<i class="fa fa-database" aria-hidden="true"></i>&nbsp;Restaurer base de donnée
							</button>
							<button id="send-error-log" type="button" class="kontava red" title="{$langs->transnoentitiesnoconv('restoreSendLog')}" style="display:none; line-height:3; border-width:0;">
								<i class="fa fa-envelope"></i> Envoyer le log au support
							</button>
						</div>
					</div>

					<script type="text/javascript">
					(function(){
						const token = {$token_json};
						const existingFile = {$existingFile_json};
						const needMigration = {$needMigration_json};
						const isKontavaInBackup = {$isKontavaInBackup_json};
						const dolibarrVersionFrom = {$dolibarrVersionFrom};
						const dolibarrVersionTo = {$dolibarrVersionTo};

						async function startRestore() {
							const btn = document.getElementById('btn-start-restore'); //Bouton de restauration
							const progressDiv = document.getElementById('restore-progress'); //le pourcentage dans la barre de progression
							const logDiv = document.getElementById('restore-log'); //le log
							const bar = document.getElementById('restore-progress-bar'); //la barre de progression
							const status = document.getElementById('restore-status'); //Le status de la restauration
							const sendLogBtn = document.getElementById('send-error-log'); //Bouton d'envoi des logs
							sendLogBtn.dataset.logfile = ''; //Par défaut je charge un fichier de log vide.
							try {

								const confirmMessage1 = {$confirm1_js};
								const confirmMessage2 = {$confirm2_js};
								const msg = confirmMessage1 + ' "' + existingFile + '" ?\\n\\n' + confirmMessage2;
								if (!window.confirm(msg)) return false;
								//Initialisation
								btn.disabled = true; //On désactive le bouton
								btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>&nbsp;Restauration...'; //On affiche un spinner sur le bouton

								logDiv.innerHTML = '';
								bar.style.width = '0%';
								const text = document.getElementById('restore-progress-text');
								if (text) text.textContent = '0%';
								progressDiv.style.display = 'block';//On affiche la barre de progression
								logDiv.style.display = 'block';//On affiche le log
								status.textContent = 'Démarrage...';

								// Build form-encoded body so Dolibarr's GETPOST() can read token and other params
								const params = new URLSearchParams();
								params.append('action', 'restore');
								params.append('token', String(token));
								params.append('file', String(existingFile));
								params.append('need_migration', needMigration ? '1' : '0');
								params.append('is_kontava_in_backup', isKontavaInBackup ? '1' : '0');
								params.append('dolibarr_version_from', String(dolibarrVersionFrom));
								params.append('dolibarr_version_to', String(dolibarrVersionTo));

								const response = await fetch('/custom/kontava/core/class/restore_sql.php', {
									method: 'POST',
									headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
									body: params.toString()
								});

								// Lire la réponse en streaming et traiter chaque ligne JSON dès qu'elle arrive
								const reader = response.body.getReader();
								const decoder = new TextDecoder();
								let redirectUrl = null;
								let finalStatusType = null;
								let finalSeen = false;
								let buffer = '';

								while (true) {
									const {value, done} = await reader.read();
									if (done) break;
									// Décoder le chunk reçu et l'ajouter au buffer
									const chunk = decoder.decode(value, {stream: true});
									buffer += chunk;

									// Séparer par lignes. Le dernier élément peut être incomplet -> on le remet dans buffer
									const lines = buffer.split('\\n');
									buffer = lines.pop();

									for (const line of lines) {
										if (!line || !line.trim()) continue;
										try {
											const data = JSON.parse(line);
											if (data.log) {
												const logEntry = document.createElement('div');
												if (data.status_type === 'error') {
													logEntry.style.color = 'crimson';
												}
												logEntry.textContent = data.log;
												logDiv.appendChild(logEntry);
												logDiv.scrollTop = logDiv.scrollHeight;
											}
											if (data.logfile) { //Si on a envoyé le chemin vers le fichier de log on le met dans les paramètre du bouton d'envoi de log
												sendLogBtn.dataset.logfile = data.logfile;
											}
											if (typeof data.progress !== 'undefined') {
												bar.style.width = data.progress + '%';
												status.textContent = data.status || '...';
												const text = document.getElementById('restore-progress-text');
												if (text) text.textContent = Math.round(data.progress) + '%';
											}
											if (data.redirect) redirectUrl = data.redirect;
											if (data.status_type) finalStatusType = data.status_type;
											if (data.final) finalSeen = true;
										} catch (e) {
											console.error('Erreur JSON (chunk):', line, e);
											const errorEntry = document.createElement('div');
											errorEntry.style.color = 'crimson';
											errorEntry.textContent = "Erreur: " + line;
											logDiv.appendChild(errorEntry);
											logDiv.scrollTop = logDiv.scrollHeight;
										}
									}
								}

								// Traiter enfin le reste du buffer s'il contient une ligne JSON complète
								if (buffer && buffer.trim()) {
									try {
										const data = JSON.parse(buffer);
										if (data.log) {
											const logEntry = document.createElement('div');
											if (data.status_type === 'error') logEntry.style.color = 'crimson';
											logEntry.textContent = data.log;
											logDiv.appendChild(logEntry);
											logDiv.scrollTop = logDiv.scrollHeight;
										}
										if (data.logfile) { //Si on a envoyé le chemin vers le fichier de log on le met dans les paramètre du bouton d'envoi de log
											sendLogBtn.dataset.logfile = data.logfile;
										}
										if (typeof data.progress !== 'undefined') {
											bar.style.width = data.progress + '%';
											status.textContent = data.status || '...';
											const text = document.getElementById('restore-progress-text');
											if (text) text.textContent = Math.round(data.progress) + '%';
										}
										if (data.redirect) redirectUrl = data.redirect;
										if (data.status_type) finalStatusType = data.status_type;
										if (data.final) finalSeen = true;
									} catch (e) {
										console.error('Erreur JSON (buffer final):', buffer, e);
									}
								}

								// Finalize UI
								if (finalSeen && finalStatusType === 'ok') {
									// Success: show final status and schedule refresh after 10 seconds
									status.textContent = 'Terminé ✅ — la page va se rafraîchir dans 10s';
									// slight delay to allow user to see final bar before re-enable button
									setTimeout(() => {
										btn.disabled = false;
										btn.innerHTML = '<i class="fa fa-database" aria-hidden="true"></i>&nbsp;Restaurer base de donnée';
									}, 2000);
									if (redirectUrl) {
										setTimeout(() => { window.location.href = redirectUrl; }, 10000);
									}
								} else if (finalSeen && finalStatusType === 'error') {
									// Error: don't refresh; color logs/status in red
									status.textContent = 'Erreur ❌';
									status.style.color = 'crimson';
									if (sendLogBtn.dataset.logfile != '') {
										const sendLogEntry = document.createElement('div');
										sendLogEntry.style.color = 'crimson';
										sendLogEntry.textContent = "Vous pouvez envoyer le log à Kontava via le bouton ci-dessus pour demander du support.";
										logDiv.appendChild(sendLogEntry);
										logDiv.scrollTop = logDiv.scrollHeight;
										btn.style.display = 'none';
										sendLogBtn.style.display = 'inline-block';
									}
									btn.disabled = false;
									btn.innerHTML = '<i class="fa fa-database" aria-hidden="true"></i>&nbsp;Restaurer base de donnée';
								} else {
									// Fallback: no explicit final meta — behave like before
									status.textContent = 'Erreur inconnue ❌';
									status.style.color = 'crimson';
									const errorEntry = document.createElement('div');
									errorEntry.style.color = 'crimson';
									errorEntry.textContent = 'Code reçu de restore-sql.php inconnu !';
									logDiv.appendChild(errorEntry);
									logDiv.scrollTop = logDiv.scrollHeight;
									const sendLogEntry = document.createElement('div');
									sendLogEntry.style.color = 'crimson';
									sendLogEntry.textContent = "Vous pouvez envoyer le log à Kontava via le bouton ci-dessus pour demander du support.";
									logDiv.appendChild(sendLogEntry);
									logDiv.scrollTop = logDiv.scrollHeight;
									btn.disabled = false;
									btn.innerHTML = '<i class="fa fa-database" aria-hidden="true"></i>&nbsp;Restaurer base de donnée';
									btn.style.display = 'none';
									sendLogBtn.style.display = 'inline-block';
									// setTimeout(() => {
									// }, 2000);
									if (redirectUrl) {
										setTimeout(() => { window.location.href = redirectUrl; }, 2200);
									}
								}
							} catch (error) {
								console.error('Erreur:', error);
								status.textContent = 'Erreur ❌';
								const errorEntry = document.createElement('div');
								errorEntry.style.color = 'crimson';
								errorEntry.textContent = "Erreur: " + error.message;
								logDiv.appendChild(errorEntry);
								logDiv.scrollTop = logDiv.scrollHeight;
								const sendLogEntry = document.createElement('div');
								sendLogEntry.style.color = 'crimson';
								sendLogEntry.textContent = "Vous pouvez envoyer le log à Kontava via le bouton ci-dessus pour demander du support.";
								logDiv.appendChild(sendLogEntry);
								logDiv.scrollTop = logDiv.scrollHeight;
								btn.disabled = false;
								btn.innerHTML = '<i class="fa fa-database" aria-hidden="true"></i>&nbsp;Restaurer base de donnée';
								btn.style.display = 'none';
								sendLogBtn.style.display = 'inline-block';
							}
						}
						//On surveille le clic sur le bouton de restauration
						document.getElementById('btn-start-restore').addEventListener('click', startRestore);
						//On surveille le clic sur le bouton d'envoi de log
						document.getElementById('send-error-log').addEventListener('click', async function () {
							const filename = this.dataset.logfile;
							if (!filename) {
								alert("Aucun fichier log disponible.");
								return;
							}
							const logDiv = document.getElementById('restore-log'); //le log

							const btn_log = this;
							btn_log.disabled = true;
							btn_log.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Envoi en cours...';

							try {
								const params = new URLSearchParams();
								params.append("token", String(token));
								params.append("logfile", String(filename));

								const response = await fetch('/custom/kontava/core/class/send_restore-log.php', {
									method: 'POST',
									headers: { 
										'Content-Type': 'application/x-www-form-urlencoded',
										'X-Requested-With': 'XMLHttpRequest'
									},
									body: params.toString()
								});

								const data = await response.json();   // Doit renvoyer JSON
								const logEntry = document.createElement('div'); // <-- Crée le div ici
								if (data.status === "ok") {
									btn_log.innerHTML = '<i class="fa fa-check"></i> Log envoyé';
									logEntry.style.color = 'green';
									logEntry.textContent = 'Log envoyé avec succès Kontava !';
								} else {
									btn_log.innerHTML = '<i class="fa fa-times"></i> Erreur d’envoi';
									logEntry.style.color = 'crimson';
									logEntry.textContent = 'Échec de l’envoi du log : ' + (data.message || 'Erreur inconnue.');
								}
								logDiv.appendChild(logEntry);
								logDiv.scrollTop = logDiv.scrollHeight;
							} 
							catch (err) {
								console.error(err);
								const logEntry = document.createElement('div'); // <-- Crée le div ici aussi
								btn_log.innerHTML = '<i class="fa fa-times"></i> Erreur d’envoi inconnue';
								logEntry.style.color = 'crimson';
								logEntry.textContent = 'Erreur JS lors de l’envoi du log : ' + error.message;
								logDiv.appendChild(logEntry);
								logDiv.scrollTop = logDiv.scrollHeight;
							}

							setTimeout(() => {
								btn_log.disabled = false;
								btn_log.innerHTML = '<i class="fa fa-envelope"></i> Envoyer le log au support';
							}, 5000);
						}
					);
					})();
					</script>
				HTML;
			}
        } else {
            // Aucun fichier -> afficher formulaire d'upload (input stylé + drag & drop)
            $token_esc = htmlspecialchars($token, ENT_QUOTES);
            $uploadForm = <<<HTML
				<form action="/custom/kontava/core/class/upload_sql.php" method="post" enctype="multipart/form-data" style="margin:10px;">
					<input type="hidden" name="returnurl" value="{$_SERVER['REQUEST_URI']}"/>
					<input type="hidden" name="token" value="{$token_esc}" />
					<div class="kv-file-input-wrapper">
						<div id="kv-dropzone" class="kv-dropzone" aria-label="Zone de dépôt de fichier">
							<label class="kv-file-btn" title="{$langs->trans('BrowseSQL')}" for="kv-sqlfile" style="margin:0;">
								<i class="fa fa-folder" aria-hidden="true"></i>&nbsp;{$langs->trans('Browse')}
							</label>
							<input id="kv-sqlfile" class="kv-file-input-hidden" type="file" name="sqlfile" accept=".sql" required />
							<span id="kv-filename" class="kv-file-name">{$langs->trans('NoFile')}</span>
						</div>
						<button id="btn-start-restore" type="submit" class="kontava" style="margin:0; line-height: 3; border-width: 0px;" title="{$langs->trans('UploadFichier')}"><i class="fa fa-upload" aria-hidden="true"></i></button>
						<span id="kv-file-error" class="kv-file-error" style="display:none;"></span>
					</div>
				</form>
				<script>
				(function(){
					var input = document.getElementById('kv-sqlfile');
					var drop = document.getElementById('kv-dropzone');
					var nameSpan = document.getElementById('kv-filename');
					var errorSpan = document.getElementById('kv-file-error');

					function clearError(){ errorSpan.style.display='none'; errorSpan.textContent=''; }
					function showError(msg){ errorSpan.style.display='inline-block'; errorSpan.textContent=msg; }

					// Click on label triggers file chooser automatically thanks to for/id
					input.addEventListener('change', function(){
						clearError();
						if(!this.files || !this.files.length) { nameSpan.textContent = 'Aucun fichier choisi'; return; }
						var f = this.files[0];
						nameSpan.textContent = f.name;
						if(!/\\.sql$/i.test(f.name)) {
							showError('Seuls les fichiers .sql sont autorisés');
						}
					});
					// Drag & drop handlers
					['dragenter','dragover'].forEach(function(evt){
						drop.addEventListener(evt, function(e){
							e.preventDefault(); e.stopPropagation();
							drop.classList.add('kv-dragover');
						}, false);
					});
					['dragleave','dragend','drop'].forEach(function(evt){
						drop.addEventListener(evt, function(e){
							e.preventDefault(); e.stopPropagation();
							drop.classList.remove('kv-dragover');
						}, false);
					});
					drop.addEventListener('drop', function(e){
						clearError();
						var dt = e.dataTransfer;
						if(!dt || !dt.files || !dt.files.length) return;
						var f = dt.files[0];
						// validate extension
						if(!/\\.sql$/i.test(f.name)) {
							showError('Seuls les fichiers .sql sont autorisés');
							return;
						}
						// set file into input (works in modern browsers)
						try {
							var dataTransfer = new DataTransfer();
							dataTransfer.items.add(f);
							input.files = dataTransfer.files;
						} catch(err) {
							// fallback: cannot set input.files programmatically in some browsers
						}
						nameSpan.textContent = f.name;
					}, false);
				})();
				</script>
			HTML;

        }
		if($kontava_instance) {
		$widget='<div class="widget-content ' . $hiddenClass . '" style="background:rgba(0,102,51, 0.2);">'.
                    $importDBText.
                    // $toastMsg.
                    '<div style="width:100%">'.
                        $backupForm.
                        $uploadForm.
						$importLog.
                    '</div>'.
                '</div>';
		} else {
			$widget = '<div class="widget-content ' . $hiddenClass . '" style="background:rgba(0,102,51, 0.2);">
				<div class="kontava" style="margin:10px; text-align:center; ">
					<span  class="kontava" >'.$langs->trans('FirstStepsDiscoverKontava').'</span><br>
					<a class="kontava" href="https://kontava.fr/" target="_blank" style="font-weight:bold; color:'.$bleu1_hex.'; ">kontava.fr</a>
				</div>
			</div>';
		}
		//dol_include_once("/kontava/class/kontava.class.php");

		// Populate the head at runtime
		$text = $langs->trans("KontavaBoxDescription", $max);
		$this->info_box_head = array(
			// Title text
			// 'text' => '<a href="/custom/kontava/kontavaindex.php?mainmenu=kontava" title='.$langs->trans("WidgetNameBigButtonToolTip").'><img src="'.DOL_URL_ROOT.'/custom/kontava/img/gros_logo_kontava.png" alt="Kontava" style="height: 25px;"></a>', //$text,
			'text' => $titreDuWidget, //$text,
			// Add a link
			'sublink' => '',
			// Sublink icon placed after the text
			'subpicto' => '',
			// Sublink icon HTML alt text
			'subtext' => '',
			// Sublink HTML target
			'target' => '',
			// HTML class attached to the picto and link
			'subclass' => 'center',
			// Limit and truncate with "…" the displayed text lenght, 0 = disabled
			'limit' => 0,
			// Adds translated " (Graph)" to a hidden form value's input (?)
			'graph' => 0
		);

		// Populate the contents at runtime
		$this->info_box_contents = array(
			0 => array( // Another line
				0 => array( // TR
					//  HTML properties of the TR element. Only available on the first column.
					'tr' => 'class="left"',
					// HTML properties of the TD element
					'td' => 'style="padding:0px; "', // PYNC Utile sur les widget à une seule ligne pour enlever le padding et faire que 
					'target' => '', //'_blank',
					'textnoformat' => '',

					// Main text for content of cell (other method)  
					'text2' => 
						$widget
						,//'<p><strong>Another text</strong></p>',

					// Truncates 'text' element to the specified character length, 0 = disabled
					'maxlength' => 0,
					// Prevents HTML cleaning (and truncation)
					'asis' => 0,
					// Same for 'text2'
					'asis2' => 1		//C'est cette commande qui fait que le text2 est conservé au format joli
				),
			),
		);
	}

	/**
	 * Method to show box. Called by Dolibarr eatch time it wants to display the box.
	 *
	 * @param array $head       Array with properties of box title
	 * @param array $contents   Array with properties of box lines
	 * @param int   $nooutput   No print, only return string
	 * @return string
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0, $backgroundColor = "", $dragndropColor = "", $noCloseBtn = 0)
	{
		// You may make your own code here…
		// … or use the parent's class function using the provided head and contents templates
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput, $this->backgroundColor, $this->dragndropColor, 0);
	}
}