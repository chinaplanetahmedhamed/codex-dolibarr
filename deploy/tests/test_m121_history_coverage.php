<?php
/** Pure reporting-period boundary, no database/auth/complete-history claim. */
require __DIR__.'/../../custom/chinaplanetcustomercenter/lib/financial.lib.php';
$checks=0;function hc_assert($ok,$why){$GLOBALS['checks']++;if(!$ok)throw new RuntimeException($why);}
hc_assert(function_exists('cpcc_financial_report_period'),'Missing explicit reporting coverage boundary');
foreach(['',null,'2026-11-04']as$date){$r=cpcc_financial_report_period($date,'2026-11-04');hc_assert($r['mode']==='current'&&$r['date']==='2026-11-04'&&$r['can_show_current']===true,'Current date/default workflow must be explicit');hc_assert($r['historical_totals']===null&&$r['historical_complete']===false,'Current view must not certify historical reconstruction');}
foreach(['2024-02-29','2026-11-03','1000-01-01']as$date){$r=cpcc_financial_report_period($date,'2026-11-04');hc_assert($r['mode']==='historical_unavailable'&&$r['date']===$date&&!$r['can_show_current'],'Valid past date must not reuse current eligibility');hc_assert($r['historical_totals']===null&&!$r['historical_complete'],'Unsupported history is null, never zero/empty verified balance');}
$r=cpcc_financial_report_period('2026-11-05','2026-11-04');hc_assert($r['mode']==='future_unavailable'&&!$r['can_show_current'],'Future date must not certify future balance');
foreach(['2026-02-29','2026-02-31','2026-00-01','2026-13-01','2026-11-4',"2026-11-04\n","2026-11-04\r\n",' 2026-11-04','2026-11-04 ','0000-01-01','0999-12-31','9999-12-31','2026-11-04<script>','<script>',' ',[],42,false,true]as$invalid){$r=cpcc_financial_report_period($invalid,'2026-11-04');hc_assert($r['mode']==='invalid_date'&&$r['date']===null&&!$r['can_show_current']&&$r['historical_totals']===null,'Malformed date must not normalize/fallback to today');}
// One captured application-local calendar day, including rollover/leap boundaries.
foreach([['2026-11-04','2026-11-04','current'],['2026-11-04','2026-11-05','historical_unavailable'],['2024-02-29','2024-03-01','historical_unavailable'],['2026-12-31','2027-01-01','historical_unavailable']]as[$selected,$today,$mode])hc_assert(cpcc_financial_report_period($selected,$today)['mode']===$mode,'Calendar boundary must use captured day');
$threw=false;try{cpcc_financial_report_period('','invalid-clock');}catch(InvalidArgumentException$e){$threw=true;}hc_assert($threw,'Invalid internal clock cannot broaden a request');
echo json_encode(['checks'=>$checks,'status'=>'passed','scope'=>'Pure explicit unavailable historical/date contract; no historical reconstruction or DB/ERP acceptance'],JSON_PRETTY_PRINT)."\n";
