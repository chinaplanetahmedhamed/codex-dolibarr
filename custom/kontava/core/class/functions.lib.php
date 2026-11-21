<?php
/**
 * Start a table with headers and a optional clickable number (don't forget to use "finishSimpleTable()" after the last table row)
 *
 * @param string	$header			The first left header of the table (automatic translated)
 * @param string	$link			(optional) The link to a internal dolibarr page, where to go on clicking on the number or the ... (without the first "/")
 * @param string	$arguments		(optional) Additional arguments for the link (e.g. "search_status=0")
 * @param integer	$emptyColumns	(optional) Number of empty columns to add after the first column
 * @param integer	$number			(optional) The number that is shown right after the first header, when -1 the link is shown as '...'
 * @param string	$pictofulllist 	(optional) The picto to use for the full list link
 * @return void
 *
 * @see finishSimpleTable()
 */
//PYNC 02/03/2025 : écris depuis /core/lib/function.lib.php sur un dolibarr D16, checké versus un D21 mais toujours OK
function startSimpleTable_kontava($header, $link = "", $arguments = "", $emptyColumns = 0, $number = -1, $pictofulllist = '')
{
    global $langs;
    $result = '';
	// $result .= '<div class="div-table-responsive-no-min">';
	// $result .= '<table class="noborder centpercent">';
	// $result .= '<tr class="liste_titre">';

	// $result .= ($emptyRows < 1) ? '<th>' : '<th colspan="'.($emptyRows + 1).'">';
    // $result .= '<p>';
	$result .= $langs->trans($header);

	// PYNC_MODIF extra space between the first header and the number (D16 mais plus en 21)
	if ($number > -1) {
		$result .= '&nbsp';//PYNC modifié en D21, avant c'était ' ' mais ça marchait pas, ça collait le badge avec le nombre contre le titre
	}
    // $result .= '</p>';
	if (!empty($link)) {
		if (!empty($arguments)) {
			$result .= '<a href="'.DOL_URL_ROOT.'/'.$link.'?'.$arguments.'">';
		} else {
			$result .= '<a href="'.DOL_URL_ROOT.'/'.$link.'">';
		}
	}

	if ($number > -1) {
		$result .= '<span class="badge">'.$number.'</span>';
			} elseif (!empty($link)) {
		$result .= '<span class="badge marginleftonlyshort">...</span>';
	}

	if (!empty($link)) {
		$result .= '</a>';
	}

	// $result .= '</th>';

	if ($number < 0 && !empty($link)) {
		$result .= '<th class="right">';

		if (!empty($arguments)) {
			$result .= '<a class="commonlink" href="'.DOL_URL_ROOT.'/'.$link.'?'.$arguments.'">';
		} else {
			$result .= '<a class="commonlink" href="'.DOL_URL_ROOT.'/'.$link.'">';
		}

		$result .= $langs->trans("FullList");
		$result .= '</a>';
		// $result .= '</th>';
	}

	// $result .= '</tr>';
    return $result;
}

?>