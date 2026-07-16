<?php
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$_SERVER['DOCUMENT_ROOT'] = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/OperationsSopService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php';

function cpoAssert($condition,$message){if(!$condition)throw new RuntimeException($message);}
class CpoTestUser{public $id;public $socid;public $admin;private $rights;public function __construct($id,$socid,$admin,array $rights){$this->id=$id;$this->socid=$socid;$this->admin=$admin;$this->rights=$rights;}public function hasRight($module,$right){return $module==='chinaplanetoperations'&&in_array($right,$this->rights,true);}}
$admin=new CpoTestUser(1,0,1,array());$reader=new CpoTestUser(2,0,0,array('read'));$external=new CpoTestUser(3,99,0,array('read','write','publish','delete','category','translation'));
cpoAssert(cpo_can($admin,'delete'),'Admin must pass the internal gate.');
cpoAssert(cpo_can($reader,'read')&&!cpo_can($reader,'write'),'Read-only internal user permission failed.');
cpoAssert(!cpo_can($external,'read'),'External user must be denied even when rights are assigned.');

$enabled=$db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE entity=".(int)$conf->entity." AND name='MAIN_MODULE_CHINAPLANETOPERATIONS'");$enabled=$enabled?$db->fetch_object($enabled):null;cpoAssert($enabled&&(string)$enabled->value==='1','Operations module is not enabled.');
$menu=$db->query("SELECT url FROM ".MAIN_DB_PREFIX."menu WHERE entity=".(int)$conf->entity." AND mainmenu='chinaplanetoperations' AND type='top'");$menu=$menu?$db->fetch_object($menu):null;$moduleDescriptor=new modChinaPlanetOperations($db);cpoAssert($menu&&$menu->url==='/custom/chinaplanetoperations/index.php'&&$moduleDescriptor->picto==='fa-route','Operations top-menu link or icon was not registered.');$dashboardSource=file_get_contents(DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/admin_dashboard.php');cpoAssert(strpos($dashboardSource,"/chinaplanetoperations/index.php")!==false&&strpos($dashboardSource,"service_type=shipping_booking")!==false,'Vision dashboard operations shortcuts are missing.');
foreach(array('cpo_sop','cpo_sop_revision','cpo_sop_revision_lang','cpo_sop_category','cpo_sop_category_lang')as$table){$res=$db->query("SHOW TABLES LIKE '".MAIN_DB_PREFIX.$table."'");cpoAssert($res&&$db->num_rows($res)===1,'Missing table '.$table);}
$rights=$db->query("SELECT id FROM ".MAIN_DB_PREFIX."rights_def WHERE id BETWEEN 500501 AND 500510");cpoAssert($rights&&$db->num_rows($rights)===10,'Operations permissions were not registered.');
$categoryResult=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpo_sop_category WHERE entity=".(int)$conf->entity." AND code='shipping_booking'");$category=$categoryResult?$db->fetch_object($categoryResult):null;cpoAssert($category,'Multilingual seed categories are missing.');
$translationCount=$db->query("SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."cpo_sop_category_lang WHERE entity=".(int)$conf->entity." AND fk_category=".(int)$category->rowid);$translationCount=$translationCount?$db->fetch_object($translationCount):null;cpoAssert($translationCount&&(int)$translationCount->total===3,'Seed category does not have three translations.');

$service=new OperationsSopService($db);$ref='TEST-CPO-'.date('YmdHis').'-'.mt_rand(100,999);$id=0;
$context=array('ref'=>$ref,'fk_category'=>(int)$category->rowid,'country_code'=>'SA','pol'=>'SHANGHAI','pod'=>'JEDDAH','shipping_line'=>'COSCO','service_type'=>'direct','container_type'=>'40HQ','incoterm'=>'FOB','cargo_type'=>'normal','document_codes'=>'booking,packing_list','effective_date'=>date('Y-m-d'));
$translations=array(
 'en_US'=>array('title'=>'Shipping booking workflow','applicability'=>'Approved Saudi bookings.','required_information'=>"Customer\nCargo",'procedure_steps'=>"Review cargo\nSend booking request",'required_documents'=>"Packing list",'warnings'=>"Do not send unapproved documents",'escalation_instructions'=>'Escalate missed closing.','internal_notes'=>'Test','responsible_department'=>'Operations','change_summary'=>'Initial'),
 'ar_SA'=>array('title'=>'إجراء حجز الشحن','applicability'=>'الحجوزات المعتمدة للسعودية.','required_information'=>"العميل\nالبضاعة",'procedure_steps'=>"مراجعة البضاعة\nإرسال طلب الحجز",'required_documents'=>"قائمة التعبئة",'warnings'=>"لا ترسل مستندات غير معتمدة",'escalation_instructions'=>'تصعيد عند فوات الموعد.','internal_notes'=>'اختبار','responsible_department'=>'العمليات','change_summary'=>'أولي'),
 'zh_CN'=>array('title'=>'订舱操作流程','applicability'=>'已批准的沙特订舱。','required_information'=>"客户\n货物",'procedure_steps'=>"审核货物\n发送订舱申请",'required_documents'=>"装箱单",'warnings'=>"不得发送未批准文件",'escalation_instructions'=>'错过截单时间时升级处理。','internal_notes'=>'测试','responsible_department'=>'运营部','change_summary'=>'初始版本'),
);
try{
 $id=$service->create($context,$translations,1);cpoAssert($id>0,'Could not create multilingual SOP.');cpoAssert($service->get($id,'ar_SA',false)===null,'Draft leaked to reader query.');
 $service->publish($id,1);$arabic=$service->get($id,'ar_SA',false);$chinese=$service->get($id,'zh_CN',false);cpoAssert($arabic&&$arabic['title']==='إجراء حجز الشحن','Arabic translation lookup failed.');cpoAssert($chinese&&$chinese['title']==='订舱操作流程','Chinese translation lookup failed.');cpoAssert((int)$arabic['approved_languages']===3,'Publication did not approve all required translations.');
 $missing=$translations;$missing['zh_CN']['procedure_steps']='';$service->saveDraft($id,$context,$missing,1);$blocked=false;try{$service->publish($id,1);}catch(RuntimeException$e){$blocked=true;}cpoAssert($blocked,'Publishing incomplete translations was not blocked.');
 $readerCopy=$service->get($id,'en_US',false);$editorCopy=$service->get($id,'en_US',true);cpoAssert((int)$readerCopy['revision_no']===1,'Published revision was silently overwritten.');cpoAssert((int)$editorCopy['revision_no']===2,'Editor did not receive revision 2 draft.');
 $restoredRevision=$service->restoreRevision($id,1,1);cpoAssert($restoredRevision===3,'Restoring an old multilingual revision did not create revision 3.');$restored=$service->get($id,'zh_CN',false);cpoAssert((int)$restored['revision_no']===3&&$restored['title']==='订舱操作流程','Restored revision did not preserve the Chinese translation.');
 $search=$service->search(array('q'=>'订舱操作流程'),'ar_SA',true);cpoAssert(in_array($id,array_map(function($row){return(int)$row['sop_id'];},$search),true),'Cross-language search failed.');
 print "OK enabled multilingual SOP module, permissions, external denial, AR/EN/ZH publication, incomplete-translation block, revision restore and cross-language search\n";
}finally{if($id>0){$db->query("DELETE tl FROM ".MAIN_DB_PREFIX."cpo_sop_revision_lang tl JOIN ".MAIN_DB_PREFIX."cpo_sop_revision r ON r.rowid=tl.fk_revision WHERE r.entity=".(int)$conf->entity." AND r.fk_sop=".(int)$id);$db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_sop_revision WHERE entity=".(int)$conf->entity." AND fk_sop=".(int)$id);$db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_sop WHERE entity=".(int)$conf->entity." AND rowid=".(int)$id);}}
