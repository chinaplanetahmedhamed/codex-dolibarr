<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseNativeConnection.php';
require_once __DIR__.'/OfficeExpenseStaffAccess.php';

/** Read-only bridge to native cash accounts. No balance cache, FX or posting path. */
final class OfficeExpenseNativeTreasury
{
    public const REFERENCES=['egypt'=>['EGP'=>'CPO-EG-EGP','USD'=>'CPO-EG-USD'],'china'=>['CNY'=>'CPO-CN-CNY','USD'=>'CPO-CN-USD']];
    private OfficeExpenseNativeConnection $connection;private $db;private $user;private int $entity;private OfficeExpenseStaffAccess $access;
    public function __construct($db,$user,int $entity,OfficeExpenseStaffAccess $access)
    {
        if(($GLOBALS['db']??null)!==$db||!$access->usesContext($user,$entity))throw new DomainException('Native context differs.');
        $this->db=$db;$this->connection=new OfficeExpenseNativeConnection($db);$this->user=$user;$this->entity=$entity;$this->access=$access;
    }
    private function authorize(string $office,string $section): void
    {
        if(($GLOBALS['db']??null)!==$this->db||!$this->access->usesContext($this->user,$this->entity))throw new DomainException('Native context differs.');
        $this->access->assertAction($office,$section,'read');
        if(!isset(self::REFERENCES[$office]))throw new DomainException('Branch unavailable.');
    }
    private function account(string $office,string $currency): array
    {
        $reference=self::REFERENCES[$office][$currency]??null;
        if($reference===null)throw new DomainException('Branch currency unavailable.');
        $row=$this->connection->one('SELECT rowid,ref,label,currency_code,courant,clos FROM '.MAIN_DB_PREFIX.'bank_account WHERE entity='.$this->entity.' AND ref='.$this->connection->quote($reference));
        if(!$row||$row['ref']!==$reference||(int)$row['courant']!==2||(int)$row['clos']!==0||$row['currency_code']!==$currency)throw new RuntimeException('Native branch cash-account mapping requires review.');
        return ['id'=>(int)$row['rowid'],'reference'=>$row['ref'],'label'=>$row['label'],'currency'=>$currency];
    }
    /** Safe branch metadata only; never returns another account's bank details. */
    public function accounts(string $office): array
    {
        $this->authorize($office,'settings');$out=[];
        foreach(self::REFERENCES[$office]as$currency=>$reference)$out[]=$this->account($office,$currency);
        $this->authorize($office,'settings');return $out;
    }
    public function assertPrimaryAccount(string $office,int $id): void
    {
        $this->authorize($office,'settings');$currency=array_key_first(self::REFERENCES[$office]);
        if($this->account($office,$currency)['id']!==$id)throw new DomainException('Account is not the branch primary cash account.');
    }
    private static function decimal($value): string
    {
        // MariaDB's DECIMAL-to-CHAR wire result stays decimal text, including tiny and signed values.
        if(!is_string($value)||!preg_match('/^-?[0-9]{1,22}(?:\.[0-9]{1,8})?$/D',$value))throw new RuntimeException('Native money precision unavailable.');
        $exact=bcadd($value,'0',8);$whole=rtrim(rtrim($exact,'0'),'.');
        if(!str_contains($whole,'.'))return $whole.'.00';
        return str_pad($whole,strpos($whole,'.')+3,'0');
    }
    public function read(string $office): array
    {
        $this->authorize($office,'treasury');
        if($this->access->recordScope($office,'treasury')!=='branch')return ['restricted'=>true,'accounts'=>[]];
        $accounts=[];
        foreach(self::REFERENCES[$office]as$currency=>$reference){
            $account=$this->account($office,$currency);$id=$account['id'];$c=$this->connection;
            // One statement supplies each account's total and latest entries from the same snapshot.
            // bank has no entity column: both legs are fenced by the validated native account.
            $rows=$c->rows('SELECT a.rowid AS account_id,totals.balance,b.rowid,b.dateo,b.datev,b.fk_type,b.label,CAST(CAST(b.amount AS DECIMAL(30,8)) AS CHAR) AS amount FROM '.MAIN_DB_PREFIX.'bank_account a CROSS JOIN (SELECT CAST(CAST(COALESCE(SUM(amount),0) AS DECIMAL(30,8)) AS CHAR) AS balance FROM '.MAIN_DB_PREFIX.'bank WHERE fk_account='.$id.') totals LEFT JOIN (SELECT rowid,dateo,datev,fk_type,label,amount FROM '.MAIN_DB_PREFIX.'bank WHERE fk_account='.$id.' ORDER BY dateo DESC,rowid DESC LIMIT 101) b ON 1=1 WHERE a.rowid='.$id.' AND a.entity='.$this->entity.' AND a.ref='.$c->quote($reference).' AND a.currency_code='.$c->quote($currency).' AND a.courant=2 AND a.clos=0 ORDER BY b.dateo DESC,b.rowid DESC');
            if(!$rows)throw new RuntimeException('Native cash account changed during read.');
            $account['balance']=self::decimal($rows[0]['balance']);$account['has_more']=count($rows)>100;$account['entries']=[];
            foreach(array_slice($rows,0,100)as$row){if($row['rowid']===null)continue;$account['entries'][]=['id'=>(int)$row['rowid'],'date'=>$row['dateo'],'value_date'=>$row['datev'],'label'=>$row['label'],'opening'=>$row['fk_type']==='SOLD','amount'=>self::decimal($row['amount'])];}
            $accounts[]=$account;
        }
        // Revocation or a scope downgrade during the read must discard all financial results.
        $this->authorize($office,'treasury');if($this->access->recordScope($office,'treasury')!=='branch')throw new DomainException('Branch treasury unavailable.');
        return ['restricted'=>false,'accounts'=>$accounts];
    }
}
