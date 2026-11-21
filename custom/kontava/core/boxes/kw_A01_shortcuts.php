<style>
	<?php 
		include 'kontava-style.css';
	?>
</style>

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
class kw_A01_shortcuts extends ModeleBoxesKontava //PYNC Attention on crée un objet de notre classe de widget modifée
{
	/**
	 * @var string Alphanumeric ID. Populated by the constructor.
	 */
	public $boxcode = "kw_A01_shortcuts";

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
		$langs->loadLangs(array("boxes", "kontava@kontava"));

		parent::__construct($db, $param);

		$this->boxlabel = '&nbsp;'. $langs->transnoentitiesnoconv("WidgetNameShortcut");

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
		global $user, $langs, $conf;// PYNC_MODIF was $langs;
		
		// Use configuration value for max lines count
		$this->max = $max;
		include DOL_DOCUMENT_ROOT.'/custom/kontava/core/boxes/kontava_color.php';
		include_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$productstatic = new Product($this->db);


		//START_PYNC_MODIF
		//Calcul du nombre de tiers (clients et prospects)
		
		$numTiers =0;
		include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$thirdpartystatic = new Societe($this->db);
		
		if ($user->rights->societe->lire) {
			$sql = "SELECT s.rowid as socid";
			$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
			if (empty($user->rights->societe->client->voir) && !$user->socid) {
				$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			}
			$sql .= " WHERE s.client IN (1, 2, 3)";	//PYNC_MODIF 1=client, 2=prospect, 3=les deux
			$sql .= " AND s.entity IN (".getEntity('societe').")";
			if (empty($user->rights->societe->client->voir) && !$user->socid) {
				$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($user->socid) {
				$sql .= " AND s.rowid = $user->socid";
			}
			
			dol_syslog(get_class($this)."::loadBox", LOG_DEBUG);
			$result = $this->db->query($sql);
			if ($result) {
				$numTiers = $this->db->num_rows($result);
				$this->db->free($result);
			} else {
				$this->info_box_contents[0][0] = array(
					'td' => '',
					'maxlength'=>500,
					'text' => ($this->db->error().' sql='.$sql)
				);
			}
		} else {
			$this->info_box_contents[0][0] = array(
				'td' => 'class="nohover left"',
				'text' => '<span class="opacitymedium">'.$langs->trans("ReadPermissionNotAllowed").'</span>'
			);
		}
		//Calcul du nombre de produits

		$numProduct =0;

		if ($user->rights->produit->lire) {
			$sql = "SELECT p.rowid, p.fk_product_type";
			$sql .= " FROM ".MAIN_DB_PREFIX."product as p";
			$sql .= ' WHERE p.entity IN ('.getEntity($productstatic->element).')';
			$sql .= ' AND p.fk_product_type = 0';
			// if (empty($user->rights->produit->lire)) {
				// $sql .= ' AND p.fk_product_type != 0';
			// }
			// if (empty($user->rights->service->lire)) {
			// 	$sql .= ' AND p.fk_product_type != 1';
			// }
			$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$numProduct = $this->db->num_rows($result);
				
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

		//Calcul du nombre de services

		$numService =0;

		if ($user->rights->produit->lire) {
			$sql = "SELECT p.rowid, p.fk_product_type";
			$sql .= " FROM ".MAIN_DB_PREFIX."product as p";
			$sql .= ' WHERE p.entity IN ('.getEntity($productstatic->element).')';
			$sql .= ' AND p.fk_product_type = 1';
			// if (empty($user->rights->service->lire)) {
				// $sql .= ' AND p.fk_product_type != 1';
			// }
			$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$numService = $this->db->num_rows($result);
				
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

		//Calcul du nombre de Devis

		$numDevis =0;
		include_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
		// $propalstatic = new Propal($this->db);
		// $societestatic = new Societe($this->db);

		if ($user->rights->propale->lire) {
			$sql = "SELECT s.rowid as socid";
			$sql .= ", p.rowid";
			$sql .= " FROM ".MAIN_DB_PREFIX."propal as p, ".MAIN_DB_PREFIX."societe as s";
			if (empty($user->rights->societe->client->voir) && !$user->socid) {
				$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			}
			$sql .= " WHERE p.fk_soc = s.rowid";
			$sql .= " AND p.entity IN (".getEntity('propal').")";
			if (empty($user->rights->societe->client->voir) && !$user->socid) {
				$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($user->socid) {
				$sql .= " AND s.rowid = ".((int) $user->socid);
			}
			$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$numDevis = $this->db->num_rows($result);

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

		//Calcul du nombre de Factures

		$numFacture =0;
		include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		// include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

		// $facturestatic = new Facture($this->db);
		// $societestatic = new Societe($this->db);

		if ($user->rights->facture->lire) {
			$sql = "SELECT f.rowid as facid";
			$sql .= ", s.rowid as socid";
			$sql .= " FROM ".MAIN_DB_PREFIX."facture as f, ".MAIN_DB_PREFIX."societe as s";
			if (empty($user->rights->societe->client->voir) && !$user->socid) {
				$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			}
			$sql .= " WHERE f.fk_soc = s.rowid";
			$sql .= " AND f.entity IN (".getEntity('invoice').")";
			if (empty($user->rights->societe->client->voir) && !$user->socid) {
				$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($user->socid) {
				$sql .= " AND s.rowid = ".((int) $user->socid);
			}
			$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$numFacture = $this->db->num_rows($result);

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
		
		//Def des boutons :
		// $boutonProduct = //La première condition n'est jamais vraie et je ne sais pas pourquoi ! 
			// (($numProduct>4)?
			// '<a class="kontava" href="/product/card.php?action=create&type=0&mainmenu=kontava&leftmenu=&canvas=product@kontava&country_id=1">'.'<i class="fas fa-cube", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("Product")."&nbsp".$numProduct.'<i class="fas fa-plus-circle"></i>'.'</a>'
			// :	($numProduct>0)?
			// '<a class="kontava" href="/product/card.php?action=create&type=0&mainmenu=kontava&leftmenu=&canvas=product@kontava&country_id=1">'.'<i class="fas fa-cube", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherProduct").$numProduct.'</a>'	
			// :'<a class="kontava" href="/product/card.php?action=create&type=0&mainmenu=kontava&leftmenu=&canvas=product@kontava&country_id=1">'.'<i class="fas fa-cube", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstProduct").$numProduct.'</a>'
			// );
		if ($numProduct>4)
		{
			$boutonProduct ='<a class="kontava" href="/product/card.php?action=create&type=0&mainmenu=kontava&leftmenu=&canvas=product@kontava&country_id=1">'.'<i class="fas fa-cube", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("Product")."&nbsp".'<i class="fas fa-plus-circle"></i>'.'</a>';
		}
		elseif ($numProduct>0)
		{
			$boutonProduct ='<a class="kontava" href="/product/card.php?action=create&type=0&mainmenu=kontava&leftmenu=&canvas=product@kontava&country_id=1">'.'<i class="fas fa-cube", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherProduct").'</a>';
		}
		else
		{
			$boutonProduct ='<a class="kontava" href="/product/card.php?action=create&type=0&mainmenu=kontava&leftmenu=&canvas=product@kontava&country_id=1">'.'<i class="fas fa-cube", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstProduct").'</a>';
		}
		// $boutonService = //La première condition n'est jamais vraie et je ne sais pas pourquoi !
		// 	($numService<1?
		// 	'<a class="kontava" href="/societe/card.php?action=create&type=1&mainmenu=kontava&leftmenu=&canvas=product@kontava">'.'<i class="fas fa-concierge-bell", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstService").'</a>'
		// 	:	$numService<5?
		// 		'<a class="kontava" href="/societe/card.php?action=create&type=1&mainmenu=kontava&leftmenu=&canvas=product@kontava">'.'<i class="fas fa-concierge-bell", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherService").'</a>'
		// 		:'<a class="kontava" href="/societe/card.php?action=create&type=1&mainmenu=kontava&leftmenu=&canvas=product@kontava">'.'<i class="fas fa-concierge-bell", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("Service")."&nbsp".'<i class="fas fa-plus-circle"></i>'.'</a>'
		// 	);
		if ($numService>4)
		{
			$boutonService ='<a class="kontava" href="/product/card.php?action=create&type=1&mainmenu=kontava&leftmenu=&canvas=product@kontava">'.'<i class="fas fa-concierge-bell", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("Service")."&nbsp".'<i class="fas fa-plus-circle"></i>'.'</a>';
		}
		elseif ($numService>0)
		{
			$boutonService ='<a class="kontava" href="/product/card.php?action=create&type=1&mainmenu=kontava&leftmenu=&canvas=product@kontava">'.'<i class="fas fa-concierge-bell", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherService").'</a>';
		}
		else
		{
			$boutonService ='<a class="kontava" href="/product/card.php?action=create&type=1&mainmenu=kontava&leftmenu=&canvas=product@kontava">'.'<i class="fas fa-concierge-bell", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstService").'</a>';
		}
		
		// $boutonTiers =  //La première condition n'est jamais vraie et je ne sais pas pourquoi !
		// 	($numTiers<1?
		// 	'<a class="kontava" href="/societe/card.php?action=create&mainmenu=kontava&canvas=societe@kontava">'.'<i class="fas fa-building", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstThirdParty").'</a>'
		// 	:	$numTiers<5?
		// 		'<a class="kontava" href="/societe/card.php?action=create&mainmenu=kontava&canvas=societe@kontava">'.'<i class="fas fa-building", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherThirdParty").'</a>'
		// 		:'<a class="kontava" href="/societe/card.php?action=create&mainmenu=kontava&canvas=societe@kontava">'.'<i class="fas fa-building", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("ThirdParty")."&nbsp".'<i class="fas fa-plus-circle"></i>'.'</a>'
		// 	);
		if ($numTiers>4)
		{
			$boutonTiers ='<a class="kontava" href="/societe/card.php?action=create&mainmenu=kontava&canvas=societe@kontava">'.'<i class="fas fa-building", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("ThirdParty")."&nbsp".'<i class="fas fa-plus-circle"></i>'.'</a>';
		}
		elseif ($numTiers>0)
		{
			$boutonTiers ='<a class="kontava" href="/societe/card.php?action=create&mainmenu=kontava&canvas=societe@kontava">'.'<i class="fas fa-building", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherThirdParty").'</a>';
		}
		else
		{
			$boutonTiers ='<a class="kontava" href="/societe/card.php?action=create&mainmenu=kontava&canvas=societe@kontava">'.'<i class="fas fa-building", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstThirdParty").'</a>';
		}

		// $boutonDevis =  //La première condition n'est jamais vraie et je ne sais pas pourquoi !
		// 	($numDevis<1?
		// 	'<a class="kontava" href="/comm/propal/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-signature", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstQuotation").'</a>'
		// 	:	$numDevis<5?
		// 		'<a class="kontava" href="/comm/propal/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-signature", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherQuotation").'</a>'
		// 		:'<a class="kontava" href="/comm/propal/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-signature", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("Quotation")."&nbsp".'<i class="fas fa-plus-circle"></i>'.'</a>'
		// 	);
		if ($numDevis>4)
		{
			$boutonDevis ='<a class="kontava" href="/comm/propal/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-signature", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("Quotation")."&nbsp".'<i class="fas fa-plus-circle"></i>'.'</a>';
		}
		elseif ($numDevis>0)
		{
			$boutonDevis ='<a class="kontava" href="/comm/propal/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-signature", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherQuotation").'</a>';
		}
		else
		{
			$boutonDevis ='<a class="kontava" href="/comm/propal/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-signature", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstQuotation").'</a>';
		}
			
		// $boutonFacture =  //La première condition n'est jamais vraie et je ne sais pas pourquoi !
		// 	($numFacture<1?
		// 	'<a class="kontava" href="/compta/facture/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-invoice-dollar", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstInvoice").'</a>'
		// 	:	$numFacture<5?
		// 		'<a class="kontava" href="/compta/facture/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-invoice-dollar", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherInvoice").'</a>'
		// 		:'<a class="kontava" href="/compta/facture/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-invoice-dollar", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("Invoice")."&nbsp".'<i class="fas fa-plus-circle"></i>'.'</a>'
		// 	);
		if ($numFacture>4)
		{
			$boutonFacture ='<a class="kontava" href="/compta/facture/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-invoice-dollar", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("Invoice")."&nbsp".'<i class="fas fa-plus-circle"></i>'.'</a>';
		}
		elseif ($numFacture>0)
		{
			$boutonFacture ='<a class="kontava" href="/compta/facture/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-invoice-dollar", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateAnotherInvoice").'</a>';
		}
		else
		{
			$boutonFacture ='<a class="kontava" href="/compta/facture/card.php?action=create&mainmenu=kontava">'.'<i class="fas fa-file-invoice-dollar", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("CreateFirstInvoice").'</a>';
		}
		
		include_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/class/kontava_function.php';
		$filename = basename(__FILE__); //On récupère le nom du fichier php
		$idWidget=getWidgetId($filename);//On récupère l'id du widget tel que défini dans la table llx_boxes_def

		$dataUnfold = getWidgetState($idWidget, $zone, $user->id); //on reçoit la $zone directement en entrée de la fonction showBox, par défaut 0 pour accueil et sinon 99 pour l'accueil du module Kontava
		$hiddenClass = $dataUnfold ? '' : 'hidden';

		$couleurFont = '#FFF' ;
		$this->dragndropColor = $couleurFont;
		$this->backgroundColor = $vert1_hex;
		$titreDuWidget =
			'<script>window.DOL_URL_ROOT = "'.DOL_URL_ROOT.'";</script>'.
			'<script src="'.DOL_URL_ROOT.'/custom/kontava/core/boxes/widget.js" defer></script>'.
			'<div class="widget-container" data-box-id="'.$idWidget.'" data-user-id="'.$user->id.'" data-zone="'.$zone.'" data-dol-url-root="'.DOL_URL_ROOT.'">'.//AUTOMATISER data-box-id et data-user-id
				'<div class="widget-title">'.
					'<img src="'.DOL_URL_ROOT.'/custom/kontava/img/object_kontava.png" alt="Kontava" style="height: 12px;">'.
					'&nbsp;'.
					'<a  title='.$langs->trans('WidgetNameShortcutToolTip').' style="text-decoration:none; color : '.$couleurFont.'; flex-grow: 1;">'.
						$langs->trans('WidgetNameShortcutShort').'&nbsp;&nbsp;&nbsp;
					</a>'.
				'</div>'.
				'<i class="widget-arrow fa fa-chevron-down" style="color:'.$couleurFont.';"></i>'.
				'</div>';

		//END_PYNC_MODIF

		$widget='<div class="widget-content ' . $hiddenClass . '" style="background:rgba(0,102,51, 0.2);">'.
					$boutonProduct.
					$boutonService.'<br/>'.
					$boutonTiers.
					$boutonDevis.
					$boutonFacture.
				'</div>';
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
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput, $this->backgroundColor, $this->dragndropColor, $noCloseBtn = 0);
	}
}
