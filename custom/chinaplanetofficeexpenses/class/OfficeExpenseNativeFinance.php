<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseFinanceSettings.php';
require_once __DIR__.'/OfficeExpenseFinancialPlan.php';

/** Native money/bookkeeping bridge. It can only run inside the source/audit transaction. */
final class OfficeExpenseNativeFinance
{
    private $db;private $user;private $conf;private OfficeExpenseStaffAccess $access;private OfficeExpenseFinanceSettings $settings;private OfficeExpenseNativeConnection $c;
    public function __construct($db,$user,$conf,OfficeExpenseStaffAccess $access)
    {
        if(($GLOBALS['db']??null)!==$db||($GLOBALS['user']??null)!==$user||($GLOBALS['conf']??null)!==$conf||!$access->usesContext($user,(int)$conf->entity))throw new DomainException('Native financial context differs.');
        $this->db=$db;$this->user=$user;$this->conf=$conf;$this->access=$access;$this->settings=new OfficeExpenseFinanceSettings($db,$conf,$access);$this->c=new OfficeExpenseNativeConnection($db);
    }
    public function today(): string {return (string)dol_print_date(dol_now(),'%Y-%m-%d','tzserver');}
    public function customer(int $id,array $config): array
    {
        if(!$this->access->isFinanceAdministrator()&&$config['customer_mode']!=='all_company_customers'&&!in_array($id,$config['customer_ids'],true))throw new DomainException('Native customer is not assigned to this branch.');
        $p=$this->c->one('SELECT rowid,nom,code_client FROM '.MAIN_DB_PREFIX.'societe WHERE rowid='.$id.' AND entity='.(int)$this->conf->entity.' AND client IN (1,2,3)');if(!$p)throw new DomainException('Native customer unavailable.');return $p;
    }
    public function post(array $row,array $plan,array $config,bool $reverse=false): array
    {
        if($this->db->transaction_opened!==1)throw new LogicException('Native financial writer requires the source outer transaction.');
        $this->settings->assertReady($row['office'],$config);$this->period($plan['date']);
        foreach($plan['lines']as$line){$this->nativeAmount($line['debit']);$this->nativeAmount($line['credit']);if(in_array($line['role'],['fx_gain','fx_loss'],true)&&$config['fx_policy']!=='historical_with_realized_difference')throw new DomainException('Review the realized FX policy before creating native documents.');}

        require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
        $party=null;if($row['kind']==='receipt')$party=$this->customer($plan['party_id'],$config);
        $sourceRow=$this->c->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace WHERE entity='.(int)$this->conf->entity.' AND record_ref='.$this->c->quote($row['id']));if(!$sourceRow)throw new DomainException('Financial source unavailable.');
        $native=['resolved_accounts'=>[],'resolved_journals'=>[]];foreach(array_unique(array_merge(array_values($config['accounts']),array_values($config['category_accounts'])))as$glId)if($glId>0)$native['resolved_accounts'][$glId]=$this->settings->account($glId)['account_number'];foreach(['journal_id','expense_journal_id','adjustment_journal_id','opening_journal_id']as$journalKey)$native['resolved_journals'][$config[$journalKey]]=$this->settings->journal($config[$journalKey])['code'];$account=null;$map=null;$bankId=0;$payment=null;$expense=null;$expenseLine=0;
        $sign=$reverse?-1:1;$baseAmount=$this->nativeAmount($plan['valuation']['base_amount']);
        if(bccomp($plan['cash_amount'],'0',2)!==0){
            $map=$config['money'][$plan['currency']][$plan['method']]??null;if(!$map)throw new DomainException('Review the branch payment-channel account before posting.');
            $mapped=$this->settings->moneyAccount($row['office'],$plan['currency'],$plan['method'],$map);$id=(int)$mapped['rowid'];
            $locked=$this->c->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'bank_account WHERE rowid='.$id.' AND entity='.(int)$this->conf->entity.' FOR UPDATE');if(!$locked)throw new DomainException('Native money account unavailable.');
            $account=new Account($this->db);$this->ok($account->fetch($id),'Native money account fetch');
            if($account->currency_code!==$plan['currency']||(int)$account->status!==0)throw new DomainException('Native money account changed.');
            $this->funds($id,$plan['date'],$plan['cash_amount']);$native['account_id']=$id;
        }
        if(in_array($row['kind'],['expense','advance_settlement'],true)){
            [$expense,$expenseLine]=$this->expense($row,$plan,$config,$sign);$native['expense_id']=(int)$expense->id;$native['expense_line_id']=$expenseLine;
            require_once DOL_DOCUMENT_ROOT.'/expensereport/class/paymentexpensereport.class.php';$payment=new PaymentExpenseReport($this->db);$payment->datep=self::timestamp($plan['date']);$payment->fk_expensereport=$expense->id;
            $payment->amounts=[$expense->id=>$this->nativeAmount(bcmul($baseAmount,(string)$sign,2))];$payment->fk_typepayment=$map?(int)$mapped['payment']['id']:$config['offset_payment_id'];$payment->num_payment='CPO-'.$row['id'].($reverse?'-R':'');$payment->note_public=$row['content']['purpose'];
            $this->ok($payment->create($this->user),'Native expense payment create');$native['expense_payment_id']=(int)$payment->id;$this->ok($expense->setPaid($expense->id,$this->user,1),'Native expense paid state');
        }elseif($row['kind']==='payroll'){
            if(empty($this->conf->salaries->enabled))throw new DomainException('Enable the native salary module before posting payroll.');
            require_once DOL_DOCUMENT_ROOT.'/salaries/class/salary.class.php';require_once DOL_DOCUMENT_ROOT.'/salaries/class/paymentsalary.class.php';
            // Core Salary represents the net payable; gross/deductions/loan remain distinct in native GL.
            $salary=new Salary($this->db);$salary->fk_user=$plan['staff_id'];$salary->label='CPO '.($reverse?'REV ':'').$row['content']['month'].' '.$row['id'];$salary->amount=$this->nativeAmount($plan['cash_base']==='0.00'?'0.00':bcsub('0',$plan['cash_base'],2));$salary->datesp=self::timestamp($row['content']['month'].'-01');$salary->dateep=self::timestamp(date('Y-m-t',strtotime($row['content']['month'].'-01')));$salary->note='Office '.$row['office'].'; gross '.$row['content']['gross'].' '.$plan['currency'].'; source '.$row['id'];
            $this->ok($salary->create($this->user),'Native salary create');$native['salary_id']=(int)$salary->id;
            if(bccomp($plan['cash_base'],'0',2)!==0){$payment=new PaymentSalary($this->db);$payment->datep=self::timestamp($plan['date']);$payment->datev=$payment->datep;$payment->amounts=[$salary->id=>$salary->amount];$payment->fk_typepayment=(int)$mapped['payment']['id'];$payment->num_payment='CPO-'.$row['id'].($reverse?'-R':'');$this->ok($payment->create($this->user,1),'Native salary payment create');$native['salary_payment_id']=(int)$payment->id;}
            else $this->ok($salary->setPaid($this->user),'Native zero-net salary paid state');
        }elseif($row['kind']==='receipt'&&$plan['invoice_id']>0){
            [$payment,$invoice]=$this->invoicePayment($row,$plan,$config,$reverse);$native['customer_payment_id']=(int)$payment->id;$native['invoice_id']=(int)$invoice->id;
        }
        if($account){
            $roles=array_values(array_filter(array_column($plan['lines'],'role'),static fn($r)=>$r!=='cash'));$counter=count($roles)===1?$roles[0]:'';if($expense)$counter='employee_payable';$counterCode=$counter===''?'':$this->settings->account($config['accounts'][$counter])['account_number'];
            $native['resolved_accounts'][$map['gl_id']]=$mapped['gl']['account_number'];
            $bankId=$account->addline(self::timestamp($plan['date']),$mapped['payment']['code'],'CPO '.$row['office'].' '.$row['kind'].' '.$row['id'].($reverse?' REV':''),$this->nativeAmount($plan['cash_amount']),'',0,$this->user,'','',$counterCode,self::timestamp($plan['date']),'',$this->nativeAmount($plan['cash_base']),'Source '.$row['id']);$this->ok($bankId,'Native bank movement create');$native['bank_id']=$bankId;
            if($payment){$this->ok($payment->update_fk_bank($bankId),'Native payment-bank link');
                if($payment instanceof PaymentExpenseReport){$type='payment_expensereport';$path='/expensereport/payment/card.php?rowid=';}
                elseif($payment instanceof PaymentSalary){$type='payment_salary';$path='/salaries/payment_salary/card.php?id=';}
                else{$type='payment';$path='/compta/paiement/card.php?id=';}
                $this->ok($account->add_url_line($bankId,(int)$payment->id,DOL_URL_ROOT.$path,'(paiement)',$type),'Native payment URL');
            }
            $url=DOL_URL_ROOT.'/custom/chinaplanetofficeexpenses/workspace.php?office='.$row['office'].'&view='.OfficeExpenseWorkspaceDocument::SECTIONS[$row['kind']].'&edit='.$row['id'].'#financial-record-';
            $this->ok($account->add_url_line($bankId,(int)$sourceRow['rowid'],$url,$row['id'],'cpo_office'),'Native source-bank URL');
            if($party)$this->ok($account->add_url_line($bankId,(int)$party['rowid'],DOL_URL_ROOT.'/societe/card.php?socid=','Customer #'.$party['rowid'],'company'),'Native customer-bank URL');
            $this->verifyBank($bankId,$account->id,$plan);
        }
        $groups=[];$lines=$plan['lines'];$workspace=$this->c->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_oe_workspace WHERE entity='.(int)$this->conf->entity.' AND record_ref='.$this->c->quote($row['id']));if(!$workspace)throw new DomainException('Financial source no longer exists.');
        if($expense){
            $expenseBase=$this->nativeAmount(bcmul($baseAmount,(string)$sign,2));$isCredit=bccomp($expenseBase,'0',2)<0;$value=$isCredit?bcsub('0',$expenseBase,2):$expenseBase;
            $expenseLines=[['role'=>'expense','debit'=>$isCredit?'0.00':$value,'credit'=>$isCredit?$value:'0.00'],['role'=>'employee_payable','debit'=>$isCredit?$value:'0.00','credit'=>$isCredit?'0.00':$value]];
            $groups[]=['type'=>'expense_report','source'=>(int)$expense->id,'detail'=>$expenseLine,'journal'=>$config['expense_journal_id'],'lines'=>$expenseLines];
            foreach($lines as&$line)if($line['role']==='expense')$line['role']='employee_payable';unset($line);
        }
        $groups[]=['type'=>$bankId>0?'bank':'cpo_office','source'=>$bankId>0?$bankId:(int)$workspace['rowid'],'detail'=>$bankId>0?$bankId:$row['revision'],'journal'=>$bankId>0?$config['journal_id']:$config['adjustment_journal_id'],'lines'=>$lines];
        $native['journals']=[];
        foreach($groups as$group){OfficeExpenseFinancialPlan::assertBalanced($group['lines']);$native['journals'][]=$this->journal($row,$plan,$config,$group,$mapped['gl']??null,$party,$reverse);}
        if($this->db->transaction_opened!==1)throw new RuntimeException('Native API changed the outer transaction boundary.');
        $native['mapping']=['accounts'=>$config['accounts'],'money'=>$config['money'][$plan['currency']][$plan['method']]??null,'journal_id'=>$config['journal_id'],'expense_journal_id'=>$config['expense_journal_id'],'adjustment_journal_id'=>$config['adjustment_journal_id'],'expense_type_id'=>$config['expense_type_id'],'offset_payment_id'=>$config['offset_payment_id'],'expense_account'=>$row['content']['category_ref']??''];$native['plan']=$plan;$native['posted_at']=gmdate('Y-m-d H:i:s');$native['posted_by']=(int)$this->user->id;return $native;
    }
    private function expense(array $row,array $plan,array $config,int $sign): array
    {
        require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';$report=new ExpenseReport($this->db);$report->fk_user_author=$plan['staff_id'];$report->date_debut=self::timestamp($row['content']['date']);$report->date_fin=$report->date_debut;$report->ref_ext='CPO-'.$row['id'].($sign<0?'-R':'');$report->note_private='Office '.$row['office'].'; original '.$row['content']['amount'].' '.$plan['currency'].'; source '.$row['id'];
        $this->ok($report->create($this->user,1),'Native expense report create');$value=$this->nativeAmount(bcmul($plan['valuation']['base_amount'],(string)$sign,2));$line=$report->addline(1,$value,$config['expense_type_id'],0,self::timestamp($row['content']['date']),htmlspecialchars($row['content']['purpose'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));$this->ok($line,'Native expense line create');
        $roleId=$config['category_accounts'][$row['content']['category_ref']??'']??$config['accounts']['expense'];$ledger=$this->settings->account($roleId);
        $detail=new ExpenseReportLine($this->db);$this->ok($detail->fetch($line),'Native expense line fetch');$this->ok($detail->setValueFrom('fk_code_ventilation',$ledger['rowid'],'expensereport_det',$line,'int','rowid',$this->user,'',''),'Native expense account binding');
        $this->ok($report->setValidate($this->user,1),'Native expense report validation');$this->ok($report->setApproved($this->user,1),'Native expense approval');return [$report,(int)$line];
    }
    public function invoiceValuation(array $row,array $intent,array $config): ?array
    {
        $id=$intent['invoice_id'];if($row['kind']!=='receipt'||$id===0)return null;$this->customer($intent['party_id'],$config);
        if(!$this->access->isFinanceAdministrator()&&!in_array($id,$config['invoice_ids'],true))throw new DomainException('Invoice is not assigned to this branch.');
        $i=$this->c->one('SELECT rowid,fk_soc,fk_statut,type,multicurrency_code,CAST(CAST(multicurrency_tx AS DECIMAL(30,12)) AS CHAR) AS rate FROM '.MAIN_DB_PREFIX.'facture WHERE rowid='.$id.' AND entity='.(int)$this->conf->entity);
        if(!$i||(int)$i['fk_soc']!==$intent['party_id']||!in_array((int)$i['fk_statut'],[1,2],true)||in_array((int)$i['type'],[2,4],true))throw new DomainException('Native customer invoice unavailable.');
        $currency=$i['multicurrency_code']?:$this->conf->currency;
        if($currency!==$row['content']['currency']&&$currency!==$this->conf->currency)throw new DomainException('Receive these funds unallocated until the cross-currency allocation is reviewed.');
        if($currency!==$this->conf->currency&&empty($this->conf->multicurrency->enabled))throw new DomainException('Enable native multicurrency before allocating a foreign-currency invoice.');
        $original=$currency===$this->conf->currency?$intent['valuation']['base_amount']:$row['content']['amount'];
        if(bccomp($i['rate']??'0','0',12)<=0)throw new DomainException('Native invoice exchange-rate evidence is missing.');
        $base=$currency===$this->conf->currency?$original:bcadd(bcdiv($original,$i['rate'],12),'0.005',2);
        $ar=$this->settings->account($config['accounts']['customer_receivable']);
        $ledger=$this->c->rows('SELECT subledger_account FROM '.MAIN_DB_PREFIX.'accounting_bookkeeping WHERE entity='.(int)$this->conf->entity." AND doc_type='customer_invoice' AND fk_doc=".$id.' AND numero_compte='.$this->c->quote($ar['account_number']));
        if(!$ledger)throw new DomainException('Post the native customer invoice to its reviewed receivable account first.');$aux=(string)($ledger[0]['subledger_account']??'');foreach($ledger as$l)if((string)($l['subledger_account']??'')!==$aux)throw new DomainException('Native invoice receivable allocation requires review.');
        return ['base_amount'=>$base,'original_amount'=>$original,'currency'=>$currency,'aux'=>$aux];
    }
    public function verifyPosted(array $posting): void
    {
        foreach($posting['resolved_accounts']??[]as$id=>$number)if($this->settings->account((int)$id)['account_number']!==$number)throw new DomainException('Posted native account mapping changed; reconcile before reversal.');foreach($posting['resolved_journals']??[]as$id=>$code)if($this->settings->journal((int)$id)['code']!==$code)throw new DomainException('Posted native journal mapping changed; reconcile before reversal.');
        $plan=$posting['plan'];if(!empty($posting['bank_id']))$this->verifyBank($posting['bank_id'],$posting['account_id'],$plan);
        foreach($posting['journals']as$journal)foreach($journal['lines']as$line){
            $r=$this->c->one('SELECT numero_compte,doc_type,fk_doc,code_journal,CAST(CAST(debit AS DECIMAL(30,8)) AS CHAR) AS debit,CAST(CAST(credit AS DECIMAL(30,8)) AS CHAR) AS credit FROM '.MAIN_DB_PREFIX.'accounting_bookkeeping WHERE rowid='.$line['id'].' AND entity='.(int)$this->conf->entity);
            if(!$r||$r['numero_compte']!==$line['account']||$r['doc_type']!==$line['type']||(int)$r['fk_doc']!==$line['source']||$r['code_journal']!==$line['journal']||bccomp($r['debit'],$line['debit'],2)!==0||bccomp($r['credit'],$line['credit'],2)!==0)throw new DomainException('Native posting drift requires reconciliation before settlement/reversal.');
        }
    }
    private function invoicePayment(array $row,array $plan,array $config,bool $reverse): array
    {
        $id=$plan['invoice_id'];if(!$this->access->isFinanceAdministrator()&&!in_array($id,$config['invoice_ids'],true))throw new DomainException('Invoice is not assigned to this branch.');
        $invoiceRow=$this->c->one('SELECT rowid,fk_soc,entity,fk_statut,type,multicurrency_code,multicurrency_tx,CAST(CAST(total_ttc AS DECIMAL(30,8)) AS CHAR) AS base_total,CAST(CAST(multicurrency_total_ttc AS DECIMAL(30,8)) AS CHAR) AS original_total FROM '.MAIN_DB_PREFIX.'facture WHERE rowid='.$id.' AND entity='.(int)$this->conf->entity.' FOR UPDATE');
        if(!$invoiceRow||(int)$invoiceRow['fk_soc']!==$plan['party_id']||!in_array((int)$invoiceRow['fk_statut'],[1,2],true)||in_array((int)$invoiceRow['type'],[2,4],true))throw new DomainException('Native customer invoice unavailable.');
        $currency=$invoiceRow['multicurrency_code']?:$this->conf->currency;
        if($currency!==$plan['currency']&&$currency!==$this->conf->currency)throw new DomainException('Record these funds unallocated until an explicit cross-currency invoice allocation is reviewed.');
        require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
        $invoice=new Facture($this->db);$this->ok($invoice->fetch($id),'Native invoice fetch');
        $original=$plan['invoice_original_amount'];$sign=$reverse?-1:1;
        $payments=$this->c->rows('SELECT rowid,CAST(CAST(amount AS DECIMAL(30,8)) AS CHAR) AS amount,CAST(CAST(multicurrency_amount AS DECIMAL(30,8)) AS CHAR) AS original_amount FROM '.MAIN_DB_PREFIX.'paiement_facture WHERE fk_facture='.$id.' FOR UPDATE');$paid='0.00';foreach($payments as$p)$paid=bcadd($paid,$currency===$this->conf->currency?$p['amount']:$p['original_amount'],2);
        $total=$currency===$this->conf->currency?$invoiceRow['base_total']:$invoiceRow['original_total'];if(!$reverse&&bccomp($original,bcsub($total,$paid,2),2)>0)throw new DomainException('Receipt exceeds the remaining native invoice amount.');
        $payment=new Paiement($this->db);$payment->datepaye=self::timestamp($plan['date']);$payment->paiementid=(int)$this->settings->moneyAccount($row['office'],$plan['currency'],$plan['method'],$config['money'][$plan['currency']][$plan['method']])['payment']['id'];$payment->num_paiement='CPO-'.$row['id'].($reverse?'-R':'');
        $payment->amounts=[$id=>$this->nativeAmount(bcmul($plan['invoice_base_amount'],(string)$sign,2))];$payment->multicurrency_code=[$id=>$currency];$payment->multicurrency_amounts=$currency===$this->conf->currency?[]:[$id=>$this->nativeAmount(bcmul($original,(string)$sign,2))];
        $this->ok($payment->create($this->user,1),'Native customer payment create');
        // Native invoice conversion must agree with the approved journal preview, never silently differ.
        if(bccomp(($this->c->one('SELECT CAST(CAST(amount AS DECIMAL(30,8)) AS CHAR) AS amount FROM '.MAIN_DB_PREFIX.'paiement_facture WHERE fk_paiement='.(int)$payment->id.' AND fk_facture='.$id)['amount']??'0'),bcmul($plan['invoice_base_amount'],(string)$sign,2),2)!==0)throw new DomainException('Invoice carrying valuation differs; review the invoice FX allocation before posting.');
        if($reverse&&method_exists($invoice,'set_unpaid'))$this->ok($invoice->set_unpaid($this->user),'Native invoice reopened state');return [$payment,$invoice];
    }
    private function journal(array $row,array $plan,array $config,array $group,?array $cash,?array $party,bool $reverse): array
    {
        $journal=$this->settings->journal($group['journal']);$ids=[];$debit='0.00';$credit='0.00';$piece=null;$evidence=[];
        foreach($group['lines']as$line){
            if(in_array($line['role'],['fx_gain','fx_loss'],true)&&$config['fx_policy']!=='historical_with_realized_difference')throw new DomainException('Review the realized FX policy before posting a carrying-value difference.');
            $account=$line['role']==='cash'?$cash:$this->settings->account($line['role']==='expense'?($config['category_accounts'][$row['content']['category_ref']??'']??$config['accounts']['expense']):$config['accounts'][$line['role']]);if(!$account)throw new DomainException('Mapped ledger account unavailable.');
            $book=new BookKeeping($this->db);$book->doc_type=$group['type'];$book->fk_doc=$group['source'];$book->fk_docdet=$group['detail'];$book->doc_ref='CPO-'.$row['id'].($reverse?'-R':'').'-'.$group['type'];$book->doc_date=self::timestamp($plan['date']);$book->numero_compte=$account['account_number'];$book->label_compte=$account['label'];$book->label_operation=$row['office'].' '.$row['kind'].' '.$row['id'].' '.$line['role'].($reverse?' REV':'');$book->debit=$this->nativeAmount($line['debit']);$book->credit=$this->nativeAmount($line['credit']);$book->montant=bccomp($line['debit'],'0',2)>0?$book->debit:$book->credit;$book->amount=$book->montant;$book->sens=bccomp($line['debit'],'0',2)>0?'D':'C';$book->code_journal=$journal['code'];$book->journal_label=$journal['label'];$book->fk_user_author=$this->user->id;$book->import_key='CPO_OFFICE';
            if(in_array($line['role'],['staff_advance','employee_payable','salary_deductions'],true)){$book->subledger_account='CPO-STAFF-'.$plan['staff_id'];$book->subledger_label=$book->subledger_account;}
            if(in_array($line['role'],['customer_funds','customer_receivable'],true)&&$party){$book->thirdparty_code=$party['code_client'];$book->subledger_account=$line['role']==='customer_receivable'?$plan['invoice_aux']:'CPO-CUSTOMER-'.$party['rowid'];$book->subledger_label=htmlspecialchars($party['nom'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
            $result=$book->create($this->user,1);if(!is_numeric($result)||(int)$result<0||(int)$book->id<1)throw new RuntimeException('Native general-ledger entry create failed; transaction must roll back.');$id=(int)$book->id;$actual=$this->c->one('SELECT rowid,entity,numero_compte,piece_num,code_journal,fk_doc,doc_type,CAST(CAST(debit AS DECIMAL(30,8)) AS CHAR) AS debit,CAST(CAST(credit AS DECIMAL(30,8)) AS CHAR) AS credit FROM '.MAIN_DB_PREFIX.'accounting_bookkeeping WHERE rowid='.$id.' AND entity='.(int)$this->conf->entity);
            if(!$actual||$actual['numero_compte']!==$account['account_number']||$actual['code_journal']!==$journal['code']||(int)$actual['fk_doc']!==$group['source']||$actual['doc_type']!==$group['type']||bccomp($actual['debit'],$line['debit'],2)!==0||bccomp($actual['credit'],$line['credit'],2)!==0)throw new RuntimeException('Native ledger readback differs.');
            if($piece!==null&&$piece!==(int)$actual['piece_num'])throw new RuntimeException('Journal entries did not share one native movement.');$piece=(int)$actual['piece_num'];$debit=bcadd($debit,$actual['debit'],2);$credit=bcadd($credit,$actual['credit'],2);$ids[]=(int)$id;$evidence[]=['id'=>(int)$id,'account'=>$account['account_number'],'debit'=>$line['debit'],'credit'=>$line['credit'],'type'=>$group['type'],'source'=>$group['source'],'journal'=>$journal['code']];
        }
        if(bccomp($debit,$credit,2)!==0)throw new RuntimeException('Native ledger movement is not balanced.');return ['ids'=>$ids,'piece'=>$piece,'journal'=>$journal['code'],'debit'=>$debit,'credit'=>$credit,'lines'=>$evidence];
    }
    private function period(string $date): void
    {
        OfficeExpenseMoney::date($date);$q=$this->c->quote($date);$rows=$this->c->rows('SELECT rowid,statut FROM '.MAIN_DB_PREFIX.'accounting_fiscalyear WHERE entity='.(int)$this->conf->entity.' AND date_start<='.$q.' AND (date_end IS NULL OR date_end>='.$q.') FOR UPDATE');
        if(count($rows)!==1||(int)$rows[0]['statut']!==0)throw new DomainException('A single open native accounting period is required.');
    }
    private function funds(int $id,string $date,string $amount): void
    {
        if(bccomp($amount,'0',2)>=0)return;$rows=$this->c->rows('SELECT rowid,dateo,CAST(CAST(amount AS DECIMAL(30,8)) AS CHAR) AS amount FROM '.MAIN_DB_PREFIX.'bank WHERE fk_account='.$id.' ORDER BY dateo,rowid FOR UPDATE');$balance='0.00';$applied=false;foreach($rows as$r){if(substr($r['dateo'],0,10)>$date&&!$applied){$balance=bcadd($balance,$amount,8);$applied=true;if(bccomp($balance,'0',8)<0)throw new DomainException('Insufficient funds on the selected posting date.');}$balance=bcadd($balance,$r['amount'],8);if($applied&&bccomp($balance,'0',8)<0)throw new DomainException('This backdated movement would overdraw a later recorded balance.');}
        if(!$applied&&bccomp(bcadd($balance,$amount,8),'0',8)<0)throw new DomainException('The selected branch account has insufficient available funds on this date.');
    }
    private function verifyBank(int $id,int $account,array $plan): void
    {
        $b=$this->c->one('SELECT fk_account,dateo,CAST(CAST(amount AS DECIMAL(30,8)) AS CHAR) AS amount,CAST(CAST(amount_main_currency AS DECIMAL(30,8)) AS CHAR) AS base_amount FROM '.MAIN_DB_PREFIX.'bank WHERE rowid='.$id);
        if(!$b||(int)$b['fk_account']!==$account||substr($b['dateo'],0,10)!==$plan['date']||bccomp($b['amount'],$plan['cash_amount'],2)!==0||bccomp($b['base_amount']??'0',$plan['cash_base'],2)!==0)throw new RuntimeException('Native bank original/base linkage did not verify.');
    }
    private function nativeAmount(string $value): string
    { $canonical=bcadd($value,'0',2);if(number_format((float)$canonical,2,'.','')!==$canonical)throw new DomainException('Amount exceeds verified native cent precision.');return $canonical; }
    private function ok($value,string $operation): void {if(!is_numeric($value)||(int)$value<=0)throw new RuntimeException($operation.' failed; native transaction must roll back.');}
    private static function timestamp(string $date): int {OfficeExpenseMoney::date($date);return (int)dol_mktime(12,0,0,(int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4));}
}
