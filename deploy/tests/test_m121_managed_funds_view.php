<?php
/** Actual shared presenter, native language values and view-contract checks only; no DB/runtime acceptance. */
error_reporting(E_ALL);
function m121v_assert($ok,$why){if(!$ok)throw new RuntimeException($why);}
function dol_escape_htmltag($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function cpcc_portal_money($v){return number_format((float)$v,2,'.',',');}
class M121ViewLangs {
 public $defaultlang; public $probe=false;private $values=array();
 public function __construct($lang){$this->defaultlang=$lang;}
 public function load($domain){m121v_assert($domain==='chinaplanetcustomercenter@chinaplanetcustomercenter','owning language domain');foreach(file(dirname(__DIR__,2).'/custom/chinaplanetcustomercenter/langs/'.$this->defaultlang.'/chinaplanetcustomercenter.lang',FILE_IGNORE_NEW_LINES) as $line){if($line!==''&&$line[0]!=='#'&&strpos($line,'=')!==false){list($k,$v)=explode('=',$line,2);$this->values[$k]=$v;}}}
 public function trans($key){m121v_assert(isset($this->values[$key]),'missing native label '.$key);return $this->values[$key].($this->probe?' <img src=x onerror=alert(1)>':'');}
}
require dirname(__DIR__,2).'/custom/chinaplanetcustomercenter/lib/ui.lib.php';
$phaseFile='/tmp/m121-managed-position-result.json';
$phases=is_file($phaseFile)?json_decode(file_get_contents($phaseFile),true)['phases']:array();
// Independent minimal contract fixture when the parent's temporary probe is absent.
if(!$phases)$phases=array();
$base=array('scope'=>'managed_receipts_only','coverage'=>'verified_managed','currencies'=>array('USD'=>array('received_cash'=>100,'paid_cash'=>40,'reserved'=>0,'available_to_allocate'=>60,'assigned'=>40,'credit_consumed'=>0,'other_settled'=>0,'coverage'=>'verified_managed')));
$projectFixture=$base;$projectFixture['scope']='project_allocations_only';foreach(array('received_cash','available_to_allocate') as $key)$projectFixture['currencies']['USD'][$key]=null;
$multi=$base;$multi['currencies']['EGP']=$base['currencies']['USD'];$multi['currencies']['RMB']=$base['currencies']['USD'];
$cases=array_merge(array('fixture'=>$base,'project_fixture'=>$projectFixture,'multicurrency_fixture'=>$multi),$phases);
function m121v_dom($html){$d=new DOMDocument();$old=libxml_use_internal_errors(true);$d->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);return array($d,new DOMXPath($d));}
foreach(array('en_US','ar_SA') as $language){$langs=new M121ViewLangs($language);foreach($cases as $name=>$position){$position['reasons'][]='PRIVATE_REASON_SENTINEL';$position['supplier']='PRIVATE_SUPPLIER_SENTINEL';$html=cpcc_ui_managed_funds($position);list($dom,$xp)=m121v_dom($html);m121v_assert(strpos($html,'PRIVATE_')===false,'whitelist must omit private reasons/source details');m121v_assert($xp->query('//script|//img')->length===0,'no executable output');$project=$position['scope']==='project_allocations_only';m121v_assert($xp->query('//th[@data-fund-field="received_cash"]')->length===($project?0:1),'project must not repeat customer receipt');m121v_assert($xp->query('//th[@data-fund-field="available_to_allocate"]')->length===($project?0:1),'project must not imply its own customer pool');m121v_assert($xp->query('//th[@data-fund-field="assigned"]')->length===($project?1:0),'project assignments have their own column');foreach($position['currencies'] as $cur=>$bucket){foreach($project?array('assigned','reserved','paid_cash'):array('received_cash','paid_cash','reserved','available_to_allocate') as $field){$cell=$xp->query('//tr[@data-fund-currency="'.$cur.'"]/td[@data-fund-field="'.$field.'"]');m121v_assert($cell->length===1,'field/currency cell present');$expected=$bucket[$field]===null?$langs->trans('CPCCFundsUnknown'):cpcc_portal_money($bucket[$field]);m121v_assert(trim($cell->item(0)->textContent)===$expected,'actual value or explicit Unknown: '.$name.'/'.$field);}}}}
$langs=new M121ViewLangs('en_US');$unknown=$base;foreach($unknown['currencies']['USD'] as $key=>$v)if(is_numeric($v))$unknown['currencies']['USD'][$key]=null;$unknown['coverage']='unavailable';$html=cpcc_ui_managed_funds($unknown);m121v_assert(strpos($html,'Unknown')!==false&&strpos($html,'Needs review')!==false,'failure must not become zero');
$partial=$base;$partial['currencies']['USD']['credit_consumed']=10;$partial['currencies']['USD']['other_settled']=20;$partial['coverage']='partial';$html=cpcc_ui_managed_funds($partial);m121v_assert(strpos($html,'Applied to invoices')!==false&&strpos($html,'Other debits')!==false,'noncash uses distinguished from supplier payments');
$html=cpcc_ui_managed_funds($base,array('asof'=>'2000-01-01'));m121v_assert(strpos($html,'100.00')===false&&strpos($html,'60.00')===false&&strpos($html,'today')!==false,'current values never appear as historical');
$project=$base;$project['scope']='project_allocations_only';$html=cpcc_ui_managed_funds($project,array('customer_url'=>'/custom/chinaplanetcustomercenter/balance.php?socid=7&lang=en_US'));m121v_assert(strpos($html,'socid=7&amp;lang=en_US')!==false,'scoped pool link escaped');
$hostile=$base;$hostile['currencies']=array('<img src=x onerror=alert(1)>'=>$base['currencies']['USD']);list($hd,$hx)=m121v_dom(cpcc_ui_managed_funds($hostile));m121v_assert($hx->query('//img')->length===0,'currency output escaped');
$langs->probe=true;list($d,$x)=m121v_dom(cpcc_ui_managed_funds($base));m121v_assert($x->query('//img')->length===0&&strpos($d->textContent,'<img src=x')!==false,'native translated labels escaped');
$root=dirname(__DIR__,2);foreach(array('funding.php','balance.php','project.php','admin/financial_portfolio.php') as $page){$s=file_get_contents($root.'/custom/chinaplanetcustomercenter/'.$page);m121v_assert(strpos($s,'cpcc_ui_managed_funds(')!==false,'shared presenter consumed: '.$page);m121v_assert(strpos($s,'llxHeader(')!==false,'native header retained');}
// Adapter seam only: the independently tested owning read model is not reimplemented here.
class CustomerFundsService {
 public static $calls=array();public static $fail=false;public function __construct($db,$entity){self::$calls[]=array('entity',$entity);}
 public function currentPosition($customer,$project){self::$calls[]=array($customer,$project);if(self::$fail)throw new RuntimeException('PRIVATE_QUERY_ERROR');return array('scope'=>$project?'project_allocations_only':'managed_receipts_only','coverage'=>'verified_managed','currencies'=>array());}
}
$conf=(object)array('entity'=>2);require $root.'/custom/chinaplanetcustomercenter/lib/financial.lib.php';
$result=cpcc_financial_managed_position(null,7,11);m121v_assert($result['scope']==='project_allocations_only'&&CustomerFundsService::$calls===array(array('entity',2),array(7,11)),'thin wrapper preserves exact entity/customer/project');
CustomerFundsService::$fail=true;$failed=cpcc_financial_managed_position(null,7);m121v_assert($failed['coverage']==='unavailable'&&$failed['currencies']===array(),'failed read is unavailable, never zero/fallback');
echo "PASS actual managed-funds presenter EN/AR (".(count($cases)*2)." rendered positions), null/partial/project/privacy/escaping/history/scoped links, thin-wrapper failure and four consumer contracts; no database/browser acceptance\n";
