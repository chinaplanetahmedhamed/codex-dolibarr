<?php
declare(strict_types=1);
// Included before cleanup by the exact-engine M119 finance CI suite only.
// No standalone DB, credential, schema or posting implementation is introduced.
if(PHP_SAPI!=='cli'||!function_exists('ftest')||!isset($db,$finance,$source,$access,$config,$accounts,$name)||!preg_match('/^m119_ci_[a-f0-9]{16}$/D',$name))throw new LogicException('Owned native finance CI context required.');
function m121FinanceRefuses(callable $f):void
{
    try{$f();}catch(DomainException|InvalidArgumentException $expected){return;}
    throw new RuntimeException('Expected financial authority/state refusal.');
}
function m121FinanceContext(int $id):array
{
    global $db,$conf,$user;
    $user=new User($db);fcheck($user->fetch($id),1);
    $acl=new OfficeExpenseStaffAccess($db,$user,$conf,2101);
    $src=new OfficeExpenseWorkspaceService($db,$user,1,$acl);
    return [$src,new OfficeExpenseFinancialService($db,$user,$conf,$acl,$src)];
}
$m121EgyptBefore=(new OfficeExpenseNativeConnection($db))->rows('SELECT fk_account,CAST(SUM(amount) AS DECIMAL(30,2)) AS amount FROM llx_bank WHERE fk_account IN (101,102) GROUP BY fk_account ORDER BY fk_account');
ftest('M121 synthetic China mappings and funding keep CNY and USD accounts separate',function()use($db,$access,$finance,$source,$config,$today){
    $access->assign(2102,'china',OfficeExpenseStaffAccess::ACTIONS,'branch',0);
    $access->assign(2103,'china',['expenses'=>['read','create','update'],'advances'=>['read','create','update']],'own',0);
    foreach([103=>['CPO-CN-CNY','CNY',203],104=>['CPO-CN-USD','USD',204]]as$id=>[$ref,$currency,$gl]){
        fsql("INSERT INTO llx_accounting_account(rowid,entity,fk_pcg_version,pcg_type,account_number,label,active) VALUES($gl,1,'SYNTHETIC','SYNTHETIC','SYN$gl','SYNTHETIC CHINA CASH',1)");
        fsql("INSERT INTO llx_bank_account(rowid,entity,ref,label,courant,clos,currency_code,fk_pays,account_number,fk_accountancy_journal) VALUES($id,1,'$ref','SYNTHETIC $currency',2,0,'$currency',1,'SYN$gl',1)");
    }
    $v=$config;$v['enabled']='1';$v['money']=['CNY'=>['cash'=>['native_id'=>'103','gl_id'=>'203','payment_id'=>'4']],'USD'=>['cash'=>['native_id'=>'104','gl_id'=>'204','payment_id'=>'4']]];
    fcheck($finance->settings()->save('china',$v,'m121-synthetic-china-setup',0)['revision'],1);
    $r=$source->save('china','deposit',['amount'=>'1000.00','currency'=>'CNY','date'=>$today,'source'=>'SYNTHETIC owner','purpose'=>'SYNTHETIC China test funds','notes'=>''],'m121-china-funding-draft');
    $r=$finance->transition('china','deposit',$r['id'],1,'submit','m121-china-funding-submit',['date'=>$today,'setup_revision'=>'1','rate'=>'0.14','rate_date'=>$today,'rate_source'=>'SYNTHETIC reviewed quote','funding_type'=>'owner']);
    $r=$finance->transition('china','deposit',$r['id'],2,'approve','m121-china-funding-approve',['confirmation'=>$finance->confirmation($r)]);
    $r=$finance->transition('china','deposit',$r['id'],3,'post','m121-china-funding-post',['confirmation'=>$finance->confirmation($r)]);
    fcheck($r['content']['_finance']['posting']['plan']['currency'],'CNY');fcheck($r['content']['_finance']['posting']['plan']['cash_base'],'140.00');
});
foreach(['expense'=>'88.20','advance'=>'50.05']as$m121Kind=>$m121Amount){
    ftest('M121 CNY staff '.$m121Kind.' correction approval native posting reversal and retry',function()use($db,$today,$m121Kind,$m121Amount){
        global $user;$originalUser=$user;$kind=$m121Kind;$prefix='m121-cny-'.$kind;
        try{
            [$workerSource,$workerFinance]=m121FinanceContext(2103);
            $payload=['amount'=>$m121Amount,'currency'=>'CNY','date'=>$today,'purpose'=>'SYNTHETIC employee '.$kind,'source_ref'=>'SYNTHETIC','notes'=>''];if($kind==='expense')$payload['category_ref']='';
            $r=$workerSource->save('china',$kind,$payload,$prefix.'-draft');fcheck($r['status'],'draft');
            fcheck($workerSource->save('china',$kind,$payload,$prefix.'-draft')['id'],$r['id']);
            $input=['date'=>$today,'setup_revision'=>'1','rate'=>'0.14','rate_date'=>$today,'rate_source'=>'SYNTHETIC reviewed quote','staff_id'=>'2103'];
            $r=$workerFinance->transition('china',$kind,$r['id'],1,'submit',$prefix.'-submit',$input);fcheck($r['subject'],2103);
            m121FinanceRefuses(fn()=>$workerFinance->transition('china',$kind,$r['id'],2,'approve',$prefix.'-worker-approve',['confirmation'=>$workerFinance->confirmation($r)]));
            [$managerSource,$managerFinance]=m121FinanceContext(2102);
            $r=$managerFinance->transition('china',$kind,$r['id'],2,'reject',$prefix.'-reject',['reason'=>'SYNTHETIC missing description']);fcheck($r['status'],'rejected');
            m121FinanceContext(2103);
            $payload['notes']='SYNTHETIC corrected';$r=$workerSource->save('china',$kind,$payload,$prefix.'-correction',$r['id'],3);fcheck($r['revision'],4);
            $r=$workerFinance->transition('china',$kind,$r['id'],4,'submit',$prefix.'-resubmit',$input);
            m121FinanceContext(2102);
            $r=$managerFinance->transition('china',$kind,$r['id'],5,'approve',$prefix.'-approve',['confirmation'=>$managerFinance->confirmation($r)]);fcheck($r['status'],'approved');
            $c=new OfficeExpenseNativeConnection($db);$before=$c->one('SELECT COUNT(*) AS n FROM llx_bank');
            $confirmation=['confirmation'=>$managerFinance->confirmation($r)];
            m121FinanceRefuses(fn()=>$workerFinance->transition('china',$kind,$r['id'],6,'post',$prefix.'-worker-post',$confirmation));
            m121FinanceRefuses(fn()=>$managerFinance->transition('egypt',$kind,$r['id'],6,'post',$prefix.'-cross-branch',$confirmation));
            $r=$managerFinance->transition('china',$kind,$r['id'],6,'post',$prefix.'-post',$confirmation);fcheck($r['status'],'posted');
            fcheck((int)$c->one('SELECT COUNT(*) AS n FROM llx_bank')['n'],(int)$before['n']+1);
            $posting=$r['content']['_finance']['posting'];$bank=$c->one('SELECT fk_account,CAST(amount AS DECIMAL(30,2)) AS amount FROM llx_bank WHERE rowid='.(int)$posting['bank_id']);
            fcheck((int)$bank['fk_account'],103);fcheck($bank['amount'],'-'.$m121Amount);
            $bankCount=$c->one('SELECT COUNT(*) AS n FROM llx_bank');$glCount=$c->one('SELECT COUNT(*) AS n FROM llx_accounting_bookkeeping');
            fcheck($managerFinance->transition('china',$kind,$r['id'],6,'post',$prefix.'-post',$confirmation),$r);
            m121FinanceRefuses(fn()=>$managerFinance->transition('china',$kind,$r['id'],6,'post',$prefix.'-post',['confirmation'=>str_repeat('0',64)]));
            fcheck($c->one('SELECT COUNT(*) AS n FROM llx_bank'),$bankCount);fcheck($c->one('SELECT COUNT(*) AS n FROM llx_accounting_bookkeeping'),$glCount);
            $reverse=['date'=>$today,'reason'=>'SYNTHETIC reviewed correction'];
            m121FinanceRefuses(fn()=>$workerFinance->transition('china',$kind,$r['id'],7,'reverse',$prefix.'-worker-reverse',$reverse));
            fsql("CREATE TRIGGER m121_reverse_audit_fail BEFORE INSERT ON llx_cpo_oe_workspace_audit FOR EACH ROW BEGIN IF NEW.action='reverse' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='SYNTHETIC reversal audit failure'; END IF; END");
            try{
                freject(fn()=>$managerFinance->transition('china',$kind,$r['id'],7,'reverse',$prefix.'-failed-reverse',$reverse));
                fcheck($managerSource->get('china',$kind,$r['id'])['status'],'posted');
                fcheck($c->one('SELECT COUNT(*) AS n FROM llx_bank'),$bankCount);fcheck($c->one('SELECT COUNT(*) AS n FROM llx_accounting_bookkeeping'),$glCount);fcheck($db->transaction_opened,0);
            }finally{fsql('DROP TRIGGER m121_reverse_audit_fail');}
            $r=$managerFinance->transition('china',$kind,$r['id'],7,'reverse',$prefix.'-reverse',$reverse);fcheck($r['status'],'reversed');
            fcheck($r['content']['currency'],'CNY');fcheck($r['content']['amount'],$m121Amount);fcheck($r['content']['_finance']['posting'],$posting);
            $reversal=$r['content']['_finance']['reversal'];fcheck($reversal['bank_id']!==$posting['bank_id'],true);fcheck($reversal['plan']['cash_amount'],$m121Amount);fcheck($reversal['plan']['valuation'],$posting['plan']['valuation']);
            $bankCount=$c->one('SELECT COUNT(*) AS n FROM llx_bank');$glCount=$c->one('SELECT COUNT(*) AS n FROM llx_accounting_bookkeeping');
            fcheck($managerFinance->transition('china',$kind,$r['id'],7,'reverse',$prefix.'-reverse',$reverse),$r);
            fcheck($c->one('SELECT COUNT(*) AS n FROM llx_bank'),$bankCount);fcheck($c->one('SELECT COUNT(*) AS n FROM llx_accounting_bookkeeping'),$glCount);fcheck($db->transaction_opened,0);
            $GLOBALS['m121_cny_last_row']=$r;
        }finally{$user=$originalUser;}
    });
}
ftest('M121 China reversal report retains original currency and leaves Egypt untouched',function()use($db,$finance,$today,$m121EgyptBefore){
    $c=new OfficeExpenseNativeConnection($db);
    fcheck($c->rows('SELECT fk_account,CAST(SUM(amount) AS DECIMAL(30,2)) AS amount FROM llx_bank WHERE fk_account IN (101,102) GROUP BY fk_account ORDER BY fk_account'),$m121EgyptBefore);
    fcheck($c->one('SELECT CAST(SUM(amount) AS DECIMAL(30,2)) AS balance FROM llx_bank WHERE fk_account=103')['balance'],'1000.00');
    fcheck($finance->report('china',$today,$today)['totals']['CNY']['net'],'1000.00');
});
ftest('M121 revoked manager cannot replay an old native financial acknowledgement',function()use($db,$access,$today){
    global $user;$originalUser=$user;$access->revoke(2102,'china',1);
    try{
        [, $managerFinance]=m121FinanceContext(2102);$r=$GLOBALS['m121_cny_last_row'];$c=new OfficeExpenseNativeConnection($db);$before=$c->one('SELECT COUNT(*) AS n FROM llx_bank');
        m121FinanceRefuses(fn()=>$managerFinance->transition('china','advance',$r['id'],7,'reverse','m121-cny-advance-reverse',['date'=>$today,'reason'=>'SYNTHETIC reviewed correction']));
        fcheck($c->one('SELECT COUNT(*) AS n FROM llx_bank'),$before);
    }finally{$user=$originalUser;}
});
