<style><?php 
	include 'kontava-style.css';
	?></style>
<?php
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
//On Inclut une classe de widget modifiée pour afficher correctement la petite flêche qui tourne
include_once DOL_DOCUMENT_ROOT."/custom/kontava/core/class/modules_boxes.php";


/**
 * Class to manage the box
 *
 * Warning: for the box to be detected correctly by dolibarr,
 * the filename should be the lowercase classname
 */
class kw_B11_listxdraftorder extends ModeleBoxesKontava
{
	/**
	 * @var string Alphanumeric ID. Populated by the constructor.
	 */
	public $boxcode = "kw_B11_listxdraftorder";

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
	public $depends = array('kontava','commande');//PYNC : permet de n'activer le widget que si le module de commande est activé

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
		$langs->loadLangs(array("boxes", "kontava@kontava", "commercial", "propal", "orders"));

		parent::__construct($db, $param);

		$this->boxlabel = '&nbsp;'. $langs->transnoentitiesnoconv("WidgetNameListxDraftOrder");

		$this->param = $param;

		//START_PYNC_MODIF
		$this->db = $db;
		// disable box for such cases
		if (!empty($conf->global->SOCIETE_DISABLE_CUSTOMERS)) {
			$this->enabled = 0; // disabled by this option
		}

		$this->hidden = !($user->hasRight('order', 'read'));//PYNC changement de syntaxe avec Dolibarr20
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
		global $user, $langs, $conf;

		$this->max = $max;
		include DOL_DOCUMENT_ROOT.'/custom/kontava/core/boxes/kontava_color.php';


		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
		// include_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
		include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		include DOL_DOCUMENT_ROOT.'/custom/kontava/core/boxes/kontava_color.php';

		// $propalstatic = new Propal($this->db);
		$societestatic = new Societe($this->db);
		$commandestatic = new Commande($this->db);
		// $form = new Form($db);
		$formfile = new FormFile($this->db);
		

