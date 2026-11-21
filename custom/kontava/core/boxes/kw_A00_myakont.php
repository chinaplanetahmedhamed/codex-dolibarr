<style>
	<?php 
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
 * \file    kontava/core/boxes/kontavawidget1.php
 * \ingroup kontava
 * \brief   Widget provided by Kontava
 *
 * Put detailed description here.
 */

// include_once DOL_DOCUMENT_ROOT."/core/boxes/modules_boxes.php";
//On Inclut une classe de widget modifiée pour afficher correctement la petite flèche qui tourne
include_once DOL_DOCUMENT_ROOT."/custom/kontava/core/class/modules_boxes.php";


/**
 * Class to manage the box
 *
 * Warning: for the box to be detected correctly by dolibarr,
 * the filename should be the lowercase classname
 */
class kw_A00_myakont extends ModeleBoxesKontava //PYNC Attention on crée un objet de notre classe de widget modifée
{
	/**
	 * @var string Alphanumeric ID. Populated by the constructor.
	 */
	public $boxcode = "kw_A00_myakont";

	/**
	 * @var string Box icon (in configuration page)
	 * Automatically calls the icon named with the corresponding "object_" prefix
	 */
	public $boximg = "kontava@kontava";

	/**
	 * @var string Box label (in configuration page)
	 */
		public $boxlabel = 'MyWidget';

	/**
	 * @var string Box language file if it needs a specific language file.
	 */
	public $lang = 'kontavavierge21@kontavavierge21';

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

		$this->boxlabel = '&nbsp;'. $langs->transnoentitiesnoconv("WidgetNameMyAkont");

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

		if (!($user->admin)) {
			// $this->enabled = 0; // disabled by this option
		}
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
		require DOL_DOCUMENT_ROOT.'/custom/kontava/core/boxes/kontava_color.php';
		// Récupération de la version du module depuis la classe modKontava
		require_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/modules/modKontava.class.php';
		//Je détecte si l'adresse de mon instance dolibarr est hébergée sur *.kontava.fr
		$host = $_SERVER['HTTP_HOST'];
		if (strpos($host, 'kontava.fr') !== false) {
			// L'instance est hébergée sur kontava.fr
			$kontava_instance = true;
			dol_syslog("Instance hébergée sur kontava.fr : affichage du widget MyAkont avec les données Kontava", LOG_DEBUG);

		} else {
			// L'instance n'est pas hébergée sur kontava.fr
			$kontava_instance = false;
			dol_syslog("Instance PAS hébergée sur kontava.fr", LOG_DEBUG);

		}

		$modKontava = new modKontava($this->db);
		$latest_version = $modKontava->version;
		// Récupération de la version du module en cours (llx_const, note de MAIN_MODULE_KONTAVA)
		$module_version = 'inconnue';
		$sql_version = "SELECT note FROM ".MAIN_DB_PREFIX."const WHERE name = 'MAIN_MODULE_KONTAVA' AND entity = 1 LIMIT 1";
		$res_version = $this->db->query($sql_version);
		if ($res_version && $this->db->num_rows($res_version) > 0) {
			$obj_version = $this->db->fetch_object($res_version);
			if (!empty($obj_version->note)) {
				$note_data = json_decode($obj_version->note, true);
				if (is_array($note_data) && !empty($note_data['lastactivationversion'])) {
					$module_version = $note_data['lastactivationversion'];
				}
			}
			$this->db->free($res_version);
		}
		//On détermine si on aura besoin d'une mise à jour automatique du module
		// $need_update = ($user->admin && $latest_version && $latest_version !== $module_version);
		$need_update = ($latest_version && $latest_version !== $module_version);
		
		$couleurFont = '#FFF' ;

		//On détermine si c'est un utilisateur admin ou non
		$adminUser=0;
		if (($user->admin)) {
			$adminUser=1;
		}

		//Après une mise à jour on affiche un toast message
		if (GETPOST('kontava_updated', 'int')) {
			setEventMessages("Le module Kontava a été mis à jour avec succès vers la version $latest_version.", null, 'mesgs');
			// header("Location: " . $_SERVER['PHP_SELF']);
    		// exit;
		}

