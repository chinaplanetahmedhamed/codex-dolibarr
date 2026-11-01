<?php
declare(strict_types=1);
// Private release tool, never installed under the web root. Existing native context only.
if(PHP_SAPI!=='cli'||!in_array($argv[1]??'',array('inspect','migrate','verify'),true)){fwrite(STDERR,"M119_NATIVE_OPERATION_REFUSED\n");exit(1);}
$mode=$argv[1];ob_start();
try {
    require '/var/www/html/master.inc.php';
    if(DOL_VERSION!=='24.0.1'||PHP_VERSION!=='8.2.32'||$db->type!=='mysqli'||$conf->entity!==1
        ||!in_array($conf->currency,array('EGP','CNY','USD'),true)||DOL_DATA_ROOT!=='/var/www/documents'
        ||!preg_match('/^[A-Za-z0-9_]{1,40}$/D',MAIN_DB_PREFIX)
        ||!preg_match('/^[A-Za-z0-9_]{1,64}$/D',(string)$db->database_name)
        ||(int)($conf->global->MAIN_MAX_DECIMALS_TOT??2)!==2)throw new RuntimeException('Native staging context differs.');
    $user=new User($db);if($user->fetch(1)!==1)throw new RuntimeException('Approved administrator is unavailable.');$user->loadRights('expensereport');
    if(empty($user->admin)||(int)$user->status!==1||!empty($user->socid)||$user->isNotIntoValidityDateRange()||!$user->hasRight('expensereport','creer')||empty($conf->expensereport->enabled))throw new RuntimeException('Approved administrator/module is unavailable.');
    $r=$db->query('SELECT VERSION() AS v');$version=$r?$db->fetch_object($r)->v:'';if(strpos($version,'10.6.27-MariaDB')!==0)throw new RuntimeException('Native database engine differs.');
    $tables=array();foreach(array('cpo_oe_schema','cpo_oe_draft','cpo_oe_draft_audit','cpo_oe_receipt','cpo_oe_receipt_audit')as$table){$r=$db->query("SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$db->escape(MAIN_DB_PREFIX.$table)."'");if(!$r)throw new RuntimeException('Owned schema inspection failed.');$tables[$table]=(int)$db->fetch_object($r)->n;}
    $out=array('status'=>'native_context_verified','dolibarr'=>DOL_VERSION,'php'=>PHP_VERSION,'database_version'=>$version,'database_name'=>$db->database_name,'prefix'=>MAIN_DB_PREFIX,'entity'=>$conf->entity,'base_currency'=>$conf->currency,'administrator_id'=>(int)$user->id,'bcmath'=>extension_loaded('bcmath'),'jpeg'=>function_exists('imagejpeg')&&function_exists('imagecreatefromjpeg'),'owned_table_presence'=>$tables);
    if($mode!=='inspect') {
        if(!$out['bcmath']||!$out['jpeg'])throw new RuntimeException('Required exact-money/image capability is unavailable.');
        $module=DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/office_expenses';require $module.'/class/OfficeExpenseNativeStorageFactory.php';require $module.'/class/OfficeExpensePilotConfiguration.php';
        $hashes=array($argv[2]??'', $argv[3]??'');foreach($hashes as$hash)if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new RuntimeException('Reviewed migration pins are required.');
        if(!hash_equals(hash_file('sha256',$module.'/schema/manifest.json'),$hashes[0])||!hash_equals(hash_file('sha256',$module.'/schema/receipts/manifest.json'),$hashes[1]))throw new RuntimeException('Installed migration pins differ.');
        $backend=new OfficeExpenseMysqliSchemaBackend($db);$base=new OfficeExpenseNativeSchemaMigration($backend);$receipt=OfficeExpenseNativeSchemaMigration::forReceipts($backend);
        if($mode==='migrate'){$base->install($hashes[0]);$receipt->install($hashes[1]);}
        $base->assertReady();$receipt->assertReady();
        $pilot=OfficeExpensePilotConfiguration::read(OfficeExpensePilotConfiguration::PATH,DOL_DOCUMENT_ROOT);$offices=$pilot->scope($user,$conf);
        $factory=new OfficeExpenseNativeStorageFactory($db,$user,$conf,$offices,$pilot->privateRoot(),DOL_DOCUMENT_ROOT,array(),10,26214400,$pilot->precision());
        $drafts=$factory->draftFactory($db,$user,$conf);$receipts=$factory->receiptFactory($db,$user,$conf,$drafts);
        if(!$drafts->usesNativeContext($user,$conf->entity)||!$receipts->usesDraftService($drafts))throw new RuntimeException('Native storage binding differs.');
        try{$drafts->post(array());throw new RuntimeException('Financial posting unexpectedly returned.');}catch(LogicException $expected){}
        $r=$db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE rowid<>1 ORDER BY rowid LIMIT 1');$row=$r?$db->fetch_object($r):null;
        if(!$row)throw new RuntimeException('Independent native identity denial cannot be verified.');
        $other=new User($db);if($other->fetch((int)$row->rowid)!==1)throw new RuntimeException('Independent native identity unavailable.');
        $denied=false;try{$pilot->scope($other,$conf);}catch(OfficeExpensePilotAccessDenied $expected){$denied=true;}if(!$denied)throw new RuntimeException('Unapproved identity unexpectedly entered pilot scope.');
        $out['status']='native_storage_verified';$out['base_schema']='installed';$out['receipt_schema']='installed';$out['unapproved_identity_denied']=true;$out['financial_posting_disabled']=true;$out['owned_pilot_draft_count']=count($drafts->listOwn());
    }
    ob_end_clean();echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $error){ob_end_clean();echo json_encode(array('status'=>'blocked','error_class'=>get_class($error),'reason'=>'Native release context/storage verification failed; no success is claimed.'),JSON_THROW_ON_ERROR);exit(1);}
