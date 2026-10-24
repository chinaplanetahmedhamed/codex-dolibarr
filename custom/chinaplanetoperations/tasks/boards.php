<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/InternalTaskBoardsService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
cpo_task_require($db,$user);try{$svc=new InternalTaskBoardsService($db,$user);}catch(Throwable $e){accessforbidden();}
$langs->load('chinaplanetoperations@chinaplanetoperations');$ar=strpos($langs->defaultlang,'ar')===0;
$conf->dol_hide_leftmenu=1; // Request-local presentation; no preference or permission changes.
llxHeader('',$ar?'لوحات العمل':'Boards','','',0,0,[],[cpo_asset('/custom/chinaplanetoperations/css/boards.css')],'','cpo-board-canvas');
$config=['request'=>bin2hex(random_bytes(24)),'token'=>newToken(),'board'=>GETPOSTINT('id'),'lang'=>$ar?'ar':'en','mode'=>GETPOST('view','aZ09')==='my'?'my':(GETPOST('view','aZ09')==='general'?'general':'boards'),'entity'=>(int)$conf->entity,'user'=>(int)$user->id,'customer'=>GETPOSTINT('customer'),'workspaces'=>InternalTaskWorkspaceService::enabled()];
?>
<main id="cp-boards" dir="<?php echo $ar?'rtl':'ltr'; ?>"><header class="cb-top"><a href="boards.php" class="cb-brand">▦ China Planet</a><input type="search" id="cb-search" placeholder="<?php echo $ar?'بحث في اللوحات والبطاقات':'Search boards and cards'; ?>" aria-label="<?php echo $ar?'بحث':'Search'; ?>"><a href="boards.php?view=my"><?php echo $ar?'مهامي':'My Work'; ?></a><button id="cb-shared"><?php echo $ar?'مشترك معي':'Shared with me'; ?></button><a href="inbox.php"><?php echo $ar?'التنبيهات':'Notifications'; ?></a><button id="cb-theme" aria-label="<?php echo $ar?'المظهر':'Theme'; ?>">◐</button></header><div id="cb-content" aria-live="polite"></div><output id="cb-status" role="status"></output></main>
<dialog id="cb-dialog"><header><h2></h2><button type="button" data-dialog-close aria-label="Close">×</button></header><div class="cb-dialog-body"></div></dialog>
<dialog id="cb-card-dialog"><header><strong><?php echo $ar?'تفاصيل البطاقة':'Card details'; ?></strong><button type="button" data-card-close aria-label="Close">×</button></header><iframe title="<?php echo $ar?'تفاصيل البطاقة':'Card details'; ?>"></iframe></dialog>
<script type="application/json" id="cb-config"><?php echo json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
<script src="<?php echo cpo_asset('/custom/chinaplanetcustomercenter/js/kanban_pointer.js'); ?>"></script><script src="<?php echo cpo_asset('/custom/chinaplanetoperations/js/boards.js'); ?>"></script>
<?php llxFooter();$db->close(); ?>
