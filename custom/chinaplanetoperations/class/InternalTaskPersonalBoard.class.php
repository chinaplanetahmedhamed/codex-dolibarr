<?php
require_once __DIR__.'/InternalTaskService.class.php';

/** Personal presentation only: never changes a task's operational stage or owner. */
class InternalTaskPersonalBoard
{
    const PARAM = 'CPO_PERSONAL_BOARD_V1';
    private $db;
    private $actor;
    private $entity;
    private $tasks;
    private $auth;
    public function __construct($db, $actor, $entity = null)
    {
        global $conf;
        $this->db=$db; $this->actor=$actor; $this->entity=$entity===null?(int)$conf->entity:(int)$entity;
        $this->tasks=new InternalTaskService($db,$actor,$this->entity); $this->auth=$this->tasks->authorization();
    }
    private function authorize()
    {
        if (!$this->auth->isInternalActive() || (!$this->auth->hasRight('task_read_own')&&!$this->auth->isAdministrator())) throw new InternalTaskAccessException('Task access denied.');
    }
    private function q($value){return "'".$this->db->escape((string)$value)."'";}
    private function where(){return 'entity='.$this->entity.' AND fk_user='.$this->auth->actorId().' AND param='.$this->q(self::PARAM);}
    private function must($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Unable to save personal board.');return $r;}
    public function get()
    {
        $this->authorize();
        $r=$this->must('SELECT value FROM '.MAIN_DB_PREFIX.'user_param WHERE '.$this->where());
        $row=$this->db->fetch_object($r);
        if($row){$state=json_decode($row->value,true);if(!is_array($state)||!isset($state['version'],$state['columns'],$state['cards']))throw new RuntimeException('Invalid personal board.');return $state;}
        $columns=array();
        foreach($this->tasks->stageService()->listStages(false) as $stage){
            $columns[]=array_intersect_key($stage,array_flip(array('rowid','code','label_en','label_ar','label_zh','color','behavior')));
        }
        return array('version'=>0,'columns'=>$columns,'cards'=>array());
    }
    private function change($version, $callback)
    {
        $this->authorize();if(!$this->db->begin())throw new RuntimeException('Unable to start save.');
        try {
            // Native user row serializes first-write too, when no preference exists yet.
            $this->must('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.$this->auth->actorId().' FOR UPDATE');
            $state=$this->get();
            if((int)$state['version']!==(int)$version)throw new InternalTaskConflictException('Personal board changed.');
            $state=$callback($state);$state['version']++;
            $json=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if($json===false||strlen($json)>62000)throw new RuntimeException('Personal board limit reached.');
            if((int)$version===0)$this->must('INSERT INTO '.MAIN_DB_PREFIX.'user_param(entity,fk_user,param,value) VALUES('.$this->entity.','.$this->auth->actorId().','.$this->q(self::PARAM).','.$this->q($json).')');
            else $this->must('UPDATE '.MAIN_DB_PREFIX.'user_param SET value='.$this->q($json).' WHERE '.$this->where());
            if(!$this->db->commit())throw new RuntimeException('Unable to confirm save.');return $state;
        }catch(Throwable $e){$this->db->rollback();throw $e;}
    }
    public function saveColumns($version,array $submitted)
    {
        return $this->change($version,function($state)use($submitted){
            if(count($submitted)<1||count($submitted)>30)throw new RuntimeException('Invalid column count.');
            $existing=array_column($state['columns'],null,'rowid');$next=max(array_keys($existing))+1;$seen=array();$columns=array();
            foreach($submitted as $input){
                if(!is_array($input)||!is_scalar($input['name']??null)||!is_scalar($input['color']??null)||isset($input['id'])&&!is_scalar($input['id']))throw new RuntimeException('Invalid column.');
                $id=isset($input['id'])?(int)$input['id']:0;$name=trim((string)($input['name']??''));$color=(string)($input['color']??'');
                if($name===''||mb_strlen($name)>70||preg_match('/[<>\x00-\x1f]/u',$name)||!preg_match('/^#[a-f0-9]{6}$/iD',$color))throw new RuntimeException('Invalid column.');
                if($id>0){if(!isset($existing[$id])||isset($seen[$id]))throw new RuntimeException('Invalid column identity.');$column=$existing[$id];}
                else{$id=$next++;$column=array('rowid'=>$id,'code'=>'personal_'.$id,'behavior'=>'personal');}
                $seen[$id]=true;$column['label_en']=$column['label_ar']=$column['label_zh']=$name;$column['color']=strtolower($color);$columns[]=$column;
            }
            foreach($existing as $id=>$column)if(!isset($seen[$id]))throw new RuntimeException('Existing columns must be retained.');
            $state['columns']=$columns;return $state;
        });
    }
    public function move($version,$taskId,$columnId,$beforeId=0)
    {
        return $this->change($version,function($state)use($taskId,$columnId,$beforeId){
            $task=$this->tasks->requireReadableTask((int)$taskId);
            if((int)$task['fk_user_assign']!==$this->auth->actorId()||!empty($task['date_archived']))throw new InternalTaskAccessException('Task access denied.');
            if(!in_array((int)$columnId,array_map('intval',array_column($state['columns'],'rowid')),true))throw new InternalTaskAccessException('Column access denied.');
            // Materialize the current ordering before inserting, including untouched cards.
            // This also discards stale references to tasks no longer assigned to this user.
            $ordered=$this->arrange($this->tasks->listTasks(array('view'=>'my')),$state);
            $state['cards']=array();$beforeFound=!$beforeId;
            foreach($ordered as $current){
                if((int)$current['rowid']===(int)$beforeId){
                    if((int)$current['personal_column']!==(int)$columnId)throw new InternalTaskConflictException('Destination changed.');
                    $beforeFound=true;
                }
                if((int)$current['rowid']!==(int)$taskId)$state['cards'][(string)$current['personal_column']][]=(int)$current['rowid'];
            }
            if(!$beforeFound)throw new InternalTaskAccessException('Task access denied.');
            $key=(string)(int)$columnId;$ids=$state['cards'][$key]??array();$index=array_search((int)$beforeId,array_map('intval',$ids),true);
            if($index===false)$ids[]=(int)$taskId;else array_splice($ids,$index,0,array((int)$taskId));
            $state['cards'][$key]=$ids;return $state;
        });
    }
    public function arrange(array $tasks,array $state)
    {
        $positions=array();foreach($state['cards'] as $column=>$ids)foreach($ids as $index=>$id)$positions[(int)$id]=array((int)$column,$index);
        $columns=array_map('intval',array_column($state['columns'],'rowid'));
        foreach($tasks as &$task){$id=(int)$task['rowid'];$p=$positions[$id]??array(in_array((int)$task['fk_stage'],$columns,true)?(int)$task['fk_stage']:$columns[0],100000+(int)$task['position']);$task['personal_column']=$p[0];$task['personal_position']=$p[1];}unset($task);
        usort($tasks,function($a,$b){return ($a['personal_position']<=>$b['personal_position'])?:((int)$a['rowid']<=>(int)$b['rowid']);});return $tasks;
    }
}
