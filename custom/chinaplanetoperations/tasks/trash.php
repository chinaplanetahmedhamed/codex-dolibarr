<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskService.class.php';
require_once __DIR__.'/../lib/task_experience.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
cpo_task_require($db,$user);
$langs->load('chinaplanetoperations@chinaplanetoperations');
$service=new InternalTaskService($db,$user);$auth=$service->authorization();
$error=false;try{$tasks=$service->listTrash();}catch(Throwable $e){$tasks=array();$error=true;}
$morecss=array(cpo_asset('/custom/chinaplanetoperations/css/operations.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'));
$morejs=array(cpo_asset('/custom/chinaplanetoperations/js/internal_tasks.js'));
llxHeader('',$langs->trans('CPTTrash'),'','',0,0,$morejs,$morecss);
cpcc_admin_shell_open('tasks',$langs->trans('CPTTrash'),'','fa-tasks',array('flat'=>true));cpo_experience_assets();
?>
<template id="cpo-upload-messages" data-cancel="<?php echo dol_escape_htmltag($langs->trans('Cancel')); ?>"></template>
<main class="cpo-page cpo-task-page">
<section class="cpo-hero"><div><h1><?php echo $langs->trans('CPTTrash'); ?></h1><p><?php echo $langs->trans('CPTTrashIntro'); ?></p></div><a class="button" href="board.php"><?php echo $langs->trans('CPTBackToTasks'); ?></a></section>
<?php if($error){ ?><section role="alert"><?php echo $langs->trans('InternalTaskSafeError'); ?></section><?php }elseif(!$tasks){ ?><p role="status"><?php echo $langs->trans('CPTTrashEmpty'); ?></p><?php } ?>
<div class="cpt-trash-list">
<?php foreach($tasks as $task){ ?>
<article class="cpo-panel"><small><bdi><?php echo dol_escape_htmltag($task['ref']); ?></bdi></small><h2><bdi dir="auto"><?php echo dol_escape_htmltag($task['title']); ?></bdi></h2>
<?php cpo_task_people($task); ?>
<?php if($auth->canRestoreTrash($task)){ ?><div class="cpo-task-lifecycle"><form method="post" action="action.php">
<input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="restore_trash"><input type="hidden" name="task_id" value="<?php echo (int)$task['rowid']; ?>"><input type="hidden" name="version" value="<?php echo (int)$task['version']; ?>">
<label class="cpo-field"><span><?php echo $langs->trans('InternalReason'); ?></span><textarea required maxlength="500" name="reason"></textarea></label><button type="submit" class="button"><?php echo $langs->trans('CPTRestoreTrash'); ?></button>
</form></div><?php } ?></article>
<?php } ?></div></main>
<?php cpcc_admin_shell_close();llxFooter();$db->close(); ?>
