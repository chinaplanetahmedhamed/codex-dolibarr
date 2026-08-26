<?php

if(PHP_SAPI!=='cli'){http_response_code(403);exit(1);}
define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);define('NOCSRFCHECK',1);
$documentRoot=getenv('M062_DOL_DOCUMENT_ROOT');if(!$documentRoot)$documentRoot='/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$_SERVER['DOCUMENT_ROOT']=$documentRoot;$_SERVER['SERVER_NAME']='127.0.0.1';$_SERVER['SERVER_PORT']='8088';require $documentRoot.'/master.inc.php';
$root=dirname(__DIR__,2);require_once $root.'/custom/chinaplanetshippingprices/class/PortNameResolver.class.php';
function m062dataAssert($condition,$message){if(!$condition)throw new RuntimeException($message);}global$db,$conf;$entity=(int)$conf->entity;
function m062dataOne($db,$sql){$res=$db->query($sql);if(!$res)throw new RuntimeException('Current-data query failed.');return$db->fetch_object($res);}
$summary=m062dataOne($db,"SELECT COUNT(*) total,SUM(destination_country IS NULL OR destination_country='') unmapped,SUM(port_match_method='composite') composite,SUM(pod_raw IS NOT NULL AND pod_raw<>pod) normalized FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".$entity);
m062dataAssert((int)$summary->total>0&&(int)$summary->unmapped===0&&(int)$summary->composite>0&&(int)$summary->normalized>0,'Current rates are not fully normalized/audited.');
$aliases=m062dataOne($db,"SELECT COUNT(*) total,COUNT(DISTINCT normalized_alias) unique_total FROM ".MAIN_DB_PREFIX."chinaplanetsp_port_alias WHERE entity=".$entity." AND status=1");
m062dataAssert((int)$aliases->total===(int)$aliases->unique_total&&(int)$aliases->total>=90,'Active normalized aliases are duplicated or incomplete.');
$targets=array('UMM AL QUWAIN'=>'AE','SKIKDA'=>'DZ','ORAN'=>'DZ','AL KHUMS'=>'LY','BEJAIA'=>'DZ','AGADIR'=>'MA','NADOR'=>'MA','DJEN-DJEN'=>'DZ','ALGIERS'=>'DZ','ANNABA'=>'DZ','MISURATA'=>'LY','RADES'=>'TN','SOUSSE'=>'TN','SFAX'=>'TN','PORT SUDAN'=>'SD','JIZAN'=>'SA','ALEXANDRIA'=>'EG','PORT SAID WEST'=>'EG','SOKHNA'=>'EG');
$targetRows=0;foreach($targets as$pod=>$country){$row=m062dataOne($db,"SELECT COUNT(*) total,SUM(destination_country='".$db->escape($country)."') correct_country FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".$entity." AND pod='".$db->escape($pod)."'");if((int)$row->total===0)continue;$targetRows+=(int)$row->total;m062dataAssert((int)$row->total===(int)$row->correct_country,'Current canonical mapping failed for '.$pod);}m062dataAssert($targetRows>0,'No canonical target rates exist for current-data validation.');
$destinationWarnings=0;$otherWarnings=0;$res=$db->query("SELECT rowid,warning_count,report_json FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE entity=".$entity);while($batch=$db->fetch_object($res)){$report=json_decode((string)$batch->report_json,true);$warnings=is_array($report)&&isset($report['warnings'])&&is_array($report['warnings'])?$report['warnings']:array();foreach($warnings as$warning){if(preg_match('/^(?:Unmapped|Ambiguous) destination/u',$warning))$destinationWarnings++;else$otherWarnings++;}}
m062dataAssert($destinationWarnings===0,'Resolved destination warnings remain in batch reports.');
$cloneDuplicates=m062dataOne($db,"SELECT COUNT(*) total FROM (SELECT fk_batch,source_row,COALESCE(source_cell,'') source_cell,pod,container_type,shipping_line,cost_price,COUNT(*) copies FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".$entity." AND port_match_method='composite' GROUP BY fk_batch,source_row,COALESCE(source_cell,''),pod,container_type,shipping_line,cost_price HAVING COUNT(*)>1) duplicate_composites");
m062dataAssert((int)$cloneDuplicates->total===0,'Composite migration created duplicate destination rows.');
print 'OK M062 current data rates='.(int)$summary->total.' unmapped=0 composite='.(int)$summary->composite.' aliases='.(int)$aliases->total.' destination_warnings=0 other_warnings='.$otherWarnings."\n";
