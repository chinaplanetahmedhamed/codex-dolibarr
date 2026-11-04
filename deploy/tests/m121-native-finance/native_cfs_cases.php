<?php
/** Actual CFS SQL/native locks+triggers in the guarded disposable server only. */
require_once $s['source'].'/custom/chinaplanetcustomercenter/class/CustomerFundsService.class.php';
$cases=[];$failed=0;
function nc_case($label,$fn){global$cases,$failed;try{$fn();$cases[]=['name'=>$label,'status'=>'passed'];}catch(Throwable$e){$failed++;$cases[]=['name'=>$label,'status'=>'failed','error'=>$e->getMessage()];}}
function nc_expect($ok,$message){if(!$ok)throw new RuntimeException($message);}
function nc_refused($fn){try{$fn();}catch(Throwable$e){return;}throw new RuntimeException('Expected refusal, got success');}
function nc_count($table){global$pdo;return(int)$pdo->query('SELECT COUNT(*) FROM llx_'.$table)->fetchColumn();}
class NcFaultDb {
    private $native;public $failure='';public $rollbackCalls=0;
    function __construct($native){$this->native=$native;}
    function __call($name,$args){return$this->native->$name(...$args);}
    function begin(){return$this->failure==='begin'?0:$this->native->begin();}
    function commit(){if($this->failure==='commit_before')return 0;$v=$this->native->commit();return$this->failure==='commit_after'?0:$v;}
    function rollback(){$this->rollbackCalls++;return$this->native->rollback();}
    function query($sql){if($this->failure==='event'&&strpos($sql,'INSERT INTO llx_cpcc_fund_event')===0)return false;return$this->native->query($sql);}
}
$funds=new CustomerFundsService($db,1);$receipt=null;$allocation=null;
nc_case('native managed receipt creation retry+confirmation uses actual100 payment',function()use($funds,$customerPayment,&$receipt){
 $data=['fk_soc'=>7,'receipt_date'=>'2026-11-03','amount'=>100,'currency_code'=>'USD','reference'=>'SYN-NATIVE-CFS','request_key'=>hash('sha256','native-cfs-receipt')];
 $receipt=$funds->createPendingReceipt($data,9);nc_expect($receipt===$funds->createPendingReceipt($data,9),'Receipt retry identity');$funds->confirmReceipt($receipt,$customerPayment,9);$funds->confirmReceipt($receipt,$customerPayment,9);
 nc_expect(nc_count('cpcc_customer_receipt')===1,'One receipt');nc_expect(nc_count('cpcc_fund_event')===2,'Create+confirm audit only');
});
nc_case('native managed-credit trigger lifecycle installs and verifies in empty fixture only',function()use($db){nc_expect(ManagedFundsNativeGuard::install($db),'Actual native trigger guard failed');});
nf_sql("INSERT INTO llx_cpo_operation(rowid,entity,ref,service_type,title,fk_soc,fk_user_creat,date_creation) VALUES(101,1,'SYN-NATIVE-CFS','procurement','Synthetic operation',7,9,'2026-11-03')");
$reservation=['target_type'=>'operation','target_id'=>101,'amount'=>40,'request_key'=>hash('sha256','native-cfs-allocation')];
nc_case('native reserve+supplier attribution retries do not create cash',function()use($funds,$receipt,$reservation,$supplierPayment,&$allocation){
 $allocation=$funds->reserve($receipt,$reservation,9);nc_expect($allocation===$funds->reserve($receipt,$reservation,9),'Allocation retry identity');
 $funds->settleWithSupplierPayment($allocation,$supplierPayment,9);$funds->settleWithSupplierPayment($allocation,$supplierPayment,9);
 nc_expect(nc_count('cpcc_fund_allocation')===1&&nc_count('cpcc_fund_event')===4,'One reserve+settlement audit');
 $r=$funds->reconcileReceipt($receipt);nc_expect($r['available']===60.0&&$r['managed_active']===40.0,'Native CFS coverage100 less attribution40');
 nc_expect(nc_count('bank')===2&&nc_count('paiement')===1&&nc_count('paiementfourn')===1,'No duplicated native cash');
});
nc_case('settled attribution reversal refused before original cash/evidence changes',function()use($funds,$allocation){nc_refused(fn()=>$funds->reverseAllocation($allocation,'SYNTHETIC settled reversal',9));nc_expect(nc_count('bank')===2&&nc_count('cpcc_fund_event')===4,'Settled reversal preserves cash and audit');});
$reversalId=null;
nc_case('unsettled reservation reversal is idempotent and preserves native original cash',function()use($funds,$receipt,$reservation,&$reversalId){
 $v=$reservation;$v['amount']=10;$v['request_key']=hash('sha256','native-cfs-reversible');$reversalId=$funds->reserve($receipt,$v,9);$funds->reverseAllocation($reversalId,'Synthetic release',9);$funds->reverseAllocation($reversalId,'Synthetic release',9);
 nc_expect(nc_count('cpcc_fund_event')===6,'Reverse audit once');nc_expect($funds->reconcileReceipt($receipt)['available']===60.0,'Unsettled reversal releases10 only, settled40 stays');nc_expect(nc_count('bank')===2,'No reverse cash created');
});
foreach(['begin','commit_before','commit_after','event']as$mode)nc_case('receipt transaction fault '.$mode.' reconciles durable identity',function()use($db,$pdo,$mode){
 $adapter=new NcFaultDb($db);$service=new CustomerFundsService($adapter,1);$data=['fk_soc'=>7,'receipt_date'=>'2026-11-03','amount'=>3,'currency_code'=>'USD','reference'=>'SYN-FAULT-'.$mode,'request_key'=>hash('sha256','native-cfs-fault-'.$mode)];$before=nc_count('cpcc_customer_receipt');$events=nc_count('cpcc_fund_event');$adapter->failure=$mode;nc_refused(fn()=>$service->createPendingReceipt($data,9));
 $persisted=$mode==='commit_after';nc_expect(nc_count('cpcc_customer_receipt')===$before+($persisted?1:0),'Receipt persistence incorrect');nc_expect(nc_count('cpcc_fund_event')===$events+($persisted?1:0),'Audit atomicity incorrect');
 $adapter->failure='';$id=$service->createPendingReceipt($data,9);nc_expect($id===$service->createPendingReceipt($data,9),'Recovered identity mismatch');nc_expect(nc_count('cpcc_customer_receipt')===$before+1&&nc_count('cpcc_fund_event')===$events+1,'Recovery duplicated receipt/event');
 nc_expect((int)$db->transaction_opened===0,'Owned transaction counter leaks');
});
foreach(['begin','commit_before','commit_after','event']as$mode)nc_case('reservation reversal fault '.$mode.' never acknowledges an unconfirmed transition',function()use($db,$pdo,$funds,$receipt,$reservation,$mode){
 $v=$reservation;$v['amount']=5;$v['request_key']=hash('sha256','native-cfs-fault-reverse-'.$mode);$id=$funds->reserve($receipt,$v,9);$events=nc_count('cpcc_fund_event');$adapter=new NcFaultDb($db);$adapter->failure=$mode;$service=new CustomerFundsService($adapter,1);$refused=false;
 try{$service->reverseAllocation($id,'Synthetic transaction failure',9);}catch(Throwable$e){$refused=true;}
 $status=$pdo->query('SELECT status FROM llx_cpcc_fund_allocation WHERE rowid='.$id)->fetchColumn();$auditDelta=nc_count('cpcc_fund_event')-$events;
 try{
  nc_expect($refused,'Reversal acknowledged; durable status='.$status.'; audit delta='.$auditDelta);
  $persisted=$mode==='commit_after';nc_expect($status===($persisted?'reversed':'reserved'),'Wrong persisted reversal state');nc_expect($auditDelta===($persisted?1:0),'Reversal audit not atomic');
  if($mode==='begin')nc_expect($adapter->rollbackCalls===0,'Rolled back unowned failed-BEGIN transaction');
  $adapter->failure='';$service->reverseAllocation($id,'Synthetic transaction failure',9);$service->reverseAllocation($id,'Synthetic transaction failure',9);
  nc_expect(nc_count('cpcc_fund_event')===$events+1,'Reversal retry duplicated audit');nc_expect((int)$db->transaction_opened===0,'Reversal left owned transaction open');
 }finally{if((int)$db->transaction_opened>0)$db->rollback();}
});
foreach(['confirm','reserve','supplier_settle','reconcile']as$method)foreach(['begin','commit_before','commit_after']as$mode)nc_case('native '.$method.' rejects transaction acknowledgement fault '.$mode,function()use($db,$pdo,$funds,$receipt,$reservation,$customerPayment,$supplierPayment,$allocation,$method,$mode){
 $adapter=new NcFaultDb($db);$service=new CustomerFundsService($adapter,1);$v=$reservation;$v['amount']=1;$v['request_key']=hash('sha256','native-cfs-matrix-'.$method.'-'.$mode);$before=nc_count('cpcc_fund_allocation');$events=nc_count('cpcc_fund_event');$adapter->failure=$mode;$refused=false;
 $call=function()use($service,$method,$receipt,$v,$customerPayment,$supplierPayment,$allocation){switch($method){case'confirm':return$service->confirmReceipt($receipt,$customerPayment,9);case'reserve':return$service->reserve($receipt,$v,9);case'supplier_settle':return$service->settleWithSupplierPayment($allocation,$supplierPayment,9);default:return$service->reconcileReceipt($receipt);}};
 try{
  try{$call();}catch(Throwable$e){$refused=true;}
  nc_expect($refused,'Transaction fault reported success; durable allocation delta='.(nc_count('cpcc_fund_allocation')-$before));
  $persisted=$method==='reserve'&&$mode==='commit_after';nc_expect(nc_count('cpcc_fund_allocation')===$before+($persisted?1:0),'Unexpected allocation persistence');nc_expect(nc_count('cpcc_fund_event')===$events+($persisted?1:0),'Unexpected audit persistence');
  if($mode==='begin')nc_expect($adapter->rollbackCalls===0,'Failed BEGIN rolled back unowned transaction');
  $adapter->failure='';$first=$call();nc_expect($first===$call(),'Reconciled retry changed result/identity');
  nc_expect(nc_count('cpcc_fund_allocation')===$before+($method==='reserve'?1:0),'Retry created duplicate allocation');nc_expect((int)$db->transaction_opened===0,'Retry left transaction open');
 }finally{if((int)$db->transaction_opened>0)$db->rollback();}
 $free=$pdo->query("SELECT IS_FREE_LOCK('".ManagedFundsNativeGuard::LIFECYCLE_LOCK."')")->fetchColumn();nc_expect((int)$free===1,'Lifecycle advisory lock leaked');
});
$snapshotProof=[];
nc_case('native snapshot either verifies or explicitly fails closed without changing session settings',function()use($db,$funds,&$snapshotProof){
 $before=$db->fetch_object(nf_sql('SELECT @@SESSION.autocommit ac,@@SESSION.tx_isolation iso,@@SESSION.tx_read_only ro,@@SESSION.completion_type completion'));
 $probe=$db->query('SELECT @@SESSION.in_transaction active');$probeCode=$db->db->errno;
 $position=$funds->currentPosition(7);$snapshotProof=['coverage'=>$position['coverage'],'reasons'=>$position['reasons'],'ownership_probe_supported'=>(bool)$probe,'ownership_probe_mysql_error'=>$probeCode];
 nc_expect(in_array($position['coverage'],['verified_managed','unavailable','partial'],true),'Unexpected coverage');
 if($position['coverage']==='verified_managed')nc_expect($position['currencies']['USD']['received_cash']===100.0&&$position['currencies']['USD']['paid_cash']===40.0,'Verified native snapshot mismatch');
 else foreach($position['currencies']as$bucket)nc_expect($bucket['received_cash']===null&&$bucket['paid_cash']===null&&$bucket['available_to_allocate']===null,'Failed snapshot certified numeric cash');
 $after=$db->fetch_object(nf_sql('SELECT @@SESSION.autocommit ac,@@SESSION.tx_isolation iso,@@SESSION.tx_read_only ro,@@SESSION.completion_type completion'));
 nc_expect($before==$after&&(int)$db->transaction_opened===0,'Native snapshot changed session settings/transaction ownership');
});
nc_case('native caller transaction and uncommitted audit survive snapshot refusal',function()use($db,$pdo,$funds){
 $before=nc_count('cpcc_fund_event');nc_expect((bool)$db->begin(),'Fixture caller begin');
 try{
  nf_sql("INSERT INTO llx_cpcc_fund_event(entity,fk_soc,object_type,object_id,action_code,status_from,status_to,message,fk_user,date_action) VALUES(1,7,'fixture',0,'caller_probe','','','SYNTHETIC caller-owned pending audit',9,'2026-11-03')");
  $position=$funds->currentPosition(7);nc_expect($position['coverage']==='unavailable'&&(int)$db->transaction_opened===1,'Caller ownership altered');
  nc_expect(nc_count('cpcc_fund_event')===$before,'Reader committed caller audit');
  $row=$db->fetch_object(nf_sql('SELECT COUNT(*) n FROM llx_cpcc_fund_event'));nc_expect((int)$row->n===$before+1,'Reader rolled back caller audit');
 }finally{$db->rollback();}
 nc_expect(nc_count('cpcc_fund_event')===$before,'Fixture caller rollback failed');
});
nc_case('actual loss of owned secondary connection never certifies cash or recreates payment',function()use($s,$pdo,$db){
 $owned=new DoliDBMysqli('mysqli','localhost','root','',$s['database']);nc_expect($owned->ok,'Secondary fixture connection unavailable');
 $q=$owned->query('SELECT CONNECTION_ID() id,@@socket socket');$identity=$q?$owned->fetch_object($q):null;
 nc_expect($identity&&$identity->socket===$s['fixture'].'/mysql.sock','Secondary connection not fixture-owned');
 $main=$db->fetch_object(nf_sql('SELECT CONNECTION_ID() id'));$control=(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();$id=(int)$identity->id;
 nc_expect($id>0&&$id!==(int)$main->id&&$id!==$control,'Refuse to terminate unowned/main connection');
 $pdo->exec('KILL CONNECTION '.$id);
 try{$position=(new CustomerFundsService($owned,1))->currentPosition(7);nc_expect($position['coverage']==='unavailable','Lost connection certified managed state');nc_expect(!empty($position['connection_retired']),'Physically lost native connection must signal request retirement');foreach($position['currencies']as$b)nc_expect($b['received_cash']===null&&$b['available_to_allocate']===null,'Lost connection returned cash amount');}
 finally{try{$owned->close();}catch(Throwable$ignored){}}
 nc_expect(nc_count('bank')===2&&nc_count('paiement')===1&&nc_count('paiementfourn')===1,'Connection failure recreated native payment/cash');
});
// Fresh-state mutation faults, separate from the preceding retry acknowledgements.
function nc_native_payment($supplierSide,$invoiceId,$amount,$token){
 global$db,$user,$third,$supplier;
 $paymentMode=$db->fetch_object(nf_sql("SELECT id FROM llx_c_paiement WHERE code='VIR' LIMIT 1"));nc_expect($paymentMode,'Native VIR dictionary required');
 // Synthetic invoice is seeded; actual unmodified core creates payment+bank.
 if($supplierSide)nf_sql("INSERT INTO llx_facture_fourn(rowid,ref,ref_supplier,entity,type,fk_soc,datef,fk_statut,paye,total_ht,total_ttc,multicurrency_code,multicurrency_tx,multicurrency_total_ht,multicurrency_total_ttc) VALUES(".$invoiceId.",'SYN-FAULT-SUP".$invoiceId."','SYN-VENDOR.".$invoiceId."',1,0,88,'2026-11-03',1,0,".$amount.",".$amount.",'USD',1,".$amount.",".$amount.")");
 else nf_sql("INSERT INTO llx_facture(rowid,ref,entity,type,fk_soc,datef,fk_statut,paye,total_ht,total_ttc,multicurrency_code,multicurrency_tx,multicurrency_total_ht,multicurrency_total_ttc) VALUES(".$invoiceId.",'SYN-FAULT-DEP".$invoiceId."',1,3,7,'2026-11-03',1,0,".$amount.",".$amount.",'USD',1,".$amount.",".$amount.")");
 $p=$supplierSide?new PaiementFourn($db):new Paiement($db);$p->datepaye=strtotime('2026-11-03 12:00:00');$p->paiementid=(int)$paymentMode->id;$p->num_payment=$token;$p->note_private='Synthetic fresh-state fault';$p->fk_account=11;$p->amounts=[$invoiceId=>$amount];$p->multicurrency_code=[$invoiceId=>'USD'];$p->multicurrency_tx=[$invoiceId=>1];
 $id=$p->create($user,0,$supplierSide?$supplier:$third);nc_expect($id>0,'Fresh actual native payment failed');$bank=$p->addPaymentToBank($user,$supplierSide?'payment_supplier':'payment','Synthetic fresh-state fault',11,'','');nc_expect($bank>0,'Fresh actual native bank line failed');return ['payment'=>$id,'bank'=>$bank];
}
$freshProof=[];
foreach(['begin','commit_before','commit_after','event']as$index=>$faultMode)nc_case('fresh pending receipt confirmation fault '.$faultMode.' is atomic and recoverable',function()use($db,$pdo,$funds,$index,$faultMode,&$freshProof){
 $native=nc_native_payment(false,30+$index,3,'SYN-FRESH-CONFIRM-'.$faultMode);$id=$funds->createPendingReceipt(['fk_soc'=>7,'receipt_date'=>'2026-11-03','amount'=>3,'currency_code'=>'USD','reference'=>'SYN-FRESH-CONFIRM','request_key'=>hash('sha256','fresh-confirm-'.$faultMode)],9);$events=nc_count('cpcc_fund_event');$cash=[nc_count('bank'),nc_count('paiement'),nc_count('paiementfourn')];
 $adapter=new NcFaultDb($db);$adapter->failure=$faultMode;$service=new CustomerFundsService($adapter,1);nc_refused(fn()=>$service->confirmReceipt($id,$native['payment'],9));
 $status=$pdo->query('SELECT status FROM llx_cpcc_customer_receipt WHERE rowid='.$id)->fetchColumn();$persisted=$faultMode==='commit_after';nc_expect($status===($persisted?'confirmed':'pending'),'Fresh confirmation persistence mismatch');nc_expect(nc_count('cpcc_fund_event')===$events+($persisted?1:0),'Fresh confirmation audit not atomic');
 $adapter->failure='';$service->confirmReceipt($id,$native['payment'],9);$service->confirmReceipt($id,$native['payment'],9);nc_expect(nc_count('cpcc_fund_event')===$events+1,'Fresh confirmation retry duplicated audit');nc_expect([nc_count('bank'),nc_count('paiement'),nc_count('paiementfourn')]===$cash,'Confirmation recreated native money');
 $freshProof[]=['transition'=>'pending_to_confirmed','fault'=>$faultMode,'native_payment'=>$native['payment'],'native_bank'=>$native['bank'],'cash_recreated_by_cfs'=>false];
});
foreach(['begin','commit_before','commit_after','event']as$index=>$faultMode)nc_case('fresh supplier attribution fault '.$faultMode.' is atomic and recoverable',function()use($db,$pdo,$funds,$receipt,$reservation,$index,$faultMode,&$freshProof){
 $native=nc_native_payment(true,202+$index,3,'SYN-FRESH-SETTLE-'.$faultMode);$v=$reservation;$v['amount']=3;$v['request_key']=hash('sha256','fresh-settle-'.$faultMode);$id=$funds->reserve($receipt,$v,9);$events=nc_count('cpcc_fund_event');$cash=[nc_count('bank'),nc_count('paiement'),nc_count('paiementfourn')];
 $adapter=new NcFaultDb($db);$adapter->failure=$faultMode;$service=new CustomerFundsService($adapter,1);nc_refused(fn()=>$service->settleWithSupplierPayment($id,$native['payment'],9));
 $status=$pdo->query('SELECT status FROM llx_cpcc_fund_allocation WHERE rowid='.$id)->fetchColumn();$persisted=$faultMode==='commit_after';nc_expect($status===($persisted?'settled':'reserved'),'Fresh settlement persistence mismatch');nc_expect(nc_count('cpcc_fund_event')===$events+($persisted?1:0),'Fresh settlement audit not atomic');
 $adapter->failure='';$service->settleWithSupplierPayment($id,$native['payment'],9);$service->settleWithSupplierPayment($id,$native['payment'],9);nc_expect(nc_count('cpcc_fund_event')===$events+1,'Fresh settlement retry duplicated audit');nc_expect([nc_count('bank'),nc_count('paiement'),nc_count('paiementfourn')]===$cash,'Attribution recreated native money');
 $free=$pdo->query("SELECT IS_FREE_LOCK('".ManagedFundsNativeGuard::LIFECYCLE_LOCK."')")->fetchColumn();nc_expect((int)$free===1,'Fresh settlement lifecycle lock leaked');
 $freshProof[]=['transition'=>'reserved_to_settled_supplier_payment','fault'=>$faultMode,'native_payment'=>$native['payment'],'native_bank'=>$native['bank'],'cash_recreated_by_cfs'=>false];
});
nc_case('native nested confirmation preserves caller-owned transaction and pending audit',function()use($db,$pdo,$funds,$receipt,$customerPayment){
 $events=nc_count('cpcc_fund_event');nc_expect((bool)$db->begin(),'Nested fixture begin');
 try{nf_sql("INSERT INTO llx_cpcc_fund_event(entity,fk_soc,object_type,object_id,action_code,status_from,status_to,message,fk_user,date_action) VALUES(1,7,'fixture',0,'nested_probe','','','SYNTHETIC caller-owned audit',9,'2026-11-03')");$funds->confirmReceipt($receipt,$customerPayment,9);nc_expect((int)$db->transaction_opened===1,'Nested confirmation committed caller depth');nc_expect(nc_count('cpcc_fund_event')===$events,'Nested confirmation committed caller audit');$r=$db->fetch_object(nf_sql('SELECT COUNT(*) n FROM llx_cpcc_fund_event'));nc_expect((int)$r->n===$events+1,'Nested confirmation lost caller audit');}finally{$db->rollback();}
 nc_expect(nc_count('cpcc_fund_event')===$events,'Explicit caller rollback failed');
});
$finalNativeCounts=[];
nc_case('all native fault/recovery cases preserve exact cash source counts and signed USD net',function()use($pdo,&$finalNativeCounts){
 $finalNativeCounts=['customer_payments'=>nc_count('paiement'),'supplier_payments'=>nc_count('paiementfourn'),'bank_lines'=>nc_count('bank')];
 nc_expect($finalNativeCounts===['customer_payments'=>5,'supplier_payments'=>5,'bank_lines'=>10],'Native source identities duplicated/lost');
 $rows=$pdo->query('SELECT b.amount,a.currency_code,b.fk_account FROM llx_bank b JOIN llx_bank_account a ON a.rowid=b.fk_account')->fetchAll(PDO::FETCH_ASSOC);$net=0.0;
 foreach($rows as$r){nc_expect($r['currency_code']==='USD'&&(int)$r['fk_account']===11,'Refuse cross-currency/account summation');$net+=(float)$r['amount'];}
 nc_expect(abs($net-60.0)<0.000001,'Signed native USD cash differs from explicit actual payments');$finalNativeCounts['USD_net']=60;
});
file_put_contents($s['output'].'/native-cfs-cases.json',json_encode(['cases'=>$cases,'snapshot'=>$snapshotProof,'fresh_mutations'=>$freshProof,'final_native_counts'=>$finalNativeCounts,'failed'=>$failed,'scope'=>'Native SQL/trigger/lock execution with explicit transaction API fault adapters; not network fault or MariaDB proof'],JSON_PRETTY_PRINT));chmod($s['output'].'/native-cfs-cases.json',0600);
foreach($cases as$c)echo strtoupper($c['status']).' '.$c['name'].(isset($c['error'])?': '.$c['error']:'')."\n";
if($failed)throw new RuntimeException('Native CFS lifecycle failures: '.$failed);
return ['passed'=>count($cases),'final_native_counts'=>$finalNativeCounts,'fresh_mutations'=>$freshProof,'snapshot'=>$snapshotProof,'cases'=>$cases];
