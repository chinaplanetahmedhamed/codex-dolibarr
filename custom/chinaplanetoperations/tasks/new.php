<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../lib/task_experience.lib.php';
require_once __DIR__.'/../class/InternalTaskDetailsService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
cpo_task_require($db,$user);$langs->load('chinaplanetoperations@chinaplanetoperations');
$service=new InternalTaskService($db,$user);$auth=$service->authorization();$canUpload=$auth->hasRight('task_upload_own');$selfCreate=!$auth->isAdministrator()&&!$auth->hasRight('task_manage_team');
$teams=$selfCreate?$service->ownTaskTeams():$service->teamOptions(!$auth->isAdministrator());if(!$teams)accessforbidden();
$members=array();foreach($teams as $team)$members[(int)$team['rowid']]=$service->userOptionsForTeam((int)$team['rowid']);
$personalColumns=$selfCreate?(new InternalTaskPersonalBoard($db,$user))->get()['columns']:array();$selectedColumn=GETPOSTINT('column');
$defaults=(new InternalTaskDetailsService($db,$user))->validate(array());
$css=array(cpo_asset('/custom/chinaplanetoperations/css/operations.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'));
llxHeader('',$langs->trans('AddTask'),'','',0,0,array(),$css);cpcc_admin_shell_open('tasks',$langs->trans('AddTask'),'','fa-tasks',array('flat'=>true));cpo_experience_assets();
?>
<main class="cpt-composer"><form data-task-composer data-request="<?php echo bin2hex(random_bytes(24)); ?>" action="personal_action.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="token" value="<?php echo newToken(); ?>">
<header class="cpt-composer-header"><div><span class="cpt-eyebrow"><?php echo $langs->trans('CPTNewCard'); ?></span><h1><?php echo $langs->trans('AddTask'); ?></h1><p><?php echo $langs->trans('CPTCreateHelp'); ?></p></div><a class="cpt-close" href="board.php?view=my" aria-label="<?php echo $langs->trans('Close'); ?>">×</a></header>
<div class="cpt-composer-grid"><div class="cpt-composer-main">
<section class="cpt-editor-section"><label class="cpo-field"><span><?php echo $langs->trans('Task'); ?> *</span><input class="cpt-title-input" required name="title" maxlength="255" autofocus></label>
<label class="cpo-field"><span><?php echo $langs->trans('Description'); ?> *</span><textarea required name="description" maxlength="20000" rows="5" placeholder="<?php echo $langs->trans('CPTDescriptionHelp'); ?>"></textarea></label></section>
<?php cpo_details_editor(array('version'=>0,'data'=>$defaults)); ?>
<section class="cpt-editor-section"><h2><?php echo $langs->trans('InternalTaskChecklists'); ?></h2><textarea name="checklist" rows="4" placeholder="<?php echo $langs->trans('CPTChecklistHelp'); ?>" aria-label="<?php echo $langs->trans('InternalTaskChecklists'); ?>"></textarea></section>
<section class="cpt-editor-section cpt-files-create"<?php echo !$canUpload?' hidden':''; ?>><h2><?php echo $langs->trans('InternalTaskFiles'); ?></h2><label class="cpt-file-drop"><span class="fas fa-paperclip" aria-hidden="true"></span><strong><?php echo $langs->trans('InternalTaskDropFiles'); ?></strong><input type="file" name="files" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.docx,.xlsx,.csv,.txt"></label><ul data-create-files></ul><label class="cpt-check"><input type="checkbox" name="first_image_cover" checked><?php echo $langs->trans('CPTFirstImageCover'); ?></label><small><?php echo $langs->trans('InternalTaskFileHelp'); ?></small></section>
<section class="cpt-editor-section"><h2><?php echo $langs->trans('InternalTaskComments'); ?></h2><textarea name="comment" maxlength="5000" rows="3" aria-label="<?php echo $langs->trans('Comment'); ?>"></textarea></section>
</div><aside class="cpt-composer-side">
<label class="cpo-field"><span><?php echo $langs->trans('InternalTaskTeam'); ?></span><select name="fk_usergroup" required><?php foreach($teams as $team){ ?><option value="<?php echo (int)$team['rowid']; ?>"><?php echo dol_escape_htmltag($team['nom']); ?></option><?php } ?></select></label>
<?php if($selfCreate){ ?><input type="hidden" name="fk_user_assign" value="<?php echo (int)$user->id; ?>"><div class="cpt-assignee"><span class="fas fa-user-circle" aria-hidden="true"></span><strong><?php echo dol_escape_htmltag(trim($user->firstname.' '.$user->lastname)?:$user->login); ?></strong><small><?php echo $langs->trans('InternalTaskSelfAssigned'); ?></small></div><?php }else{ ?><label class="cpo-field"><span><?php echo $langs->trans('Owner'); ?></span><select name="fk_user_assign"><option value="0"><?php echo $langs->trans('Unassigned'); ?></option></select></label><script type="application/json" data-composer-members><?php echo json_encode($members,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script><?php } ?>
<?php if($personalColumns){ ?><label class="cpo-field"><span><?php echo $langs->trans('CPTColumnName'); ?></span><select name="personal_column"><option value="0"><?php echo $langs->trans('Default'); ?></option><?php foreach($personalColumns as $column){$key=strpos($langs->defaultlang,'ar')===0?'label_ar':(strpos($langs->defaultlang,'zh')===0?'label_zh':'label_en'); ?><option value="<?php echo (int)$column['rowid']; ?>"<?php echo $selectedColumn===(int)$column['rowid']?' selected':''; ?>><?php echo dol_escape_htmltag($column[$key]); ?></option><?php } ?></select></label><?php } ?>
<label class="cpo-field"><span><?php echo $langs->trans('Priority'); ?></span><select name="priority"><?php foreach(InternalTaskService::priorities() as $priority){ ?><option value="<?php echo $priority; ?>"<?php echo $priority==='normal'?' selected':''; ?>><?php echo $langs->trans('InternalTaskPriority_'.$priority); ?></option><?php } ?></select></label>
<label class="cpo-field"><span><?php echo $langs->trans('Deadline'); ?></span><input type="datetime-local" name="date_deadline"></label>
<div class="cpt-create-summary"><?php echo $langs->trans('CPTPrivateTaskHelp'); ?></div>
<button type="submit" class="cpt-primary"><?php echo $langs->trans('CPTCreateCard'); ?></button><a href="board.php?view=my" class="cpt-secondary"><?php echo $langs->trans('Cancel'); ?></a><output data-compose-status role="status" aria-live="polite"></output><a hidden data-created-link class="cpt-secondary"><?php echo $langs->trans('CPTContinueCard'); ?></a>
</aside></div></form></main>
<?php cpcc_admin_shell_close();llxFooter();$db->close(); ?>
