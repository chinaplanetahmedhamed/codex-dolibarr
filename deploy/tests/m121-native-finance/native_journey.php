<?php
/** Actual owner-service journey, exclusively inside scenario.php's guarded fresh fixture. */
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
foreach([
 '/custom/chinaplanetcustomercenter/class/NativeAdvanceWorkflowService.class.php',
 '/custom/chinaplanetoperations/class/OperationsService.class.php',
 '/custom/chinaplanetoperations/class/OperationSupplierWorkflowService.class.php',
 '/custom/chinaplanetoperations/class/OperationShipmentService.class.php',
 '/custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php',
 '/custom/chinaplanetlogistics/class/ShipmentContextService.class.php',
 '/custom/chinaplanetdocuments/class/DocumentsWorkspace.class.php'
]as$file)require_once $s['source'].$file;

$nj=['status'=>'running','steps'=>[],'positions'=>[],'suppliers'=>[],'checks'=>0,
 'stubs'=>['Module settings, actor9/bank11 and standard dictionary context are synthetic','Role records/rights are seeded; real User.hasRight and owner authorization execute','Only upload provenance/move seam is substituted; no authenticated HTTP or editor PDF generation'],
 'native_calls'=>[],'limits'=>['Local MySQL5.7/PHP8.3 is not target MariaDB/PHP8.2','No real posting, remote access or production','No native authenticated desktop/phone/session/CSRF acceptance']];
