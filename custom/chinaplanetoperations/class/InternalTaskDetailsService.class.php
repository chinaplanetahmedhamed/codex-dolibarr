<?php
require_once __DIR__.'/InternalTaskService.class.php';
require_once __DIR__.'/InternalTaskSchedule.class.php';
require_once __DIR__.'/InternalTaskCollaborationService.class.php';
require_once __DIR__.'/InternalTaskPersonalBoard.class.php';

/** Additional internal card content. All reads inherit task ownership rules. */
class InternalTaskDetailsService
{
    private $db; private $actor; private $entity; private $tasks; private $auth;
    public function __construct($db,$actor,$entity=null){global $conf;$this->db=$db;$this->actor=$actor;$this->entity=$entity===null?(int)$conf->entity:(int)$entity;$this->tasks=new InternalTaskService($db,$actor,$this->entity);$this->auth=$this->tasks->authorization();}
    private function q($s){return "'".$this->db->escape((string)$s)."'";}
    private function must($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Unable to save task details.');return $r;}
    private function text($s,$max){if(!is_scalar($s)&&$s!==null)throw new RuntimeException('Invalid text.');$s=trim((string)$s);if(mb_strlen($s)>$max||preg_match('/[<>\x00-\x08\x0b\x0c\x0e-\x1f]/u',$s))throw new RuntimeException('Invalid text.');return $s;}
    public function validate(array $data)
    {
        $out=array('start_date'=>'','start_time'=>$this->text($data['start_time']??'',5),'cover_color'=>'','labels'=>array(),'fields'=>array(),'notes'=>$this->text($data['notes']??'',10000));
        $date=$this->text($data['start_date']??'',10);if($date!==''){if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)))throw new RuntimeException('Invalid start date.');$out['start_date']=$date;}
        InternalTaskSchedule::start($out);
        $color=$this->text($data['cover_color']??'',7);if($color!==''&&!preg_match('/^#[a-f0-9]{6}$/iD',$color))throw new RuntimeException('Invalid cover color.');$out['cover_color']=strtolower($color);
        if(!is_array($data['labels']??array())||count($data['labels']??array())>20)throw new RuntimeException('Invalid labels.');
        foreach($data['labels']??array() as $label){if(!is_array($label))throw new RuntimeException('Invalid label.');$name=$this->text($label['name']??'',70);$color=$this->text($label['color']??'',7);if($name===''||!preg_match('/^#[a-f0-9]{6}$/iD',$color))throw new RuntimeException('Invalid label.');$out['labels'][]=array('name'=>$name,'color'=>strtolower($color));}
        if(!is_array($data['fields']??array())||count($data['fields']??array())>30)throw new RuntimeException('Invalid custom fields.');
        $names=array();foreach($data['fields']??array() as $field){if(!is_array($field))throw new RuntimeException('Invalid field.');$name=$this->text($field['name']??'',70);$type=$this->text($field['type']??'text',12);$value=$this->text($field['value']??'',1000);if($name===''||isset($names[$name])||!in_array($type,array('text','number','date','checkbox'),true))throw new RuntimeException('Invalid custom field.');$names[$name]=true;if($type==='number'&&$value!==''&&!preg_match('/^-?\d{1,15}(\.\d{1,8})?$/D',$value))throw new RuntimeException('Invalid number.');if($type==='date'&&$value!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)||!checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4))))throw new RuntimeException('Invalid date.');if($type==='checkbox'&&!in_array($value,array('0','1'),true))throw new RuntimeException('Invalid checkbox.');$out['fields'][]=array('name'=>$name,'type'=>$type,'value'=>$value);}
        return $out;
    }
    private function row($id){$r=$this->must('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_task_details WHERE entity='.$this->entity.' AND fk_task='.(int)$id);$row=$this->db->fetch_object($r);return $row?(array)$row:null;}
    public function get($id){$this->tasks->requireReadableTask($id);$row=$this->row($id);$data=$row?json_decode($row['payload'],true):$this->validate(array());if(!is_array($data))throw new RuntimeException('Invalid stored task details.');return array('version'=>$row?(int)$row['version']:0,'data'=>$data);}
    public function forBoard(array $tasks)
    {
        $out=array();$ids=array();foreach($tasks as $task){if(!$this->auth->canReadTask($task))throw new InternalTaskAccessException('Task access denied.');$ids[]=(int)$task['rowid'];}
        if(!$ids)return $out;
        $r=$this->must('SELECT fk_task,payload FROM '.MAIN_DB_PREFIX.'cpo_task_details WHERE entity='.$this->entity.' AND fk_task IN ('.implode(',',$ids).')');while($row=$this->db->fetch_object($r))$out[(int)$row->fk_task]=json_decode($row->payload,true);return $out;
    }
    public function canEdit(array $task){return empty($task['date_archived'])&&($this->auth->canManageTask($task)||$this->auth->canEditOwnInstructions($task));}
    public function save($id,$version,array $data,$createHash=null)
    {
        return $this->saveInternal($id,$version,$data,$createHash);
    }
    public function saveWithSchedule($id,$version,$taskVersion,array $data,$deadline)
    {
        return $this->saveInternal($id,$version,$data,null,array('version'=>(int)$taskVersion,'deadline'=>InternalTaskSchedule::dateTime($deadline)));
    }
    private function saveInternal($id,$version,array $data,$createHash=null,$schedule=null)
    {
        $clean=$this->validate($data);if(!$this->db->begin())throw new RuntimeException('Unable to start save.');
        try{$this->must('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task WHERE entity='.$this->entity.' AND rowid='.(int)$id.' FOR UPDATE');$task=$this->tasks->requireReadableTask($id);if(!$this->canEdit($task))throw new InternalTaskAccessException('Task access denied.');$row=$this->row($id);if(($row?(int)$row['version']:0)!==(int)$version)throw new InternalTaskConflictException('Task details changed.');
            if($schedule!==null&&(int)$task['version']!==$schedule['version'])throw new InternalTaskConflictException('Task changed.');
            $deadline=$schedule!==null?$schedule['deadline']:$task['date_deadline'];
            InternalTaskSchedule::validate($clean,$deadline);
            $json=$this->q(json_encode($clean,JSON_UNESCAPED_UNICODE));$actor=$this->auth->actorId();
            if($schedule!==null){
                $updated=$this->must('UPDATE '.MAIN_DB_PREFIX.'cpo_task SET date_deadline='.($deadline!==''?$this->q($deadline):'NULL').',fk_user_modif='.$actor.',version=version+1 WHERE entity='.$this->entity.' AND rowid='.(int)$id.' AND version='.(int)$task['version']);
                if($this->db->affected_rows($updated)!==1)throw new InternalTaskConflictException('Task changed.');
            }
            if($row)$this->must('UPDATE '.MAIN_DB_PREFIX.'cpo_task_details SET payload='.$json.',version=version+1,fk_user_modif='.$actor.' WHERE entity='.$this->entity.' AND fk_task='.(int)$id.' AND version='.(int)$version);
            else $this->must('INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_details(entity,fk_task,payload,create_hash,version,fk_user_creat,fk_user_modif,date_creation) VALUES('.$this->entity.','.(int)$id.','.$json.','.($createHash?$this->q($createHash):'NULL').',1,'.$actor.','.$actor.','.$this->q(date('Y-m-d H:i:s')).')');
            $this->must('INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity(entity,fk_task,fk_user,action_code,message,date_action) VALUES('.$this->entity.','.(int)$id.','.$actor.",'task_details_updated','',".$this->q(date('Y-m-d H:i:s')).')');
            if(!$this->db->commit())throw new RuntimeException('Unable to confirm save.');return array('version'=>(int)$version+1,'task_version'=>(int)$task['version']+($schedule!==null?1:0),'data'=>$clean);
        }catch(Throwable $e){$this->db->rollback();throw $e;}
    }
    public function compose(array $taskData,array $details,array $items,$comment,$request,$columnId=0)
    {
        if(!preg_match('/^[a-f0-9]{48}$/D',(string)$request))throw new RuntimeException('Invalid request.');
        $clean=$this->validate($details);InternalTaskSchedule::validate($clean,$taskData['date_deadline']??'');$comment=$this->text($comment,5000);if(count($items)>100)throw new RuntimeException('Checklist limit reached.');$items=array_values(array_filter(array_map(function($s){return $this->text($s,500);},$items),function($s){return $s!=='';}));
        $digest=hash('sha256',json_encode(array($taskData,$clean,$items,$comment,(int)$columnId),JSON_UNESCAPED_UNICODE));$lock='cpo_compose_'.$this->entity.'_'.$this->auth->actorId().'_'.substr($request,0,25);
        $r=$this->must('SELECT GET_LOCK('.$this->q($lock).',5) acquired');$locked=$this->db->fetch_object($r);if(!$locked||(int)$locked->acquired!==1)throw new RuntimeException('Please retry.');
        $begun=false;
        try{
            if(!$this->db->begin())throw new RuntimeException('Unable to start save.');$begun=true;
            $taskData['request_hash']=hash('sha256',$this->entity.':'.$this->auth->actorId().':'.$request);
            $id=$this->tasks->createTask($taskData,false,false);$existing=$this->row($id);
            if($existing){if(!hash_equals((string)$existing['create_hash'],$digest))throw new InternalTaskConflictException('Task request changed.');if(!$this->db->commit())throw new RuntimeException('Unable to confirm save.');return $id;}
            $this->save($id,0,$clean,$digest);$collab=new InternalTaskCollaborationService($this->db,$this->actor,$this->entity);
            if($items){global $langs;$list=$collab->createChecklist($id,isset($langs)?$langs->trans('InternalTaskChecklists'):'Checklist');foreach($items as $item)$collab->createItem($id,$list,$item);}
            if($comment!==''){$key=substr($request,0,8).'-'.substr($request,8,4).'-4'.substr($request,13,3).'-8'.substr($request,17,3).'-'.substr($request,20,12);$collab->createComment($id,$comment,array(),$key);}
            if((int)$columnId>0){$personal=new InternalTaskPersonalBoard($this->db,$this->actor,$this->entity);$state=$personal->get();$personal->move($state['version'],$id,(int)$columnId);}
            if(!$this->db->commit())throw new RuntimeException('Unable to confirm save.');return $id;
        }catch(Throwable $e){if($begun)$this->db->rollback();throw $e;}
        finally{$this->db->query('SELECT RELEASE_LOCK('.$this->q($lock).')');}
    }
}
