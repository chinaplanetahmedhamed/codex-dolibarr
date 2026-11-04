<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$file=$root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseRequestProgress.php';
if(!is_file($file)){fwrite(STDERR,"FAIL request progress renderer is missing\n");exit(1);}require $file;
require $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseFinancialPage.php';
$passed=0;$failed=0;
function pr(bool $condition,string $name):void{global$passed,$failed;if($condition){$passed++;echo "PASS $name\n";}else{$failed++;echo "FAIL $name\n";}}
$finance=['enabled'=>false,'base'=>'EGP','revision'=>1,'administrator'=>false,'evidence_available'=>false,'evidence'=>[],'submit'=>false,'permissions'=>['approve'=>false,'post'=>false,'reverse'=>false]];
$preview=getenv('M121_PROGRESS_VISUAL_DIR');
if($preview&&(!is_dir($preview)||is_link($preview)||!is_file($preview.'/.m121-synthetic-preview')||file_get_contents($preview.'/.m121-synthetic-preview')!=='M121 synthetic progress preview'))throw new RuntimeException('Owned synthetic preview directory required.');
foreach(['ar','en']as$lang)foreach(['egypt'=>'EGP','china'=>'CNY']as$office=>$currency){
    foreach(['draft','submitted','approved','rejected','posted','reversed','archived']as$status){
        $row=['id'=>str_repeat('a',32),'kind'=>'expense','office'=>$office,'status'=>$status,'owner'=>7,'subject'=>7,'revision'=>3,'content'=>['amount'=>'180.29','currency'=>$currency,'purpose'=>'SYNTHETIC request','_finance'=>['rejection_reason'=>'<script>SYNTHETIC return</script>','private_canary'=>'PRIVATE-FINANCE-CANARY']]];
        $before=$row;$html=OfficeExpenseRequestProgress::workspace($row,$finance,$lang);
        pr(str_contains($html,'data-request-status="'.$status.'"')&&str_contains($html,'180.29 '.$currency),$lang.' '.$office.' '.$status.' retains stored status and original currency');
        pr(!str_contains($html,'<form')&&!str_contains($html,'<button')&&!str_contains($html,'PRIVATE-FINANCE-CANARY')&&$row===$before,$lang.' '.$office.' '.$status.' guidance is read-only and omits private metadata');
        if($status==='rejected')pr(str_contains($html,'&lt;script&gt;SYNTHETIC return&lt;/script&gt;')&&str_contains($html,$lang==='ar'?'الحفظ لا يعيد الإرسال':'Saving edits does not resubmit'),$lang.' '.$office.' rejection reason is escaped and revision rules are explicit');
        else pr(!str_contains($html,'SYNTHETIC return'),$lang.' '.$office.' obsolete rejection reason is not shown');
        if($preview)file_put_contents($preview.'/'.$lang.'-'.$office.'-'.$status.'.html','<!doctype html><html lang="'.$lang.'" dir="'.($lang==='ar'?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>M121 synthetic read-only progress</title><link rel="stylesheet" href="employee.css"></head><body><div class="office-expenses-app"><main><h1>SYNTHETIC · read-only status preview</h1>'.$html.'</main></div></body></html>');
    }
    $row['status']='approved';$html=OfficeExpenseRequestProgress::workspace($row,$finance,$lang);
    pr(str_contains($html,$lang==='ar'?'معتمد · غير مدفوع':'Approved · unpaid'),$lang.' '.$office.' approval is never represented as paid');
    $row['status']='rejected';unset($row['content']['_finance']['rejection_reason']);
    pr(str_contains(OfficeExpenseRequestProgress::workspace($row,$finance,$lang),$lang==='ar'?'سجل التعديلات':'revision history'),$lang.' '.$office.' corrected record without a current reason points to history');
    $entry=['amount'=>'700.10','currency'=>$currency,'office'=>$office,'status'=>'draft','purpose'=>'SYNTHETIC entry','_finance'=>['status'=>'approved','private_canary'=>'PRIVATE-FINANCE-CANARY']];
    $html=OfficeExpenseRequestProgress::entry($lang,$entry);
    pr(str_contains($html,$lang==='ar'?'لا توجد حالة موافقة مرتبطة':'No linked approval status')&&!str_contains($html,'PRIVATE-FINANCE-CANARY')&&!str_contains($html,'data-request-status="approved"'),$lang.' '.$office.' entry drafts never infer workspace approval');
    $row['status']='submitted';$s=['finance'=>$finance,'language'=>$lang,'print'=>false,'view'=>'expenses','office'=>$office,'selected'=>$row];
    pr(str_contains((new OfficeExpenseFinancialPage())->render($s,'synthetic-token'),'data-request-status="submitted"'),$lang.' '.$office.' existing finance page includes progress without enabling actions');
}
$row['status']='submitted';$finance['enabled']=true;$finance['permissions']['approve']=true;
pr(str_contains(OfficeExpenseRequestProgress::workspace($row,$finance,'en'),'Your current approval permission'),'self-action guidance follows existing approval permission');
$finance['permissions']['approve']=false;
pr(!str_contains(OfficeExpenseRequestProgress::workspace($row,$finance,'en'),'Your current approval permission'),'read-only employee is not presented as the approver');
$row['status']='unexpected-private-state';
pr(!str_contains(OfficeExpenseRequestProgress::workspace($row,$finance,'en'),'unexpected-private-state'),'unknown state does not create or display an invented status');
foreach(['opening','deposit','receipt','payroll']as$kind){$row['kind']=$kind;pr(OfficeExpenseRequestProgress::workspace($row,$finance,'en')==='','employee status guidance leaves '.$kind.' workflow unchanged');}
echo "$passed passed, $failed failed\n";exit($failed?1:0);
