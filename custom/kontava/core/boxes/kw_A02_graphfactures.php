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


// function moisDebut($YYYY_MM = "") 
// {
// 	return substr($YYYY_MM,5,2);
// }
// function anneeDebut($YYYYMM = "") 
// {
// 	return substr($YYYYMM,0,4);
// }


/**
 * Class to manage the box
 *
 * Warning: for the box to be detected correctly by dolibarr,
 * the filename should be the lowercase classname
 */
class kw_A02_graphfactures extends ModeleBoxesKontava
{
	/**
	 * @var string Alphanumeric ID. Populated by the constructor.
	 */
	public $boxcode = "kw_A02_graphfactures";

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

		$this->boxlabel = '&nbsp;'. $langs->transnoentitiesnoconv("WidgetNameGraphFactures");

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

		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
		include_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
		include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		include DOL_DOCUMENT_ROOT.'/custom/kontava/core/boxes/kontava_color.php';

		// $propalstatic = new Propal($this->db);
		// $societestatic = new Societe($this->db);
		// $form = new Form($db);
		// $formfile = new FormFile($db);
		//START_PYNC_MODIF
		
		$month_today = dol_print_date(dol_now(), "%Y-%m", 'tzuserrel');

//BUIBUIMODIF
		if (isset($_GET['moisFin'])) {
			$monmoisposted = "".$_GET['moisFin']."";
			
		} else {
			$monmoisposted = "";
		}

		if ($monmoisposted != "") {
			$month_today = $monmoisposted;
		}
//END_BUIBUIMODIF

		// $monthLastYear=date('Y-m', strtotime($month_today." +1 month -1 year"));
		// $dateLastYear=date('Y-m-d', strtotime(dol_print_date(dol_now(), "%Y-%m-%d", 'tzuserrel')." +1 month -1 year"));
		$date_today = dol_print_date(dol_now(), "%Y-%m-%d", 'tzuserrel');

//BUIBUIMODIF
        if ($monmoisposted != "") {
			$date_today = $monmoisposted."-01";
		}
//END_BUIBUIMODIF		
		
	$sqlFacture = "
		SELECT 
		DATE_FORMAT(mths.mois_annee, '%Y-%m') AS mois_annee, 
		COALESCE(ROUND(SUM(CASE WHEN llx_facture.paye = 0 AND (llx_facture.fk_statut = 1 OR llx_facture.fk_statut = 2) THEN llx_facture.total_ht ELSE 0 END)), NULL) AS Montant_des_factures_impayees, 
		COALESCE(ROUND(SUM(CASE WHEN llx_facture.paye = 1 THEN llx_facture.total_ht ELSE 0 END)), NULL) AS Montant_des_factures_payees,
		llx_facture.total_ht AS tot_ht, llx_facture.ref AS fref,
		llx_facture.rowid, llx_facture.paye AS fpaye, DATE_FORMAT(llx_facture.datef, '%Y-%m') AS factdate
		FROM (
			SELECT DATE_ADD('".$date_today."', INTERVAL (1-units.i) MONTH) AS mois_annee
			FROM (
				SELECT 1 AS i UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION 
				SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION 
				SELECT 11 UNION SELECT 12
			) units
		) mths
		LEFT JOIN llx_facture ON DATE_FORMAT(llx_facture.datef, '%Y-%m') = DATE_FORMAT(mths.mois_annee, '%Y-%m')
		GROUP BY mois_annee
		";
	
		
	$resqlFacture = $this->db->query($sqlFacture);
	$graphique = '';

 
 	 $tableauFacture = array();
		if ($resqlFacture) {
			while ($row = $resqlFacture->fetch_array()) {
				$tableauFacture[] = $row;
			}
			$this->db->free($resqlFacture);

		}
		else {
			dol_print_error($db);
		}

		


	$graphique = ''; //POUR NE PAS AFFICHER LE TABLEAU	