		// --- Récupération des constantes depuis la table llx_konst ---
		$wanted = array(
			'KONTAVA_PLAN',
			'STOCKAGE_QUOTA',
			'STOCKAGE_REEL',
			'USERS_QUOTA',
			'PROCHAIN_PAIEMENT_MONTANT',
			'PROCHAIN_PAIEMENT_DATE',
			'LIEN_PAIEMENT',
			'COMPTE_GRATUIT'
		);

		// Construire la liste pour l'IN (sécuriser les noms)
		$inList = "'" . implode("','", $wanted) . "'";

		$sql = "SELECT nom, valeur FROM " . MAIN_DB_PREFIX . "konst";
		$sql .= " WHERE nom IN ($inList)";
		$res = $this->db->query($sql);

		$konst = array();
		if ($res) {
			while ($obj = $this->db->fetch_object($res)) {
				// Normaliser le nom en majuscules (au cas où)
				$key = strtoupper(trim($obj->nom));
				$konst[$key] = $obj->valeur;
			}
			$this->db->free($res);
		}

		// --- Récupération du nombre réel d'utilisateurs Dolibarr ---
		$sql2 = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user";
		$result2 = $this->db->query($sql2);
		$num2 = $result2 ? $this->db->num_rows($result2) : 0;

		// --- Vérifications : présence minimale des valeurs ---
		$has_minimum = !empty($konst['KONTAVA_PLAN']) && isset($konst['USERS_QUOTA']);
		if (!$kontava_instance) {
			$mainContent='<div class="kontava" style="margin:10px; text-align:center; ">
				<span  class="kontava" >'.$langs->trans('FirstStepsDiscoverKontava').'</span><br>
				<a class="kontava" href="https://kontava.fr/" target="_blank" style="font-weight:bold; color:'.$bleu1_hex.'; ">kontava.fr</a>
			</div>';
			$iconePaiementAlert='';
			$iconeDiskQuotaAlert='';
			$iconeUserQuotaAlert='';
			$iconeUpdateAlert='';
		}
		elseif (!$has_minimum || $num2 == 0) {
			$mainContent='Votre table llx_konst est vide ou incomplète !</br>
				C\'est normal après une restauration de base de donnée par exemple, dans ce cas tout devrait rentrer dans l\'ordre d\'ici 24h.</br>
				Sinon, veuillez nous contacter à contact@kontava.fr';
			$iconePaiementAlert='';
			$iconeDiskQuotaAlert='';
			$iconeUserQuotaAlert='';
			$iconeUpdateAlert='';
		} else {
			// forfait : attendre un entier (1,2,3)
			$forfait = isset($konst['KONTAVA_PLAN']) ? (int)$konst['KONTAVA_PLAN'] : 0;

			// stockage : float (arrondir à 2 décimales affichage)
			$stockage_quota = isset($konst['STOCKAGE_QUOTA']) ? round((float)$konst['STOCKAGE_QUOTA'], 0) : 0.0;
			$stockage_reel_brut  = isset($konst['STOCKAGE_REEL'])  ? round((float)$konst['STOCKAGE_REEL'], 2)  : 0.0; //espace réellement occupé
			//On veut qu'à la création de l'instance le stockage affiché soit 0Mo. Or une instance vierge occupe environ 11Mo. On crée donc une 
			//valeur de stockage qui reste à 0 tant qu'inférieure à 20 puis qui monte un plus vite pour retrouver la valeur du quota
			$stockage_reel=round(max(0,($stockage_quota/($stockage_quota-20))*($stockage_reel_brut-20)),0);
			// users quota
			$users_quota = isset($konst['USERS_QUOTA']) ? (int)$konst['USERS_QUOTA'] : 1;

			// users réels
			$users_reel = $num2;

			// Paiement
			$prochain_paiement_montant = isset($konst['PROCHAIN_PAIEMENT_MONTANT']) ? $konst['PROCHAIN_PAIEMENT_MONTANT'] : null;
			$prochain_paiement_date = isset($konst['PROCHAIN_PAIEMENT_DATE']) ? $konst['PROCHAIN_PAIEMENT_DATE'] : null;
			$lien_paiement = isset($konst['LIEN_PAIEMENT']) ? $konst['LIEN_PAIEMENT'] : null;

			// compte_gratuit : gérer valeurs '0','1','false','true'
			$compte_gratuit_raw = isset($konst['COMPTE_GRATUIT']) ? strtolower(trim($konst['COMPTE_GRATUIT'])) : '0';
			$compte_gratuit = in_array($compte_gratuit_raw, array('1','true','t','y','yes'), true) ? 1 : 0;

			// --- Détermination du nom et de l'image du forfait ---
			if ($forfait == 1) {
				$nomForfait = $langs->trans("Plan") . " Jeune Pousse";
				$imgForfait = "../../custom/kontava/img/formulejeunepousse.svg";
			} elseif ($forfait == 2) {
				$nomForfait = $langs->trans("Plan") . " Arbuste";
				$imgForfait = "../../custom/kontava/img/formulearbuste.svg";
			} elseif ($forfait == 3) {
				$nomForfait = $langs->trans("Plan") . " Kombava";
				$imgForfait = "../../custom/kontava/img/formulearbre.svg";
			} else {
				$nomForfait = $langs->trans("Plan") . " " . $langs->trans("Inconnue");
				$imgForfait = "";
			}

			$graphique = '<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/echarts@5.6.0/dist/echarts.min.js" charset="utf-8"></script>';
			$graphique .= '<div id="main2" class="echart-instance" style="width: 98%;height:80%;"></div>';
			$graphique .= '<script type="text/javascript" charset="utf-8">';
			$graphique .= "
				
				var chartDom = document.getElementById('main2');
				var myChart = echarts.init(chartDom);
				chartDom.dataset.echartsInstance = 'main2'; // pour référence
				window.echartsInstances = window.echartsInstances || {};
				window.echartsInstances['main2'] = myChart;
				// var myChart = echarts.init(document.getElementById('main2'));

				var option = {
					tooltip: {
						trigger: 'item',
						formatter: function (params) {
						return params.name + ' : ' +params.value + ' Mo / ' + ".$stockage_quota." + ' Mo'; // Ajoute \"Mo\" dans l'info-bulle
						}
					},
					series: [
						{
						type: 'pie',
						radius: ['0%', '80%'], // Agrandit le camembert
						center: ['50%', '50%'], // Centre le graphique
						data: [
							{ value: ".($stockage_quota - $stockage_reel).", itemStyle: { color: '".$vert2_hex."' }, name: '".$langs->trans("Free")."' },
							{ value: ".$stockage_reel.", itemStyle: { color: '".(($stockage_reel/$stockage_quota>0.9)?$rouge_hex:$orange_hex)."' }, name: '".$langs->trans("Used")."' },
						],
						label: { 
							show: true, 
							position: 'inside', // Valeurs à l'intérieur
							fontSize: 10, // Taille du texte plus petite
							formatter: '{c}' // Affiche uniquement la valeur
						},
						emphasis: {
							itemStyle: {
							shadowBlur: 10,
							shadowOffsetX: 0,
							shadowColor: 'rgba(0,102,51, 0.5)'
							}
						}
						}
					]
					};
				myChart.setOption(option);
	
				</script>";
			
			// Récupérer le nom du client depuis la constante
			$sql_societe = "SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name = 'MAIN_INFO_SOCIETE_NOM' AND entity = 1 LIMIT 1";
			$res_societe = $this->db->query($sql_societe);
			$nom_client = '';
			if ($res_societe && $this->db->num_rows($res_societe) > 0) {
				$obj_societe = $this->db->fetch_object($res_societe);
				$nom_client = $obj_societe->value;
				$this->db->free($res_societe);
			}
			// Construire l’objet et le corps du mail
			$objet_mail = rawurlencode($nom_client . ' - '.$langs->transnoentitiesnoconv("DiskQuotaEmailSubject"));//transnoentitiesnoconv pour ne pas avoir les &amp;
			$corps_mail = rawurlencode($langs->transnoentitiesnoconv("DiskQuotaEmailBody").$nom_client);//transnoentitiesnoconv pour ne pas avoir les &amp; ou des 
			$accountData='';
			if ($adminUser == 1) {
				$accountData=(($compte_gratuit == 1)?'<p  class="kontava titre" >'.$langs->trans("FreeAccount").'</p>' : '<span  class="kontava" >'.$langs->trans("NextPayment")." : ".'<br>'.
						price($prochain_paiement_montant, 0, $langs, 0, -1, -1, "€").' '.$langs->trans("the").' '.'</span><span class="kontava" '.(($this->db->jdate($prochain_paiement_date) <= dol_now())?'style="color:'.$rouge_hex.';font-weight: bolder;"':'').'>'.dol_print_date($this->db->jdate($prochain_paiement_date), 'day', 'tzuserrel').'</span>'.
						'<p>'.(($lien_paiement != "")?'<a class="kontava" target="_blank" href="'.$lien_paiement.'">'.$langs->trans("LinkToPay").'</a></p>':'</p>'));
			}

			$mainContent=
				'<div style="flex:1; display:flex; justify-content: space-around;">'.
					'<div style="text-align:left;">'.
						'<p  class="kontava titre" >'.$nomForfait.'</p>'.
						'<img src='.$imgForfait.'>'.
						$accountData.
					'</div>'.
				'</div>'.
				'<div style="background:'.'none'.'; flex:1; text-align:center;">'.
					'<p  class="kontava titre" >'.$langs->trans("Users")." : ".'</p>'.
					'<p  class="user" '.(($users_reel > $users_quota)?'style="color:'.$rouge_hex.'"':'').' >'.$users_reel."/".$users_quota."&nbsp".'</p>'.
				'</div>'.
				'<div style="background:'.'none'.'; flex:1; text-align:center;">'.
					'<p class="kontava titre" >'.$langs->trans("DiskUsage")." : ".'</p>'.
					(($stockage_reel/$stockage_quota<1) ?
						$graphique
						:
						'<p  class="kontava" style="color:'.$rouge_hex.'" >'.$langs->trans("DiskQuotaExceededText").'<b>'.($stockage_reel - $stockage_quota).$langs->trans("DiskQuotaUnit").'</b>. '.$langs->trans("DiskQuotaWarning").'<a href="mailto:contact@kontava.fr?subject='.$objet_mail.'&body='.$corps_mail.'" target="_blank" rel="noopener">('.$langs->trans("ContactUs").')</a></p>'
					)
				.'</div>';

			$iconePaiementAlert = ($kontava_instance && $adminUser == 1 && $lien_paiement != "") ? '<span title="'.$langs->trans('LinkToPayNote').'" style="display:inline-block;width:18px;height:18px;line-height:18px;border-radius:50%;background:'.$orange_hex.';text-align:center;margin-left:6px;"><i class="fa fa-euro-sign" style="color:'.$couleurFont.';font-size:12px;"></i></span>' : '';
			$iconeDiskQuotaAlert = ($kontava_instance && $stockage_reel/$stockage_quota>1) ? 
				'<span title="'.$langs->trans('DiskQuotaExceeded').'" style="display:inline-block;width:18px;height:18px;line-height:18px;border-radius:50%;background:'.$rouge_hex.';text-align:center;margin-left:6px;"><i class="fa fa-database" style="color:'.$couleurFont.';font-size:12px;"></i></span>'
				: (($kontava_instance && $stockage_reel/$stockage_quota>0.9) ? 
					'<span title="'.$langs->trans('DiskQuota90p').'" style="display:inline-block;width:18px;height:18px;line-height:18px;border-radius:50%;background:'.$orange_hex.';text-align:center;margin-left:6px;"><i class="fa fa-database" style="color:'.$couleurFont.';font-size:12px;"></i></span>' 
					: '');
			$iconeUserQuotaAlert = ($kontava_instance && $users_reel > $users_quota) ? '<span title="'.$langs->trans('UserQuotaExceeded').'" style="display:inline-block;width:18px;height:18px;line-height:18px;border-radius:50%;background:'.$rouge_hex.';text-align:center;margin-left:6px;"><i class="fa fa-user" style="color:'.$couleurFont.';font-size:12px;"></i></span>' : '';
			$iconeUpdateAlert = '';
			if ($need_update) {
				$iconeUpdateAlert = '<span title="'.$langs->trans('UpdateAvailable').'" style="display:inline-block;width:18px;height:18px;line-height:18px;border-radius:50%;background:'.$orange_hex.';text-align:center;margin-left:6px;"><i class="fa fa-spinner fa-spin" style="color:'.$couleurFont.';font-size:12px;"></i></span>';
			}
		}
		include_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/class/kontava_function.php';
		$filename = basename(__FILE__); //On récupère le nom du fichier php
		$idWidget=getWidgetId($filename);//On récupère l'id du widget tel que défini dans la table llx_boxes_def

