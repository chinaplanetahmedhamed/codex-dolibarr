<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseMoney.php';
require_once __DIR__.'/OfficeExpenseWorkspaceDocument.php';

/** Exact posting preview; account roles are resolved against reviewed native mappings later. */
final class OfficeExpenseFinancialPlan
{
    public const KINDS=['opening','expense','deposit','receipt','advance','advance_return','advance_settlement','payroll'];
    public static function intent(array $row,array $input,string $base,string $today): array
    {
        if(!in_array($row['kind'],self::KINDS,true))throw new DomainException('Document has no financial operation.');
        $allowed=['date','rate','rate_date','rate_source','reason','party_id','invoice_id','staff_id','loan_ref','funding_type','setup_revision','method'];
        if(array_diff(array_keys($input),$allowed))throw new InvalidArgumentException('Unexpected posting input.');
        foreach($input as$value)if(!is_string($value))throw new InvalidArgumentException('Posting fields must be scalar text.');
        $date=$input['date']??'';OfficeExpenseMoney::date($date);OfficeExpenseMoney::date($today);
        if($date>$today)throw new DomainException('Future financial posting is not allowed.');
        if($date<$row['content']['date'])throw new DomainException('Payment date cannot precede the source document date.');
        $currency=$row['content']['currency'];$quote=[];
        if($currency!==$base){$quote=['rate'=>$input['rate']??'','date'=>$input['rate_date']??'','source'=>$input['rate_source']??''];OfficeExpenseMoney::date($quote['date']);if($quote['date']>$date)throw new InvalidArgumentException('Exchange-rate date cannot follow the posting date.');}
        elseif(($input['rate']??'')!==''&&!preg_match('/^1(?:\.0{1,8})?$/D',$input['rate']))throw new InvalidArgumentException('Identity currency requires rate one.');
        $money=new OfficeExpenseMoney();$amount=$row['kind']==='payroll'?$row['content']['gross']:$row['content']['amount'];
        $valuation=$money->convert($amount,$currency,$base,$quote);
        if(bccomp($valuation['base_amount'],'0',2)<=0)throw new DomainException('Positive base-currency valuation is required.');
        $reason=trim($input['reason']??'');if($reason!=='')OfficeExpenseMoney::text($reason,1000);
        $method=$row['kind']==='receipt'?$row['content']['method']:($input['method']??'cash');if(!in_array($method,['cash','wallet','transfer','cheque'],true))throw new InvalidArgumentException('Invalid payment channel.');
        $out=['method'=>$method,'date'=>$date,'valuation'=>$valuation,'reason'=>$reason,'loan_ref'=>$input['loan_ref']??'','funding_type'=>$input['funding_type']??'','setup_revision'=>OfficeExpenseWorkspaceDocument::number($input['setup_revision']??'0',true)];
        foreach(['party_id','invoice_id','staff_id']as$key)$out[$key]=OfficeExpenseWorkspaceDocument::number($input[$key]??'0',true);
        if($out['loan_ref']!==''&&!preg_match('/^[a-f0-9]{32}$/D',$out['loan_ref']))throw new InvalidArgumentException('Invalid advance reference.');
        if($row['kind']==='receipt'&&$out['party_id']<1)throw new DomainException('Select the native customer before receiving funds.');
        if($row['kind']==='deposit'&&!in_array($out['funding_type'],['owner','transfer'],true))throw new DomainException('Classify the funding source before receiving funds.');
        return $out;
    }
    public static function build(array $row,array $intent,?array $allocation=null): array
    {
        $v=$row['content'];$kind=$row['kind'];$valuation=$intent['valuation'];$base=$valuation['base_currency'];$currency=$v['currency'];$money=new OfficeExpenseMoney();
        $quote=$currency===$base?[]:['rate'=>$valuation['rate'],'date'=>$valuation['rate_date'],'source'=>$valuation['rate_source']];
        $convert=static fn(string $amount):string=>$money->convert($amount,$currency,$base,$quote)['base_amount'];
        $lines=[];$line=static function(string $role,string $amount,string $side)use(&$lines):void{if(bccomp($amount,'0',2)!==0)$lines[]=['role'=>$role,'debit'=>$side==='debit'?$amount:'0.00','credit'=>$side==='credit'?$amount:'0.00'];};
        $amount=$valuation['base_amount'];$cash=$v[$kind==='payroll'?'net':'amount'];$cashBase=$convert($cash);$direction=1;
        if($kind==='opening'){$line('cash',$amount,'debit');$line('opening_equity',$amount,'credit');$cash='0.00';$cashBase='0.00';}
        elseif($kind==='expense'){$direction=-1;$line('expense',$amount,'debit');$line('cash',$amount,'credit');}
        elseif($kind==='deposit'){$line('cash',$amount,'debit');$line($intent['funding_type']==='owner'?'owner_funding':'transfer_clearing',$amount,'credit');}
        elseif($kind==='receipt'){
            $line('cash',$amount,'debit');$receivable=$intent['invoice_id']>0?($allocation['base_amount']??null):$amount;if($receivable===null)throw new DomainException('Verified native invoice valuation is required.');
            $line($intent['invoice_id']>0?'customer_receivable':'customer_funds',$receivable,'credit');if($intent['invoice_id']>0)self::balance($lines,$line,'fx');
        }
        elseif($kind==='advance'){$direction=-1;$line('staff_advance',$amount,'debit');$line('cash',$amount,'credit');}
        elseif(in_array($kind,['advance_return','advance_settlement'],true)){
            self::allocation($allocation,$v['amount'],$currency);$carrying=self::carrying($allocation,$v['amount']);
            $line($kind==='advance_return'?'cash':'expense',$amount,'debit');$line('staff_advance',$carrying,'credit');
            self::balance($lines,$line,'fx');if($kind==='advance_settlement'){$cash='0.00';$cashBase='0.00';}
        }elseif($kind==='payroll'){
            $direction=-1;$line('salary_expense',$amount,'debit');$line('cash',$cashBase,'credit');$deduction=$convert($v['deduction']);$line('salary_deductions',$deduction,'credit');
            $loan=$convert($v['loan_repayment']);$carrying='0.00';
            if(bccomp($v['loan_repayment'],'0',2)>0){self::allocation($allocation,$v['loan_repayment'],$currency);$carrying=self::carrying($allocation,$v['loan_repayment']);$line('staff_advance',$carrying,'credit');
                $delta=bcsub($loan,$carrying,2);if(bccomp($delta,'0',2)>0)$line('fx_gain',$delta,'credit');elseif(bccomp($delta,'0',2)<0)$line('fx_loss',bcsub('0',$delta,2),'debit');
            }
            self::balance($lines,$line,'rounding');
        }else throw new DomainException('Unsupported financial document.');
        self::assertBalanced($lines);
        return ['source_bank_id'=>$kind==='opening'?$v['bank_id']:0,'source_bank_date'=>$kind==='opening'?$v['date']:'','source_bank_amount'=>$kind==='opening'?$v['amount']:'0.00','document'=>$row['id'],'kind'=>$kind,'office'=>$row['office'],'date'=>$intent['date'],'currency'=>$currency,'base_currency'=>$base,'cash_amount'=>$direction<0?bcsub('0',$cash,2):$cash,'cash_base'=>$direction<0?bcsub('0',$cashBase,2):$cashBase,'valuation'=>$valuation,'lines'=>$lines,'staff_id'=>$kind==='payroll'?$v['user_id']:$intent['staff_id'],'party_id'=>$intent['party_id'],'invoice_id'=>$intent['invoice_id'],'allocation_ref'=>$v['settles_ref']??$intent['loan_ref'],'allocation_original'=>$kind==='payroll'?$v['loan_repayment']:(in_array($kind,['advance_return','advance_settlement'],true)?$v['amount']:'0.00'),'allocation_base'=>$carrying??'0.00','setup_revision'=>$intent['setup_revision'],'method'=>$intent['method'],'invoice_base_amount'=>$kind==='receipt'&&$intent['invoice_id']>0?$allocation['base_amount']:'0.00','invoice_original_amount'=>$kind==='receipt'&&$intent['invoice_id']>0?$allocation['original_amount']:'0.00','invoice_aux'=>$kind==='receipt'&&$intent['invoice_id']>0?($allocation['aux']??''):''];
    }
    private static function allocation(?array $a,string $amount,string $currency): void
    {
        if(!$a||($a['currency']??null)!==$currency||bccomp($amount,$a['remaining_original'],2)>0||bccomp($a['remaining_original'],'0',2)<=0||bccomp($a['remaining_base'],'0',2)<0)throw new DomainException('Allocation exceeds the outstanding posted advance.');
    }
    public static function carrying(array $a,string $amount): string
    {
        if(bccomp($amount,$a['remaining_original'],2)===0)return $a['remaining_base'];
        return bcadd(bcdiv(bcmul($a['remaining_base'],$amount,8),$a['remaining_original'],8),'0.005',2);
    }
    private static function balance(array $lines,callable $line,string $role): void
    {
        $debit='0.00';$credit='0.00';foreach($lines as$l){$debit=bcadd($debit,$l['debit'],2);$credit=bcadd($credit,$l['credit'],2);}$delta=bcsub($debit,$credit,2);
        if(bccomp($delta,'0',2)>0)$line($role==='fx'?'fx_gain':'rounding',$delta,'credit');elseif(bccomp($delta,'0',2)<0)$line($role==='fx'?'fx_loss':'rounding',bcsub('0',$delta,2),'debit');
    }
    public static function assertBalanced(array $lines): void
    {
        $debit='0.00';$credit='0.00';foreach($lines as$l){if(bccomp($l['debit'],'0',2)<0||bccomp($l['credit'],'0',2)<0||(bccomp($l['debit'],'0',2)>0&&bccomp($l['credit'],'0',2)>0))throw new LogicException('Invalid ledger side.');$debit=bcadd($debit,$l['debit'],2);$credit=bcadd($credit,$l['credit'],2);}
        if(count($lines)<2||bccomp($debit,$credit,2)!==0||bccomp($debit,'0',2)<=0)throw new LogicException('Balanced positive native journal required.');
    }
    public static function reverse(array $plan,string $date,string $today): array
    {
        OfficeExpenseMoney::date($date);if($date<$plan['date']||$date>$today)throw new DomainException('Invalid reversal date.');
        $plan['date']=$date;$plan['cash_amount']=bcsub('0',$plan['cash_amount'],2);$plan['cash_base']=bcsub('0',$plan['cash_base'],2);
        foreach($plan['lines']as&$line){$old=$line['debit'];$line['debit']=$line['credit'];$line['credit']=$old;}unset($line);self::assertBalanced($plan['lines']);$plan['reversal']=true;return $plan;
    }
}
