"""Actual PHP entry dispatch/prefixes with synthetic bootstrap/roles; no native session acceptance."""
from pathlib import Path
import subprocess,tempfile,json
ROOT=Path(__file__).resolve().parents[2]
PHP=r'''<?php
error_reporting(E_ALL);define('MAIN_DB_PREFIX','llx_');define('DOL_URL_ROOT','/erp');
$root=__ROOT__;$kind=$argv[1];$action=$argv[2];$fault=$argv[3];$role=$argv[4];$GLOBALS['calls']=[];$GLOBALS['queries']=[];$GLOBALS['access']=0;
$_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_X_REQUESTED_WITH']='xmlhttprequest';$_SERVER['CONTENT_LENGTH']=10;$_SERVER['SCRIPT_NAME']='/custom/chinaplanetoperations/tasks/card.php';$_POST=['token'=>'SYNTHETIC'];$_FILES=[];
function GETPOST($key,$mode=''){global$kind,$action;return ['action'=>$action,'title'=>'Synthetic title','reason'=>'Synthetic reason','view'=>$kind==='workspace'?$action:'','theme'=>'dark'][$key]??'';}
function GETPOSTINT($key){return ['task_id'=>7,'version'=>3,'asset_id'=>12,'replace_asset_id'=>0,'socid'=>7][$key]??0;}
function accessforbidden(){throw new RuntimeException('SYNTHETIC_DENIED',403);}
function cpo_task_require($db,$user){$GLOBALS['access']++;}
function setEventMessages(...$a){}
function dol_escape_htmltag($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function dol_buildpath($v,$mode=0){return '/erp/custom'.$v;}
function getDolGlobalInt($k){return $k==='CPO_BOARDS_ENABLED'?1:0;}
function getDolGlobalString($k){return $k==='CPCC_ENVIRONMENT_LABEL'?'SYNTHETIC':'';}
function cpo_can($user,$right){return $user->admin||($GLOBALS['role']==='ops'&&$right==='read');}
function cpo_task_can_open($db,$user){return $user->id>0&&!$user->socid&&$GLOBALS['role']!=='denied';}
function chinaplanetlogistics_status_map(){return [];}
class EntryActor{public$id=9,$socid=0,$admin=0;function hasRight($module,...$keys){return $this->admin||($module==='societe'&&$keys===['lire'])||($module==='chinaplanetoperations'&&(($GLOBALS['role']==='task'&&$keys===['task_read_own'])||($GLOBALS['role']==='ops'&&$keys===['read'])));}}
class EntryLangs{public$defaultlang='en_US';private$values=[];function load($domain){$p=$GLOBALS['root'].'/custom/chinaplanetcustomercenter/langs/en_US/m084_ui.lang';foreach(file($p,FILE_IGNORE_NEW_LINES)as$l)if(str_contains($l,'=')){[$k,$v]=explode('=',$l,2);$this->values[$k]=$v;}}function trans($k){return$this->values[$k]??$k;}}
class EntryDb{function query($sql){$GLOBALS['queries'][]=$sql;return false;}function fetch_object($r){return false;}}
class EntryAuth{function canOpenBoard(){return true;}}
class InternalTaskConflictException extends RuntimeException{}class InternalTaskAccessException extends RuntimeException{}
class InternalTaskService{function __construct(...$args){}function authorization(){return new EntryAuth;}function listTasks($filters){$GLOBALS['calls'][]=['listTasks',$filters];return [];}function __call($name,$args){$GLOBALS['calls'][]=[$name,$args];if($GLOBALS['fault']==='stale')throw new InternalTaskConflictException;if($GLOBALS['fault']==='denied')throw new InternalTaskAccessException;return null;}}
class InternalTaskAssetService{function __construct(...$args){}function __call($name,$args){$GLOBALS['calls'][]=[$name,$args];if($GLOBALS['fault']==='stale')throw new InternalTaskConflictException;if($GLOBALS['fault']==='denied')throw new InternalTaskAccessException;return ['version'=>4,'asset_id'=>12];}}
class OperationsService{function __construct(...$args){}function stats($user){$GLOBALS['calls'][]=['operationStats'];return [];}function search(...$args){$GLOBALS['calls'][]=['operationSearch'];return [];}}
$db=new EntryDb;$user=new EntryActor;$langs=new EntryLangs;$conf=(object)['entity'=>1];if($role==='admin')$user->admin=1;if($role==='external')$user->socid=7;
require $root.'/custom/chinaplanetcustomercenter/lib/ui.lib.php';
register_shutdown_function(function(){fwrite(STDERR,json_encode(['status'=>http_response_code(),'calls'=>$GLOBALS['calls'],'queries'=>$GLOBALS['queries'],'access'=>$GLOBALS['access']])."\n");});
if($kind==='nav'){
 require $root.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
 ob_start();cpcc_admin_shell_open('workspace','Synthetic workspace','','fa-compass',['flat'=>$action==='flat']);cpcc_admin_shell_close();$html=ob_get_clean();echo $html;exit;
}
if($kind==='asset'&&$action==='size'){$_POST=[];$_FILES=[];}
$file=$kind==='workspace'?'workspace.php':('tasks/'.$kind.'.php');$source=file_get_contents($root.'/custom/chinaplanetoperations/'.$file);
if($kind==='workspace')$source=substr($source,0,strpos($source,'$morecss ='));
$source=preg_replace('/require(?:_once)?\s+[^;]+;/','',$source);$source=substr($source,5);
try{eval($source);if($kind==='workspace')echo json_encode(['view'=>$view,'canReadCustomers'=>$canReadCustomers]);}catch(Throwable$e){if($e->getCode()===403){http_response_code(403);echo 'DENIED';}else throw $e;}
'''
rows=[]
with tempfile.TemporaryDirectory(prefix='m121-entry-integration-') as d:
 p=Path(d)/'entry.php';p.write_text(PHP.replace('__ROOT__',json.dumps(str(ROOT))))
 def run(kind,action,fault='none',role='task'):
  r=subprocess.run(['php',str(p),kind,action,fault,role],capture_output=True,text=True);assert r.returncode==0,(kind,action,r.stdout,r.stderr);meta=json.loads(r.stderr.strip().splitlines()[-1]);return r.stdout,meta
 for action,method in [('rename','renameTask'),('trash','trashTask'),('restore_trash','restoreTrash'),('cancel_archive','cancelAndArchive'),('restore_cancelled','restoreCancelled')]:
  for fault,status in [('none',200),('stale',409),('denied',403)]:
   body,meta=run('action',action,fault);result=json.loads(body);assert meta['status']==status and meta['access']==1,(action,fault,result,meta);assert meta['calls']==[[method,[7,3,'Synthetic title' if action=='rename'else'Synthetic reason']]],meta;assert result['ok']==(status==200);rows.append({'entry':'action','action':action,'fault':fault,'status':status})
 for fault,status in [('none',200),('stale',409),('denied',403)]:
  body,meta=run('asset','upload',fault);result=json.loads(body);assert meta['status']==status and meta['access']==1 and result['ok']==(status==200),(fault,meta,result)
  if status==200:assert result['version']==4 and result['assetId']==12
  rows.append({'entry':'asset','fault':fault,'status':status})
 body,meta=run('asset','size');assert meta['status']==413 and meta['access']==0 and json.loads(body)['code']=='upload_size';rows.append({'entry':'asset','case':'empty over-size request','status':413})
 for role,view,status in [('task','',200),('task','manager',200),('task','customers',403),('task','customer',403),('denied','',403),('external','',403),('ops','',200),('admin','manager',200)]:
  body,meta=run('workspace',view,role=role);actual=meta['status'] if meta['status'] else 200;assert actual==status,(role,view,body,meta)
  if role in ['task','denied','external']:assert meta['queries']==[] and all(c[0]not in ['operationStats','operationSearch']for c in meta['calls']),(role,view,meta)
  if status==200 and role=='task':assert json.loads(body)=={'view':'my','canReadCustomers':False}
  rows.append({'entry':'workspace','role':role,'requested_view':view,'status':status})
 for role,flat in [('task','flat'),('task','normal'),('admin','flat'),('ops','normal')]:
  html,meta=run('nav',flat,role=role);assert ('class="cpad-head' not in html)==(flat=='flat'),(role,flat)
  if role=='task':assert 'tasks/boards.php' in html and 'financial_portfolio.php'not in html and 'card.php?action=create'not in html and 'class="new"'not in html
  if role=='admin':assert 'financial_portfolio.php'in html and 'class="new"'in html
  rows.append({'entry':'navigation','role':role,'layout':flat,'result':'passed'})
print(json.dumps({'cases':rows,'passed':len(rows),'scope':'Actual PHP dispatch/workspace prefix/nav renderer; synthetic native bootstrap/authorization/services; no native login/rights or business mutation acceptance'},indent=2))
