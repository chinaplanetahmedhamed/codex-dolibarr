<?php
if(PHP_SAPI!=='cli')exit(1);
define('MAIN_DB_PREFIX','llx_');class InternalTaskAccessException extends RuntimeException{}class InternalTaskConflictException extends RuntimeException{}
function dol_now(){return 1;}
class InternalTaskService {function __construct(...$v){}function requireReadableTask($id){return ['status'=>'todo','date_archived'=>null];}function authorization(){return $this;}function canManageTask($t){return true;}}
class InternalTaskStageService {function __construct(...$v){}function systemStage(...$v){return ['rowid'=>2];}}
require __DIR__.'/../../custom/chinaplanetoperations/class/InternalTaskCompletionService.class.php';
class FaultDB {
 public $transaction_opened=0,$points=[],$rollbackCalls=0,$status='todo',$failure;
 function __construct($failure){$this->failure=$failure;}function begin(){++$this->transaction_opened;return true;}
 function commit(){if($this->failure==='commit')return false;--$this->transaction_opened;return true;}
 function rollback(){++$this->rollbackCalls;$this->transaction_opened=0;$this->status='todo';return true;}
 function escape($s){return $s;}function idate($s){return '2026-10-24 00:00:00';}
 function fetch_object($r){return (object)['status'=>'todo','version'=>1];}
 function query($sql){
  if(preg_match('/^SAVEPOINT (\w+)$/',$sql,$m)){if($this->failure==='setup')return false;$this->points[$m[1]]=true;return true;}
  if(preg_match('/^RELEASE SAVEPOINT (\w+)$/',$sql,$m)){if($this->failure==='release')return false;unset($this->points[$m[1]]);return true;}
  if(preg_match('/^ROLLBACK TO SAVEPOINT (\w+)$/',$sql,$m))return isset($this->points[$m[1]]);
  if(str_starts_with($sql,'UPDATE llx_cpo_task')){if($this->failure==='update')return false;$this->status='done';}
  if(str_starts_with($sql,'INSERT INTO llx_cpo_activity')){if($this->failure==='activity')return false;if($this->failure==='deadlock'){$this->points=[];return false;}}
  return true;
 }
}
$conf=(object)['global'=>(object)['CPO_BOARD_COMPLETION_ENABLED'=>1,'CPO_PROJECT_TASK_BRIDGE_ENABLED'=>0]];
foreach(['setup','update','activity','release','deadlock','commit'] as $failure){$db=new FaultDB($failure);$error=null;try{(new InternalTaskCompletionService($db,(object)['id'=>1],1))->task(1,1,true);}catch(RuntimeException $e){$error=$e;}
 if(!$error||$db->transaction_opened!==0||$db->rollbackCalls!==1||$db->status!=='todo')throw new RuntimeException('FAIL transaction recovery '.$failure);
 if($failure==='commit'&&$error->getMessage()!=='Unable to confirm task completion.')throw new RuntimeException('Original commit error was masked');echo 'PASS '.$failure.' cleanup and original error preservation'.PHP_EOL;
}
echo 'M114_COMPLETION_FAULTS=PASS ASSERTIONS=6'.PHP_EOL;
