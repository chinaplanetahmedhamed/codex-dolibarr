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
class kw_firststeps extends ModeleBoxesKontava
{
	/**
	 * @var string Alphanumeric ID. Populated by the constructor.
	 */
	public $boxcode = "kw_firststeps";

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

		$this->boxlabel = '&nbsp;'. $langs->transnoentitiesnoconv("WidgetNameFirstSteps");

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
		$titreDuWidget =
			'<script>window.DOL_URL_ROOT = "'.DOL_URL_ROOT.'";</script>'.
			'<script src="'.DOL_URL_ROOT.'/custom/kontava/core/boxes/widget.js" defer></script>'.
			'<div class="widget-container" data-box-id="'.$idWidget.'" data-user-id="'.$user->id.'" data-zone="'.$zone.'" data-dol-url-root="'.DOL_URL_ROOT.'">'.//AUTOMATISER data-box-id et data-user-id
				'<div class="widget-title">'.
					'<img src="'.DOL_URL_ROOT.'/custom/kontava/img/object_kontava.png" alt="Kontava" style="height: 12px;">'.
					'&nbsp;'.
					'<a  title='.$langs->trans('WidgetNameFirstStepsToolTip').' style="text-decoration:none; color : '.$couleurFont.'; flex-grow: 1;">'.
					$langs->trans('WidgetNameFirstStepsShort').'&nbsp;&nbsp;&nbsp;
					</a>'.
				'</div>'.
			'<i class="widget-arrow fa fa-chevron-down" style="color:'.$couleurFont.';"></i>'.
			'</div>';
								
								//END_PYNC_MODIF
		if ($kontava_instance) {
			$texteBienvenue = '<div class="kontava" style="margin:10px;">
				<span  class="kontava" >'.$langs->trans('FirstStepsWelcome').
				'</span>
			</div>';
			$boutonConfig ='<div style="width:100%;"><a class="kontava" href="/admin/company.php?mainmenu=home&leftmenu=setup">'.'<i class="fas fa-cog", style="color:#ffffff"></i>'.'&nbsp'.$langs->trans("SettingsCompagny").'</a></div>';
			$texteAutresInfos = '<div class="kontava" style="margin:10px;">
				<span  class="kontava" >'.$langs->trans('FirstStepsOtherInfo').
				'</span></br>
				<b style="color:'.$orange_hex.'">'.$langs->trans('FirstStepsAdmin').'</b>
			</div>';
		} else {
		// Si $kontava_instance == false, j'affiche un message pour inciter l'utilisateur à découvrir l'hébergement kontava
			$texteBienvenue ='';
			$boutonConfig ='';
			$texteAutresInfos .= '<div class="kontava" style="margin:10px; text-align:center; ">
				<span  class="kontava" >'.$langs->trans('FirstStepsDiscoverKontava').'</span><br>
				<a class="kontava" href="https://kontava.fr/" target="_blank" style="font-weight:bold; color:'.$bleu1_hex.'; ">kontava.fr</a>
			</div>';
		}
		$widget='<div class="widget-content ' . $hiddenClass . '" style="background:rgba(0,102,51, 0.2);">'.
					$texteBienvenue.			
					$boutonConfig.
					$texteAutresInfos.
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
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput, $this->backgroundColor, $this->dragndropColor, 0);
	}
}