<?php
/** Native persisted actors and source templates; never an authenticated ERP session. */
require_once $s['source'].'/custom/chinaplanetoperations/lib/main.lib.php';
$nb=['status'=>'running','checks'=>0,'cases'=>[],'failed'=>0,'views'=>[],
 'boundary'=>'Real native User/driver/owner services; synthetic persisted actors and rights. Existing request tokens only; no new cash.',
 'rendering'=>'Actual PHP template bodies/partials and native SQL/formatters, with explicit bootstrap/header/session seams. No authenticated UI/CSRF acceptance.'];
function nb_assert($ok,$label){$GLOBALS['nb']['checks']++;if(!$ok)throw new RuntimeException($label);}
function nb_case($label,$fn){global$nb;try{$fn();$nb['cases'][]=['name'=>$label,'status'=>'passed'];}catch(Throwable$e){$nb['failed']++;$nb['cases'][]=['name'=>$label,'status'=>'failed','error'=>$e->getMessage()];}}
function nb_actor($id){global$db;$u=new User($db);nb_assert($u->fetch($id)>0,'Persisted native actor fetch');return$u;}
function nb_rights($u){
 $u->rights->chinaplanetoperations=(object)['read'=>1,'operation_write'=>1];
 $u->rights->fournisseur=(object)['commande'=>(object)['lire'=>1,'creer'=>1],'facture'=>(object)['lire'=>1,'creer'=>1]];
 $u->rights->banque=(object)['lire'=>1];$u->rights->facture=(object)['creer'=>1,'paiement'=>1];return$u;
}
function nb_cash_signature(){global$pdo;return array_map('intval',[$pdo->query('SELECT COUNT(*) FROM llx_paiement')->fetchColumn(),$pdo->query('SELECT COUNT(*) FROM llx_paiementfourn')->fetchColumn(),$pdo->query('SELECT COUNT(*) FROM llx_bank')->fetchColumn()]);}
function nb_supplier_retry($db,$actor){global$ops,$pdo;$m=$pdo->query("SELECT fk_allocation FROM llx_cpo_supplier_action WHERE request_token='CPO-SPY-".strtoupper(sha1('journey-pay-0'))."'")->fetch(PDO::FETCH_ASSOC);nb_assert((bool)$m,'Original same-token supplier marker');return(new OperationSupplierWorkflowService($db,1))->postPayment($ops[0],200,11,'VIR',date('Y-m-d'),'SYN-J-PAY-1',(int)$m['fk_allocation'],$actor,sha1('journey-pay-0'));}
function nb_guided_retry($db,$actor){global$customer,$deposit;return(new NativeAdvanceWorkflowService($db,1))->postBankReceipt((int)$customer->id,$deposit,11,'VIR',date('Y-m-d'),'SYN-JOURNEY-DEPOSIT',$actor,hash('sha256','journey-v2-receipt'));}
function nb_expect_actor_refusal($method,$actor){
 [$d,$wire]=ns_fault_db('NO_FAULT','before');$wire->history=[];$before=nb_cash_signature();$refused=false;
 try{try{$method($d,$actor);}catch(Throwable$e){$refused=true;}
  nb_assert($refused,'Invalid actor accepted by financial service');
  $business=array_filter($wire->history,fn($sql)=>!preg_match('/^SELECT\b.*\bFROM\s+llx_user\b/is',$sql));
  nb_assert(!$business,'Invalid actor reached business SQL/transaction');nb_assert(nb_cash_signature()===$before,'Actor probe changed native cash');
 }finally{ns_close($d);}
}
function nb_balance_body($actor,$requestedCustomerId){
 global$db,$conf,$langs,$s,$user,$nb;
 $source=file_get_contents($s['source'].'/custom/chinaplanetcustomercenter/balance.php');
 $preludeStart=strpos($source,'cpcc_portal_require_access($user);');$preludeEnd=strpos($source,'llxHeader(');
 $start=strpos($source,'?>');$end=strrpos($source,'<?php if(!$isExternal)cpcc_admin_shell_close();llxFooter();');
 nb_assert($preludeStart!==false&&$preludeEnd!==false&&$start!==false&&$end!==false&&$end>$start,'Exact source prelude/template delimiters');
 $prelude=$s['fixture'].'/balance-prelude.php';file_put_contents($prelude,"<?php\n".substr($source,$preludeStart,$preludeEnd-$preludeStart));chmod($prelude,0600);
 $path=$s['fixture'].'/balance-template-body.php';file_put_contents($path,substr($source,$start+2,$end-$start-2));chmod($path,0600);
 $savedUser=$user;$savedGet=$_GET;$user=$actor;$_GET=['socid'=>(string)$requestedCustomerId];
 ob_start();try{
  include $prelude;$nb['rendered_scopes'][]=['actor'=>(int)$actor->id,'requested_customer'=>(int)$requestedCustomerId,'resolved_customer'=>$socid,'row_ids'=>array_column($rows,'id')];
  include $path;return ob_get_contents();
 }finally{ob_end_clean();$user=$savedUser;$_GET=$savedGet;}
}
function nb_supplier_view($actor,$operationId){
 global$db,$s,$order,$project,$langs;
 $id=(int)$operationId;$action='';$user=$actor;$operation=['fk_commande'=>(int)$order->id,'fk_project'=>(int)$project->id];
 if(!isset($_SESSION))$_SESSION=[];
 ob_start();try{
  include $s['source'].'/custom/chinaplanetoperations/operations/supplier_workflow.inc.php';
  include $s['source'].'/custom/chinaplanetoperations/operations/supplier_workflow.view.php';
  return ob_get_contents();
 }finally{ob_end_clean();}
}

