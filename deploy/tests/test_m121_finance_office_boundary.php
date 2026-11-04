<?php
/** Actual CFS SQL + Office draft controller/plan/presenters; fresh shared SQLite only. */
if(PHP_SAPI!=='cli')exit(1);
$boundaryArgs=$argv;$argv=[__FILE__];ob_start();try{require __DIR__.'/test_m121_managed_position.php';}finally{ob_end_clean();$argv=$boundaryArgs;}
$root=dirname(__DIR__,2);
require_once $root.'/deploy/tools/office_expenses/LocalDraftStore.php';
require_once $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeeController.php';
require_once $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseEmployeePage.php';
require_once $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseFinancialPlan.php';
require_once $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseWorkspacePage.php';
$checks=0;$phases=[];
function bo_assert($ok,$label){$GLOBALS['checks']++;if(!$ok)throw new RuntimeException($label);}
function bo_refusal_matches($e,$class,$message){return !($e instanceof PDOException)&&get_class($e)===$class&&$e->getMessage()===$message;}
function bo_refuses($f,$class,$message,$label){try{$f();}catch(Throwable $e){bo_assert(bo_refusal_matches($e,$class,$message),$label.' wrong refusal: '.get_class($e).' '.$e->getMessage());return;}throw new RuntimeException('Unexpected acceptance: '.$label);}
bo_assert(!bo_refusal_matches(new PDOException('Allocation target is invalid.'),RuntimeException::class,'Allocation target is invalid.'),'SQL failure cannot satisfy business refusal even with matching message');
bo_assert(!bo_refusal_matches(new RuntimeException('Unrelated failure'),RuntimeException::class,'Allocation target is invalid.'),'Unrelated runtime failure cannot satisfy business refusal');
bo_assert(bo_refusal_matches(new RuntimeException('Allocation target is invalid.'),RuntimeException::class,'Allocation target is invalid.'),'Exact intended refusal control');
function bo_customer_state($db){$rows=mp_state($db);return array_filter($rows,fn($key)=>str_starts_with($key,'llx_'),ARRAY_FILTER_USE_KEY);}
list($db,$funds)=mp_fixture();$receipt=mp_register($db,$funds);$initial=$funds->currentPosition(7);
$store=new LocalDraftStore($db->pdo); // Same fresh synthetic database, existing separate owning schema.
$staff=new class{public $id=19;public $socid=0;public $status=1;function hasRight($m,$r){return $m==='expensereport'&&$r==='creer';}};
$office=new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),$staff,1,['egypt'=>['USD','EGP'],'china'=>['CNY']],'USD');
$controller=new OfficeExpenseEmployeeController($office,'synthetic-csrf','synthetic-csrf');
$post=['action'=>'save','token'=>'synthetic-csrf','submission_key'=>'synthetic-office-expense-40','kind'=>'expense_claim','office'=>'egypt','amount'=>'40.00','currency'=>'USD','occurred_on'=>'2026-11-03','source_ref'=>'SYNTHETIC company office receipt','purpose'=>'Synthetic company office supplies','quote_rate'=>'','quote_date'=>'','quote_source'=>'','settles_ref'=>'','lang'=>'en'];
$before=bo_customer_state($db);$first=$controller->handle('POST',[],$post);$retry=$controller->handle('POST',[],$post);
bo_assert($first['status']===303&&$retry['redirect']===$first['redirect'],'Office duplicate POST keeps one draft');
$drafts=$office->listOwn();$draft=$drafts[0];bo_assert(count($drafts)===1&&$draft['status']==='draft'&&$draft['native_record']===null,'Office request is not a native posting');
bo_assert($draft['owner_id']===19&&$draft['entity']===1&&$draft['amount']==='40.00'&&$draft['currency']==='USD','Exact Office staff/entity/original money');
bo_assert(bo_customer_state($db)===$before&&$funds->currentPosition(7)===$initial,'Office save/retry leaves all customer/native tables and position identical');
$bad=$controller->handle('POST',[],array_replace($post,['submission_key'=>'synthetic-office-inject','customer_receipt_id'=>(string)$receipt]));bo_assert($bad['status']===422,'Employee cannot inject customer deposit source');
bo_assert($controller->handle('POST',[],array_replace($post,['action'=>'post']))['status']===403,'Employee cannot turn draft into posting');
bo_assert($controller->handle('POST',[],array_replace($post,['token'=>'wrong']))['status']===403,'Bad CSRF rejected');
bo_assert($controller->handle('POST',[],array_replace($post,['amount'=>'41.00']))['status']===409,'Changed Office intent cannot reuse key');
bo_assert(count($office->listOwn())===1&&bo_customer_state($db)===$before,'Office failures change neither ledger nor draft count');
$phases[]=['name'=>'office_draft','customer'=>$initial,'office'=>$draft];
bo_refuses(fn()=>$funds->reserve($receipt,['target_type'=>'office_expense','target_id'=>1,'amount'=>40,'request_key'=>hash('sha256','invalid-office-target')],9),RuntimeException::class,'Allocation target is invalid.','Office expense cannot be a customer allocation target');
bo_assert(bo_customer_state($db)===$before,'Cross-owner target rejection is side effect free');
$allocation=mp_reserve($funds,$receipt);$reserved=$funds->currentPosition(7);
bo_assert($reserved['currencies']['USD']['reserved']===40.0&&$reserved['currencies']['USD']['paid_cash']===0.0&&$reserved['currencies']['USD']['available_to_allocate']===60.0,'Customer reservation is not payment');
$officeRows=$db->pdo->query('SELECT * FROM oe_draft ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);$officeAudit=$db->pdo->query('SELECT * FROM oe_audit ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
$beforeWrongNative=bo_customer_state($db);
bo_refuses(fn()=>$funds->settleWithSupplierPayment($allocation,900,9),RuntimeException::class,'Native supplier payment and bank evidence are required.','Unlinked Office/native ID cannot masquerade as supplier payment');
bo_assert(bo_customer_state($db)===$beforeWrongNative,'Wrong native payment refusal preserves every customer/native table');
mp_supplier($db,$allocation);$funds->settleWithSupplierPayment($allocation,200,9);$funds->settleWithSupplierPayment($allocation,200,9);$settled=$funds->currentPosition(7);
bo_assert($settled['currencies']['USD']['paid_cash']===40.0&&$settled['currencies']['USD']['available_to_allocate']===60.0,'Verified customer supplier share attributed once');
bo_assert($db->pdo->query('SELECT * FROM oe_draft ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC)===$officeRows&&$db->pdo->query('SELECT * FROM oe_audit ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC)===$officeAudit,'Customer settlement/retry leaves Office draft and audit identical');
bo_assert($office->getOwn($draft['id'])['native_record']===null,'Supplier payment cannot settle Office expense');
bo_assert(count($db->rows('paiement'))===1&&count($db->rows('paiementfourn'))===1&&count($db->rows('bank'))===2&&count($db->rows('cpcc_financial_entry'))===0,'No duplicate customer payment, supplier cash or invented debit');
$phases[]=['name'=>'customer_supplier_attributed','customer'=>$settled,'office'=>$office->getOwn($draft['id']),'supplier'=>fi_supplier($db)];
$row=['id'=>$draft['id'],'office'=>'egypt','kind'=>'expense','content'=>['amount'=>'40.00','currency'=>'USD','date'=>'2026-11-03','method'=>'cash']];
$intent=['date'=>'2026-11-03','rate'=>'1','setup_revision'=>'1','staff_id'=>'19'];$plan=OfficeExpenseFinancialPlan::build($row,OfficeExpenseFinancialPlan::intent($row,$intent,'USD','2026-11-03'));
bo_assert($plan['cash_amount']==='-40.00'&&$plan['lines']===[['role'=>'expense','debit'=>'40.00','credit'=>'0.00'],['role'=>'cash','debit'=>'0.00','credit'=>'40.00']],'Office expense has distinct exact expense/cash preview, not a customer-funds posting');
bo_refuses(fn()=>OfficeExpenseFinancialPlan::intent($row,$intent+['customer_receipt_id'=>(string)$receipt],'USD','2026-11-03'),InvalidArgumentException::class,'Unexpected posting input.','Office posting intent cannot inject customer receipt identity');
bo_assert($funds->currentPosition(7)===$settled&&$office->getOwn($draft['id'])['status']==='draft','Office financial preview executes no posting');
$receiptRow=array_replace($row,['kind'=>'receipt','content'=>array_replace($row['content'],['amount'=>'25.00'])]);
$receiptIntent=OfficeExpenseFinancialPlan::intent($receiptRow,array_replace($intent,['party_id'=>'7']),'USD','2026-11-03');
$officeReceipt=OfficeExpenseFinancialPlan::build($receiptRow,$receiptIntent);
bo_assert($officeReceipt['lines'][1]['role']==='customer_funds'&&$officeReceipt['party_id']===7&&$officeReceipt['invoice_id']===0,'Unallocated Office customer receipt is liability preview, not company income or managed native deposit');
$depositRow=array_replace($receiptRow,['kind'=>'deposit']);
$ownerDeposit=OfficeExpenseFinancialPlan::build($depositRow,OfficeExpenseFinancialPlan::intent($depositRow,array_replace($intent,['funding_type'=>'owner']),'USD','2026-11-03'));
bo_assert($ownerDeposit['lines'][1]['role']==='owner_funding'&&$ownerDeposit['party_id']===0,'Office owner deposit is distinct from customer deposit invoice/payment');
bo_assert($funds->currentPosition(7)===$settled&&count($db->rows('cpcc_customer_receipt'))===1,'Neither Office preview registers another customer receipt');
// Seed the documented Office-native base-currency wire shape, not an executed Office cash posting.
$db->pdo->exec('UPDATE llx_bank SET amount_main_currency=100 WHERE rowid=301');
$unsupported=$funds->currentPosition(7);
bo_assert($unsupported['currencies']['USD']['received_cash']===null&&$unsupported['currencies']['USD']['available_to_allocate']===null,'Unsupported cross-owner bank proof is Unknown; no duplicate cash or silent conversion');
$db->pdo->exec('UPDATE llx_bank SET amount_main_currency=NULL WHERE rowid=301');
bo_assert($funds->currentPosition(7)===$settled,'Original seeded proof restored exactly');

foreach([['egypt','EGP','700.10','0.02'],['china','CNY','180.29','0.14']]as[$branch,$currency,$amount,$rate]){
 $p=array_replace($post,['submission_key'=>'synthetic-office-'.$currency,'office'=>$branch,'currency'=>$currency,'amount'=>$amount,'quote_rate'=>$rate,'quote_date'=>'2026-11-03','quote_source'=>'SYNTHETIC fixture rate']);bo_assert($controller->handle('POST',[],$p)['status']===303,'Office original currency persists separately '.$currency);
}
bo_assert($funds->currentPosition(7)===$settled&&array_keys($settled['currencies'])===['USD'],'EGP/CNY Office requests do not enter USD customer pool or invent FX total');
bo_assert(count($office->listOwn())===3,'Exactly three original-currency drafts; no combined money total');
$other=new OfficeExpenseDraftService($store,new OfficeExpenseMoney(),(object)['id'=>20,'socid'=>0,'status'=>1,'rights'=>(object)[]],1,['egypt'=>['USD']],'USD');
// Existing service authorization, not a new ACL abstraction.
try{$own=$other->listOwn();bo_assert($own===[],'Another user sees no Office draft');}catch(DomainException $e){bo_assert(true,'Unprivileged other user denied');}
$render=[];if(!function_exists('dol_escape_htmltag')){function dol_escape_htmltag($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
class BoundaryLang{public $defaultlang;private $labels=[];function __construct($language,$root){$this->defaultlang=$language;foreach(file($root.'/custom/chinaplanetcustomercenter/langs/'.$language.'/chinaplanetcustomercenter.lang',FILE_IGNORE_NEW_LINES)as$l){if(preg_match('/^([A-Za-z0-9_]+)=(.*)$/u',$l,$m))$this->labels[$m[1]]=$m[2];}}function load($d){}function trans($key){return $this->labels[$key]??$key;}}
foreach(['en'=>'en_US','ar'=>'ar_SA']as$lang=>$domain){$langs=new BoundaryLang($domain,$root);$state=$controller->handle('GET',['lang'=>$lang,'view'=>'expenses'],[]);$render[$lang]=['customer'=>cpcc_ui_managed_funds($settled),'office'=>(new OfficeExpenseEmployeePage())->render($state,$lang,'synthetic-csrf')];bo_assert(str_contains($render[$lang]['customer'],'60.00')&&str_contains($render[$lang]['office'],'40.00'),'Actual customer/Office product presenters '.$lang);}
// Actual R13 full workspace renderer, supplied synthetic read-only state only.
// This sample is not promoted from or saved over an employee draft.
foreach(['en','ar']as$lang){
 $sample=['id'=>str_repeat('b',32),'entity'=>1,'office'=>'egypt','kind'=>'expense','status'=>'approved','owner'=>19,'subject'=>19,'revision'=>2,'content'=>['amount'=>'40.00','currency'=>'USD','date'=>'2026-11-03','purpose'=>'SYNTHETIC workspace expense sample','notes'=>'Read-only renderer fixture; no saved workspace approval or posting.']];
 $ws=['status'=>200,'error'=>null,'redirect'=>null,'csv'=>null,'view'=>'expenses','kind'=>'expense','office'=>'egypt','language'=>$lang,'offices'=>['egypt'=>['USD','EGP']],'rows'=>[$sample],'legacy'=>[],'staff'=>[['id'=>19,'name'=>'Synthetic employee','login'=>'synthetic-19']],'categories'=>[],'advances'=>[],'selected'=>$sample,'history'=>[],'print'=>false,'report'=>null,'permissions'=>['read'=>true,'create'=>false,'update'=>false,'delete'=>false],'cash'=>null,'accounts'=>[],'settings'=>null,'form'=>null,'submission_key'=>'synthetic-readonly','receipt_filter'=>null,'receivers'=>[],'actor_name'=>'Synthetic employee','receipt_link'=>'','can_edit_receipt_settings'=>false,'report_currency'=>'','month'=>'','financial_export'=>false,'navigation_views'=>['overview','expenses','advances','treasury','receipts','categories','reports','employees','payroll','settings'],'actor_id'=>19,'record_scope'=>'own','manage_staff'=>false,
 'finance'=>['enabled'=>false,'base'=>'USD','revision'=>1,'administrator'=>false,'evidence_available'=>false,'evidence'=>[],'submit'=>false,'permissions'=>['approve'=>false,'post'=>false,'reverse'=>false]]];
 $render[$lang]['workspace']=(new OfficeExpenseWorkspacePage())->render($ws,'synthetic-readonly');
 bo_assert(str_contains($render[$lang]['workspace'],'workspace-app')&&str_contains($render[$lang]['workspace'],'app-sidebar')&&str_contains($render[$lang]['workspace'],'40.00 USD'),'Actual R13 full workspace shell and exact original amount '.$lang);
 bo_assert(str_contains($render[$lang]['workspace'],$lang==='ar'?'معتمد · غير مدفوع':'Approved · unpaid')&&!str_contains($render[$lang]['workspace'],'name="action" value="post"'),'Workspace approval is unpaid with no posting action '.$lang);
}
bo_assert($funds->currentPosition(7)===$settled&&count($office->listOwn())===3,'Read-only full-workspace rendering does not change either owner');
$result=['scope'=>'Actual shared SQLite CFS and Office draft/controller/plan/presenters; native cash seeded; no native posting/trigger/role/runtime acceptance','checks'=>$checks,'phases'=>$phases,'office_drafts'=>$office->listOwn(),'office_preview'=>$plan,'render'=>$render,'native_posting_performed'=>false,'source_root'=>$root];
if(isset($argv[1]))file_put_contents($argv[1],json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL);
echo 'M121 finance/Office boundary PASS ('.$checks.' assertions; shared fresh SQLite; native evidence SEEDED; Office drafts/financial preview only)'.PHP_EOL;
