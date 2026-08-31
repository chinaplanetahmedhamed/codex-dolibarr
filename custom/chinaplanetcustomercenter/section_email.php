<?php
define('CSRFCHECK_WITH_TOKEN', 1);
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING']='';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/class/CustomerSectionEmailService.class.php';
cpcc_portal_require_access($user);
$scope=cpcc_portal_user_scope($user);
if(!empty($scope['is_external'])||!($user->admin||$user->hasRight('chinaplanetcustomercenter','email_write')))accessforbidden();
$socid=GETPOSTINT('socid');$section=GETPOST('section','alpha');$contextId=GETPOSTINT('contextid');$action=GETPOST('action','alpha');
$service=new CustomerSectionEmailService($db);
try{
    $preview=$service->preview($section,$socid,$contextId);
    if($action==='send'){
        if(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'))!=='POST')accessforbidden();
        $result=$service->send($section,$socid,$contextId,$user);
        setEventMessages('Email sent to '.$result['recipient'].'.'.(empty($result['history_recorded'])?' Agenda history could not be recorded.':''),null,empty($result['history_recorded'])?'warnings':'mesgs');
        $redirect=$_SERVER['PHP_SELF'].'?section='.urlencode($section).'&socid='.(int)$socid.'&contextid='.(int)$contextId.'&sent=1';
        header('Location: '.$redirect);
        exit;
    }
}catch(CustomerSectionEmailException $e){setEventMessages($e->getMessage(),null,'errors');$preview=null;}
catch(Throwable $e){dol_syslog('Unexpected customer section email failure: '.get_class($e),LOG_ERR);setEventMessages('Customer email preview or send could not be completed.',null,'errors');$preview=null;}
llxHeader('','Customer section email / بريد قسم العميل','','',0,0,array(),array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css')));
cpcc_admin_shell_open('portal','Customer section email / بريد قسم العميل','معاينة آمنة قبل الإرسال','fa-envelope',array('socid'=>$socid));
?>
<main class="cpcc-dashboard" dir="rtl"><header class="cpcc-page-head"><div><span class="cpcc-eyebrow">CUSTOMER EMAIL PREVIEW</span><h1>معاينة البريد / Email preview</h1><p>المستلم والمحتوى مشتقان من سجل العميل والقسم؛ لا يمكن إدخال مستلم آخر.</p></div></header>
<?php if($preview){ ?><section class="cpcc-email-preview"><dl><dt>إلى / To</dt><dd><?php echo dol_escape_htmltag($preview['recipient']['email']); ?></dd><dt>الموضوع / Subject</dt><dd><?php echo dol_escape_htmltag($preview['subject']); ?></dd></dl><p class="cpcc-email-preview-note">هذه هي المعاينة المطابقة لمحتوى HTML الذي سيصل إلى العميل. لن يتم إرسال شيء حتى تضغط زر التأكيد أدناه. / This is the HTML content the customer will receive. Nothing is sent until you confirm below.</p><div class="cpcc-email-preview-frame"><?php echo $preview['body_html']; // Server-generated from escaped allowlisted values only. ?></div><form method="POST"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="send"><input type="hidden" name="section" value="<?php echo dol_escape_htmltag($section); ?>"><input type="hidden" name="socid" value="<?php echo (int)$socid; ?>"><input type="hidden" name="contextid" value="<?php echo (int)$contextId; ?>"><button class="button button-save" type="submit">إرسال وتسجيله في الأجندة / Send and record in Agenda</button></form></section><?php } ?>
</main><?php cpcc_admin_shell_close();llxFooter();