// Explicit synthetic records; no installed grants, users or module state are touched.
nf_sql("INSERT INTO llx_user(rowid,entity,login,lastname,admin,statut,fk_soc) VALUES(57,1,'synthetic-inactive','Synthetic inactive',0,0,NULL),(58,0,'synthetic-shared','Synthetic entityzero',0,1,NULL),(59,1,'synthetic-reader','Synthetic supplier reader',0,1,NULL)");
$actors=['inactive'=>nb_rights(nb_actor(57)),'other_entity'=>nb_rights(nb_actor(54)),'external'=>nb_rights(nb_actor(51))];
$stale=nb_rights(nb_actor(57));$stale->statut=1;$actors['stale_active_object']=$stale;
$forged=nb_rights(nb_actor(53));$forged->admin=1;$actors['object_admin_exceeds_database']=$forged;
foreach($actors as$name=>$actor){
 foreach(['supplier'=>'nb_supplier_retry','guided'=>'nb_guided_retry']as$kind=>$method)nb_case($kind.' refuses '.$name.' before business SQL',fn()=>nb_expect_actor_refusal($method,$actor));
}
foreach(['authorized_nonadmin'=>nb_rights(nb_actor(56)),'native_entityzero'=>nb_rights(nb_actor(58))]as$name=>$actor)foreach(['supplier'=>'nb_supplier_retry','guided'=>'nb_guided_retry']as$kind=>$method)nb_case($kind.' preserves '.$name.' native compatibility',function()use($method,$actor){global$db;$before=nb_cash_signature();$result=$method($db,$actor);nb_assert(is_array($result)&&!empty($result['payment_id']),'Existing native identity returned');nb_assert(nb_cash_signature()===$before,'Authorized retry recreated cash');nb_assert((int)$db->transaction_opened===0,'Authorized retry leaked transaction');});

