<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       kontava/kontavaindex.php
 *	\ingroup    kontava
 *	\brief      Home page of kontava top menu
 */

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
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array("kontava@kontava"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Security check - Protection if external user
$socid = GETPOSTINT('socid');
if (!empty($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

// PYNC_ETRANGE, ressemble à une PYNCMODIF mais pas documenté

define('CSRFCHECK_WITH_TOKEN', 1); // We force need to use a token to login when making a POST
// require 'main.inc.php';
// require_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/class/html.formother.class.php';//PYNCD16 on n'affiche que les widget Kontava
dol_include_once('/kontava/core/class/html.formother.class.php');//PYNC Dolistore rules correction
// require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';


// If not defined, we select menu "home"
$_GET['mainmenu'] = GETPOST('mainmenu', 'aZ09') ? GETPOST('mainmenu', 'aZ09') : 'home';
// Initialize a technical object to manage hooks. Note that conf->hooks_modules contains array
//$hookmanager->initHooks(array($object->element.'index'));

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//if (!isModEnabled('kontavavierge21')) {
//	accessforbidden('Module not enabled');
//}
//if (! $user->hasRight('kontavavierge21', 'myobject', 'read')) {
//	accessforbidden();
//}
//restrictedArea($user, 'kontavavierge21', 0, 'kontavavierge21_myobject', 'myobject', '', 'rowid');
//if (empty($user->admin)) {
//	accessforbidden('Must be admin');
//}


/*
* Actions
*/
// PYNC_ETRANGE, ressemble à une PYNCMODIF mais pas documenté
if (GETPOST('addbox')) {	// Add box (when submit is done from a form when ajax disabled)
	// require_once DOL_DOCUMENT_ROOT.'/core/class/infobox.class.php';//PYNC original sur index.php
	// require_once DOL_DOCUMENT_ROOT.'/custom/kontava/core/class/infobox.class.php';//Utilisé en D16
	dol_include_once('/kontava/core/class/infobox.class.php');//PYNC Dolistore rules correction

	$zone = GETPOST('areacode');//PYNC was in D16 : 	$zone = GETPOST('areacode', 'int');
	$userid = GETPOST('userid');//PYNC was in D16 :  $userid = GETPOST('userid', 'int');
	$boxorder = GETPOST('boxorder', 'aZ09');
	$boxorder .= GETPOST('boxcombo', 'aZ09');
	$result = InfoBox::saveboxorder($db, $zone, $boxorder, $userid);
	if ($result > 0) {
		setEventMessages($langs->trans("BoxAdded"), null);
	}
}
// FIN PYNC_ETRANGE

//PYNC ADD on va tester si la table llx_kontava_boxes existe et sinon on la crée
//La table sera crée à la première ouverture de l'instance puisqu'on arrive par défaut sur le module kontava, ensuite on fera à chaque fois, seulement le teste de si la table existe.
// Vérifier si la table existe déjà
$sql = "SHOW TABLES LIKE '".MAIN_DB_PREFIX."kontava_boxes'";
$resql = $db->query($sql);
if (!$resql || $db->num_rows($resql) == 0) {
	// La table n'existe pas, on la crée
	dol_syslog("La table llx_kontava_boxes n'existe pas, on la crée", LOG_DEBUG);

	$sql_create = "CREATE TABLE ".MAIN_DB_PREFIX."kontava_boxes (
		rowid         INTEGER AUTO_INCREMENT PRIMARY KEY,
		entity        INTEGER NOT NULL DEFAULT 1,  -- Code de l'entreprise
		box_id        INTEGER NOT NULL,            -- ID du widget
		zone          SMALLINT NOT NULL DEFAULT 0, -- Écran sur lequel il est affiché
		fk_user       INTEGER NOT NULL,            -- ID de l'utilisateur
		unfold        BOOLEAN NOT NULL DEFAULT 1    -- État du widget (1 = déplié, 0 = replié)
	) ENGINE=InnoDB;";
	$resql = $db->query($sql_create);
	// dol_syslog("Réponse à la commande SQL : " . $resql, LOG_DEBUG);
	if ($resql) {
		dol_syslog("Table llx_kontava_boxes créée avec succès", LOG_DEBUG);
	} else {
		dol_syslog("Erreur lors de la création de la table : " . $db->lasterror(), LOG_DEBUG);
	}
} else {
	dol_syslog("La table llx_kontava_boxes existe déjà, ou erreur = " . $db->lasterror(), LOG_DEBUG);
}



/*
 * View
 */
//if (!isset($form) || !is_object($form)) {
	$form = new Form($db);
//}
$formfile = new FormFile($db);//PYNC Etait en D16 mais plus en 20, j'ai l'impression que ça sert à rien
llxHeader("", $langs->trans("KontavaArea"), '', '', 0, 0, '', '', '', 'mod-kontava page-index');
// llxHeader("", $langs->trans("KontavaArea"));//PYNC Ajout CSRF-TOKEN dans le head
// $langs->loadLangs(array('commercial', 'bills', 'orders', 'contracts'));
// PYNC-MODIF
$resultboxes = FormOther::getBoxesArea($user, "99" /*"KONTAVA"*/); // PYNC_MODIF Load $resultboxes (selectboxlist + boxactivated + boxlista + boxlistb)
print load_fiche_titre('&nbsp;', $resultboxes['selectboxlist'], '', 0, '', 'titleforhome');






print '<div class="clearboth"></div>';
print '<div class="fichecenter fichecenterbis">';

$boxlist = '<div class="twocolumns">';

$boxlist .= '<div class="firstcolumn fichehalfleft boxhalfleft" id="boxhalfleft">';
// if (!empty($nbworkboardcount)) {
// 	$boxlist .= $boxwork;
// }
$boxlist .= $resultboxes['boxlista'];
$boxlist .= '</div>';

$boxlist .= '<div class="secondcolumn fichehalfright boxhalfright" id="boxhalfright">';
$boxlist .= $resultboxes['boxlistb'];
$boxlist .= '</div>';
$boxlist .= "\n";

$boxlist .= '</div>';


print $boxlist;
// END PYNC_MODIF
print '</div>';
// print load_fiche_titre($langs->trans("KontavaArea"), '', 'kontava.png@kontava');


// print '<div class="fichecenter"><div class="fichethirdleft">';



// End of page
llxFooter();
$db->close();
?>