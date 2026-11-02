<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseNativeConnection.php';
/** Serialize this module's financial/configuration work on the existing physical connection. */
final class OfficeExpenseFinancialLock
{
    public static function run($db,int $entity,callable $work)
    {
        $c=new OfficeExpenseNativeConnection($db);$c->outer();if($entity<1)throw new InvalidArgumentException('Invalid financial entity.');
        if(defined('M119_SYNTHETIC_NATIVE')&&M119_SYNTHETIC_NATIVE&&$db instanceof M119NativeSqlite)return $work();
        if(!in_array($db->type??'', ['mysqli','mysql'],true))throw new RuntimeException('Verified native financial locking is unavailable.');
        $key=$c->quote('cpo_office_finance_'.$entity);$row=$c->one('SELECT GET_LOCK('.$key.',5) AS financial_lock');
        if((int)($row['financial_lock']??0)!==1)throw new DomainException('Financial processing is busy; retry with the same operation key.');
        try{return $work();}
        finally{$row=$c->one('SELECT RELEASE_LOCK('.$key.') AS released');if((int)($row['released']??0)!==1){$c->retire();throw new RuntimeException('Financial connection lock release requires reconciliation.');}}
    }
}