nb_case('Native customer invoice-credit history stays separate from managed cash',function()use($customer,&$nb){global$db;$rows=cpcc_financial_statement($db,$customer->id,'USD');$summary=cpcc_financial_summary($db,$customer->id,'USD');$position=cpcc_financial_managed_position($db,$customer->id);nb_assert(count($rows)===1&&$summary['USD']['received']===1000.0&&$summary['USD']['available']===1000.0,'Native invoice-credit1000 preserved');nb_assert($position['currencies']['USD']['paid_cash']===453.0&&$position['currencies']['USD']['available_to_allocate']===497.0,'Current managed cash remains453paid/497allocatable');$nb['statement_data']=['history_rows'=>$rows,'invoice_credit_summary'=>$summary,'managed_position'=>$position];});
$reader=nb_actor(59);$reader->rights->chinaplanetoperations=(object)['read'=>1];$reader->rights->fournisseur=(object)['commande'=>(object)['lire'=>1],'facture'=>(object)['lire'=>1]];
foreach($ops as$i=>$op)nb_case('Actual supplier'.($i+1).' readonly statement values and name',function()use($reader,$op,$i,$vendors,&$nb){$html=nb_supplier_view($reader,$op);$nb['views']['supplier'.($i+1)]=$html;nb_assert(str_contains($html,dol_escape_htmltag($vendors[$i]->name)),'Actual supplier human name missing');nb_assert(str_contains($html,price($i===0?203:($i===1?150:100))),'Native supplier paid amount missing');nb_assert(!str_contains($html,'Record native supplier payment'),'Readonly role received payment form');});
nb_case('Unauthorized supplier view discloses no private vendor or totals',function()use($ops,$vendors,&$nb){$actor=nb_actor(53);$html=nb_supplier_view($actor,$ops[0]);$nb['views']['supplier_denied']=$html;foreach($vendors as$v)nb_assert(!str_contains($html,$v->name),'Private supplier name leaked');nb_assert(str_contains($html,'You need permission'),'Supplier permission denial absent');nb_assert(!str_contains($html,'Total / Paid / Remaining'),'Supplier totals leaked');});
foreach(['en_US','ar_SA']as$locale)nb_case('Actual customer statement body '.$locale.' renders with private supplier identities excluded',function()use($locale,$customer,$vendors,&$nb){global$langs,$conf,$db;$langs=new Translate('',$conf);$langs->setDefaultLang($locale);$langs->loadLangs(['main','chinaplanetcustomercenter@chinaplanetcustomercenter']);$actor=nb_actor(51);$actor->rights->chinaplanetcustomercenter=(object)['read'=>1];$html=nb_balance_body($actor,$customer->id);$nb['views']['customer_'.$locale]=$html;nb_assert(str_contains($html,cpcc_portal_money(497)),'Managed allocatable497 missing');nb_assert(str_contains($html,cpcc_portal_money(1000)),'Invoice-credit1000 missing');foreach($vendors as$v)nb_assert(!str_contains($html,$v->name),'Private supplier identity in customer statement');nb_assert(!str_contains($html,'1970'),'SQL date rendered as Unix epoch');nb_assert(!str_contains($html,'Warning:')&&!str_contains($html,'Deprecated:'),'PHP diagnostic leaked into rendered statement');nb_assert($langs->trans('CPCCFundsCurrent')!=='CPCCFundsCurrent'&&str_contains($html,dol_escape_htmltag($langs->trans('CPCCFundsCurrent'))),'Native localized managed-funds heading absent');$date=$nb['statement_data']['history_rows'][0]['date'];nb_assert(str_contains($html,dol_escape_htmltag(dol_print_date($db->jdate($date),'day'))),'Native statement date formatting mismatch');});