		$dataUnfold = getWidgetState($idWidget, $zone, $user->id); //on reçoit la $zone directement en entrée de la fonction showBox, par défaut 0 pour accueil et sinon 99 pour l'accueil du module Kontava
		$hiddenClass = $dataUnfold ? '' : 'hidden';

		$widget=
		'<div class="widget-content ' . $hiddenClass . '" style="background:rgba(0,102,51, 0.2); position:relative;">'.
			$mainContent.
			// Ajout du pied de widget avec version et bouton de mise à jour
			'<div style="Display: flex; Align-items:center; justify-content:space-between; width:100%; border-top:1px solid #ccc; text-align:right; font-size:12px; background:rgba(0,0,0,0); vertical-align: middle;">'
				.'<span id="kontava-module-version" style="margin-left:5px; color:#666; float:left;">'.$langs->trans('VersionModule').' '.dol_escape_htmltag($module_version).'</span>'
				// .($need_update ?
				// 	'<a id="kontava-update-btn" data-target-version="'.dol_escape_htmltag($latest_version).'" class="kontava">'
				// 	.$langs->trans('UpdateModule').' '.dol_escape_htmltag($latest_version).'</a>'
				// 	: '')
			.'</div>'
			.'<script>'.
    			// Si l'URL contient ?kontava_updated=1, on le retire de l'historique sans recharger
				'if (window.location.search.includes(\'kontava_updated=1\')) {
					window.history.replaceState({}, document.title, window.location.pathname);
				}
			</script>';

		if ($need_update) {
			// Message temporaire pendant l’opération (affiché côté navigateur)
			$widget .= '
			<div id="kontava-updating" style="padding:8px;font-size:13px;color:#333;">
				<i class="fa fa-spinner fa-spin"></i> Mise à jour automatique du module Kontava vers '.$latest_version.'...
			</div>
			<script>
				(async () => {
					try {
						const csrf = document.querySelector("meta[name=\'anti-csrf-newtoken\']")?.content;
						const response = await fetch("/custom/kontava/core/class/force_module_update.php", {
							method: "POST",
							headers: { "Content-Type": "application/x-www-form-urlencoded" },
							body: new URLSearchParams({ token: csrf })
						});
						const data = await response.json();
						if (data.success) {
							// Redirige avec un paramètre pour afficher le message Dolibarr après reload
							window.location.href = window.location.pathname + "?kontava_updated=1";
						} else {
							alert("Erreur de mise à jour Kontava : " + (data.message || "inconnue"));
						}
					} catch (e) {
						alert("Erreur réseau : " + e.message);
					}
				})();
			</script>';
		}
		// // Ajout du JS pour toast et gestion du bouton de mise à jour (avec token CSRF)
		// $widget .= "<script>
		// 	function showKontavaToast(msg, success) {
		// 		var toast = document.getElementById('kontava-toast');
		// 		toast.innerText = msg;
		// 		toast.style.background = success ? '#65953d' : '#c0392b';
		// 		toast.style.display = 'block';
		// 		toast.style.opacity = 1;
		// 		setTimeout(function(){ toast.style.opacity = 0; setTimeout(function(){toast.style.display='none';}, 400); }, 3500);
		// 	}
		// 	if (document.getElementById('kontava-update-btn')) {
		// 		document.getElementById('kontava-update-btn').onclick = function() {
		// 			var btn = this;
		// 			btn.disabled = true;
		// 			btn.style.opacity = 0.6;
		// 			let csrf_token = document.querySelector('meta[name=\"anti-csrf-newtoken\"]')?.getAttribute('content');
		// 			fetch('/custom/kontava/core/class/force_module_update.php', {
		// 				method: 'POST',
		// 				headers: {
		// 					'Content-Type': 'application/x-www-form-urlencoded'
		// 				},
		// 				//credentials: 'same-origin',
		// 				body: new URLSearchParams({
		// 					'token': csrf_token
		// 				})
		// 			})
		// 			.then(r => r.json())
		// 			.then(function(data) {
		// 				if (data.success) {
		// 					showKontavaToast('Mise à jour réussie vers la version '+btn.getAttribute('data-target-version'), true);
		// 					setTimeout(function(){ location.reload(); }, 1200); // Laisse le toast s’afficher avant reload
		// 				} else {
		// 					showKontavaToast('Échec de la mise à jour : '+(data.message||'Erreur inconnue'), false);
		// 					btn.disabled = false;
		// 					btn.style.opacity = 1;
		// 				}
		// 			})
		// 			.catch(function(e){
		// 				showKontavaToast('Erreur réseau lors de la mise à jour', false);
		// 				btn.disabled = false;
		// 				btn.style.opacity = 1;
		// 			});
		// 		};
		// 	}
		// </script>";

		$this->dragndropColor = $couleurFont;
		$this->backgroundColor = $vert1_hex;
		$this->noCloseBtn = ($kontava_instance)?1:0; // on n'affiche pas le bouton de fermeture si c'est une instance Kontava pour éviter que l'utilisateur ferme le widget par erreur


		//On crée le titre du widget
		$titreDuWidget =
			'<script src="'.DOL_URL_ROOT.'/custom/kontava/core/boxes/widget.js" defer></script>'.
			'<div class="widget-container" data-box-id="'.$idWidget.'" data-user-id="'.$user->id.'" data-zone="'.$zone.'" data-dol-url-root="'.DOL_URL_ROOT.'">'.
				'<div class="widget-title">'.
					'<a title="">
						<img src="'.DOL_URL_ROOT.'/custom/kontava/img/object_kontava.png" alt="Kontava" style="height: 12px;">
					</a>'.
					'&nbsp;'.
					'<a  title='.$langs->trans('WidgetNameMyAkontToolTip').' style="text-decoration:none; color : '.$couleurFont.';">'.
						$langs->trans('WidgetNameMyAkontShort').$iconePaiementAlert.$iconeDiskQuotaAlert.$iconeUserQuotaAlert.$iconeUpdateAlert.'&nbsp;&nbsp;&nbsp;'.
					'</a>
				</div>
				<i class="widget-arrow fa fa-chevron-down" style="color:'.$couleurFont.';"></i>
			</div>';

		$this->info_box_head = array(
			// Title text
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
		$this->info_box_contents = array(
			0 => array( // First line
				0 => array( // First Column
					'tr' => 'class="left"',
					'td' => 'style="padding:0px; "', // PYNC Utile sur les widget à une seule ligne pour enlever le padding et faire que 
					'text' => $widget,
					'asis' => 1	//  HTML properties of the TR element. Only available on the first column.
				)
			)
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
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput, $this->backgroundColor, $this->dragndropColor, $this->noCloseBtn); //PYNC on force le noCloseBtn à 1 pour ne pas avoir la croix de fermeture
	}
}

