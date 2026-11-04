<?php
/** Presentation only; verified source state and native rights supplied by synthetic adapters. */
error_reporting(E_ALL);
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
define('DOL_URL_ROOT','/erp');
function getDolCurrency(){return 'USD';}
function dol_escape_htmltag($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function price($n){return number_format((float)$n,2,'.',',');}
$helper=__DIR__.'/../../custom/chinaplanetoperations/lib/supplier_obligation.lib.php';
if(!is_file($helper))throw new RuntimeException('Supplier obligation presentation missing');
require_once $helper;
class ObligationLang121 {
    public $defaultlang='en_US';private $labels=[];
    function load($module){foreach(file(__DIR__.'/../../custom/chinaplanetoperations/langs/'.$this->defaultlang.'/chinaplanetoperations.lang')as$line){$pair=explode('=',trim($line),2);if(count($pair)===2)$this->labels[$pair[0]]=$pair[1];}}
    function trans($key){return $this->labels[$key]??$key;}
}
function obligationFixture121($currency='USD'){
 return ['operation'=>['rowid'=>101,'stage'=>'in_progress'],
 'supplier_order'=>(object)['id'=>301,'ref'=>'SYN-ORDER-301','status'=>1,'multicurrency_code'=>$currency,'multicurrency_total_ttc'=>300,'total_ttc'=>999,'thirdparty'=>(object)['name'=>'Fictional supplier <script>']],
 'supplier_invoice'=>(object)['id'=>401,'ref'=>'SYN-INVOICE-401','ref_supplier'=>'SYN-PRIVATE','type'=>0,'status'=>1],
 'summary'=>['total'=>250,'paid'=>100,'remaining'=>150,'currency'=>$currency,'status'=>1],
 'markers'=>[],'customer_order'=>(object)['lines'=>[]]];
}
$count=0;function obligationCheck121($ok,$why){global$count;if(!$ok)throw new RuntimeException($why);$count++;}
$state=obligationFixture121();$position=cpo_supplier_obligation_projection($state);
obligationCheck121($position['order']['amount']===300.0,'Original order amount, not base999');
obligationCheck121($position['invoice']['total']===250.0&&$position['invoice']['paid']===100.0&&$position['invoice']['remaining']===150.0,'Separate invoice250/payments100/residual150, not order+invoice550');
$done=$state;$done['operation']['stage']='completed';$done['markers']['supplier_payment']=[(object)['amount'=>100],(object)['amount'=>100]];
obligationCheck121(cpo_supplier_obligation_projection($done)===$position,'Task completion and duplicate workflow markers cannot alter native amounts');
foreach([0,6,7,9,99]as$status){$s=$state;$s['supplier_order']->status=$status;$p=cpo_supplier_obligation_projection($s);obligationCheck121($p['order']['state']!== 'committed'&&$p['invoice']['remaining']===150.0,'Nonactive order does not suppress linked invoice');$state=obligationFixture121();}
foreach([0,2,3,99]as$status){$s=obligationFixture121();$s['summary']['status']=$status;$s['supplier_invoice']->status=$status;$p=cpo_supplier_obligation_projection($s);obligationCheck121($p['invoice']['remaining']===null,'Draft/closed-writeoff/abandoned/unknown not treated as paid');}
$s=obligationFixture121();$s['summary']['paid']=250;$s['summary']['remaining']=0;$s['summary']['status']=2;$s['supplier_invoice']->status=2;
obligationCheck121(cpo_supplier_obligation_projection($s)['invoice']['remaining']===0.0,'Recorded full payments remain explicit zero');
foreach([['total',-1],['paid',-1],['paid',251],['paid',INF],['total',NAN],['remaining',0],['currency','BAD-CURRENCY']]as[$key,$value]){$s=obligationFixture121();$s['summary'][$key]=$value;$p=cpo_supplier_obligation_projection($s);obligationCheck121($p['invoice']['remaining']===null,'Malformed/mismatched source becomes unknown');}
$s=obligationFixture121();$s['summary']=null;obligationCheck121(cpo_supplier_obligation_projection($s)['invoice']['remaining']===null,'Missing source never zero');
$s=obligationFixture121();$s['supplier_invoice']=null;$s['summary']=null;$p=cpo_supplier_obligation_projection($s);obligationCheck121($p['order']['state']==='committed'&&$p['invoice']===null,'Order-only commitment is not an invoice/payment');
foreach(['CNY','EGP','USD']as$currency){$p=cpo_supplier_obligation_projection(obligationFixture121($currency));obligationCheck121($p['order']['currency']===$currency&&$p['invoice']['currency']===$currency,'Original currencies separate without FX');}
$s=obligationFixture121();$s['supplier_order']->multicurrency_code='';$s['supplier_order']->total_ttc=300;obligationCheck121(cpo_supplier_obligation_projection($s)['order']['amount']===300.0,'Blank native code uses base total');
$s=obligationFixture121();unset($s['supplier_order']->multicurrency_total_ttc);obligationCheck121(cpo_supplier_obligation_projection($s)['order']['amount']===null,'Missing original-currency amount not invented from base');
$s=obligationFixture121();$s['supplier_invoice']->type=2;obligationCheck121(cpo_supplier_obligation_projection($s)['invoice']['remaining']===null,'Unsupported native invoice type not guessed');
foreach(['en_US','ar_SA']as$locale){
 $langs=new ObligationLang121;$langs->defaultlang=$locale;$langs->load('chinaplanetoperations');
 $html=cpo_supplier_obligation_html(cpo_supplier_obligation_projection(obligationFixture121()),$langs);
 $previous=libxml_use_internal_errors(true);$doc=new DOMDocument;$doc->loadHTML('<html><meta charset="utf-8"><body>'.$html.'</body></html>');$xp=new DOMXPath($doc);libxml_clear_errors();libxml_use_internal_errors($previous);
 obligationCheck121(str_contains($html,'250.00')&&str_contains($html,'100.00')&&str_contains($html,'150.00')&&str_contains($html,'300.00'),'All separate facts rendered');
 obligationCheck121(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'Supplier name escaped');
 obligationCheck121(str_contains($html,'/erp/fourn/commande/card.php?id=301')&&str_contains($html,'/erp/fourn/facture/card.php?facid=401'),'Exact native source links');
 obligationCheck121($xp->query('//form|//input|//button')->length===0,'Read-only statement has no actions');
 obligationCheck121(str_contains($html,$langs->trans('CPOSupplierObligationLimit'))&&!str_contains($html,'CPOSupplierObligation'),'Labels and limitation translated');
 obligationCheck121($xp->query('//*[@data-obligation-coverage="recorded_payments_only"]')->length===1,'Coverage explicitly bounded');
 // Actual template distinguishes failed source reads from a loaded empty workflow.
 $supplierEligible=true;$supplierCanRead=true;
 foreach(['unavailable'=>null,'not_started'=>array_merge(obligationFixture121(),['supplier_order'=>null,'supplier_invoice'=>null,'summary'=>null]),'order_linked'=>array_merge(obligationFixture121(),['supplier_invoice'=>null,'summary'=>null]),'invoice_linked'=>obligationFixture121()]as$scenario=>$fixture){
  $supplierState=$fixture;$supplierCanOrder=$supplierCanValidateOrder=$supplierCanInvoice=$supplierCanValidateInvoice=$supplierCanPay=false;
  ob_start();include __DIR__.'/../../custom/chinaplanetoperations/operations/supplier_workflow.view.php';$body=ob_get_clean();
  $keys=['unavailable'=>'CPOSupplierWorkflowUnavailable','not_started'=>'CPOSupplierWorkflowNotStarted','order_linked'=>'CPOSupplierWorkflowOrderLinked','invoice_linked'=>'CPOSupplierWorkflowInvoiceLinked'];
  $previous=libxml_use_internal_errors(true);$doc=new DOMDocument;$doc->loadHTML('<html><meta charset="utf-8"><body>'.$body.'</body></html>');$xp=new DOMXPath($doc);libxml_clear_errors();libxml_use_internal_errors($previous);
  $badge=$xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," cpo-badge ")]')->item(0);
  obligationCheck121($langs->trans($keys[$scenario])!==$keys[$scenario]&&$badge&&$badge->textContent===$langs->trans($keys[$scenario]),'Translated exact badge: '.$locale.'/'.$scenario);
  if($scenario==='unavailable')obligationCheck121(!str_contains($body,'data-obligation-coverage')&&!str_contains($body,'<form'),'Failed source exposes no amounts/forms');
 }
 // Actual incumbent view gate: denied role receives no new supplier data.
 $supplierEligible=true;$supplierCanRead=false;$supplierState=obligationFixture121();
 ob_start();include __DIR__.'/../../custom/chinaplanetoperations/operations/supplier_workflow.view.php';$denied=ob_get_clean();
 obligationCheck121(!str_contains($denied,'Fictional supplier')&&!str_contains($denied,'150.00')&&!str_contains($denied,'data-obligation-coverage'),'Existing read gate protects statement');
}
echo "M121_SUPPLIER_OBLIGATION_VIEW=PASS ASSERTIONS=$count (synthetic source/rights; no financial writes)\n";
