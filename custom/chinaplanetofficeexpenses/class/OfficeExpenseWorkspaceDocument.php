<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseMoney.php';
require_once __DIR__.'/OfficeExpenseWorkspaceLogo.php';

/** Validated operational documents. This class never writes a cash balance or native payment. */
final class OfficeExpenseWorkspaceDocument
{
    public const SECTIONS=['category'=>'categories','employee'=>'employees','payroll'=>'payroll','settings'=>'settings','deposit'=>'treasury','receipt'=>'receipts','expense'=>'expenses','advance'=>'advances','advance_return'=>'advances','advance_settlement'=>'advances'];
    public const ICONS=['folder','building','bolt','truck-fast','utensils','print','bullhorn','wrench','receipt','users'];
    private OfficeExpenseMoney $money;
    public function __construct(OfficeExpenseMoney $money){$this->money=$money;}
    public function validate(string $kind,string $office,array $p): array
    {
        if(!isset(self::SECTIONS[$kind])||!isset(['egypt'=>1,'china'=>1][$office]))throw new InvalidArgumentException('Invalid document type or branch.');
        if($kind==='settings'&&!array_key_exists('logo',$p))$p['logo']='';
        $fields=[
            'category'=>['name','icon','active'],
            'employee'=>['user_id','job','phone','salary','currency','hired_on','active','notes'],
            'payroll'=>['user_id','month','base','bonus','deduction','loan_repayment','currency','date','notes'],
            'settings'=>['name','phone','currency','account_id','logo'],
            'deposit'=>['amount','currency','date','source','purpose','notes'],
            'receipt'=>['amount','currency','date','payer','phone','method','purpose','notes'],
            'expense'=>['amount','currency','date','category_ref','purpose','source_ref','notes'],
            'advance'=>['amount','currency','date','purpose','source_ref','notes'],
            'advance_return'=>['amount','currency','date','purpose','source_ref','settles_ref','notes'],
            'advance_settlement'=>['amount','currency','date','purpose','source_ref','settles_ref','category_ref','notes'],
        ][$kind];
        if(array_diff(array_keys($p),$fields)||array_diff($fields,array_keys($p)))throw new InvalidArgumentException('Unexpected or missing document fields.');
        $v=[];foreach($fields as$field){
            $value=$p[$field];
            if($field==='logo'){$v[$field]=OfficeExpenseWorkspaceLogo::validate($value);}
            elseif($field==='currency'){$expected=$office==='egypt'?'EGP':'CNY';$allowed=$kind==='settings'?[$expected]:[$expected,'USD'];if(!in_array($value,$allowed,true))throw new InvalidArgumentException('Currency does not match branch configuration.');$v[$field]=$value;}
            elseif(in_array($field,['amount','salary','base','bonus','deduction','loan_repayment'],true)){$currency=$p['currency']??'';if(!is_string($currency))throw new InvalidArgumentException('Invalid currency.');$v[$field]=$this->money->amount($value,$currency);if($field==='amount'&&bccomp($v[$field],'0',4)<=0)throw new InvalidArgumentException('Amount must be positive.');}
            elseif(in_array($field,['user_id','account_id'],true)){$v[$field]=self::number($value,$field==='account_id');}
            elseif(in_array($field,['date','hired_on'],true)){OfficeExpenseMoney::date($value);$v[$field]=$value;}
            elseif($field==='month'){if(!is_string($value)||!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D',$value))throw new InvalidArgumentException('Invalid salary month.');OfficeExpenseMoney::date($value.'-01');$v[$field]=$value;}
            elseif($field==='active'){if(!in_array($value,['0','1'],true))throw new InvalidArgumentException('Invalid enabled state.');$v[$field]=$value==='1';}
            elseif($field==='icon'){if(!in_array($value,self::ICONS,true))throw new InvalidArgumentException('Unsupported icon.');$v[$field]=$value;}
            elseif($field==='method'){if(!in_array($value,['cash','wallet','transfer','cheque'],true))throw new InvalidArgumentException('Unsupported payment method.');$v[$field]=$value;}
            else{$optional=in_array($field,['phone','notes','category_ref'],true);if(!is_string($value))throw new InvalidArgumentException('Text must be a string.');$value=trim(str_replace(["\r\n","\r"],"\n",$value));if($value!==''||!$optional)OfficeExpenseMoney::text($field==='notes'?str_replace("\n",' ',$value):$value,$field==='notes'?2000:($field==='purpose'?1000:200));$v[$field]=$value;}
        }
        if($kind==='payroll'){
            $v['gross']=$this->money->amount($this->money->total([$v['base'],$v['bonus']],$v['currency']),$v['currency']);$deductions=$this->money->total([$v['deduction'],$v['loan_repayment']],$v['currency']);
            $v['net']=bcsub($v['gross'],$deductions,strlen(explode('.',$v['base'])[1]??''));
            if(bccomp($v['net'],'0',4)<0)throw new InvalidArgumentException('Deductions exceed gross salary.');
        }
        return $v;
    }
    public function objectKey(string $kind,array $v,string $ref): string
    {
        if($kind==='payroll')return 'payroll:'.$v['user_id'].':'.$v['month'];
        if($kind==='employee')return 'employee:'.$v['user_id'];
        return $kind==='settings'?'settings':$kind.':'.$ref;
    }
    public static function number($value,bool $zero=false): int
    {
        if(!is_string($value)||!preg_match($zero?'/^(0|[1-9][0-9]{0,8})$/D':'/^[1-9][0-9]{0,8}$/D',$value))throw new InvalidArgumentException('Invalid native identity.');return (int)$value;
    }
}
