<?php
declare(strict_types=1);
// Read-only backup-ERP release verification. Never served by HTTP; never activates finance.
if(PHP_SAPI!=='cli'||$argc!==2||!in_array($argv[1],['inspect','verify','paused'],true))exit(1);
$mode=$argv[1];$phase='identity';$locked=false;$transaction=false;ob_start();
function fvMust($condition,string $reason): void {if(!$condition)throw new RuntimeException($reason);}
function fvRows(string $sql): array {global $db;$result=$db->query($sql);fvMust((bool)$result,'Read-only native query failed.');$rows=[];while($row=$db->fetch_object($result)){$rows[]=(array)$row;fvMust(count($rows)<=100000,'Verification exceeds its bounded population.');}return $rows;}
function fvFingerprint(): string {
    $hash=hash_init('sha256');
    foreach(['bank_account','bank','bank_url','expensereport','expensereport_det','payment_expensereport','salary','payment_salary','paiement','paiement_facture','accounting_bookkeeping','cpo_oe_workspace','cpo_oe_workspace_audit','cpo_oe_membership','cpo_oe_access_audit','user','user_rights','usergroup_user','usergroup_rights']as$name){
        hash_update($hash,$name."\n");foreach(fvRows('SELECT * FROM '.MAIN_DB_PREFIX.$name.' ORDER BY rowid')as$row)hash_update($hash,serialize($row)."\n");
    }return hash_final($hash);
}
try{
    fvMust(function_exists('posix_geteuid')&&posix_geteuid()===33&&posix_getegid()===33,'Service identity differs.');
    require '/var/www/html/master.inc.php';
    fvMust(DOL_VERSION==='24.0.1'&&PHP_VERSION==='8.2.32'&&$db->type==='mysqli'&&$db->database_name==='dolibarr_db'&&$conf->entity===1&&$conf->currency==='USD'&&DOL_DATA_ROOT==='/var/www/documents','Runtime differs.');
    fvMust(preg_match('/^[A-Za-z0-9_]{1,40}$/D',MAIN_DB_PREFIX)&&str_starts_with(fvRows('SELECT VERSION() AS v')[0]['v'],'10.6.27-MariaDB'),'Database differs.');
    fvMust($db->transaction_opened===0,'Unexpected transaction.');
    fvMust((int)fvRows("SELECT GET_LOCK('cpo_office_finance_1',30) AS acquired")[0]['acquired']===1,'Financial processing did not drain.');$locked=true;
    $phase='schema';$module=DOL_DOCUMENT_ROOT.'/custom/chinaplanetofficeexpenses';require_once $module.'/class/OfficeExpenseWorkspaceRuntime.php';require_once $module.'/class/OfficeExpenseWorkspaceService.php';
    $pilot=OfficeExpensePilotConfiguration::read(OfficeExpensePilotConfiguration::PATH,DOL_DOCUMENT_ROOT);$id=$pilot->staffBootstrap($conf);
    $user=new User($db);fvMust($user->fetch($id)===1&&!empty($user->admin)&&(int)$user->status===1&&empty($user->socid)&&!$user->isNotIntoValidityDateRange(),'Bootstrap differs.');
    $access=OfficeExpenseStaffRuntime::access($db,$user,$conf,$pilot);$service=new OfficeExpenseWorkspaceService($db,$user,1,$access);
    fvMust(OfficeExpenseWorkspaceRuntime::ready($db,$conf),'Existing workspace is unavailable.');
    fvMust((bool)$db->query('SET TRANSACTION READ ONLY')&&(bool)$db->begin(),'Read-only snapshot unavailable.');$transaction=true;
    $phase='cash';
    foreach(['CPO-EG-EGP'=>'EGP','CPO-EG-USD'=>'USD','CPO-CN-CNY'=>'CNY','CPO-CN-USD'=>'USD']as$ref=>$currency){$r=fvRows("SELECT rowid,currency_code,courant FROM ".MAIN_DB_PREFIX."bank_account WHERE entity=1 AND ref='".$db->escape($ref)."'");fvMust(count($r)===1&&$r[0]['currency_code']===$currency&&(int)$r[0]['courant']===2,'Original cash account binding differs.');}
    $before=fvFingerprint();$postingCount=0;$settingsDisabled=true;$gate=null;
    if($mode!=='inspect'){
        $phase='finance';require_once $module.'/class/OfficeExpenseFinancialService.php';$gate=OfficeExpenseFinanceGate::enabled();fvMust($gate===($mode==='verify'),'Release pause state differs.');
        $finance=new OfficeExpenseFinancialService($db,$user,$conf,$access,$service);
        foreach(['egypt','china']as$office){$config=$finance->settings()->load($office);$settingsDisabled=$settingsDisabled&&!$config['content']['enabled'];$report=$finance->report($office,'1900-01-01','2099-12-31');$postingCount+=count($report['rows']);}
    }
    fvMust($before===fvFingerprint(),'Read-only verification changed native records.');fvMust($db->transaction_opened===1&&(bool)$db->rollback(),'Snapshot did not close.');$transaction=false;
    fvMust((int)fvRows("SELECT RELEASE_LOCK('cpo_office_finance_1') AS released")[0]['released']===1,'Financial lock did not release.');$locked=false;
    ob_end_clean();echo json_encode(['status'=>$mode.'_verified','native_mutations'=>false,'financial_fingerprint'=>$before,'settings_disabled'=>$settingsDisabled,'release_gate'=>$gate,'verified_postings'=>$postingCount],JSON_THROW_ON_ERROR);
}catch(Throwable $error){
    if($transaction&&isset($db))$db->rollback();if($locked&&isset($db))$db->query("SELECT RELEASE_LOCK('cpo_office_finance_1')");ob_end_clean();echo json_encode(['status'=>'STOP','phase'=>$phase,'error_class'=>get_class($error),'reason'=>'Read-only financial verification stopped; inspect retained state.']);exit(1);
}
