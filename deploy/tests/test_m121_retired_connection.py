"""Actual pre-header page execution with synthetic auth/data and actual fail-stop guard.
Does not establish authenticated ERP/server acceptance.
"""
from pathlib import Path
import subprocess,tempfile,json
ROOT=Path(__file__).resolve().parents[2]
SCAFFOLD=r'''<?php
error_reporting(E_ALL);
define('MAIN_DB_PREFIX','llx_');
$root=__ROOT__; $page=$argv[1];$GLOBALS['header_calls']=0;$GLOBALS['after_retirement_queries']=0;
function dol_escape_htmltag($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function dol_buildpath($p,$mode=0){return $p;}
function getDolGlobalString($k){return '';}
function getDolGlobalInt($k){return 0;}
function getDolCurrency(){return 'USD';}
function GETPOSTINT($k){return $k==='id'?11:7;}
function GETPOST($k,$mode=''){return $k==='asof'?date('Y-m-d'):'';}
function accessforbidden($message=''){throw new RuntimeException('Unexpected denied fixture: '.$message);}
function cpcc_portal_require_access($user){}
function cpcc_portal_user_scope($user){return ['is_external'=>false,'socid'=>0];}
function cpcc_portal_customer_scope_id($db,$user,$id){return 7;}
function cpcc_portal_money($n){return number_format((float)$n,2,'.',',');}
function llxHeader(...$args){$GLOBALS['header_calls']++;throw new RuntimeException('Native header reached after retirement');}
function llxFooter(...$args){throw new RuntimeException('Native footer reached after retirement');}
class RetiredRows{public $rows;function __construct($rows){$this->rows=$rows;}}
class RetirementDb{
 public $retired=false;
 function escape($s){return str_replace("'","''",$s);}
 function query($sql){if($this->retired){$GLOBALS['after_retirement_queries']++;throw new RuntimeException('POST_RETIREMENT_QUERY');}
  if(strpos($sql,'SHOW TABLES')===0)return new RetiredRows([(object)['table'=>'synthetic']]);
  if(strpos($sql,'SELECT rowid, nom FROM llx_societe')===0)return new RetiredRows([(object)['rowid'=>7,'nom'=>'Synthetic7'],(object)['rowid'=>8,'nom'=>'Synthetic8']]);
  if(strpos($sql,'SELECT p.rowid,p.ref,p.title')===0)return new RetiredRows([(object)['rowid'=>11,'ref'=>'P11','title'=>'Synthetic','description'=>'','fk_soc'=>7,'fk_statut'=>1,'customer'=>'Synthetic7']]);
  return new RetiredRows([]);
 }
 function num_rows($r){return count($r->rows);}
 function fetch_object($r){return array_shift($r->rows);}
}
class RetirementLangs{
 public $defaultlang='en_US';private $values=[];
 function load($domain){foreach(file($GLOBALS['root'].'/custom/chinaplanetcustomercenter/langs/'.$this->defaultlang.'/chinaplanetcustomercenter.lang',FILE_IGNORE_NEW_LINES)as$line){if(preg_match('/^([A-Za-z0-9_]+)=(.*)$/u',$line,$m))$this->values[$m[1]]=$m[2];}}
 function trans($key){return($this->values[$key]??$key).' <img src=x onerror=sentinel>';}
}
class CustomerFundsService{
 private $db;function __construct($db,$entity){$this->db=$db;}
 function currentPosition($socid,$project=0){$this->db->retired=true;return ['coverage'=>'unavailable','currencies'=>[],'connection_retired'=>true];}
}
$db=new RetirementDb();$conf=(object)['entity'=>1,'currency'=>'USD'];$user=(object)['id'=>9,'socid'=>0,'admin'=>1];$langs=new RetirementLangs();$langs->defaultlang=$argv[2];
require $root.'/custom/chinaplanetcustomercenter/lib/financial.lib.php';
register_shutdown_function(function(){fwrite(STDERR,json_encode(['http_status'=>http_response_code(),'header_calls'=>$GLOBALS['header_calls'],'after_retirement_queries'=>$GLOBALS['after_retirement_queries']])."\n");});
if($page==='healthy'){cpcc_financial_require_live_connection(['coverage'=>'unavailable','currencies'=>[]]);echo 'HEALTHY_UNKNOWN_CONTINUES';exit;}
$source=file_get_contents($root.'/custom/chinaplanetcustomercenter/'.$page);$source=substr($source,5,strpos($source,'llxHeader(')-5);
$source=preg_replace('/require_once\s+[^;]+;/','',$source);
eval($source);echo 'FELL_THROUGH_AFTER_RETIREMENT';
'''
checks=[]
with tempfile.TemporaryDirectory(prefix='m121-retired-request-') as d:
 p=Path(d)/'request.php';p.write_text(SCAFFOLD.replace('__ROOT__',json.dumps(str(ROOT))))
 for language in ['en_US','ar_SA']:
  labels=dict(line.split('=',1) for line in (ROOT/'custom/chinaplanetcustomercenter/langs'/language/'chinaplanetcustomercenter.lang').read_text().splitlines() if '=' in line)
  for page in ['funding.php','balance.php','project.php','admin/financial_portfolio.php','healthy']:
   r=subprocess.run(['php',str(p),page,language],capture_output=True,text=True)
   meta=json.loads(r.stderr.strip().splitlines()[-1]);assert r.returncode==0,(page,r.stdout,r.stderr)
   if page=='healthy':assert r.stdout=='HEALTHY_UNKNOWN_CONTINUES',(page,r.stdout)
   else:
    assert meta=={'http_status':503,'header_calls':0,'after_retirement_queries':0},(page,meta,r.stdout)
    assert 'FELL_THROUGH' not in r.stdout and labels['CPCCFundsUnknown'] in r.stdout,(page,r.stdout)
    assert '<img' not in r.stdout and '&lt;img' in r.stdout,(page,'labels not escaped')
    assert '0.00' not in r.stdout and 'Synthetic7' not in r.stdout,(page,'financial/identity fallback leaked')
    assert ('dir="rtl"' if language=='ar_SA' else 'dir="ltr"') in r.stdout,(page,'language direction')
   checks.append({'page':page,'language':language,'result':'passed','proof':meta})
print(json.dumps({'checks':checks,'scope':'actual preheader PHP page code, synthetic auth/data; CLI503, no later query/header/footer; not native browser/session acceptance'},indent=2))
