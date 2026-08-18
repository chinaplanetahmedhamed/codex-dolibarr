<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/class/OperationsSopService.class.php';
require_once __DIR__.'/class/OperationsService.class.php';
cpo_require($user, 'read');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$contentLang = cpo_content_language();
$service = new OperationsSopService($db);
$stats = $service->getStats();
$operations = new OperationsService($db);
$operationStats = $operations->stats($user);
$morecss = array(cpo_asset('/custom/chinaplanetoperations/css/operations.css'));
llxHeader('', $langs->trans('OperationsCenter'), '', '', 0, 0, array(), $morecss);
?>
<main class="cpo-page">
  <section class="cpo-hero"><div><span class="cpo-eyebrow">CHINA PLANET OPERATIONS OS</span><h1><?php echo dol_escape_htmltag($langs->trans('OperationsCenter')); ?></h1><p><?php echo dol_escape_htmltag($langs->trans('OperationsFoundation')); ?></p></div><?php echo cpo_language_switcher($contentLang); ?></section>
  <section class="cpo-stats">
    <div class="cpo-stat"><span><?php echo $langs->trans('ActiveOperations'); ?></span><strong><?php echo (int)$operationStats['active']; ?></strong><small><?php echo $langs->trans('ControlledOperations'); ?></small></div>
    <div class="cpo-stat warn"><span><?php echo $langs->trans('Overdue'); ?></span><strong><?php echo (int)$operationStats['overdue']; ?></strong><small><?php echo $langs->trans('NextAction'); ?></small></div>
    <div class="cpo-stat good"><span><?php echo $langs->trans('OpenTasks'); ?></span><strong><?php echo (int)$operationStats['tasks']; ?></strong><small><?php echo $langs->trans('InternalTaskBoard'); ?></small></div>
    <div class="cpo-stat"><span><?php echo $langs->trans('ActiveSops'); ?></span><strong><?php echo (int)$stats['active_total']; ?></strong><small><?php echo $langs->trans('Published'); ?></small></div>
  </section>
  <section class="cpo-panel"><div class="cpo-panel-head"><h2><?php echo $langs->trans('OperationsDashboard'); ?></h2></div><div class="cpo-dashboard-links">
    <a href="operations/index.php"><strong><?php echo $langs->trans('OperationsRegister'); ?></strong><small><?php echo $langs->trans('OperationsRegisterHelp'); ?></small></a>
    <?php if (cpo_task_can_open($db, $user)) { ?><a href="tasks/board.php"><strong><?php echo $langs->trans('InternalTaskBoard'); ?></strong><small><?php echo $langs->trans('TaskBoardHelp'); ?></small></a><?php } ?>
    <a href="sop/index.php?content_lang=<?php echo urlencode($contentLang); ?>"><strong><?php echo $langs->trans('MultilingualSopCenter'); ?></strong><small><?php echo $langs->trans('OpenSopLibrary'); ?></small></a>
    <?php if (cpo_can($user, 'category')) { ?><a href="sop/categories.php?content_lang=<?php echo urlencode($contentLang); ?>"><strong><?php echo $langs->trans('SopCategories'); ?></strong><small><?php echo $langs->trans('ManageCategories'); ?></small></a><?php } ?>
    <?php if (cpo_can($user, 'translation')) { ?><a href="admin/setup.php"><strong><?php echo $langs->trans('LanguageSettings'); ?></strong><small>AR · EN · ZH</small></a><?php } ?>
  </div></section>
  <div class="cpo-notice"><?php echo dol_escape_htmltag($langs->trans('LegacySopNotice')); ?></div>
</main>
<?php llxFooter(); $db->close(); ?>