		if ($user->rights->commande->lire) {
			$sql = "SELECT s.rowid as socid, s.nom as name, s.name_alias";
			$sql .= ", s.code_client, s.code_compta, s.client";
			$sql .= ", s.logo, s.email, s.entity";
			$sql .= ", c.rowid, c.ref, c.fk_soc, c.fk_statut as status, c.fk_user_valid, c.fk_user_cloture, c.facture, c.date_commande as dc, c.total_ht, c.total_tva, c.total_ttc";
			$sql .= " FROM ".MAIN_DB_PREFIX."commande as c, ".MAIN_DB_PREFIX."societe as s";
			
			$sql .= " WHERE c.fk_soc = s.rowid";
			// $sql .= " AND p.entity IN (".getEntity('propal').")";
			$sql .= " AND (c.fk_statut = 0)";//IN (0,1,2) OR (c.fk_statut = 3 AND c.facture = 0))"; // AND c.facture = 0    //0 = DRAFT, 1=VALIDEE, 2=EN EXPEDITION, 3=CLOS
			$sql .= " ORDER BY c.date_commande DESC ";
			// } else {
				// $sql .= " ORDER BY p.tms DESC, p.ref DESC ";
			// }
			// $sql .= $this->db->plimit($max, 0);
			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				// $now = dol_now();

				include_once DOL_DOCUMENT_ROOT."/custom/kontava/core/class/functions.lib.php"; //permet d'utiliser la fonction startSimpleTable qui affiche un titre suivi d'un badge de nombre
				include_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/class/kontava_function.php';
				$filename = basename(__FILE__); //On récupère le nom du fichier php
				$idWidget=getWidgetId($filename);//On récupère l'id du widget tel que défini dans la table llx_boxes_def

				$dataUnfold = getWidgetState($idWidget, $zone, $user->id); //on reçoit la $zone directement en entrée de la fonction showBox, par défaut 0 pour accueil et sinon 99 pour l'accueil du module Kontava
				$hiddenClass = $dataUnfold ? '' : 'hidden';

				$couleurFont = '#FFF' ;
				$this->dragndropColor = $couleurFont;
				$this->backgroundColor = $orange_hex;
		
				$titreDuWidget =
					'<script>window.DOL_URL_ROOT = "'.DOL_URL_ROOT.'";</script>'.
					'<script src="'.DOL_URL_ROOT.'/custom/kontava/core/boxes/widget.js" defer></script>'.
					'<div class="widget-container" data-box-id="'.$idWidget.'" data-user-id="'.$user->id.'" data-zone="'.$zone.'" data-dol-url-root="'.DOL_URL_ROOT.'">'.//AUTOMATISER data-box-id et data-user-id
						'<div class="widget-title">'.
							'<img src="'.DOL_URL_ROOT.'/custom/kontava/img/object_kontava.png" alt="Kontava" style="height: 12px;">'.
							"&nbsp;"."<a  title=".$langs->trans("WidgetNameListxDraftOrderToolTip")." style='text-decoration:none;color: ".$couleurFont." '>".startSimpleTable_kontava("WidgetNameListxDraftOrderShort", "commande/list.php", "search_status=0", 4, $num)."</a>".
						'</div>'.
						'<i class="widget-arrow fa fa-chevron-down" style="color:'.$couleurFont.';"></i>'.
					'</div>';
				$this->info_box_head = array(
					'text' => $titreDuWidget,
					'asis' => 1,
				);

				$line = 0;
				while ($line < min($max,$num)) {
					$objc = $this->db->fetch_object($result);
					$date = $this->db->jdate($objc->dc);
					// $datec = $this->db->jdate($objc->datec);
					// $datem = $this->db->jdate($objc->tms);
					// $dateterm = $this->db->jdate($objc->fin_validite);
					// $dateclose = $this->db->jdate($objc->date_cloture);
					
					$commandestatic->id = $objc->rowid;
					$commandestatic->ref = $objc->ref;
					$commandestatic->total_ht = $objc->total_ht;
					$commandestatic->total_tva = $objc->total_tva;
					$commandestatic->total_ttc = $objc->total_ttc;
					$commandestatic->statut = $objc->status;//($objc->status == 0 )?1:NULL;
					$commandestatic->billed = $objc->facture;
					$commandestatic->date = $date;
					$commandestatic->brouillon = $objc->total_ttc;
					$commandestatic->socid = $objc->socid;
					$commandestatic->ref_client = $objc->name;
					$commandestatic->user_valid = $objc->fk_user_valid;
					
					$societestatic->id = $objc->socid;
					$societestatic->name = $objc->name;
					//$societestatic->name_alias = $objc->name_alias;
					$societestatic->code_client = $objc->code_client;
					$societestatic->code_compta = $objc->code_compta;
					$societestatic->client = $objc->client;
					$societestatic->logo = $objc->logo;
					$societestatic->email = $objc->email;
					$societestatic->entity = $objc->entity;
					
					$filename = dol_sanitizeFileName($objc->ref);
					$filedir = $conf->commande->multidir_output[$objc->entity].'/'.dol_sanitizeFileName($objc->ref);
					$download = $formfile->getDocumentsLink('commande', $filename, $filedir);
					
					$toClose = '';
					if (($objc->status == 0 || $objc->status == 1 ) && $objc->facture == 1) {
						$toClose = img_warning($langs->trans("ToClose"));
					}
					$toBill = '';
					if ($objc->status == 3 && $objc->facture == 0) {
						$toBill = img_warning($langs->trans("ToBill"));
					}
					$this->info_box_contents[$line][] = array(
						'tr' => 'class="oddeven widget-tr-content ' . $hiddenClass . ' "',//PYNC ADD
						'td' => 'class="nowraponall"',
						'text' => $commandestatic->getNomUrl(1),
						'text2'=> $toClose.$toBill.$download,
						'asis' => 1,
						'asis2' => 1,
					);
					
					$this->info_box_contents[$line][] = array(
						'td' => 'class="tdoverflowmax150 maxwidth150onsmartphone"',
						'text' => $societestatic->getNomUrl(1),
						'asis' => 1,
					);
					
					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall right amount"',
						'text' => price($objc->total_ht, 0, $langs, 0, -1, -1, $conf->currency),
					);
					
					$this->info_box_contents[$line][] = array(
						'td' => 'class="center nowraponall" title="'.dol_escape_htmltag($langs->trans("OrderDate").': '.dol_print_date($date, 'dayhour', 'tzuserrel')).'"',
						'text' => dol_print_date($date, 'day', 'tzuserrel'),
					);
					
					$this->info_box_contents[$line][] = array(
						'td' => 'class="right" width="18"',
						'text' => $commandestatic->LibStatut($objc->status, $objc->facture, 2), //0=Long label, 1=Short label, 2=Picto + Short label, 3=Picto, 4=Picto + Long label, 5=Short label + Picto, 6=Long label + Picto
					);
					
					
					$line++;
				}
				
				if ($num == 0) {
					$this->info_box_contents[$line][0] = array(
						'tr' => 'class="oddeven widget-tr-content ' . $hiddenClass . ' "',//PYNC ADD
						'td' => 'class="center"',
						'text'=>$langs->trans("NoRecordedOrder"),
					);
				}
				$othernb = $num-$max;
				if ($othernb > 0) {	
					$this->info_box_contents[][0] = array(
						'tr' => 'class="oddeven widget-tr-content ' . $hiddenClass . ' "',//PYNC ADD
						'td' => 'class="nowrap" colspan="5"',
						'text' => '<a href="'.DOL_URL_ROOT.'/commande/list.php?search_status=0"'.'><span class="opacitymedium">'.$langs->trans("More").'... ('.$othernb.')</span></a>',
						'asis' => 1,
					);
				}
				
				$this->db->free($result);
			} else {
				$this->info_box_contents[0][0] = array(
					'td' => '',
					'maxlength'=>500,
					'text' => ($this->db->error().' sql='.$sql),
				);
			}
		} else {
			$this->info_box_contents[0][0] = array(
				'td' => 'class="nohover opacitymedium left"',
				'text' => $langs->trans("ReadPermissionNotAllowed")
			);
		}

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
?>