nb_case('Customer statement actual scope ignores foreign URL customer',function()use($customer,&$nb){
 $actor=nb_actor(52);$actor->rights->chinaplanetcustomercenter=(object)['read'=>1];$html=nb_balance_body($actor,$customer->id);$scope=end($nb['rendered_scopes']);
 nb_assert($scope['resolved_customer']===7&&$scope['requested_customer']===(int)$customer->id,'External URL supplied customer was trusted');
 nb_assert(!array_intersect($scope['row_ids'],array_column($nb['statement_data']['history_rows'],'id')),'Other customer statement rows leaked');$nb['views']['foreign_url_scoped_customer']=$html;
});
nb_case('Supplier actual controller renders writer controls only with read and write roles',function()use($ops,&$nb){
 $writer=nb_rights(nb_actor(56));$html=nb_supplier_view($writer,$ops[0]);$nb['views']['supplier_writer']=$html;
 nb_assert(str_contains($html,'Record native supplier payment'),'Authorized writer control absent');
 $reader=nb_actor(59);$reader->rights->chinaplanetoperations=(object)['read'=>1];$reader->rights->fournisseur=(object)['commande'=>(object)['lire'=>1],'facture'=>(object)['lire'=>1]];
 nb_assert(!str_contains(nb_supplier_view($reader,$ops[0]),'Record native supplier payment'),'Read-only role received writer controls');
});
$deleted=nb_rights(nb_actor(56));$deleted->id=99999;
$masked=nb_rights(nb_actor(51));$masked->socid=0;
foreach(['deleted_cached_identity'=>$deleted,'masked_external_object'=>$masked]as$name=>$actor)foreach(['supplier'=>'nb_supplier_retry','guided'=>'nb_guided_retry']as$kind=>$method)nb_case($kind.' refuses '.$name.' before business SQL',fn()=>nb_expect_actor_refusal($method,$actor));
$other=nb_rights(nb_actor(54));
$entrypoints=[
 'supplier_order_create'=>function($d,$a){global$ops,$vendors,$order;return(new OperationSupplierWorkflowService($d,1))->createOrder($ops[0],['supplier_id'=>$vendors[0]->id,'currency'=>'USD','exchange_rate'=>1,'supplier_reference'=>'SYN-J-SO-1','delivery_date'=>date('Y-m-d',strtotime('+10 days')),'lines'=>[['customer_line_id'=>$order->lines[0]->id,'unit_cost'=>300,'vat'=>0,'supplier_ref'=>'SYN-COST-1']]],$a,sha1('journey-so-0'));},
 'supplier_order_validate'=>fn($d,$a)=>(new OperationSupplierWorkflowService($d,1))->validateOrder($ops[0],$a),
 'supplier_invoice_create'=>fn($d,$a)=>(new OperationSupplierWorkflowService($d,1))->createInvoice($ops[0],'SYN-J-INV-1',date('Y-m-d'),$a,sha1('journey-si-0')),
 'supplier_invoice_validate'=>fn($d,$a)=>(new OperationSupplierWorkflowService($d,1))->validateInvoice($ops[0],$a),
 'supplier_payment_resume'=>function($d,$a){global$ops,$pdo;$id=(int)$pdo->query("SELECT rowid FROM llx_cpo_supplier_action WHERE request_token='CPO-SPY-".strtoupper(sha1('journey-pay-0'))."'")->fetchColumn();return(new OperationSupplierWorkflowService($d,1))->resumePayment($ops[0],$id,$a);},
 'guided_deposit_create'=>fn($d,$a)=>(new NativeAdvanceWorkflowService($d,1))->createDepositFromOrigin((int)$customer->id,'commande',(int)$order->id,$a,hash('sha256','journey-v2-deposit')),
 'guided_receipt_resume'=>function($d,$a){global$customer,$deposit,$pdo;$id=(int)$pdo->query('SELECT rowid FROM llx_cpcc_guided_receipt WHERE fk_invoice='.(int)$deposit)->fetchColumn();return(new NativeAdvanceWorkflowService($d,1))->resumeBankReceipt((int)$customer->id,$id,$a);},
 'supplier_current_context_mismatch'=>function($d,$a){global$ops,$user;return(new OperationSupplierWorkflowService($d,2))->validateOrder($ops[0],$user);},
 'guided_current_context_mismatch'=>function($d,$a){global$customer,$deposit,$user;return(new NativeAdvanceWorkflowService($d,2))->postBankReceipt((int)$customer->id,$deposit,11,'VIR',date('Y-m-d'),'SYN-JOURNEY-DEPOSIT',$user,hash('sha256','journey-v2-receipt'));}
];
foreach($entrypoints as$name=>$method)nb_case($name.' validates identity/context before business SQL',fn()=>nb_expect_actor_refusal($method,$other));
nb_case('Saved confirmed payment has role-gated existing-record verification',function()use($ops,&$nb){
 $writer=nb_rights(nb_actor(56));$html=nb_supplier_view($writer,$ops[0]);$nb['views']['supplier_saved_writer']=$html;
 nb_assert(str_contains($html,'value="supplier_resume_payment"'),'Confirmed saved payment has no verify action');
 $reader=nb_actor(59);$reader->rights->chinaplanetoperations=(object)['read'=>1];$reader->rights->fournisseur=(object)['commande'=>(object)['lire'=>1],'facture'=>(object)['lire'=>1]];
 $readHtml=nb_supplier_view($reader,$ops[0]);$nb['views']['supplier_saved_reader']=$readHtml;nb_assert(!str_contains($readHtml,'value="supplier_resume_payment"'),'Read-only role received recovery action');
});
nb_case('Unresolved payment blocks new form; native resume preserves cash',function()use($ops,$pdo,&$nb){global$db;
 $marker=$pdo->query("SELECT * FROM llx_cpo_supplier_action WHERE request_token='CPO-SPY-".strtoupper(sha1('journey-pay-0'))."'")->fetch(PDO::FETCH_ASSOC);$id=(int)$marker['rowid'];$before=nb_cash_signature();
 try{
  $pdo->exec("UPDATE llx_cpo_supplier_action SET status='correction_required' WHERE rowid=$id");$writer=nb_rights(nb_actor(56));$html=nb_supplier_view($writer,$ops[0]);$nb['views']['supplier_review_required']=$html;
  nb_assert(!str_contains($html,'value="supplier_post_payment"'),'New cash form remains open with unresolved payment');nb_assert(str_contains($html,'value="supplier_resume_payment"'),'Saved native payment cannot be verified');
  $reader=nb_actor(59);$reader->rights->chinaplanetoperations=(object)['read'=>1];$reader->rights->fournisseur=(object)['commande'=>(object)['lire'=>1],'facture'=>(object)['lire'=>1]];nb_assert(!str_contains(nb_supplier_view($reader,$ops[0]),'value="supplier_resume_payment"'),'Read-only role receives unresolved resume form');
  $r=(new OperationSupplierWorkflowService($db,1))->resumePayment($ops[0],$id,$writer);nb_assert((int)$r['payment_id']===(int)$marker['fk_supplier_payment'],'Resume changed native payment');nb_assert(nb_cash_signature()===$before,'Resume recreated cash');
  nb_assert(str_contains(nb_supplier_view($writer,$ops[0]),'value="supplier_post_payment"'),'Resolved ordinary partial payment cannot accept a future distinct payment');
 }finally{$pdo->exec("UPDATE llx_cpo_supplier_action SET status='confirmed',error_code=NULL WHERE rowid=$id");}
});
foreach(['preparing','unknown_fixture']as$state)nb_case('Unresolved '.$state.' without native ID requires review, not automatic recovery',function()use($state,$ops,$pdo,&$nb){
 $marker=$pdo->query("SELECT * FROM llx_cpo_supplier_action WHERE request_token='CPO-SPY-".strtoupper(sha1('journey-pay-0'))."'")->fetch(PDO::FETCH_ASSOC);$id=(int)$marker['rowid'];
 try{$pdo->exec("UPDATE llx_cpo_supplier_action SET status='$state',fk_supplier_payment=NULL WHERE rowid=$id");$html=nb_supplier_view(nb_rights(nb_actor(56)),$ops[0]);$nb['views']['supplier_'.$state]=$html;
  nb_assert(!str_contains($html,'value="supplier_post_payment"'),'Unresolved marker allows new cash form');nb_assert(!str_contains($html,'name="supplier_marker_id" value="'.$id.'"'),'Missing native identity offers automatic recovery');
 }finally{$pdo->exec("UPDATE llx_cpo_supplier_action SET status='confirmed',fk_supplier_payment=".(int)$marker['fk_supplier_payment']." WHERE rowid=$id");}
});
foreach([56,58]as$actorId)nb_case('Guided persisted resume keeps native identity for actor'.$actorId,function()use($actorId,$customer,$deposit,$receipt,$pdo){global$db;
 $actor=nb_rights(nb_actor($actorId));$id=(int)$pdo->query('SELECT rowid FROM llx_cpcc_guided_receipt WHERE fk_invoice='.(int)$deposit)->fetchColumn();$before=nb_cash_signature();$r=(new NativeAdvanceWorkflowService($db,1))->resumeBankReceipt((int)$customer->id,$id,$actor);
 nb_assert($r===$receipt,'Guided resume changed identities');nb_assert(nb_cash_signature()===$before,'Guided resume recreated native cash');
});
nb_case('Read-only supplier role cannot recover an unresolved saved payment',function()use($ops,$pdo){
 $marker=$pdo->query("SELECT * FROM llx_cpo_supplier_action WHERE request_token='CPO-SPY-".strtoupper(sha1('journey-pay-0'))."'")->fetch(PDO::FETCH_ASSOC);$id=(int)$marker['rowid'];$reader=nb_actor(59);$reader->rights->chinaplanetoperations=(object)['read'=>1];$reader->rights->fournisseur=(object)['commande'=>(object)['lire'=>1],'facture'=>(object)['lire'=>1]];
 try{$pdo->exec("UPDATE llx_cpo_supplier_action SET status='correction_required' WHERE rowid=$id");$html=nb_supplier_view($reader,$ops[0]);nb_assert(!str_contains($html,'value="supplier_resume_payment"'),'Read-only user sees a recovery control');
  nb_expect_actor_refusal(fn($d,$a)=>(new OperationSupplierWorkflowService($d,1))->resumePayment($ops[0],$id,$a),$reader);
 }finally{$pdo->exec("UPDATE llx_cpo_supplier_action SET status='confirmed',error_code=NULL WHERE rowid=$id");}
});
// Current read-only obligation increment, using actual native state/controller/template.
foreach(['en_US','ar_SA'] as $locale)foreach($ops as $i=>$op)nb_case('Linked supplier obligation native values '.$locale.' supplier'.($i+1),function()use($locale,$op,$i,$vendors,&$nb){
 global $langs,$conf;$langs=new Translate('',$conf);$langs->setDefaultLang($locale);$langs->loadLangs(['main','chinaplanetoperations@chinaplanetoperations']);
 $reader=nb_actor(59);$reader->rights->chinaplanetoperations=(object)['read'=>1];$reader->rights->fournisseur=(object)['commande'=>(object)['lire'=>1],'facture'=>(object)['lire'=>1]];
 $before=nb_cash_signature();$html=nb_supplier_view($reader,$op);$state=(new OperationSupplierWorkflowService($GLOBALS['db'],1))->state($op);$p=cpo_supplier_obligation_projection($state);
 nb_assert($p['invoice']['total']===[300.0,250.0,200.0][$i]&&$p['invoice']['paid']===[203.0,150.0,100.0][$i]&&$p['invoice']['remaining']===[97.0,100.0,100.0][$i],'Actual native invoice/payment residual mismatch');
 nb_assert(str_contains($html,'data-obligation-coverage="recorded_payments_only"'),'Actual statement block missing');
 nb_assert(str_contains($html,dol_escape_htmltag($langs->trans('CPOSupplierObligationLimit')))&&$langs->trans('CPOSupplierObligationLimit')!=='CPOSupplierObligationLimit','Actual translated limitation missing');
 nb_assert(str_contains($html,'/fourn/facture/card.php?facid='.(int)$state['supplier_invoice']->id)&&str_contains($html,dol_escape_htmltag($vendors[$i]->name)),'Exact authorized native invoice/vendor missing');
 nb_assert(!str_contains($html,'Record native supplier payment')&&nb_cash_signature()===$before,'Readonly obligation view changed cash or actions');
 $nb['views']['supplier_obligation_'.$locale.'_'.($i+1)]=$html;
});
class NbObligationQueryFailure {
 private $inner;public $hit=false;function __construct($db){$this->inner=$db;}
 function __get($name){return $this->inner->$name;}function __call($name,$args){return $this->inner->$name(...$args);}
 function query($sql,...$args){if(str_starts_with($sql,'SELECT * FROM llx_cpo_supplier_action WHERE entity=')){$this->hit=true;return false;}return $this->inner->query($sql,...$args);}
}
nb_case('Supplier marker read failure is unavailable, never empty obligations',function()use($ops){
 global $db;$before=nb_cash_signature();$fault=new NbObligationQueryFailure($db);$blocked=false;
 try{(new OperationSupplierWorkflowService($fault,1))->state($ops[0]);}catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'evidence is unavailable');}
 nb_assert($fault->hit&&$blocked,'Missing query result silently became no linked invoice');nb_assert(nb_cash_signature()===$before,'Read failure changed cash');
});
$nb['status']=$nb['failed']?'failed':'passed';
file_put_contents($s['output'].'/native-statement-permissions.json',json_encode($nb,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod($s['output'].'/native-statement-permissions.json',0600);
foreach($nb['cases']as$c)echo 'BOUNDARY '.strtoupper($c['status']).' '.$c['name'].(isset($c['error'])?': '.$c['error']:'')."\n";
if($nb['failed'])throw new RuntimeException('Native statement/permission failures: '.$nb['failed']);
return $nb;
