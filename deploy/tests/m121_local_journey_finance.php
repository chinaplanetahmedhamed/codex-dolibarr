<?php
/** Synthetic assembly journey: actual finance/reader/Docs context, seeded native cash/shipment. */
$keepArgs=$argv;$argv=[__FILE__];ob_start();try{require __DIR__.'/test_m121_managed_position.php';}finally{ob_end_clean();$argv=$keepArgs;}
require_once dirname(__DIR__,2).'/custom/chinaplanetlogistics/class/ShipmentContextService.class.php';
require_once dirname(__DIR__,2).'/custom/chinaplanetdocuments/class/DocumentsWorkspace.class.php';
$journeyChecks=0;function journey_check($ok,$label){$GLOBALS['journeyChecks']++;if(!$ok)throw new RuntimeException($label);}
list($db,$service)=mp_fixture();
foreach(['ref TEXT','container_no TEXT','date_etd TEXT','date_eta TEXT','date_ata TEXT','freetime_end TEXT']as$definition)$db->pdo->exec('ALTER TABLE llx_chinaplanetlogistics ADD COLUMN '.$definition);
$db->insert('chinaplanetlogistics',['rowid'=>17,'entity'=>1,'fk_soc'=>7,'fk_project'=>11,'fk_statut'=>2,'ref'=>'SYN-M121-INTEGRATED','container_no'=>'SYN-CONTAINER-17','date_etd'=>'2026-11-02','date_eta'=>'2026-11-20','date_ata'=>null,'freetime_end'=>null]);
$db->pdo->exec('UPDATE llx_cpo_operation SET fk_shipment=17 WHERE rowid=101');
$receipt=mp_register($db,$service);$received=$service->currentPosition(7);$commitment=fi_supplier($db);
journey_check($received['currencies']['USD']['received_cash']===100.0,'Existing native receipt100 registered exactly once');
$allocation=mp_reserve($service,$receipt);$reserved=$service->currentPosition(7);
journey_check($reserved['currencies']['USD']['reserved']===40.0&&$reserved['currencies']['USD']['paid_cash']===0.0,'Reservation40 is not paid cash');
mp_supplier($db,$allocation);$service->settleWithSupplierPayment($allocation,200,9);$service->settleWithSupplierPayment($allocation,200,9);$customer=$service->currentPosition(7);$project=$service->currentPosition(7,11);$supplier=fi_supplier($db);
journey_check($customer['currencies']['USD']['paid_cash']===40.0&&$customer['currencies']['USD']['available_to_allocate']===60.0,'Retained supplierpayment40 attributed once');
journey_check($project['currencies']['USD']['received_cash']===null&&$project['currencies']['USD']['paid_cash']===40.0,'Project does not duplicate customer pool');
journey_check(count($db->rows('paiement'))===1&&count($db->rows('paiementfourn'))===1&&count($db->rows('bank'))===2&&count($db->rows('cpcc_financial_entry'))===0,'No duplicate native cash or invented financial debit');
class JourneyShipmentAccess extends ShipmentDocumentService{private $fixture;function __construct($db){$this->fixture=$db;}public function requireShipment($id,$right='read'){$q=$this->fixture->query('SELECT * FROM llx_chinaplanetlogistics WHERE entity=1 AND fk_soc=7 AND fk_project=11 AND rowid='.(int)$id);$row=$q?$this->fixture->fetch_object($q):null;if(!$row)throw new ShipmentDocumentAccessException('Synthetic scope denied.');return(array)$row;}}
$tracking=(new ShipmentContextService($db,new JourneyShipmentAccess($db),1))->get(17);
journey_check($tracking['status']===2&&$tracking['statusLabel']==='On Vessel','Payment never completes shipment');
$customerRecord=(object)['id'=>7,'entity'=>1,'name'=>'Synthetic journey customer','ref'=>'C7'];$projectRecord=(object)['id'=>11,'entity'=>1,'socid'=>7,'title'=>'Synthetic journey project','ref'=>'P11'];$shipmentRecord=(object)['id'=>17,'entity'=>1,'fk_soc'=>7,'fk_project'=>11,'ref'=>'SYN-M121-INTEGRATED'];
$scoped=DocumentsWorkspace::context(1,$customerRecord,$projectRecord,$shipmentRecord);
journey_check(DocumentsWorkspace::returnTarget($scoped,'/erp','shipment')==='/erp/custom/chinaplanetlogistics/card.php?id=17','Document return uses validated same shipment');
$wrong=clone$shipmentRecord;$wrong->fk_soc=8;$rejected=false;try{DocumentsWorkspace::context(1,$customerRecord,$projectRecord,$wrong);}catch(InvalidArgumentException $e){$rejected=$e->getMessage()==='Context unavailable';}journey_check($rejected,'Cross-customer document context rejected');
$context=['version'=>1,'entity'=>1,'customerId'=>7,'projectId'=>11,'shipmentId'=>17,'shipmentRef'=>'SYN-M121-INTEGRATED','root'=>'/erp','returnto'=>'shipment'];
require_once dirname(__DIR__,2).'/custom/chinaplanetdocuments/lib/workspace.lib.php';
$documentsHtml=[];
foreach(['en_US','ar_SA']as$lang){$labels=[];foreach(file(dirname(__DIR__,2).'/custom/chinaplanetdocuments/langs/'.$lang.'/chinaplanetdocuments.lang',FILE_IGNORE_NEW_LINES)as$line){if(preg_match('/^([A-Za-z0-9_]+)=(.*)$/u',$line,$m))$labels[$m[1]]=$m[2];}$translate=fn($key)=>$labels[$key]??$key;$documentsHtml[$lang]=cpd_documents_workspace_html($scoped,'',$translate,'/erp');}
$phases=[['name'=>'native deposit boundary','mode'=>'native customer payment/bank100 SEEDED; actual registration/confirmation','data'=>$received],['name'=>'supplier commitment','mode'=>'native order/invoice60 SEEDED; actual supplier summary; unlinked legacy manual60 remains separate, never summed','data'=>$commitment],['name'=>'reserve40','mode'=>'actual service SQL; no payment','data'=>$reserved],['name'=>'supplier payment40','mode'=>'native payment/bank40 SEEDED; actual attribution+retry, customer/project/supplier readers','customer'=>$customer,'project'=>$project,'supplier'=>$supplier],['name'=>'shipment','mode'=>'source shipment/operation linkage SEEDED; actual read-only projection; owner authorization stubbed','data'=>$tracking]];
echo json_encode(['checks'=>$journeyChecks,'documentsHtml'=>$documentsHtml,'context'=>$context,'phases'=>$phases,'customerRecord'=>$customerRecord,'projectRecord'=>$projectRecord,'shipmentRecord'=>$shipmentRecord,'native_posting_performed'=>false,'shipment_created_by_payment'=>false,'unlinked_obligations_merged'=>false],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
