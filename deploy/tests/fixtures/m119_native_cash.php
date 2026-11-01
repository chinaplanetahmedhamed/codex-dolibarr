<?php
declare(strict_types=1);
// Empty official native schemas in an already owned disposable test database only.
function m119CashSchema($db): void
{
    $sqlite=defined('M119_SYNTHETIC_NATIVE')&&M119_SYNTHETIC_NATIVE&&$db instanceof M119NativeSqlite;
    $ci=getenv('CI')==='true'&&getenv('GITHUB_ACTIONS')==='true'&&preg_match('/^m119_ci_[a-f0-9]{16}$/D',(string)$db->database_name);
    if(!$sqlite&&!$ci)throw new RuntimeException('Owned synthetic database required.');
    foreach(['bank_account','bank']as$table){
        $sql=preg_replace('/--[^\n]*/','',file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_'.$table.'.sql'));
        if($sqlite)$sql=str_ireplace('ON UPDATE CURRENT_TIMESTAMP','',$sql);
        if(!$db->query(trim($sql)))throw new RuntimeException('Synthetic native cash schema failed.');
    }
}
function m119CashAccounts($db): void
{
    // Values intentionally synthetic; no approved real opening amounts or native IDs are copied here.
    foreach([[101,1,'CPO-EG-EGP','EGP'],[102,1,'CPO-EG-USD','USD'],[103,1,'CPO-CN-CNY','CNY'],[104,1,'CPO-CN-USD','USD'],[105,2,'CPO-EG-EGP','EGP']]as[$id,$entity,$ref,$currency]){
        if(!$db->query("INSERT INTO llx_bank_account(rowid,entity,ref,label,courant,clos,currency_code,fk_pays) VALUES($id,$entity,'$ref','SYNTHETIC $id',2,0,'$currency',1)"))throw new RuntimeException('Synthetic cash account insert failed.');
    }
    foreach([[201,101,'123.45','SOLD'],[202,101,'-23.15','LIQ'],[203,102,'17.30','SOLD'],[204,103,'789.01','SOLD'],[205,104,'19.45','SOLD'],[206,105,'999.00','SOLD']]as[$id,$account,$amount,$type]){
        if(!$db->query("INSERT INTO llx_bank(rowid,dateo,datev,fk_account,amount,fk_type,label) VALUES($id,'2001-01-02','2001-01-02',$account,$amount,'$type','SYNTHETIC entry')"))throw new RuntimeException('Synthetic cash entry insert failed.');
    }
}
