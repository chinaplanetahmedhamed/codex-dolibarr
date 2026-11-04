<?php
declare(strict_types=1);
// Observational probes, never an adapter or a claim that sample rates are legally applicable.
if (PHP_SAPI !== 'cli' || $argc !== 2 || !is_file($argv[1].'/expensereport/class/expensereport.class.php')) {
    fwrite(STDERR,"Usage: php test_m119_native_capabilities.php /path/to/dolibarr/htdocs\n");exit(1);
}
require_once __DIR__.'/fixtures/m119_native_bootstrap.php';
require_once __DIR__.'/fixtures/m119_native_identity.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';
$passed=0;$failed=0;
function ccheck($actual,$expected): void {if ($actual !== $expected) throw new RuntimeException('Expected '.var_export($expected,true).' got '.var_export($actual,true));}
function camount($actual,string $expected): void {if (bccomp((string)$actual,$expected,8)!==0) throw new RuntimeException('Native capability amount differs: '.(string)$actual);}
function ctest(string $label,callable $action): void {
    global $passed,$failed;
    try {$action();echo "PASS $label\n";$passed++;}catch(Throwable $e){echo "FAIL $label: ".$e->getMessage()."\n";$failed++;}
}
function creport(): ExpenseReport {
    global $db,$user;
    m119IdentityDatabase();$user=m119FetchIdentity('synthetic-7');
    $report=new ExpenseReport($db);$report->fk_user_author=7;
    $report->date_debut=strtotime('2026-10-31 12:00:00');$report->date_fin=$report->date_debut;
    $report->note_private='SYNTHETIC capability probe';
    return $report;
}
function cline(ExpenseReport $report,string $gross='180.29',string $rate='0'): ExpenseReportLine {
    global $db,$mysoc;
    // This is the native tax-inclusive calculation used by addline; local taxes explicitly absent here.
    $totals=calcul_price_total(1,(float)$gross,0,(float)$rate,-1,-1,0,'TTC',0,0,$mysoc,array(0,0,0,0));
    $line=new ExpenseReportLine($db);$line->fk_expensereport=$report->id;$line->fk_c_type_fees=7;$line->fk_project=0;
    $line->date=$report->date_debut;$line->comments='SYNTHETIC capability line';$line->qty=1;$line->value_unit=$gross;
    $line->vatrate=$rate;$line->vat_src_code='';$line->localtax1_tx=0;$line->localtax2_tx=0;$line->localtax1_type=0;$line->localtax2_type=0;
    $line->total_ht=$totals[0];$line->total_tva=$totals[1];$line->total_ttc=$totals[2];$line->total_localtax1=0;$line->total_localtax2=0;
    $line->fk_c_exp_tax_cat=0;$line->fk_ecm_files=0;$line->rule_warning_message='';
    return $line;
}
ctest('native create ignores supplied foreign currency header fields despite schema columns',function(){
    global $db,$user;
    $report=creport();
    $report->multicurrency_code='CNY';$report->multicurrency_tx='7.25';
    $report->multicurrency_total_ht='180.29';$report->multicurrency_total_tva='0';$report->multicurrency_total_ttc='180.29';
    if ($report->create($user,1)<=0) throw new RuntimeException('Native header probe failed.');
    $row=$db->fetch_object($db->query('SELECT multicurrency_code,multicurrency_tx,multicurrency_total_ttc FROM llx_expensereport WHERE rowid='.(int)$report->id));
    ccheck($row->multicurrency_code,null);camount($row->multicurrency_tx,'1');camount($row->multicurrency_total_ttc,'0');
});
ctest('native line insert ignores supplied original currency and foreign totals',function(){
    global $db,$user;
    $report=creport();if ($report->create($user,1)<=0) throw new RuntimeException('Native header probe failed.');
    $line=cline($report);$line->multicurrency_code='CNY';$line->multicurrency_subprice='180.29';$line->multicurrency_total_ttc='180.29';
    if ($line->insert(1,true)<=0) throw new RuntimeException('Native detail probe failed.');
    $row=$db->fetch_object($db->query('SELECT multicurrency_code,multicurrency_subprice,multicurrency_total_ttc FROM llx_expensereport_det WHERE rowid='.(int)$line->id));
    ccheck($row->multicurrency_code,null);camount($row->multicurrency_subprice,'0');camount($row->multicurrency_total_ttc,'0');
});
foreach (array(array('114.00','14','100.00','14.00'),array('106.00','6','100.00','6.00'),array('0.05','14','0.04','0.01')) as $example) {
    ctest('native synthetic tax-inclusive '.$example[0].' at sample '.$example[1].'% persists net/tax/gross',function()use($example){
        global $db,$user;
        list($gross,$rate,$net,$tax)=$example;
        $report=creport();if ($report->create($user,1)<=0) throw new RuntimeException('Native header probe failed.');
        $line=cline($report,$gross,$rate);
        if ($line->insert(1,true)<=0 || $report->update_price(1)<=0) throw new RuntimeException('Native tax probe failed.');
        $detail=$db->fetch_object($db->query('SELECT total_ht,total_tva,total_ttc,tva_tx FROM llx_expensereport_det WHERE rowid='.(int)$line->id));
        camount($detail->total_ht,$net);camount($detail->total_tva,$tax);camount($detail->total_ttc,$gross);camount($detail->tva_tx,$rate);
        $header=$db->fetch_object($db->query('SELECT total_ht,total_tva,total_ttc,paid,fk_statut FROM llx_expensereport WHERE rowid='.(int)$report->id));
        camount($header->total_ht,$net);camount($header->total_tva,$tax);camount($header->total_ttc,$gross);
        ccheck((int)$header->paid,0);ccheck((int)$header->fk_statut,0);
    });
}
echo "$passed passed, $failed failed; capability observations only; native foreign-currency and nonzero-tax adapter remain blocked\n";
exit($failed?1:0);