		// $graphique .=$date_today;
		$graphique .= '<form action="" method="get">' ;
		// $graphique .= '<input type="month" name="moisDebut" value='.$monthLastYear.'>'; // On décide d'imposer un 12 mois glissant en sélectionnant uniquement le mois de fin
//		$graphique .= '<input type="month" name="moisDebut" value='.substr($date_today,0,7).'>';
		$graphique .= '<input type="month" id="moisFin" name="moisFin" value='.$month_today.'>';
//		$graphique .= '<input type="month" id="moisFin" name="moisFin" value='.substr($date_today,0,7).'>';
		$graphique .= '<input type="submit" value="OK">';
		$graphique .= '</form>';
    	// $graphique .= "<meta charset='utf-8'>"; //tentative pour faire accepter les caractères accentués
		$graphique .= '<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/echarts@5.6.0/dist/echarts.min.js" charset="utf-8"></script>';
		$graphique .= '<div id="main" class="echart-instance" style="width: 98%;height:300px;"></div>';
		$graphique .= '<script type="text/javascript" charset="utf-8">';
		$graphique .= "
			
			var chartDom = document.getElementById('main');
			var myChart = echarts.init(chartDom);
			chartDom.dataset.echartsInstance = 'main'; // pour référence
			window.echartsInstances = window.echartsInstances || {};
			window.echartsInstances['main'] = myChart;
			// var myChart = echarts.init(document.getElementById('main'));

			var option = {
				aria: {
					enabled: false,
					decal: {
						show: false
					}
				},
				tooltip: {
				  trigger: 'axis',
				  axisPointer: {
					// Use axis to trigger tooltip
					type: 'shadow' // 'shadow' as default; can also be 'line' or 'shadow'
				  }
				},
				legend: {
					left: '1%',
					// tooltip: {
					// 	show: 'true',
					// 	trigger: 'none',
					// 	axisPointer: {
					// 	  // Use axis to trigger tooltip
					// 	  type: 'line' // 'shadow' as default; can also be 'line' or 'shadow'
					// 	}
					// },
				},
				grid: {
				  left: '3%',
				  right: '4%',
				  bottom: '3%',
				  containLabel: true
				},
				yAxis: {
				  type: 'value',
				},
				xAxis: {
				  type: 'category',
				  axisTick: {
					alignWithLabel: true
				  },
				  axisLabel: {
					rotate: 30
				  },
				  data: ".json_encode(array_column($tableauFacture,0))."
				},
				series: [
				  {
					name: '".html_entity_decode($langs->trans('WidgetNameGraphEchartsPropalBilledUnpaid'))."', 	//'Devis facturés impayé',
					type: 'bar',
					stack: 'total',
					label: {
					  show: false
					},
					emphasis: {
					  focus: 'series'
					},
					itemStyle: {
						normal: {
							color: '".$rouge_hex."'
						}
					},
					data: ".json_encode(array_column($tableauFacture,1))."
				  },
				  {
					name: '".html_entity_decode($langs->trans('WidgetNameGraphEchartsPropalBilledPaid'))."', 	//'Devis facturés payés',
					type: 'bar',
					stack: 'total',
					label: {
					  show: false
					},
					emphasis: {
					  focus: 'series'
					},
					itemStyle: {
						normal: {
							color: '".$vert1_hex2."'
						},
					},
					data: ".json_encode(array_column($tableauFacture,2))."
				  }
				]
			  };
			myChart.setOption(option);
			myChart.on('click', function(params) {
				var year=parseInt(params.name.substring(0, 4));
				var month = params.name.slice(-2);
				var year = params.name.slice(0,4);
				var lastDay = new Date(year,month,0).getDate(); // On obtient le numéro du dernier jour du mois
				switch (params.seriesIndex)
				{
					case 0:
						status = 1;
						break;
					case 1:
						status = 2;
						break;
					default:
					status = 0;
					};
								
				window.location.href = ('/compta/facture/list.php?search_status=' + status + 
				'&search_date_startday=1&search_date_startmonth=' + month + '&search_date_startyear=' + year + 
				'&search_date_endday=' + lastDay + '&search_date_endmonth=' + month + '&search_date_endyear=' + year);
			});
    		</script>";

		  
		 
		// Populate the head at runtime
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
					"&nbsp;"."<a  title=".$langs->trans("WidgetNameGraphFacturesToolTip")." style='text-decoration:none; color : ".$couleurFont."'>".$langs->trans("WidgetNameGraphFacturesShort")."</a>".
				'</div>'.
				'<i class="widget-arrow fa fa-chevron-down" style="color:'.$couleurFont.';"></i>'.
			'</div>';
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
					'text' => '<div class="widget-content ' . $hiddenClass . '">'.$graphique.'</div>',
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
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput, $this->backgroundColor, $this->dragndropColor, $noCloseBtn = 0);
	}
}
