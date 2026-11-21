<?php
/**
 * Load the Dolibarr bootstrap from both standard and symlinked module layouts.
 */

$res = 0;
$mainCandidates = array();

if (!empty($_SERVER['DOCUMENT_ROOT'])) {
	$mainCandidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\').'/main.inc.php';
}

// Standard installation: htdocs/custom/<module>/lib/main.lib.php.
$mainCandidates[] = dirname(__DIR__, 3).'/main.inc.php';

foreach (array_unique($mainCandidates) as $mainCandidate) {
	if (is_file($mainCandidate)) {
		$res = @include $mainCandidate;
		if ($res) {
			break;
		}
	}
}

if (!$res) {
	die('Include of Dolibarr main.inc.php fails');
}
