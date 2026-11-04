<?php
/** Synthetic, database/network-free projection tests. Real authorization remains a separate acceptance gate. */
$root=dirname(__DIR__,2);
$file=$root.'/custom/chinaplanetlogistics/class/ShipmentContextService.class.php';
if(!is_file($file)){fwrite(STDERR,"FAIL shipment projection is not implemented\n");exit(1);}
require_once $file;
define('MAIN_DB_PREFIX','synthetic_');
$n=0;function check121($v,$m){global $n;if(!$v)throw new RuntimeException('FAIL '.$m);$n++;}
class Access121 extends ShipmentDocumentService {
    public $denied=false;
    public function __construct(){}
    public function requireShipment($id,$action='read') {if($this->denied)throw new ShipmentDocumentAccessException('Denied');return ['rowid'=>17,'entity'=>2,'fk_soc'=>23,'fk_project'=>31];}
}
class DB121 {
    public $queries=[]; public $fail=false;public $row;
    public function query($sql){$this->queries[]=$sql;return $this->fail?false:1;}
    public function fetch_object($r){return $this->row?(object)$this->row:false;}
}
$access=new Access121();$db=new DB121();
$db->row=['rowid'=>17,'entity'=>2,'fk_soc'=>23,'fk_project'=>31,'fk_statut'=>2,'container_no'=>'SYNTH-17','date_etd'=>'2026-11-01','date_eta'=>'2026-11-02','date_ata'=>null,'freetime_end'=>'2026-11-10','notes'=>'PRIVATE','supplier_cost'=>999];
$svc=new ShipmentContextService($db,$access,2);
if(in_array('--fixture',$argv,true)) {
    class Lang121 {
        private $labels;
        public function __construct($path){$this->labels=parse_ini_file($path, false, INI_SCANNER_RAW);}
        public function load($name){}
        public function trans($key){return $this->labels[$key]??$key;}
    }
    $lang=in_array('--ar',$argv,true)?'ar_SA':'en_US';
    $langs=new Lang121($root.'/custom/chinaplanetlogistics/langs/'.$lang.'/shipmentcontext.lang');
    $db->row['container_no']='<img src=x onerror=alert(1)> SYNTH-17';
    if(in_array('--delivered',$argv,true)){$db->row['fk_statut']=6;$db->row['date_ata']='2026-11-03';}
    echo json_encode($svc->get(17),JSON_UNESCAPED_UNICODE|JSON_HEX_TAG);exit;
}

$access->denied=true;try{$svc->get(17);throw new LogicException('access not denied');}catch(ShipmentDocumentAccessException $e){check121(count($db->queries)===0,'denied access never reads projection');}
$access->denied=false;$p=$svc->get(17);
check121($p['status']===2&&$p['statusLabel']==='On Vessel','source status, including ETA overdue, stays unchanged');
check121($p['fields'][0]['value']==='SYNTH-17','container context');
check121($p['fields'][3]['value']===null,'missing actual arrival remains missing');
check121(count($p['fields'])===5,'container and four source deadlines');
check121(!isset($p['notes'])&&!isset($p['supplier_cost']),'allowlist excludes private source fields');
check121(strpos($db->queries[0],'entity=2')!==false&&strpos($db->queries[0],'fk_soc=23')!==false&&strpos($db->queries[0],'fk_project')!==false,'query retains authorized source scope');
$db->row['fk_statut']=6;$db->row['date_ata']='2026-11-03';$p=$svc->get(17);
check121($p['statusLabel']==='Delivered'&&$p['fields'][3]['value']==='2026-11-03','re-read reflects source changes');
$db->row['date_eta']='2026-02-30';$db->row['date_etd']='0000-00-00';$p=$svc->get(17);
check121($p['fields'][1]['value']===null&&$p['fields'][2]['value']===null,'invalid and zero dates remain missing');
$db->fail=true;try{$svc->get(17);throw new LogicException('failure hidden');}catch(RuntimeException $e){check121($e->getMessage()==='Shipment context unavailable.','source failure returns no stale projection');}$db->fail=false;
$db->row['fk_statut']=99;$p=$svc->get(17);check121($p['status']===99&&$p['statusLabel']==='Unknown','unknown source status never invents a mapped state');
$db->row['fk_statut']=null;$p=$svc->get(17);check121($p['status']===null&&$p['statusLabel']==='Unknown','null status is not coerced to Inquiry');
$foreign=new ShipmentContextService($db,$access,3);$before=count($db->queries);try{$foreign->get(17);throw new LogicException('entity accepted');}catch(ShipmentDocumentAccessException $e){check121(count($db->queries)===$before,'inconsistent authorized entity fails before source query');}
try{$svc->get(18);throw new LogicException('wrong id accepted');}catch(ShipmentDocumentAccessException $e){check121(count($db->queries)===$before,'inconsistent authorized id fails before source query');}
$db->row=null;try{$svc->get(17);throw new LogicException('missing record exposed');}catch(ShipmentDocumentAccessException $e){check121(true,'missing or concurrently relinked source fails closed');}
check121(count(array_filter($db->queries,function($q){return !preg_match('/^SELECT /',$q);}))===0,'no writes');
echo "M121_SHIPMENT_CONTEXT=PASS ASSERTIONS=$n\n";
