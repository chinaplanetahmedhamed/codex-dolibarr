<?php
$root = dirname(__DIR__, 2);

function m075Assert($condition, $message)
{
	if (!$condition) {
		fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
		exit(1);
	}
	print 'PASS '.$message.PHP_EOL;
}

$shellPages = array(
	'custom/chinaplanetoperations/index.php',
	'custom/chinaplanetoperations/operations/index.php',
	'custom/chinaplanetoperations/operations/view.php',
	'custom/chinaplanetoperations/operations/edit.php',
	'custom/chinaplanetoperations/tasks/board.php',
	'custom/chinaplanetoperations/sop/index.php',
	'custom/chinaplanetoperations/sop/categories.php',
	'custom/chinaplanetoperations/sop/edit.php',
	'custom/chinaplanetoperations/sop/view.php',
	'custom/chinaplanetoperations/admin/setup.php',
);
foreach ($shellPages as $file) {
	$source = file_get_contents($root.'/'.$file);
	m075Assert(strpos($source, 'cpcc_admin_shell_open(') !== false, $file.' opens the shared admin shell');
	m075Assert(strpos($source, 'cpcc_admin_shell_close(') !== false, $file.' closes the shared admin shell');
	m075Assert(strpos($source, 'admin_dashboard.css') !== false, $file.' loads the shared admin navigation styles');
}

$taskBoard = file_get_contents($root.'/custom/chinaplanetoperations/tasks/board.php');
m075Assert(strpos($taskBoard, 'InternalTaskService') !== false, 'modern internal task service is preserved');
m075Assert(strpos($taskBoard, 'InternalTaskCollaborationService') !== false, 'task collaboration service is preserved');
m075Assert(strpos($taskBoard, 'internal_tasks.js') !== false, 'modern task board interactions are preserved');

$shell = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php');
m075Assert(strpos($shell, "'tasks'") !== false && strpos($shell, "'sop'") !== false, 'Tasks and SOP are available in the shared navigation');
m075Assert(strpos($shell, "'work'=>array('fa-briefcase'") !== false, 'secondary work links are grouped in a dropdown');

$css = file_get_contents($root.'/custom/chinaplanetoperations/css/operations.css');
m075Assert(strpos($css, '.cpad-section-content .cpo-stat strong{color:#0d1b3e!important}') !== false, 'stat values have readable contrast');
m075Assert(strpos($css, '.cpad-section-content .cpo-stat small{color:#526173!important}') !== false, 'stat labels have readable contrast');

$branding = file_get_contents($root.'/custom/visiontradingui/js/branding.js');
m075Assert(strpos($branding, 'Vision-China Planet OS') !== false, 'login branding uses the approved product name');

print 'M075 Vision UI release tests passed'.PHP_EOL;