function nj_check($ok,$label){$GLOBALS['nj']['checks']++;if(!$ok)throw new RuntimeException($label);}
function nj_step($name,$fn){
 global$nj;
 try{$value=$fn();$nj['steps'][]=['name'=>$name,'status'=>'passed'];echo 'JOURNEY PASS '.$name."\n";return$value;}
 catch(Throwable$e){$nj['steps'][]=['name'=>$name,'status'=>'failed','error'=>$e->getMessage()];throw$e;}
}
function nj_import($relative){
 global$s,$pdo;$path=$s['source'].'/'.$relative;nj_check(is_file($path),'Missing owner schema '.$relative);
 foreach(explode(';',nf_import_sql(file_get_contents($path)))as$sql)if(trim($sql)!=='')$pdo->exec($sql);
}
function nj_company($name,$customer){
 global$db,$user,$countryId;$o=new Societe($db);$o->entity=1;$o->name=$name;$o->status=1;
 $o->client=$customer?1:0;$o->fournisseur=$customer?0:1;$o->country_id=$countryId;$o->country_code='US';
 $o->code_client=$customer?'SYN-J-CUSTOMER':'';$o->code_fournisseur=$customer?'':'SYN-J-'.substr(sha1($name),0,8);
 $id=$o->create($user);nj_check($id>0,'Native company create: '.$o->error);nj_check($o->fetch($id)>0,'Native company reload');return$o;
}
function nj_position($name,$customer,$project=0){
 global$db,$nj;$p=(new CustomerFundsService($db,1))->currentPosition($customer,$project);$nj['positions'][$name]=$p;
 nj_check($p['coverage']==='verified_managed','Unverified position '.$name.': '.json_encode($p['reasons']));return$p['currencies']['USD'];
}
function nj_refuse($fn,$label){try{$fn();}catch(Throwable$e){$GLOBALS['nj']['checks']++;return$e->getMessage();}throw new RuntimeException('Expected refusal: '.$label);}
class NjUploadService extends ShipmentDocumentService{
 protected function validUploadedSource($path){return is_file($path)&&str_starts_with($path,DOL_DATA_ROOT.'/');}
 protected function moveUploadedSource($from,$to){return rename($from,$to);}
}
function nj_file($name){
 $path=DOL_DATA_ROOT.'/'.$name;file_put_contents($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII='));chmod($path,0600);
 return['name'=>'synthetic-invoice.png','tmp_name'=>$path,'size'=>filesize($path),'error'=>UPLOAD_ERR_OK];
}

try{
 nj_step('Fixture-only modules/source schemas/native task readiness',function()use($conf,$pdo,$db,$user){
  foreach(['commande','projet','chinaplanetoperations','chinaplanetlogistics','chinaplanetcustomercenter','chinaplanetdocuments']as$m){
   $conf->modules[$m]=1;if(!isset($conf->$m))$conf->$m=new stdClass();$conf->$m->enabled=1;
   $conf->$m->dir_output=DOL_DATA_ROOT.'/'.$m;$conf->$m->multidir_output=[1=>DOL_DATA_ROOT.'/'.$m];
  }
  $user->rights->commande=(object)['creer'=>1];$user->rights->facture=(object)['creer'=>1,'paiement'=>1];
  $user->rights->fournisseur=(object)['commande'=>(object)['creer'=>1,'lire'=>1],'facture'=>(object)['creer'=>1,'lire'=>1]];
  $user->rights->banque=(object)['lire'=>1];
  $conf->global->COMMANDE_ADDON='mod_commande_marbre';$conf->global->FACTURE_ADDON='mod_facture_terre';
  $conf->global->COMMANDE_SUPPLIER_ADDON_NUMBER='mod_commande_fournisseur_muguet';$conf->global->INVOICE_SUPPLIER_ADDON_NUMBER='mod_facture_fournisseur_cactus';
  foreach(['cpo_operation_data','cpo_activity','cpo_task']as$t)nj_import('custom/chinaplanetoperations/sql/llx_'.$t.'.sql');
  $pdo->exec("INSERT INTO llx_const(name,entity,value,type,visible) VALUES('MAIN_MODULE_CHINAPLANETOPERATIONS',1,'1','yesno',0)");
  foreach(InternalTaskMigration::rightCodes()as$id=>$code)nf_sql("INSERT INTO llx_rights_def(id,entity,module,perms,bydefault) VALUES($id,1,'chinaplanetoperations','".$db->escape($code)."',0)");
  $migration=new InternalTaskMigration($db);$migration->migrate();nj_check($migration->isReady(true),'Fixture task readiness: '.$migration->readinessReason());
  nj_import('custom/chinaplanetlogistics/sql/llx_cpl_shipment_event.sql');
  nj_import('custom/chinaplanetlogistics/sql/llx_cpl_shipment_public_document.sql');
  nj_import('custom/chinaplanetlogistics/sql/llx_cpl_shared_document.sql');
  $pdo->exec('ALTER TABLE llx_chinaplanetlogistics ADD COLUMN date_atd date AFTER date_etd');
  $conf->global->CPL_SHARED_DOCUMENTS_ENABLED=1; // Fresh fixture only; never target activation.
 });
 $customer=nj_step('Actual native customer created',fn()=>nj_company('Synthetic journey customer v2',true));
 $vendors=[];foreach(['A','B','C']as$n)$vendors[]=nj_step('Actual native supplier '.$n.' created',fn()=>nj_company('Synthetic journey supplier '.$n,false));
 $nj['native_calls'][]='Societe.create/fetch for customer and three suppliers';
 $project=nj_step('Actual native project linked to customer',function()use($db,$user,$customer){
  $p=new Project($db);$p->ref='SYN-JOURNEY-P';$p->title='Synthetic three-supplier project';$p->socid=$customer->id;$p->public=1;$p->usage_task=1;$p->statut=1;
  nj_check($p->create($user)>0,'Native project create: '.$p->error);nj_check($p->setValid($user)>0,'Native project validate: '.$p->error);
  nj_check($p->fetch($p->id)>0&&(int)$p->socid===(int)$customer->id,'Native project customer persisted');return$p;
 });$nj['native_calls'][]='Project.create/setValid/fetch';
 $order=nj_step('Actual sales order1000 with three lines validated',function()use($db,$pdo,$user,$customer,$project){
  $pdo->exec("INSERT INTO llx_c_payment_term(rowid,code,libelle,sortorder,active,deposit_percent) VALUES(990,'SYNJ100','Synthetic100percentdeposit',990,1,100)");
  $o=new Commande($db);$o->socid=$customer->id;$o->fk_project=$project->id;$o->date=dol_now();$o->source=1;$o->cond_reglement_id=990;$o->multicurrency_code='USD';$o->multicurrency_tx=1;
  nj_check($o->create($user)>0,'Native order create: '.$o->error);
  foreach([400,350,250]as$i=>$amount)nj_check($o->addline('Synthetic source line '.($i+1),$amount,1,0)>0,'Native order addline: '.$o->error);
  nj_check($o->valid($user)>0,'Native order valid: '.$o->error);nj_check($o->fetch($o->id)>0&&(float)$o->total_ttc===1000.0,'Persisted sales order1000');return$o;
 });$nj['native_calls'][]='Commande.create/addline/valid/fetch';
 $nj['sales_order']=['id'=>$order->id,'customer'=>$order->socid,'entity'=>$order->entity,'status'=>$order->status,'total'=>$order->total_ttc];
 $guided=new NativeAdvanceWorkflowService($db,1);
 $deposit=nj_step('Actual guided deposit invoice and identical retry',function()use($guided,$customer,$order,$user){
  $key=hash('sha256','journey-v2-deposit');$id=$guided->createDepositFromOrigin((int)$customer->id,'commande',(int)$order->id,$user,$key);
  nj_check($id>0&&$id===$guided->createDepositFromOrigin((int)$customer->id,'commande',(int)$order->id,$user,$key),'Deposit retry identity');return$id;
 });
 $receipt=nj_step('Actual guided bank receipt1000 confirmed once',function()use($guided,$customer,$deposit,$user){
  $key=hash('sha256','journey-v2-receipt');$r=$guided->postBankReceipt((int)$customer->id,$deposit,11,'VIR',date('Y-m-d'),'SYN-JOURNEY-DEPOSIT',$user,$key);
  nj_check($r===$guided->postBankReceipt((int)$customer->id,$deposit,11,'VIR',date('Y-m-d'),'SYN-JOURNEY-DEPOSIT',$user,$key),'Receipt retry identity');return$r;
 });$nj['native_calls'][]='NativeAdvanceWorkflowService deposit/receipt, actual Facture/Paiement/bank/CFS/guard triggers';
 $p=nj_position('deposit',$customer->id);nj_check([$p['received_cash'],$p['paid_cash'],$p['reserved'],$p['available_to_allocate']] ===[1000.0,0.0,0.0,1000.0],'Deposit projection1000');
 $operations=new OperationsService($db);$supplierFlow=new OperationSupplierWorkflowService($db,1);$funds=new CustomerFundsService($db,1);
 $ops=[];$costs=[300,250,200];$payments=[200,150,100];$paid=0;
 foreach($vendors as$i=>$vendor){
  $data=['service_type'=>'order','title'=>'Synthetic purchase '.($i+1),'fk_soc'=>$customer->id,'fk_project'=>$project->id,'fk_commande'=>$order->id,'fk_shipment'=>0,'fk_user_assign'=>0,'fk_usergroup'=>0,'priority'=>'normal','waiting_on'=>'internal','next_action'=>'Synthetic next action','date_deadline'=>'','note_private'=>'Synthetic fixture','note_public'=>'','customer_visible'=>0,'service_data'=>[],'request_hash'=>hash('sha256','journey-operation-'.$i)];
  $op=nj_step('Supplier'.($i+1).' actual operation/generated tasks',function()use($operations,$data,$user){$id=$operations->create($data,$user->id);nj_check($id===$operations->create($data,$user->id),'Operation retry identity');return$id;});$ops[]=$op;
  $input=['supplier_id'=>$vendor->id,'currency'=>'USD','exchange_rate'=>1,'supplier_reference'=>'SYN-J-SO-'.($i+1),'delivery_date'=>date('Y-m-d',strtotime('+10 days')),'lines'=>[['customer_line_id'=>$order->lines[$i]->id,'unit_cost'=>$costs[$i],'vat'=>0,'supplier_ref'=>'SYN-COST-'.($i+1)]]];
  nj_step('Supplier'.($i+1).' actual native commitment/invoice',function()use($supplierFlow,$op,$input,$user,$i){
   $key=sha1('journey-so-'.$i);$id=$supplierFlow->createOrder($op,$input,$user,$key);nj_check($id===$supplierFlow->createOrder($op,$input,$user,$key),'Supplier order retry');$supplierFlow->validateOrder($op,$user);
   $key=sha1('journey-si-'.$i);$invoice=$supplierFlow->createInvoice($op,'SYN-J-INV-'.($i+1),date('Y-m-d'),$user,$key);nj_check($invoice===$supplierFlow->createInvoice($op,'SYN-J-INV-'.($i+1),date('Y-m-d'),$user,$key),'Supplier invoice retry');$supplierFlow->validateInvoice($op,$user);
  });
  $before=$supplierFlow->state($op);nj_check($before['summary']['total']===(float)$costs[$i]&&$before['summary']['paid']===0.0,'Commitment is not cash');
  $allocation=$funds->reserve($receipt['receipt_id'],['target_type'=>'operation','target_id'=>$op,'amount'=>$payments[$i],'request_key'=>hash('sha256','journey-reserve-'.$i)],$user->id);
  $r=nj_position('supplier'.($i+1).'-reserved',$customer->id);nj_check($r['paid_cash']===(float)$paid&&$r['reserved']===(float)$payments[$i],'Reservation separate from cash');
  $posted=nj_step('Supplier'.($i+1).' actual owner payment/bank/attribution retry',function()use($supplierFlow,$op,$payments,$i,$allocation,$user){
   $key=sha1('journey-pay-'.$i);$r=$supplierFlow->postPayment($op,$payments[$i],11,'VIR',date('Y-m-d'),'SYN-J-PAY-'.($i+1),$allocation,$user,$key);
   nj_check($r===$supplierFlow->postPayment($op,$payments[$i],11,'VIR',date('Y-m-d'),'SYN-J-PAY-'.($i+1),$allocation,$user,$key),'Owner payment retry');return$r;
  });
  $paid+=$payments[$i];$r=nj_position('supplier'.($i+1).'-paid',$customer->id);nj_check($r['paid_cash']===(float)$paid&&$r['reserved']===0.0&&$r['held_cash']===(float)(1000-$paid)&&$r['available_to_allocate']===(float)(1000-$paid),'Customer projection after native payment');
  $state=$supplierFlow->state($op);nj_check($state['summary']['paid']===(float)$payments[$i]&&$state['summary']['remaining']===(float)($costs[$i]-$payments[$i]),'Supplier remaining balance');
  $nj['suppliers'][]=['supplier'=>$vendor->id,'operation'=>$op,'summary'=>$state['summary'],'posted'=>$posted];
  nj_refuse(fn()=>$supplierFlow->postPayment($op,10000,11,'VIR',date('Y-m-d'),'SYN-OVERPAY',0,$user,sha1('journey-overpay-'.$i)),'Overpayment');
 }
 $nj['native_calls'][]='OperationsService/InternalTaskService; supplier owner create/validate orders/invoices/postPayment/state across three operations';
 $funds->reserve($receipt['receipt_id'],['target_type'=>'operation','target_id'=>$ops[1],'amount'=>50,'request_key'=>hash('sha256','journey-final-reserve50')],$user->id);
 $final=nj_position('final-customer',$customer->id);nj_check([$final['received_cash'],$final['paid_cash'],$final['reserved'],$final['held_cash'],$final['available_to_allocate']] ===[1000.0,450.0,50.0,550.0,500.0],'Final customer1000/450/50/550/500');
 $pp=nj_position('final-project',$customer->id,$project->id);nj_check($pp['received_cash']===null&&$pp['paid_cash']===450.0&&$pp['reserved']===50.0&&$pp['available_to_allocate']===null,'Project attributes without duplicating cash pool');
 nj_step('Actual shipping operation creates one linked draft shipment',function()use($db,$user,$operations,$data,&$shipment,&$shippingOperation){
  $input=$data;$input['service_type']='shipping_booking';$input['title']='Synthetic linked shipping';$input['service_data']=['pol'=>'Synthetic origin','pod'=>'Synthetic destination'];$input['request_hash']=hash('sha256','journey-shipping-op');$shippingOperation=$operations->create($input,$user->id);
  $owner=new OperationShipmentService($db,1);$id=$owner->createLinkedDraft($shippingOperation,$user->id);nj_check($id===$owner->createLinkedDraft($shippingOperation,$user->id),'Shipment retry identity');
  $shipment=new ChinaPlanetLogistics($db);nj_check($shipment->fetch($id)>0,'Shipment reload');nj_check((int)$operations->get($shippingOperation)['fk_shipment']===(int)$id,'Operation/shipment link persisted');
  $ctx=(new ShipmentContextService($db,new ShipmentDocumentService($db,$user,1),1))->get($shipment->id);nj_check($ctx['status']===0,'Payment did not complete shipment');
 });$nj['native_calls'][]='OperationsService + OperationShipmentService.createLinkedDraft; actual source link/events and authorized context';
 nj_step('Actual private document DB/filesystem/version lifecycle; HTTP seam only',function()use($db,$user,$shipment,&$doc){
  $service=new NjUploadService($db,$user,1);$doc=$service->upload($shipment->id,nj_file('source-v1.png'));$one=$service->content($doc['id']);$bytes=file_get_contents($one['path']);
  $next=$service->upload($shipment->id,nj_file('source-v2.png'),$doc['id'],$doc['revision']);nj_check($next['version']===2&&$next['revision']===2,'Document version/revision persisted');nj_check(file_get_contents($one['path'])===$bytes,'Old bytes unchanged');
  nj_refuse(fn()=>$service->upload($shipment->id,nj_file('source-stale.png'),$doc['id'],1),'Stale document version');nj_check(count($service->versions($doc['id']))===2,'No stale extra version');nj_check((fileperms($service->content($doc['id'])['path'])&0777)===0640,'Private mode0640');
 });
 nj_step('Actual role/ownership checks with seeded identities/rights',function()use($db,$customer,$project,$shipment,$doc,$ops,$supplierFlow,&$njFinanceActor){
  foreach([[51,$customer->id,1],[52,7,1],[53,0,1],[54,0,2],[55,0,1],[56,0,1]]as[$id,$soc,$entity])nf_sql("INSERT INTO llx_user(rowid,entity,login,lastname,admin,statut,fk_soc) VALUES($id,$entity,'synthetic-journey-role-$id','Synthetic role',0,1,".($soc?:'NULL').")");
  $load=function($id)use($db){$u=new User($db);nj_check($u->fetch($id)>0,'Actual role fetch');return$u;};
  $own=$load(51);$own->rights->chinaplanetcustomercenter=(object)['read'=>1];$a=new ShipmentDocumentService($db,$own,1);nj_check($a->can($shipment->id,'read'),'Own customer read');nj_check(!$a->can($shipment->id,'write'),'Customer staff-write denied');nj_refuse(fn()=>$a->documents($shipment->id),'Private staff registry denied');
  $foreign=$load(52);$foreign->rights->chinaplanetcustomercenter=(object)['read'=>1];nj_check(!(new ShipmentDocumentService($db,$foreign,1))->can($shipment->id,'read'),'Foreign customer denied');
  $none=$load(53);nj_check(!(new ShipmentDocumentService($db,$none,1))->can($shipment->id,'read'),'Unprivileged staff denied');nj_refuse(fn()=>$supplierFlow->postPayment($ops[0],1,11,'VIR',date('Y-m-d'),'SYN-DENIED',0,$none,sha1('journey-denied')),'Finance rights denied');
  $other=$load(54);$other->rights->chinaplanetlogistics=(object)['read'=>1];nj_check(!(new ShipmentDocumentService($db,$other,1))->can($shipment->id,'read'),'Other entity denied');
  $reader=$load(55);$reader->rights->societe=(object)['lire'=>1,'client'=>(object)['voir'=>1]];$reader->rights->projet=(object)['all'=>(object)['lire'=>1]];$reader->rights->chinaplanetlogistics=(object)['read'=>1];
  $r=new ShipmentDocumentService($db,$reader,1);nj_check($r->can($shipment->id,'read'),'Authorized reader');nj_check(!$r->can($shipment->id,'write'),'Reader write denied');nj_check(count($r->documents($shipment->id))===1,'One document, two immutable versions');
  nj_check(!DocumentsWorkspace::canRead($reader),'Missing workspace right denied');$reader->rights->chinaplanetdocuments=(object)['workspace_read'=>1];nj_check(DocumentsWorkspace::canRead($reader),'Internal workspace right accepted');nj_check(!DocumentsWorkspace::canRead($own),'External workspace denied');
  $njFinanceActor=$load(56);$njFinanceActor->rights->chinaplanetoperations=(object)['operation_write'=>1];$njFinanceActor->rights->fournisseur=(object)['facture'=>(object)['creer'=>1]];$njFinanceActor->rights->banque=(object)['lire'=>1];nj_check(empty($njFinanceActor->admin)&&OperationSupplierWorkflowService::canPostSupplierPayment($njFinanceActor),'Authorized nonadmin native finance role');
  $ctx=DocumentsWorkspace::context(1,(object)['id'=>$customer->id,'entity'=>1],(object)['id'=>$project->id,'entity'=>1,'socid'=>$customer->id],(object)['id'=>$shipment->id,'entity'=>1,'fk_soc'=>$customer->id,'fk_project'=>$project->id]);nj_check((int)$ctx['shipment']->id===(int)$shipment->id,'Validated source context');
 });

 nj_step('Independent exact cash identities, signs and no duplicate ledger',function()use($pdo,$customer,$vendors,$ops,&$nj){
  $rows=$pdo->query('SELECT p.rowid,p.fk_bank,pf.amount,b.amount bank_amount FROM llx_paiement p JOIN llx_paiement_facture pf ON pf.fk_paiement=p.rowid JOIN llx_facture f ON f.rowid=pf.fk_facture JOIN llx_bank b ON b.rowid=p.fk_bank WHERE f.fk_soc='.(int)$customer->id)->fetchAll(PDO::FETCH_ASSOC);
  nj_check(count($rows)===1&&(float)$rows[0]['amount']===1000.0&&(float)$rows[0]['bank_amount']===1000.0,'Exactly one actual+1000customer cash source');
  $out=$pdo->query('SELECT m.fk_supplier,p.rowid,p.fk_bank,b.amount FROM llx_cpo_supplier_action m JOIN llx_paiementfourn p ON p.rowid=m.fk_supplier_payment JOIN llx_bank b ON b.rowid=p.fk_bank WHERE m.entity=1 AND m.fk_soc_customer='.(int)$customer->id." AND m.action_type='supplier_payment' ORDER BY m.rowid")->fetchAll(PDO::FETCH_ASSOC);
  nj_check(count($out)===3&&array_map(fn($v)=>(int)$v['fk_supplier'],$out)===array_map(fn($v)=>(int)$v->id,$vendors),'Three exact payee/payment identities');nj_check(array_map(fn($v)=>(float)$v['amount'],$out)===[-200.0,-150.0,-100.0],'Native outgoing signed bank amounts');
  nj_check(count(array_unique(array_merge(array_column($rows,'fk_bank'),array_column($out,'fk_bank'))))===4,'Four independent native bank rows');
  nj_check((int)$pdo->query('SELECT COUNT(*) FROM llx_cpcc_financial_entry WHERE fk_soc='.(int)$customer->id)->fetchColumn()===0,'No duplicate custom financial debit');
  nj_check((int)$pdo->query('SELECT COUNT(*) FROM llx_cpcc_project_charge WHERE fk_soc='.(int)$customer->id)->fetchColumn()===0,'No duplicate supplier commitment ledger');
  $nj['exact_cash']=['received'=>1000,'paid'=>450,'native_signed_net'=>550,'customer_payment_rows'=>1,'supplier_payment_rows'=>3,'bank_rows'=>4,'custom_financial_entries'=>0];
 });
 // Fault acknowledgement seam is on actual mysqli transport; all other owner SQL/core calls execute.
 foreach([['BEGIN','before'],['COMMIT','before'],['COMMIT','after']]as[$sql,$phase])nj_step('Owner supplier payment acknowledgement '.$sql.' '.$phase,function()use($sql,$phase,$funds,$receipt,$ops,$njFinanceActor,$pdo,$customer,&$nj){
  $user=$njFinanceActor;
  $allocation=$funds->reserve($receipt['receipt_id'],['target_type'=>'operation','target_id'=>$ops[0],'amount'=>1,'request_key'=>hash('sha256','owner-fault-'.$sql.'-'.$phase)],$user->id);
  [$fault,$wire]=ns_fault_db($sql,$phase);$owner=new OperationSupplierWorkflowService($fault,1);$key=sha1('owner-fault-'.$sql.'-'.$phase);$before=(int)$pdo->query('SELECT COUNT(*) FROM llx_paiementfourn')->fetchColumn();$refused=false;
  try{try{$owner->postPayment($ops[0],1,11,'VIR',date('Y-m-d'),'SYN-OWNER-FAULT-'.$sql.'-'.$phase,$allocation,$user,$key);}catch(Throwable$e){$refused=true;}
   nj_check($wire->fired,'Owner acknowledgement fault was not reached');nj_check($refused,'Owner payment acknowledged unconfirmed '.$sql.' '.$phase);$persisted=$sql==='COMMIT'&&$phase==='after';nj_check((int)$pdo->query('SELECT COUNT(*) FROM llx_paiementfourn')->fetchColumn()===$before+($persisted?1:0),'Owner fault durable cash mismatch');
   $wire->faultSql='';$posted=$owner->postPayment($ops[0],1,11,'VIR',date('Y-m-d'),'SYN-OWNER-FAULT-'.$sql.'-'.$phase,$allocation,$user,$key);nj_check($posted===$owner->postPayment($ops[0],1,11,'VIR',date('Y-m-d'),'SYN-OWNER-FAULT-'.$sql.'-'.$phase,$allocation,$user,$key),'Owner fault retry identity');nj_check((int)$pdo->query('SELECT COUNT(*) FROM llx_paiementfourn')->fetchColumn()===$before+1,'Owner recovery recreated cash');nj_check((int)$fault->transaction_opened===0,'Owner transaction scope leaked');$nj['owner_faults'][]=['sql'=>$sql,'phase'=>$phase,'refused'=>true,'committed_before_retry'=>$persisted,'recovered_payment'=>$posted['payment_id']];
  }finally{ns_close($fault);}
 });
 foreach([['BEGIN','before'],['COMMIT','before'],['COMMIT','after']]as[$sql,$phase])nj_step('Guided receipt retry acknowledgement '.$sql.' '.$phase,function()use($sql,$phase,$customer,$deposit,$receipt,$user,$pdo,&$nj){
  [$fault,$wire]=ns_fault_db($sql,$phase);$owner=new NativeAdvanceWorkflowService($fault,1);$key=hash('sha256','journey-v2-receipt');$before=[(int)$pdo->query('SELECT COUNT(*) FROM llx_paiement')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM llx_bank')->fetchColumn()];$refused=false;
  try{try{$owner->postBankReceipt((int)$customer->id,$deposit,11,'VIR',date('Y-m-d'),'SYN-JOURNEY-DEPOSIT',$user,$key);}catch(Throwable$e){$refused=true;}
   nj_check($wire->fired,'Guided acknowledgement fault not reached');nj_check($refused,'Guided receipt acknowledged unconfirmed '.$sql.' '.$phase);nj_check([(int)$pdo->query('SELECT COUNT(*) FROM llx_paiement')->fetchColumn(),(int)$pdo->query('SELECT COUNT(*) FROM llx_bank')->fetchColumn()]===$before,'Guided retry recreated cash');
   $wire->faultSql='';nj_check($owner->postBankReceipt((int)$customer->id,$deposit,11,'VIR',date('Y-m-d'),'SYN-JOURNEY-DEPOSIT',$user,$key)===$receipt,'Guided recovery identity');nj_check((int)$fault->transaction_opened===0,'Guided transaction scope leaked');$nj['guided_retry_faults'][]=['sql'=>$sql,'phase'=>$phase,'refused'=>true,'cash_recreated'=>false];
  }finally{ns_close($fault);}
 });
 nj_step('Post-recovery live projections agree with independent native cash',function()use($customer,$project,$pdo,$ops,$supplierFlow,&$nj){
  $b=nj_position('after-three-failure-probe-recoveries',$customer->id);nj_check([$b['received_cash'],$b['paid_cash'],$b['reserved'],$b['held_cash'],$b['available_to_allocate']] ===[1000.0,453.0,50.0,547.0,497.0],'Post-recovery1000/453/50/547/497');
  $p=nj_position('project-after-failure-probes',$customer->id,$project->id);nj_check($p['paid_cash']===453.0&&$p['reserved']===50.0&&$p['received_cash']===null,'Project attribution after recovery');
  $remaining=0;foreach($ops as$id){$state=$supplierFlow->state($id);$nj['supplier_states_after_fault_probes'][]=$state['summary'];$remaining+=$state['summary']['remaining'];}nj_check($remaining===297.0,'Native supplier remaining750-453');
  $n=$pdo->query('SELECT COUNT(*) FROM llx_paiementfourn p JOIN llx_cpo_supplier_action m ON m.fk_supplier_payment=p.rowid WHERE m.fk_soc_customer='.(int)$customer->id)->fetchColumn();nj_check((int)$n===6,'Three original plus three explicit1USD recovery payments, no duplicates');
 });
 $nj['identities']=['customer'=>$customer->id,'project'=>$project->id,'order'=>$order->id,'deposit_invoice'=>$deposit,'receipt'=>$receipt,'operations'=>$ops,'shipping_operation'=>$shippingOperation,'shipment'=>$shipment->id,'document'=>$doc];
 $nj['status']='passed';
}catch(Throwable$e){$nj['status']='failed';$nj['error']=$e->getMessage();$nj['native_error']=$db->lasterror();throw$e;}
finally{file_put_contents($s['output'].'/native-journey.json',json_encode($nj,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));chmod($s['output'].'/native-journey.json',0600);}
return $nj;
