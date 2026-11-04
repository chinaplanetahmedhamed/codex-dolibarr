<?php
require_once __DIR__.'/../../custom/chinaplanetdocuments/class/DocumentsWorkspace.class.php';
if (!method_exists('DocumentsWorkspace', 'returnTarget')) { fwrite(STDERR, "FAIL: contextual navigation missing\n"); exit(1); }
define('MAIN_DB_PREFIX', 'qa_');
$checks = 0;
function verify($ok, $why) { global $checks; ++$checks; if (!$ok) throw new RuntimeException($why); }
function reject($fn, $why) { try { $fn(); } catch (InvalidArgumentException $e) { verify(true, $why); return; } throw new RuntimeException($why); }
$c = (object) array('id'=>7,'entity'=>1,'name'=>'Synthetic customer','ref'=>'C7');
$p = (object) array('id'=>9,'entity'=>1,'socid'=>7,'title'=>'Synthetic project','ref'=>'P9');
$s = (object) array('id'=>3,'entity'=>1,'fk_soc'=>7,'fk_project'=>9,'container_no'=>'TEST-CONT','vessel_name'=>'TEST-VESSEL','ref'=>'S3');
$ctx = DocumentsWorkspace::context(1,$c,$p,$s);
verify(($ctx['shipment']->id ?? 0)===3,'shipment context');
foreach (array('entity'=>2,'fk_soc'=>8,'fk_project'=>10) as $key=>$value) {
 $wrong=clone $s; $wrong->$key=$value;
 reject(function () use ($c,$p,$wrong) { DocumentsWorkspace::context(1,$c,$p,$wrong); }, 'shipment '.$key);
}
reject(function () use ($s) { DocumentsWorkspace::context(1,null,null,$s); }, 'shipment needs customer');
reject(function () use ($c,$s) { DocumentsWorkspace::context(1,$c,null,$s); }, 'linked shipment needs validated project');
$loose=clone $s; $loose->fk_project=0;
verify(DocumentsWorkspace::context(1,$c,null,$loose)['shipment']->id===3,'unlinked shipment stays unlinked');
verify(DocumentsWorkspace::returnTarget($ctx,'/erp','shipment')==='/erp/custom/chinaplanetlogistics/card.php?id=3','shipment return');
verify(DocumentsWorkspace::returnTarget($ctx,'/erp','project')==='/erp/projet/card.php?id=9','project return');
verify(DocumentsWorkspace::returnTarget($ctx,'/erp','customer')==='/erp/societe/card.php?id=7','customer return');
verify(DocumentsWorkspace::returnTarget($ctx,'/erp','workspace')==='/erp/custom/chinaplanetdocuments/index.php?socid=7&projectid=9&shipmentid=3','workspace context retained');
foreach (array('https://evil.test','//evil.test','../x',"project\n",'task') as $target) {
 reject(function () use ($ctx,$target) { DocumentsWorkspace::returnTarget($ctx,'/erp',$target); }, 'reject return target');
}
verify(DocumentsWorkspace::returnTarget(DocumentsWorkspace::context(1,null,null),'','shipment')==='/custom/chinaplanetdocuments/index.php','missing return context safe fallback');
class DocumentsContextDb {
 public $connection;
 function __construct() { $this->connection=new SQLite3(':memory:'); }
 function query($sql) { return $this->connection->query($sql); }
 function fetch_object($result) { $row=$result->fetchArray(SQLITE3_ASSOC); return $row ? (object)$row : false; }
}
$db=new DocumentsContextDb();
$db->connection->exec('CREATE TABLE qa_projet(rowid INTEGER, entity INTEGER, fk_soc INTEGER, title TEXT, ref TEXT)');
$db->connection->exec("INSERT INTO qa_projet VALUES (9,1,7,'Allowed','P9'),(10,1,7,'Private','P10'),(11,2,7,'Foreign entity','P11'),(12,1,8,'Other customer','P12')");
$rows=DocumentsWorkspace::projectChoices($db,1,$c,array(9,11,12));
verify(count($rows)===1 && (int)$rows[0]->id===9,'project auth/customer/entity SQL scope');
verify(DocumentsWorkspace::projectChoices($db,1,$c,array())===array(),'no permitted projects');
$db->connection->exec('CREATE TABLE qa_chinaplanetlogistics(rowid INTEGER, entity INTEGER, fk_soc INTEGER, fk_project INTEGER, ref TEXT, container_no TEXT, vessel_name TEXT)');
$db->connection->exec("INSERT INTO qa_chinaplanetlogistics VALUES (3,1,7,9,'S3','TEST-CONT','TEST-VESSEL'),(4,1,7,10,'S4','',''),(5,2,7,9,'S5','',''),(6,1,8,9,'S6','',''),(7,1,7,0,'S7','','')");
$rows=DocumentsWorkspace::shipmentChoices($db,1,$c,$p);
verify(count($rows)===1 && (int)$rows[0]->id===3,'shipment customer/project/entity SQL scope');
verify(count(DocumentsWorkspace::shipmentChoices($db,1,$c,null,array(9)))===2,'customer-wide choice retains unlinked shipments');
reject(function () use ($db,$c) { DocumentsWorkspace::projectChoices($db,2,$c,array(9)); },'projection rejects foreign customer');
$failing = new class { function query($sql) { return false; } };
try { DocumentsWorkspace::shipmentChoices($failing,1,$c,$p); throw new LogicException('failure hidden'); }
catch (RuntimeException $e) { verify(true,'query failure explicit'); }
require_once __DIR__.'/../../custom/chinaplanetdocuments/lib/workspace.lib.php';
$html=cpd_documents_workspace_html($ctx,'',function ($key) { return $key; },'/erp','shipment');
verify(strpos($html,'TEST-CONT')!==false && strpos($html,'?id=3')!==false,'shipment visible with canonical return');
verify(strpos($html,'name="returnto"')===false,'unrendered selector cannot invent handoff');
$selection=cpd_documents_selectors_html($ctx,'<select id="socid" name="socid"></select>',array(),array(),function ($key) { return $key; },'/erp','shipment');
verify(substr_count($selection,'method="get"')===3,'three staged read-only selectors');
verify(substr_count($selection,'name="returnto" value="shipment"')===3,'return enum retained');
verify(strpos($selection,'value="9" selected')!==false && strpos($selection,'value="3" selected')!==false,'older selected context retained beyond bounded list');
preg_match_all('/<form[^>]*>(.*?)<\/form>/s',$selection,$forms);
verify(strpos($forms[1][0],'name="projectid"')===false && strpos($forms[1][0],'name="shipmentid"')===false,'customer selection resets project and shipment');
verify(strpos($forms[1][1],'name="shipmentid"')===false,'project form does not carry stale shipment');
$evil=clone $s; $evil->container_no='<script>bad</script>';
$selection=cpd_documents_selectors_html(DocumentsWorkspace::context(1,$c,$p,$evil),'',array(),array(),function ($key) { return $key; },'/erp','workspace');
verify(strpos($selection,'<script>')===false && strpos($selection,'&lt;script&gt;')!==false,'choice labels escaped');
$before=$db->connection->querySingle('SELECT total_changes()');
DocumentsWorkspace::projectChoices($db,1,$c,array(9));
DocumentsWorkspace::shipmentChoices($db,1,$c,$p);
verify($db->connection->querySingle('SELECT total_changes()')===$before,'choices preserve all existing data');
$payload=DocumentsWorkspace::handoffPayload(1,$ctx,'/erp','shipment');
verify($payload['customerId']===7 && $payload['projectId']===9 && $payload['shipmentId']===3 && $payload['shipmentRef']==='S3','minimal native context payload');
verify(!isset($payload['name']) && !isset($payload['draftId']) && !isset($payload['token']),'no content draft identity or credentials');
verify(DocumentsWorkspace::handoffPayload(1,DocumentsWorkspace::context(1,null,null),'/erp','workspace')===null,'no unscoped transfer');
reject(function () use ($ctx) { DocumentsWorkspace::handoffPayload(1,$ctx,'//evil.test','shipment'); },'unsafe adapter root');
$html=cpd_documents_adapter_html($payload,'/erp');
verify(strpos($html,'type="application/json"')!==false && strpos($html,'launch-context.mjs')!==false,'explicit local adapter markup');
$evilPayload=$payload; $evilPayload['shipmentRef']='</script><script>alert(1)</script>';
verify(strpos(cpd_documents_adapter_html($evilPayload,'/erp'),'</script><script>')===false,'JSON script terminator escaped');
echo "PASS: $checks contextual Documents checks\n";
