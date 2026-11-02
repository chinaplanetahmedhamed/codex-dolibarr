<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseWorkspaceStore.php';
require_once __DIR__.'/OfficeExpenseStaffAccess.php';
require_once __DIR__.'/OfficeExpenseWorkspaceDocument.php';
require_once __DIR__.'/OfficeExpenseFinancialLock.php';

/** Reviewed branch mappings; never creates accounts, changes base currency or enables core modules. */
final class OfficeExpenseFinanceSettings
{
    public const ROLES=['expense','owner_funding','transfer_clearing','customer_funds','customer_receivable','staff_advance','salary_expense','salary_deductions','fx_gain','fx_loss','rounding','employee_payable','opening_equity'];
    private $db;private $conf;private OfficeExpenseStaffAccess $access;private OfficeExpenseWorkspaceStore $store;private OfficeExpenseNativeConnection $c;
    public function __construct($db,$conf,OfficeExpenseStaffAccess $access)
    {if(($GLOBALS['db']??null)!==$db||($GLOBALS['conf']??null)!==$conf)throw new DomainException('Native financial context differs.');$access->financeActorId();$this->db=$db;$this->conf=$conf;$this->access=$access;$this->store=new OfficeExpenseWorkspaceStore($db);$this->c=new OfficeExpenseNativeConnection($db);}
    public function base(): string
    { $base=(string)($this->conf->currency??'');if(!in_array($base,['USD','EGP','CNY'],true))throw new DomainException('Review the native base-currency precision before enabling finance.');return $base; }
    public function load(string $office,bool $locked=false): array
    {
        $this->access->recordScope($office,'settings');return $this->internal($office,$locked);
    }
    public function internal(string $office,bool $locked=false): array
    {
        if(!isset(OfficeExpenseStaffAccess::OFFICES[$office]))throw new DomainException('Branch unavailable.');
        $rows=$this->store->list((int)$this->conf->entity,$office,'finance_settings');
        $row=$rows[0]??null;if($row&&$locked)$row=$this->store->locked((int)$this->conf->entity,$row['id']);
        return $row??['id'=>'','revision'=>0,'office'=>$office,'content'=>['enabled'=>false,'base_currency'=>$this->base(),'roles'=>['approve'=>[],'post'=>[],'reverse'=>[]],'accounts'=>array_fill_keys(self::ROLES,0),'money'=>[],'category_accounts'=>[],'journal_id'=>0,'expense_journal_id'=>0,'fx_policy'=>'','require_evidence'=>true,'expense_type_id'=>0,'offset_payment_id'=>0,'expense_tax_mode'=>'','customer_mode'=>'selected','customer_ids'=>[],'invoice_ids'=>[],'adjustment_journal_id'=>0,'opening_journal_id'=>0]];
    }
    public function coreEnabled(): bool {return !empty($this->conf->accounting->enabled);}
    public function metadata(): array
    {
        if(!$this->access->isFinanceAdministrator())throw new DomainException('Financial setup metadata requires administrator access.');
        if(!$this->coreEnabled())return ['enabled'=>false,'base_currency'=>$this->base(),'chart'=>'','accounts'=>[],'journals'=>[],'money_accounts'=>[]];
        $chart=(string)($this->conf->global->CHARTOFACCOUNTS??'');$e=(int)$this->conf->entity;$q=$this->c->quote(...);
        return ['enabled'=>true,'base_currency'=>$this->base(),'chart'=>$chart,
            'accounts'=>$chart===''?[]:$this->c->rows('SELECT rowid,account_number,label,pcg_type FROM '.MAIN_DB_PREFIX.'accounting_account WHERE entity='.$e.' AND active=1 AND fk_pcg_version='.$q($chart).' ORDER BY account_number LIMIT 10001'),
            'expense_types'=>$this->c->rows('SELECT id,code,label FROM '.MAIN_DB_PREFIX.'c_type_fees WHERE active=1 ORDER BY code'),
            'payment_types'=>$this->c->rows('SELECT id,code,libelle,type FROM '.MAIN_DB_PREFIX.'c_paiement WHERE active=1 AND entity IN (0,'.$e.') ORDER BY code'),
            'customers'=>$this->c->rows('SELECT rowid,nom,code_client FROM '.MAIN_DB_PREFIX.'societe WHERE entity='.$e.' AND client IN (1,2,3) ORDER BY nom LIMIT 1001'),
            'invoices'=>$this->c->rows('SELECT rowid,ref,fk_soc,multicurrency_code FROM '.MAIN_DB_PREFIX.'facture WHERE entity='.$e.' AND fk_statut IN (1,2) AND type<>2 ORDER BY rowid DESC LIMIT 1001'),
            'periods'=>$this->c->rows('SELECT rowid,label,date_start,date_end,statut FROM '.MAIN_DB_PREFIX.'accounting_fiscalyear WHERE entity='.$e.' ORDER BY date_start DESC'),
            'journals'=>$this->c->rows('SELECT rowid,code,label,nature FROM '.MAIN_DB_PREFIX.'accounting_journal WHERE entity='.$e.' AND active=1 ORDER BY code'),
            // Safe operational labels only: no bank number, IBAN or bank address.
            'money_accounts'=>$this->c->rows('SELECT rowid,ref,label,currency_code,courant FROM '.MAIN_DB_PREFIX.'bank_account WHERE entity='.$e.' AND clos=0 ORDER BY ref')];
    }
    public function save(string $office,array $input,string $key,int $expected): array
    {return OfficeExpenseFinancialLock::run($this->db,(int)$this->conf->entity,fn()=>$this->saveUnlocked($office,$input,$key,$expected));}
    private function saveUnlocked(string $office,array $input,string $key,int $expected): array
    {
        if(!$this->access->isFinanceAdministrator())throw new DomainException('Only the finance administrator can approve account/role mappings.');
        $this->access->assertAction($office,'settings','update');
        $allowed=['enabled','base_currency','roles','accounts','money','category_accounts','journal_id','expense_journal_id','fx_policy','require_evidence','expense_type_id','offset_payment_id','expense_tax_mode','customer_mode','customer_ids','invoice_ids','adjustment_journal_id','opening_journal_id'];
        if(array_diff(array_keys($input),$allowed)||array_diff($allowed,array_keys($input)))throw new InvalidArgumentException('Incomplete or unexpected financial configuration.');
        if(!in_array($input['enabled'],['0','1'],true)||!in_array($input['require_evidence'],['0','1'],true)||$input['base_currency']!==$this->base())throw new InvalidArgumentException('Native base currency or finance switch differs.');
        $v=['enabled'=>$input['enabled']==='1','base_currency'=>$this->base(),'roles'=>[],'accounts'=>[],'money'=>[],'category_accounts'=>[],'journal_id'=>self::id($input['journal_id']),'expense_journal_id'=>self::id($input['expense_journal_id']),'fx_policy'=>$input['fx_policy'],'require_evidence'=>$input['require_evidence']==='1','expense_type_id'=>self::id($input['expense_type_id']),'offset_payment_id'=>self::id($input['offset_payment_id']),'expense_tax_mode'=>$input['expense_tax_mode'],'customer_mode'=>$input['customer_mode'],'customer_ids'=>self::ids($input['customer_ids']),'invoice_ids'=>self::ids($input['invoice_ids']),'adjustment_journal_id'=>self::id($input['adjustment_journal_id']),'opening_journal_id'=>self::id($input['opening_journal_id'])];
        if(!in_array($v['customer_mode'],['selected','all_company_customers'],true))throw new InvalidArgumentException('Explicit native customer visibility is required.');
        if(!in_array($v['expense_tax_mode'],['','gross_no_tax_recovery'],true))throw new InvalidArgumentException('Explicit expense tax treatment is required.');
        if(!in_array($v['fx_policy'],['','historical_with_realized_difference'],true))throw new InvalidArgumentException('Unsupported FX policy.');
        if(!is_array($input['roles'])||array_diff(array_keys($input['roles']),['approve','post','reverse']))throw new InvalidArgumentException('Explicit approval/payment/reversal assignments required.');
        $members=$this->access->members($office);$eligible=[];foreach($members as$m)if($m['active']&&$m['record_scope']==='branch')$eligible[]=$m['user_id'];
        foreach(['approve','post','reverse']as$action){$ids=$input['roles'][$action]??[];if(!is_array($ids)||!array_is_list($ids))throw new InvalidArgumentException('Invalid financial-role list.');$v['roles'][$action]=[];foreach($ids as$id){$id=self::id($id);if($id<1||!in_array($id,$eligible,true)||in_array($id,$v['roles'][$action],true))throw new DomainException('A financial delegate must be an active branch-scoped member.');$v['roles'][$action][]=$id;}}
        if(!is_array($input['accounts'])||array_diff(array_keys($input['accounts']),self::ROLES)||array_diff(self::ROLES,array_keys($input['accounts'])))throw new InvalidArgumentException('All financial account roles must be explicit.');
        foreach($input['accounts']as$role=>$id)$v['accounts'][$role]=self::id($id);
        if(!is_array($input['category_accounts'])||!is_array($input['money']))throw new InvalidArgumentException('Invalid account mapping.');
        foreach($input['category_accounts']as$ref=>$id){if(!preg_match('/^[a-f0-9]{32}$/D',(string)$ref))throw new InvalidArgumentException('Invalid category mapping.');$category=$this->store->find((int)$this->conf->entity,$ref);if(!$category||$category['office']!==$office||$category['kind']!=='category')throw new DomainException('Category is outside this branch.');$v['category_accounts'][$ref]=self::id($id);}
        foreach($input['money']as$currency=>$channels){if(!in_array($currency,OfficeExpenseStaffAccess::OFFICES[$office],true)||!is_array($channels))throw new DomainException('Money account currency is outside this branch.');$v['money'][$currency]=[];foreach($channels as$channel=>$map){if(!in_array($channel,['cash','wallet','transfer','cheque'],true)||!is_array($map)||array_diff(array_keys($map),['native_id','gl_id','payment_id'])||!isset($map['native_id'],$map['gl_id']))throw new InvalidArgumentException('Invalid payment channel.');$v['money'][$currency][$channel]=['native_id'=>self::id($map['native_id']),'gl_id'=>self::id($map['gl_id']),'payment_id'=>self::id($map['payment_id']??'0')];}}
        foreach($v['money']as$currency=>$channels)foreach($channels as$channel=>$map)if($map['native_id']>0)$this->moneyIdentity($office,$currency,$channel,$map['native_id']);
        $old=$this->internal($office);$v['_money_ownership']=$old['content']['_money_ownership']??[];
        foreach($v['money']as$channels)foreach($channels as$map)if($map['native_id']>0){$v['_money_ownership'][]=$map['native_id'];}$v['_money_ownership']=array_values(array_unique($v['_money_ownership']));
        $other=$this->internal($office==='egypt'?'china':'egypt');
        if(array_intersect($v['_money_ownership'],$other['content']['_money_ownership']??[]))throw new DomainException('A native money account is already owned by the other branch.');
        foreach($v['customer_ids']as$id)if(!$this->c->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE rowid='.$id.' AND entity='.(int)$this->conf->entity.' AND client IN (1,2,3)'))throw new DomainException('Assigned customer is outside this entity.');
        foreach($v['invoice_ids']as$id){$invoice=$this->c->one('SELECT fk_soc FROM '.MAIN_DB_PREFIX.'facture WHERE rowid='.$id.' AND entity='.(int)$this->conf->entity);if(!$invoice||($v['customer_mode']==='selected'&&!in_array((int)$invoice['fk_soc'],$v['customer_ids'],true)))throw new DomainException('Assigned invoice is outside the reviewed branch customer scope.');}
        $v['_money_bindings']=$old['content']['_money_bindings']??[];
        foreach($v['money']as$channels)foreach($channels as$map)if($map['native_id']>0){$id=(string)$map['native_id'];if(isset($v['_money_bindings'][$id])&&$v['_money_bindings'][$id]!==$map['gl_id'])throw new DomainException('A previously enabled native money/GL binding requires a separate reviewed migration.');if($v['enabled'])$v['_money_bindings'][$id]=$map['gl_id'];}
        if($v['enabled'])$this->assertReady($office,$v);
        $old=$this->internal($office);$check=function()use($office){if(!$this->access->isFinanceAdministrator())throw new DomainException('Configuration authority revoked.');$this->access->assertAction($office,'settings','update');};
        return $old['id']===''?$this->store->create((int)$this->conf->entity,$this->access->financeActorId(),$office,'finance_settings',$key,bin2hex(random_bytes(16)),'finance_settings',$v,$check):$this->store->change((int)$this->conf->entity,$this->access->financeActorId(),$old['id'],$expected,'update',$v,fn($r)=>$check());
    }
    public function assertReady(string $office,array $v): void
    {
        if(!$v['enabled']||!$this->coreEnabled()||$v['base_currency']!==$this->base())throw new DomainException('Native double-entry accounting and reviewed branch mappings are required.');
        if($v['expense_tax_mode']!=='gross_no_tax_recovery')throw new DomainException('Review the gross expense/no automatic input-tax-recovery policy first.');
        $fee=$this->c->one('SELECT id FROM '.MAIN_DB_PREFIX.'c_type_fees WHERE id='.$v['expense_type_id'].' AND active=1');if(!$fee)throw new DomainException('Select the native expense type.');
        $offset=$this->paymentType($v['offset_payment_id']);if((int)$offset['type']!==3)throw new DomainException('Select an explicit noncash compensation payment type.');
        if((string)($this->conf->global->CHARTOFACCOUNTS??'')==='')throw new DomainException('Select the native chart of accounts first.');
        foreach(['journal_id'=>4,'expense_journal_id'=>5,'adjustment_journal_id'=>1,'opening_journal_id'=>9]as$key=>$nature){if($v[$key]<1)$this->missing('journal');if((int)$this->journal($v[$key])['nature']!==$nature)throw new DomainException('Native journal nature differs from its financial role.');}
        foreach(self::ROLES as$role){if($v['accounts'][$role]<1)$this->missing($role);$this->account($v['accounts'][$role]);}
        foreach(OfficeExpenseStaffAccess::OFFICES[$office]as$currency){$map=$v['money'][$currency]['cash']??null;if(!$map||$map['native_id']<1||$map['gl_id']<1)$this->missing($currency.' cash');$this->moneyAccount($office,$currency,'cash',$map);}
        foreach($v['money']as$currency=>$channels)foreach($channels as$channel=>$map)if($map['native_id']>0||$map['gl_id']>0){$a=$this->moneyAccount($office,$currency,$channel,$map);if((int)$a['fk_accountancy_journal']!==$v['journal_id'])throw new DomainException('Native cash/bank journal differs from the reviewed branch journal.');}
        foreach($v['category_accounts']as$id)if($id>0)$this->account($id);
        $cashIds=[];foreach($v['money']as$channels)foreach($channels as$map)if($map['gl_id']>0)$cashIds[]=$map['gl_id'];
        if(array_intersect(array_values($v['accounts']),$cashIds)||array_intersect(array_values($v['category_accounts']),$cashIds))throw new DomainException('A cash-control account cannot also be its own expense/liability counterpart.');
    }
    private function missing(string $role): never {throw new DomainException('Reviewed native mapping missing: '.$role);}
    public function account(int $id): array
    {
        $chart=(string)($this->conf->global->CHARTOFACCOUNTS??'');$a=$this->c->one('SELECT rowid,account_number,label FROM '.MAIN_DB_PREFIX.'accounting_account WHERE rowid='.$id.' AND entity='.(int)$this->conf->entity.' AND active=1 AND fk_pcg_version='.$this->c->quote($chart));
        if(!$a||$chart==='')throw new DomainException('Mapped native ledger account is unavailable.');return $a;
    }
    public function journal(int $id): array
    {
        $j=$this->c->one('SELECT rowid,code,label,nature FROM '.MAIN_DB_PREFIX.'accounting_journal WHERE rowid='.$id.' AND entity='.(int)$this->conf->entity.' AND active=1');if(!$j)throw new DomainException('Mapped native journal is unavailable.');return $j;
    }
    public function moneyIdentity(string $office,string $currency,string $channel,int $nativeId): array
    {
        require_once __DIR__.'/OfficeExpenseNativeTreasury.php';
        $a=$this->c->one('SELECT rowid,ref,label,currency_code,courant,clos,account_number,fk_accountancy_journal FROM '.MAIN_DB_PREFIX.'bank_account WHERE rowid='.$nativeId.' AND entity='.(int)$this->conf->entity);
        if(!$a||(int)$a['clos']!==0||$a['currency_code']!==$currency||!in_array($currency,OfficeExpenseStaffAccess::OFFICES[$office],true))throw new DomainException('Mapped native money account differs.');
        if($channel==='cash'&&((int)$a['courant']!==2||$a['ref']!==OfficeExpenseNativeTreasury::REFERENCES[$office][$currency]))throw new DomainException('The existing branch cash mapping cannot be replaced.');
        if($channel!=='cash'&&(int)$a['courant']===2)throw new DomainException('A noncash channel cannot post into a cash drawer.');
        return $a;
    }
    public function moneyAccount(string $office,string $currency,string $channel,array $map): array
    {
        $a=$this->moneyIdentity($office,$currency,$channel,$map['native_id']);
        $a['gl']=$this->account($map['gl_id']);
        if((string)$a['account_number']!==(string)$a['gl']['account_number'])throw new DomainException('Configure the same reviewed GL account on the native bank/cash account first.');
        $a['payment']=$channel==='cash'?$this->c->one("SELECT id,code,libelle,type FROM ".MAIN_DB_PREFIX."c_paiement WHERE code='LIQ' AND active=1 AND entity IN (0,".(int)$this->conf->entity.")"):$this->paymentType($map['payment_id']??0);
        if(!$a['payment']||!in_array((int)$a['payment']['type'],[0,1,2],true))throw new DomainException('Native cash/bank payment type unavailable.');return $a;
    }
    public function paymentType(int $id): array
    {
        $p=$this->c->one('SELECT id,code,libelle,type FROM '.MAIN_DB_PREFIX.'c_paiement WHERE id='.$id.' AND active=1 AND entity IN (0,'.(int)$this->conf->entity.')');if(!$p)throw new DomainException('Native payment type unavailable.');return $p;
    }
    private static function ids($values): array
    {
        if(!is_array($values)||!array_is_list($values)||count($values)>2000)throw new InvalidArgumentException('Invalid native identity scope.');$out=[];foreach($values as$value){$id=self::id($value);if($id<1||in_array($id,$out,true))throw new InvalidArgumentException('Invalid or repeated native identity.');$out[]=$id;}return $out;
    }
    private static function id($value): int {return OfficeExpenseWorkspaceDocument::number($value,true);}
}
