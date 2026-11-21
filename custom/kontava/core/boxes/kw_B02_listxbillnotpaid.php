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
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';


/**
 * Class to manage the box
 *
 * Warning: for the box to be detected correctly by dolibarr,
 * the filename should be the lowercase classname
 */
class kw_B02_listxbillnotpaid extends ModeleBoxesKontava
{
	/**
	 * @var string Alphanumeric ID. Populated by the constructor.
	 */
	public $boxcode = "kw_B02_listxbillnotpaid";

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
		$langs->loadLangs(array("boxes", "kontava@kontava", "commercial", "propal"));

		parent::__construct($db, $param);

		$this->boxlabel = '&nbsp;'. $langs->transnoentitiesnoconv("WidgetNameListxbillnotpaid");

		$this->param = $param;

		//START_PYNC_MODIF
		$this->db = $db;
		// disable box for such cases
		if (!empty($conf->global->SOCIETE_DISABLE_CUSTOMERS)) {
			$this->enabled = 0; // disabled by this option
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
		global $user, $langs, $conf;

		$this->max = $max;
		include DOL_DOCUMENT_ROOT.'/custom/kontava/core/boxes/kontava_color.php';


		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
		include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
 		include DOL_DOCUMENT_ROOT.'/custom/kontava/core/boxes/kontava_color.php';
		$facturestatic = new Facture($this->db);
		$societestatic = new Societe($this->db);
		// $form = new Form($db);
		$formfile = new FormFile($this->db);
		

		if ($user->rights->facture->lire) {
			$sql = "SELECT s.rowid as socid, s.nom as name, s.name_alias";
			$sql .= ", s.code_client, s.client";
			if (!empty($conf->global->MAIN_COMPANY_PERENTITY_SHARED)) {
				$sql .= ", spe.accountancy_code_customer as code_compta";
			} else {
				$sql .= ", s.code_compta";
			}
			$sql .= ", s.logo, s.email, s.entity";
			$sql .= ", s.tva_intra, s.siren as idprof1, s.siret as idprof2, s.ape as idprof3, s.idprof4, s.idprof5, s.idprof6";
			$sql .= ", f.ref, f.date_lim_reglement as datelimite";
			$sql .= ", f.type";
			$sql .= ", f.datef as date";
			$sql .= ", f.total_ht";
			$sql .= ", f.total_tva";
			$sql .= ", f.total_ttc";
			$sql .= ", f.paye, f.fk_statut as status, f.rowid as facid";
			$sql .= ", sum(pf.amount) as am";
			$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
			if (!empty($conf->global->MAIN_COMPANY_PERENTITY_SHARED)) {
				$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe_perentity as spe ON spe.fk_soc = s.rowid AND spe.entity = " . ((int) $conf->entity);
			}
			if (empty($user->rights->societe->client->voir) && !$user->socid) {
				$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			}
			$sql .= ", ".MAIN_DB_PREFIX."facture as f";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON f.rowid=pf.fk_facture ";
			$sql .= " WHERE f.fk_soc = s.rowid";
			$sql .= " AND f.entity IN (".getEntity('invoice').")";
			$sql .= " AND f.paye = 0";
			$sql .= " AND fk_statut = 1";
			if (empty($user->rights->societe->client->voir) && !$user->socid) {
				$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($user->socid) {
				$sql .= " AND s.rowid = ".((int) $user->socid);
			}
			$sql .= " GROUP BY s.rowid, s.nom, s.name_alias, s.code_client, s.client, s.logo, s.email, s.entity, s.tva_intra, s.siren, s.siret, s.ape, s.idprof4, s.idprof5, s.idprof6,";
			if (!empty($conf->global->MAIN_COMPANY_PERENTITY_SHARED)) {
				$sql .= " spe.accountancy_code_customer as code_compta,";
			} else {
				$sql .= " s.code_compta,";
			}
			$sql .= " f.ref, f.date_lim_reglement,";
			$sql .= " f.type, f.datef, f.total_ht, f.total_tva, f.total_ttc, f.paye, f.fk_statut, f.rowid";
			//$sql.= " ORDER BY f.datef DESC, f.ref DESC ";
			// $sql .= " ORDER BY datelimite ASC, f.ref ASC ";
			$sql .= " ORDER BY datelimite DESC, f.ref DESC ";
			//$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$now = dol_now();

				include_once DOL_DOCUMENT_ROOT."/custom/kontava/core/class/functions.lib.php"; //permet d'utiliser la fonction startSimpleTable_kontava qui affiche un titre suivi d'un badge de nombre
				include_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/class/kontava_function.php';
				$filename = basename(__FILE__); //On récupère le nom du fichier php
				$idWidget = getWidgetId($filename);//On récupère l'id du widget tel que défini dans la table llx_boxes_def

				$dataUnfold = getWidgetState($idWidget, $zone, $user->id); //on reçoit la $zone directement en entrée de la fonction showBox, par défaut 0 pour accueil et sinon 99 pour l'accueil du module Kontava
				$hiddenClass = $dataUnfold ? '' : 'hidden';

				$couleurFont = '#FFF' ;
				$this->dragndropColor = $couleurFont;
				$this->backgroundColor = $rouge_hex;
				
				$titreDuWidget =
					'<script>window.DOL_URL_ROOT = "'.DOL_URL_ROOT.'";</script>'.
					'<script src="'.DOL_URL_ROOT.'/custom/kontava/core/boxes/widget.js" defer></script>'.
					'<div class="widget-container" data-box-id="'.$idWidget.'" data-user-id="'.$user->id.'" data-zone="'.$zone.'" data-dol-url-root="'.DOL_URL_ROOT.'">'.//AUTOMATISER data-box-id et data-user-id
						'<div class="widget-title">'.
							'<img src="'.DOL_URL_ROOT.'/custom/kontava/img/object_kontava.png" alt="Kontava" style="height: 12px;">'.
							"&nbsp;"."<a  title=".$langs->trans("WidgetNameListxbillnotpaidToollTip")." style='text-decoration:none; color: ".$couleurFont."'>".startSimpleTable_kontava("WidgetNameListxbillnotpaidShort", "compta/facture/list.php", "search_status=1", -1, $num)."</a>".
						'</div>'.
						'<i class="widget-arrow fa fa-chevron-down" style="color:'.$couleurFont.';"></i>'.
					'</div>';
				
				$this->info_box_head = array(
					'text' => $titreDuWidget,
					'asis' => 1,
				);


				$line = 0;
				if ($num>0) {
					while ($line < min($max,$num)) {
						$objp = $this->db->fetch_object($result);

						$datelimite = $this->db->jdate($objp->datelimite);

						$facturestatic->id = $objp->facid;
						$facturestatic->ref = $objp->ref;
						$facturestatic->type = $objp->type;
						$facturestatic->total_ht = $objp->total_ht;
						$facturestatic->total_tva = $objp->total_tva;
						$facturestatic->total_ttc = $objp->total_ttc;
						$facturestatic->statut = $objp->status;
						$facturestatic->status = $objp->status;
						$facturestatic->date = $this->db->jdate($objp->date);
						$facturestatic->date_lim_reglement = $this->db->jdate($objp->datelimite);
						$facturestatic->alreadypaid = $objp->paye;

						$societestatic->id = $objp->socid;
						$societestatic->name = $objp->name;
						//$societestatic->name_alias = $objp->name_alias;
						$societestatic->code_client = $objp->code_client;
						$societestatic->code_compta = $objp->code_compta;
						$societestatic->client = $objp->client;
						$societestatic->logo = $objp->logo;
						$societestatic->email = $objp->email;
						$societestatic->entity = $objp->entity;
						$societestatic->tva_intra = $objp->tva_intra;
						$societestatic->idprof1 = $objp->idprof1;
						$societestatic->idprof2 = $objp->idprof2;
						$societestatic->idprof3 = $objp->idprof3;
						$societestatic->idprof4 = $objp->idprof4;
						$societestatic->idprof5 = $objp->idprof5;
						$societestatic->idprof6 = $objp->idprof6;

						$filename = dol_sanitizeFileName($objp->ref);
						$filedir = $conf->facture->multidir_output[$objp->entity].'/'.dol_sanitizeFileName($objp->ref);
						$download = $formfile->getDocumentsLink('facture', $filename, $filedir);
						
						$late = '';
						if ($facturestatic->hasDelay()) {
							$late = img_warning(sprintf($datelimite, dol_print_date($datelimite, 'day', 'tzuserrel')));
						}
						
						// $this->info_box_contents[$line][] = array(
						// 	'tr' => '<div class="widget-content' . $hiddenClass . '"/>',//PYNC ADD
						// );
						
						$this->info_box_contents[$line][] = array(
							'tr' => 'class="oddeven widget-tr-content ' . $hiddenClass . ' "',//PYNC ADD
							'td' => 'class="nowraponall"',
							'text' => $facturestatic->getNomUrl(1),
							'text2'=> $late.$download,
							'asis' => 1,
							'asis2' => 1,
						);
						
						$this->info_box_contents[$line][] = array(
							'td' => 'class="tdoverflowmax150 maxwidth150onsmartphone"',
							'text' => $societestatic->getNomUrl(1, '', 44),
							'asis' => 1,
						);
						
						$this->info_box_contents[$line][] = array(
							'td' => 'class="nowraponall right amount"',
							'text' => price($objp->total_ht, 0, $langs, 0, -1, -1, $conf->currency),
						);
						
						$this->info_box_contents[$line][] = array(
							'td' => 'class="center nowraponall" title="'.dol_escape_htmltag($langs->trans("DateDue").': '.dol_print_date($datelimite, 'day', 'tzuserrel')).'"',
							'text' => dol_print_date($datelimite, 'day', 'tzuserrel'),
						);
						
						$this->info_box_contents[$line][] = array(
							'td' => 'class="right" width="18"',
							'text' => $facturestatic->LibStatut($objp->paye, $objp->status, 3, $objp->am),
						);
						
						
						$line++;
					}
				}
				
				if ($num == 0) {
					$this->info_box_contents[$line][0] = array(
						'tr' => 'class="oddeven widget-tr-content ' . $hiddenClass . ' "',//PYNC ADD
						'td' => 'class="center"',
						'text'=>$langs->trans("NoRecordedInvoices"),
					);
				}
				$othernb = $num-$max;
				if ($othernb > 0) {	
					$this->info_box_contents[][0] = array(
						'td' => 'class="nowrap" colspan="5"',
						'text' => '<a href="'.DOL_URL_ROOT.'/compta/facture/list.php?search_status=1"'.'><span class="opacitymedium">'.$langs->trans("More").'... ('.$othernb.')</span></a>',
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