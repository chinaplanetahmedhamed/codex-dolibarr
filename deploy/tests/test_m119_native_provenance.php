<?php
declare(strict_types=1);
// Storage capability only. Does not convert/post claims or install fields in a real runtime.
if (PHP_SAPI !== 'cli' || $argc !== 2 || !is_file($argv[1].'/core/class/extrafields.class.php')) {
    fwrite(STDERR,"Usage: php test_m119_native_provenance.php /path/to/dolibarr/htdocs\n");exit(1);
}
require_once __DIR__.'/fixtures/m119_native_bootstrap.php';
require_once __DIR__.'/fixtures/m119_native_identity.php';
require_once dirname(__DIR__,2).'/custom/chinaplanetoperations/office_expenses/class/OfficeExpenseMoney.php';
$passed=0;$failed=0;
function pcheck($actual,$expected): void {if ($actual !== $expected) throw new RuntimeException('Native provenance readback mismatch.');}
function ptest(string $label,callable $action): void {
    global $passed,$failed;
    try {$action();echo "PASS $label\n";$passed++;}catch(Throwable $e){echo "FAIL $label: ".$e->getMessage()."\n";$failed++;}
}
function psetup(): array {
    global $db,$user,$extrafields;
    m119IdentityDatabase();$user=m119FetchIdentity('synthetic-7');
    // Header fetch also loads lines through these empty project/category joins.
    $db->query('CREATE TABLE llx_projet(rowid INTEGER PRIMARY KEY,ref TEXT,title TEXT)');
    $db->query('ALTER TABLE llx_c_type_fees ADD COLUMN label TEXT');
    $db->query('ALTER TABLE llx_c_type_fees ADD COLUMN accountancy_code TEXT');
    foreach (array('expensereport','expensereport_det') as $table) {
        $sql=file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_'.$table.'_extrafields.sql');
        $sql=str_ireplace('ON UPDATE CURRENT_TIMESTAMP','',preg_replace('/--[^\n]*/','',$sql));
        if (!$db->query(trim($sql))) throw new RuntimeException('Owned provenance schema failed.');
        $extrafields=new ExtraFields($db);
        if ($extrafields->addExtraField('cpo_source_snapshot','SYNTHETIC original evidence','text',1,'',$table) !== 1) {
            throw new RuntimeException('Native extrafield creation failed: '.$extrafields->error);
        }
    }
    $extrafields=new ExtraFields($db); // Fresh native cache for this disposable database.
    $money=new OfficeExpenseMoney();
    $snapshot=array('schema'=>1,'draft_id'=>str_repeat('a',32),'entity'=>1,'owner_id'=>7,'office'=>'china',
        'source_ref'=>'SYNTHETIC 中国 receipt','occurred_on'=>'2026-10-31','purpose'=>'SYNTHETIC supplies',
        'conversion_estimate'=>$money->convert('180.29','CNY','EGP',array('rate'=>'7.25','date'=>'2026-10-31','source'=>'SYNTHETIC illustrative quote')),
        'tax_treatment'=>'unreviewed','posting_enabled'=>false);
    return array($db,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function pheader(string $snapshot): ExpenseReport {
    global $db;
    $report=new ExpenseReport($db);$report->fk_user_author=7;
    $report->date_debut=strtotime('2026-10-31 12:00:00');$report->date_fin=$report->date_debut;
    $report->note_private='SYNTHETIC provenance storage probe';
    $report->array_options=array('options_cpo_source_snapshot'=>$snapshot);
    return $report;
}
ptest('native header extrafield API round-trips exact UTF-8 original and quote decimal strings',function(){
    global $db,$user;
    list($db,$snapshot)=psetup();$report=pheader($snapshot);
    if ($report->create($user,1)<=0) throw new RuntimeException('Native header create failed: '.$report->error);
    $loaded=new ExpenseReport($db);
    if ($loaded->fetch($report->id)<=0) throw new RuntimeException('Native header fetch failed: '.$loaded->error.'; '.$db->lasterror());
    pcheck($loaded->array_options['options_cpo_source_snapshot'] ?? null,$snapshot);
    $value=json_decode($loaded->array_options['options_cpo_source_snapshot'],true,512,JSON_THROW_ON_ERROR);
    pcheck($value['conversion_estimate']['original_amount'],'180.29');pcheck($value['conversion_estimate']['rate'],'7.25000000');
    pcheck($value['conversion_estimate']['base_amount'],'1307.10');pcheck($value['posting_enabled'],false);
    pcheck((int)$loaded->paid,0);pcheck((int)$loaded->status,0);
});
ptest('native header creation rolls back when provenance persistence fails',function(){
    global $db,$user;
    list($db,$snapshot)=psetup();
    $db->query("CREATE TRIGGER m119_provenance_fail BEFORE INSERT ON llx_expensereport_extrafields BEGIN SELECT RAISE(ABORT,'SYNTHETIC evidence failure'); END");
    $report=pheader($snapshot);pcheck($report->create($user,1)>0,false);
    pcheck((int)$db->fetch_object($db->query('SELECT COUNT(*) n FROM llx_expensereport'))->n,0);
    pcheck((int)$db->fetch_object($db->query('SELECT COUNT(*) n FROM llx_expensereport_extrafields'))->n,0);
    pcheck($db->transaction_opened,0);
});
ptest('native detail provenance requires explicit insertExtraFields then explicit fetch_optionals',function(){
    global $db,$user;
    list($db,$snapshot)=psetup();$report=pheader($snapshot);
    if ($report->create($user,1)<=0) throw new RuntimeException('Native header create failed.');
    $line=new ExpenseReportLine($db);$line->fk_expensereport=$report->id;$line->fk_c_type_fees=7;$line->fk_project=0;
    $line->date=$report->date_debut;$line->comments='SYNTHETIC metadata line';$line->qty=1;$line->value_unit='1.00';
    $line->vatrate=0;$line->vat_src_code='';$line->localtax1_tx=0;$line->localtax2_tx=0;$line->localtax1_type=0;$line->localtax2_type=0;
    $line->total_ht='1.00';$line->total_tva=0;$line->total_ttc='1.00';$line->total_localtax1=0;$line->total_localtax2=0;
    $line->fk_c_exp_tax_cat=0;$line->fk_ecm_files=0;$line->rule_warning_message='';
    $line->array_options=array('options_cpo_source_snapshot'=>$snapshot);
    if ($line->insert(1,true)<=0) throw new RuntimeException('Native detail create failed.');
    pcheck((int)$db->fetch_object($db->query('SELECT COUNT(*) n FROM llx_expensereport_det_extrafields'))->n,0);
    if ($line->insertExtraFields('', $user)!==1) throw new RuntimeException('Native explicit detail evidence write failed.');
    $loaded=new ExpenseReportLine($db);$loaded->id=$line->id;
    if ($loaded->fetch_optionals()<=0) throw new RuntimeException('Native explicit detail evidence read failed.');
    pcheck($loaded->array_options['options_cpo_source_snapshot'] ?? null,$snapshot);
});
echo "$passed passed, $failed failed; native provenance storage probe only; no FX/tax/payment adapter enabled\n";
exit($failed?1:0);
