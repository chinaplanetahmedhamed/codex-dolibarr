<?php

require_once __DIR__.'/InternalTaskService.class.php';

/** M060 task-scoped comments, mentions, checklists, labels and notifications. */
class InternalTaskCollaborationService
{
    private $db;
    private $actor;
    private $entity;
    private $tasks;
    private $auth;

    public function __construct($db, $actor = null, $entity = null)
    {
        global $conf;
        $this->db=$db;
        $this->actor=is_object($actor)?$actor:(isset($GLOBALS['user'])?$GLOBALS['user']:new stdClass());
        $this->entity=$entity===null?(int)$conf->entity:(int)$entity;
        $this->tasks=new InternalTaskService($db,$this->actor,$this->entity);
        $this->auth=$this->tasks->authorization();
    }

    public function authorization(){return $this->auth;}

    public function canComment(array $task)
    {
        return $this->auth->canReadTask($task)&&empty($task['date_archived'])
            && ((new InternalTaskBoardPolicy($this->db,$this->auth->actorId(),$this->entity))->access($task['rowid'],true)!==false)
            && in_array((string)$task['status'],array('todo','in_progress','waiting','review','done'),true)
            && ($this->auth->isDolibarrAdministrator()||$this->auth->hasRight('task_comment'));
    }

    public function canChecklist(array $task)
    {
        return empty($task['date_archived'])&&in_array((string)$task['status'],array('todo','in_progress','waiting'),true)
            && ($this->auth->isDolibarrAdministrator()||($this->auth->hasRight('task_checklist')&&($this->auth->canEditTask($task)||$this->auth->canUpdateOwnTask($task)||$this->auth->canManageTask($task))));
    }

    public function canAssignLabels(array $task)
    {
        return empty($task['date_archived'])&&in_array((string)$task['status'],array('todo','in_progress','waiting','review'),true)
            && ($this->auth->isAdministrator()||$this->auth->canEditTask($task)||$this->auth->canUpdateOwnTask($task)||$this->auth->canManageTask($task));
    }

    public function listComments($taskId,$cursorDate='',$cursorId=0,$limit=50)
    {
        $task=$this->tasks->requireReadableTask($taskId);
        $limit=max(1,min(50,(int)$limit));
        $where='c.entity='.$this->entity.' AND c.fk_task='.(int)$task['rowid'];
        if($cursorDate!==''&&$cursorId>0)$where.=' AND (c.date_creation<'.$this->q($cursorDate).' OR (c.date_creation='.$this->q($cursorDate).' AND c.rowid<'.(int)$cursorId.'))';
        $sql='SELECT c.rowid,c.fk_task,c.visibility,c.version,c.fk_user_creat,c.date_creation,'
            ."CASE WHEN c.visibility='redacted' THEN '' ELSE c.body END body,"
            ."CASE WHEN u.rowid IS NULL OR u.statut<>1 OR COALESCE(u.fk_soc,0)<>0 THEN '' ELSE u.login END author_login,"
            ."CASE WHEN u.rowid IS NULL OR u.statut<>1 OR COALESCE(u.fk_soc,0)<>0 THEN '' ELSE u.firstname END author_firstname,"
            ."CASE WHEN u.rowid IS NULL OR u.statut<>1 OR COALESCE(u.fk_soc,0)<>0 THEN '' ELSE u.lastname END author_lastname "
            .'FROM '.MAIN_DB_PREFIX.'cpo_task_comment c LEFT JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid=c.fk_user_creat AND (u.entity=c.entity OR (u.entity=0 AND u.admin=1)) '
            .'WHERE '.$where.' ORDER BY c.date_creation DESC,c.rowid DESC LIMIT '.$limit;
        return array_reverse($this->rows($sql));
    }

    public function mentionCandidates($taskId)
    {
        $task=$this->tasks->requireReadableTask($taskId);
        $sql='SELECT rowid,entity,login,firstname,lastname,admin FROM '.MAIN_DB_PREFIX.'user WHERE statut=1 AND COALESCE(fk_soc,0)=0 AND (entity='.$this->entity.' OR (entity=0 AND admin=1)) ORDER BY firstname,lastname,login,rowid';
        $out=array();
        foreach($this->rows($sql) as $row){
            $candidate=$this->candidateActor((int)$row['rowid']);
            if(!$candidate)continue;
            $candidateAuth=new InternalTaskAuthorizationService($this->db,$candidate,$this->entity);
            if($candidateAuth->canReadTask($task))$out[]=array('rowid'=>(int)$row['rowid'],'login'=>$row['login'],'firstname'=>$row['firstname'],'lastname'=>$row['lastname']);
        }
        return $out;
    }

    public function createComment($taskId,$body,array $mentionIds,$requestKey)
    {
        $body=$this->commentText($body,5000,true);
        $requestKey=strtolower(trim((string)$requestKey));
        if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/',$requestKey))throw new RuntimeException('Invalid comment request identity.');
        $mentionIds=array_values(array_unique(array_map('intval',$mentionIds)));sort($mentionIds,SORT_NUMERIC);
        if(count($mentionIds)>50)throw new RuntimeException('Too many mentions.');
        $hash=hash('sha256',json_encode(array((int)$taskId,$body,$mentionIds),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $this->begin();
        try{
            $task=$this->lockedReadableTask($taskId);
            if(!$this->canComment($task))throw new InternalTaskAccessException('Task access denied.');
            $existing=$this->one('SELECT rowid,request_hash FROM '.MAIN_DB_PREFIX.'cpo_task_comment WHERE entity='.$this->entity.' AND fk_user_creat='.$this->auth->actorId().' AND request_key='.$this->q($requestKey).' FOR UPDATE');
            if($existing){
                if(!hash_equals((string)$existing['request_hash'],$hash))throw new InternalTaskConflictException('Comment request changed.');
                $this->commit();return $this->commentById((int)$task['rowid'],(int)$existing['rowid']);
            }
            $eligible=$this->eligibleMentionIds($task,$mentionIds);
            $now=$this->now();
            $sql='INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_comment(entity,fk_task,body,request_key,request_hash,visibility,version,fk_user_creat,date_creation) VALUES('.$this->entity.','.(int)$task['rowid'].','.$this->q($body).','.$this->q($requestKey).','.$this->q($hash).",'visible',1,".$this->auth->actorId().','.$this->q($now).')';
            $this->must($sql,'Unable to create comment.');$commentId=(int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_task_comment');
            foreach($eligible as $userId){
                $this->must('INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_comment_mention(entity,fk_comment,fk_user,date_creation) VALUES('.$this->entity.','.$commentId.','.$userId.','.$this->q($now).')','Unable to create mention.');
                if($userId!==$this->auth->actorId())$this->must('INSERT INTO '.MAIN_DB_PREFIX."cpo_task_notification(entity,fk_user,fk_task,fk_comment,notification_type,date_seen,date_creation) VALUES(".$this->entity.','.$userId.','.(int)$task['rowid'].','.$commentId.",'mention',NULL,".$this->q($now).')','Unable to create notification.');
            }
            $this->activity((int)$task['rowid'],'task_comment_added');
            $this->commit();return $this->commentById((int)$task['rowid'],$commentId);
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function redactComment($taskId,$commentId,$expectedVersion,$reason)
    {
        $reason=$this->singleLine($reason,500,true);
        $this->begin();
        try{
            $task=$this->lockedReadableTask($taskId);
            if(!$this->auth->isAdministrator())throw new InternalTaskAccessException('Task access denied.');
            $row=$this->one('SELECT rowid,visibility,version FROM '.MAIN_DB_PREFIX.'cpo_task_comment WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND rowid='.(int)$commentId.' FOR UPDATE');
            if(!$row)throw new InternalTaskAccessException('Task access denied.');
            if($row['visibility']==='redacted'){if((int)$row['version']!==(int)$expectedVersion+1)throw new InternalTaskConflictException('Comment changed.');$this->commit();return $this->commentById((int)$task['rowid'],(int)$commentId);}
            if((int)$row['version']!==(int)$expectedVersion)throw new InternalTaskConflictException('Comment changed.');
            $update=$this->must('UPDATE '.MAIN_DB_PREFIX."cpo_task_comment SET visibility='redacted',redaction_reason=".$this->q($reason).',fk_user_redact='.$this->auth->actorId().',date_redacted='.$this->q($this->now()).',version=version+1 WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND rowid='.(int)$commentId.' AND version='.(int)$expectedVersion,'Unable to hide comment.');
            if($this->db->affected_rows($update)!==1)throw new InternalTaskConflictException('Comment changed.');
            $this->activity((int)$task['rowid'],'task_comment_redacted');$this->commit();return $this->commentById((int)$task['rowid'],(int)$commentId);
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function listChecklists($taskId,$includeInactive=false)
    {
        $task=$this->tasks->requireReadableTask($taskId);
        $sql='SELECT rowid,title,position,active,version FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].($includeInactive?'':' AND active=1').' ORDER BY active DESC,position,rowid';
        $lists=$this->rows($sql);
        foreach($lists as &$list){
            $list['items']=$this->rows('SELECT rowid,label,position,completed,active,version,fk_user_completed,date_completed FROM '.MAIN_DB_PREFIX.'cpo_task_checklist_item WHERE entity='.$this->entity.' AND fk_checklist='.(int)$list['rowid'].($includeInactive?'':' AND active=1').' ORDER BY active DESC,position,rowid');
            $list['item_order_fingerprint']=$this->orderFingerprintRows(array_values(array_filter($list['items'],function($row){return (int)$row['active']===1;})));
        }
        unset($list);return $lists;
    }

    public function createChecklist($taskId,$title)
    {
        $title=$this->singleLine($title,255,true);$this->begin();
        try{$task=$this->lockedReadableTask($taskId);$this->requireChecklist($task);
            if($this->count('SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'])>=25)throw new RuntimeException('Checklist retained limit reached.');
            if($this->count('SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND active=1')>=10)throw new RuntimeException('Checklist active limit reached.');
            $position=$this->count('SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND active=1')*10+10;
            $this->must('INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_checklist(entity,fk_task,title,position,active,version,fk_user_creat,fk_user_modif,date_creation) VALUES('.$this->entity.','.(int)$task['rowid'].','.$this->q($title).','.$position.',1,1,'.$this->auth->actorId().','.$this->auth->actorId().','.$this->q($this->now()).')','Unable to create checklist.');
            $id=(int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_task_checklist');$this->activity((int)$task['rowid'],'task_checklist_added');$this->commit();return $id;
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function mutateChecklist($taskId,$checklistId,$expectedVersion,$action,$value='')
    {
        $this->begin();try{$task=$this->lockedReadableTask($taskId);$this->requireChecklist($task);
            $row=$this->one('SELECT rowid,active,version FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND rowid='.(int)$checklistId.' FOR UPDATE');if(!$row)throw new InternalTaskAccessException('Task access denied.');
            if((int)$row['version']!==(int)$expectedVersion)throw new InternalTaskConflictException('Checklist changed.');
            if($action==='rename'){if((int)$row['active']!==1)throw new InternalTaskConflictException('Checklist changed.');$set='title='.$this->q($this->singleLine($value,255,true));}
            elseif($action==='remove'){if((int)$row['active']!==1)throw new InternalTaskConflictException('Checklist changed.');$set='active=0';}
            elseif($action==='restore'){
                if((int)$row['active']!==0)throw new InternalTaskConflictException('Checklist changed.');
                if($this->count('SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND active=1')>=10)throw new RuntimeException('Checklist active limit reached.');
                $childCount=$this->count('SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpo_task_checklist_item WHERE entity='.$this->entity.' AND fk_checklist='.(int)$checklistId.' AND active=1');
                if($this->activeItemCount((int)$task['rowid'])+$childCount>100)throw new RuntimeException('Checklist item active limit reached.');$set='active=1';
            }else throw new RuntimeException('Unsupported checklist action.');
            $update=$this->must('UPDATE '.MAIN_DB_PREFIX.'cpo_task_checklist SET '.$set.',fk_user_modif='.$this->auth->actorId().',version=version+1 WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND rowid='.(int)$checklistId.' AND version='.(int)$expectedVersion,'Unable to update checklist.');if($this->db->affected_rows($update)!==1)throw new InternalTaskConflictException('Checklist changed.');
            $this->activity((int)$task['rowid'],'task_checklist_updated');$this->commit();return true;
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function createItem($taskId,$checklistId,$label)
    {
        $label=$this->singleLine($label,255,true);$this->begin();try{$task=$this->lockedReadableTask($taskId);$this->requireChecklist($task);
            $list=$this->one('SELECT rowid,active FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND rowid='.(int)$checklistId.' FOR UPDATE');if(!$list||(int)$list['active']!==1)throw new InternalTaskAccessException('Task access denied.');
            if($this->retainedItemCount((int)$task['rowid'])>=250)throw new RuntimeException('Checklist item retained limit reached.');if($this->activeItemCount((int)$task['rowid'])>=100)throw new RuntimeException('Checklist item active limit reached.');
            $position=$this->count('SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpo_task_checklist_item WHERE entity='.$this->entity.' AND fk_checklist='.(int)$checklistId.' AND active=1')*10+10;
            $this->must('INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_checklist_item(entity,fk_checklist,label,position,completed,active,version,fk_user_creat,fk_user_modif,date_creation) VALUES('.$this->entity.','.(int)$checklistId.','.$this->q($label).','.$position.',0,1,1,'.$this->auth->actorId().','.$this->auth->actorId().','.$this->q($this->now()).')','Unable to create checklist item.');$id=(int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_task_checklist_item');$this->activity((int)$task['rowid'],'task_checklist_item_added');$this->commit();return $id;
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function mutateItem($taskId,$itemId,$expectedVersion,$action,$value='')
    {
        $this->begin();try{$task=$this->lockedReadableTask($taskId);$this->requireChecklist($task);
            $row=$this->one('SELECT i.rowid,i.active,i.completed,i.version,i.fk_checklist,c.active checklist_active FROM '.MAIN_DB_PREFIX.'cpo_task_checklist_item i INNER JOIN '.MAIN_DB_PREFIX.'cpo_task_checklist c ON c.rowid=i.fk_checklist AND c.entity=i.entity WHERE i.entity='.$this->entity.' AND c.fk_task='.(int)$task['rowid'].' AND i.rowid='.(int)$itemId.' FOR UPDATE');if(!$row)throw new InternalTaskAccessException('Task access denied.');if((int)$row['version']!==(int)$expectedVersion)throw new InternalTaskConflictException('Checklist item changed.');
            if(in_array($action,array('rename','toggle','remove'),true)&&((int)$row['active']!==1||(int)$row['checklist_active']!==1))throw new InternalTaskConflictException('Checklist item changed.');
            if($action==='rename')$set='label='.$this->q($this->singleLine($value,255,true));
            elseif($action==='toggle'){$done=(int)$row['completed']===1?0:1;$set='completed='.$done.',fk_user_completed='.($done?$this->auth->actorId():'NULL').',date_completed='.($done?$this->q($this->now()):'NULL');}
            elseif($action==='remove')$set='active=0';
            elseif($action==='restore'){if((int)$row['active']!==0)throw new InternalTaskConflictException('Checklist item changed.');if((int)$row['checklist_active']!==1)throw new RuntimeException('Restore the checklist first.');if($this->activeItemCount((int)$task['rowid'])>=100)throw new RuntimeException('Checklist item active limit reached.');$set='active=1';}
            else throw new RuntimeException('Unsupported checklist item action.');
            $update=$this->must('UPDATE '.MAIN_DB_PREFIX.'cpo_task_checklist_item SET '.$set.',fk_user_modif='.$this->auth->actorId().',version=version+1 WHERE entity='.$this->entity.' AND rowid='.(int)$itemId.' AND version='.(int)$expectedVersion,'Unable to update checklist item.');if($this->db->affected_rows($update)!==1)throw new InternalTaskConflictException('Checklist item changed.');$this->activity((int)$task['rowid'],'task_checklist_item_updated');$this->commit();return true;
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function orderFingerprint($taskId,$scope,$parentId=0)
    {
        $task=$this->tasks->requireReadableTask($taskId);$this->requireChecklist($task);
        if($scope==='checklists')$sql='SELECT rowid,version,position FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND active=1 ORDER BY position,rowid';
        elseif($scope==='items'){$list=$this->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND rowid='.(int)$parentId.' AND active=1');if(!$list)throw new InternalTaskAccessException('Task access denied.');$sql='SELECT rowid,version,position FROM '.MAIN_DB_PREFIX.'cpo_task_checklist_item WHERE entity='.$this->entity.' AND fk_checklist='.(int)$parentId.' AND active=1 ORDER BY position,rowid';}
        else throw new RuntimeException('Invalid order scope.');return $this->orderFingerprintRows($this->rows($sql));
    }

    public function reorder($taskId,$scope,$parentId,array $ids,$expectedFingerprint)
    {
        $ids=array_values(array_map('intval',$ids));if(count($ids)!==count(array_unique($ids)))throw new RuntimeException('Invalid order.');$this->begin();try{$task=$this->lockedReadableTask($taskId);$this->requireChecklist($task);
            if($scope==='checklists'){$sql='SELECT rowid,version,position FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND active=1 ORDER BY rowid FOR UPDATE';$table='cpo_task_checklist';}
            elseif($scope==='items'){$list=$this->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task_checklist WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND rowid='.(int)$parentId.' AND active=1 FOR UPDATE');if(!$list)throw new InternalTaskAccessException('Task access denied.');$sql='SELECT rowid,version,position FROM '.MAIN_DB_PREFIX.'cpo_task_checklist_item WHERE entity='.$this->entity.' AND fk_checklist='.(int)$parentId.' AND active=1 ORDER BY rowid FOR UPDATE';$table='cpo_task_checklist_item';}
            else throw new RuntimeException('Invalid order scope.');$rows=$this->rows($sql);if(!hash_equals($this->orderFingerprintRows($rows),(string)$expectedFingerprint))throw new InternalTaskConflictException('Order changed.');$current=array_map('intval',array_column($rows,'rowid'));$expected=$ids;sort($current);sort($expected);if($current!==$expected)throw new InternalTaskConflictException('Order changed.');
            $versions=array();foreach($rows as $row)$versions[(int)$row['rowid']]=(int)$row['version'];
            foreach($ids as $index=>$id){$update=$this->must('UPDATE '.MAIN_DB_PREFIX.$table.' SET position='.(1000000+$index).',version=version+1,fk_user_modif='.$this->auth->actorId().' WHERE entity='.$this->entity.' AND rowid='.$id.' AND version='.$versions[$id],'Unable to reorder.');if($this->db->affected_rows($update)!==1)throw new InternalTaskConflictException('Order changed.');}
            foreach($ids as $index=>$id)$this->must('UPDATE '.MAIN_DB_PREFIX.$table.' SET position='.(($index+1)*10).' WHERE entity='.$this->entity.' AND rowid='.$id,'Unable to reorder.');
            $newRows=$this->rows('SELECT rowid,version,position FROM '.MAIN_DB_PREFIX.$table.' WHERE entity='.$this->entity.' AND rowid IN('.implode(',',$ids).') ORDER BY position,rowid');$fingerprint=$this->orderFingerprintRows($newRows);$this->activity((int)$task['rowid'],'task_checklist_reordered');$this->commit();return $fingerprint;
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function labelDefinitions($includeRetired=true)
    {
        if(!$this->auth->isInternalActive())throw new InternalTaskAccessException('Task access denied.');
        return $this->rows('SELECT rowid,code,label_en,label_ar,label_zh,color,active,version FROM '.MAIN_DB_PREFIX.'cpo_task_label WHERE entity='.$this->entity.($includeRetired?'':' AND active=1').' ORDER BY active DESC,code,rowid');
    }

    public function saveLabel($labelId,$expectedVersion,array $data)
    {
        if(!$this->auth->isAdministrator())throw new InternalTaskAccessException('Task access denied.');$code=strtolower($this->singleLine(isset($data['code'])?$data['code']:'',64,true));if(!preg_match('/^[a-z0-9_]{1,64}$/',$code))throw new RuntimeException('Invalid label code.');$en=$this->singleLine($data['label_en'],255,true);$ar=$this->singleLine($data['label_ar'],255,true);$zh=$this->singleLine($data['label_zh'],255,true);$color=strtoupper(trim((string)$data['color']));if(!preg_match('/^#[0-9A-F]{6}$/',$color))throw new RuntimeException('Invalid label color.');$this->begin();try{
            if((int)$labelId>0){$row=$this->one('SELECT rowid,code,version FROM '.MAIN_DB_PREFIX.'cpo_task_label WHERE entity='.$this->entity.' AND rowid='.(int)$labelId.' FOR UPDATE');if(!$row)throw new InternalTaskAccessException('Task access denied.');if((int)$row['version']!==(int)$expectedVersion||!hash_equals((string)$row['code'],$code))throw new InternalTaskConflictException('Label changed.');$this->must('UPDATE '.MAIN_DB_PREFIX.'cpo_task_label SET label_en='.$this->q($en).',label_ar='.$this->q($ar).',label_zh='.$this->q($zh).',color='.$this->q($color).',fk_user_modif='.$this->auth->actorId().',version=version+1 WHERE entity='.$this->entity.' AND rowid='.(int)$labelId.' AND version='.(int)$expectedVersion,'Unable to update label.');}
            else{$this->must('INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_label(entity,code,label_en,label_ar,label_zh,color,active,version,fk_user_creat,fk_user_modif,date_creation) VALUES('.$this->entity.','.$this->q($code).','.$this->q($en).','.$this->q($ar).','.$this->q($zh).','.$this->q($color).',1,1,'.$this->auth->actorId().','.$this->auth->actorId().','.$this->q($this->now()).')','Unable to create label.');$labelId=(int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_task_label');}
            $this->activity(0,'task_label_definition_updated');$this->commit();return (int)$labelId;
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function setLabelActive($labelId,$expectedVersion,$active)
    {
        if(!$this->auth->isAdministrator())throw new InternalTaskAccessException('Task access denied.');$this->begin();try{$row=$this->one('SELECT rowid,version FROM '.MAIN_DB_PREFIX.'cpo_task_label WHERE entity='.$this->entity.' AND rowid='.(int)$labelId.' FOR UPDATE');if(!$row)throw new InternalTaskAccessException('Task access denied.');if((int)$row['version']!==(int)$expectedVersion)throw new InternalTaskConflictException('Label changed.');$update=$this->must('UPDATE '.MAIN_DB_PREFIX.'cpo_task_label SET active='.($active?1:0).',fk_user_modif='.$this->auth->actorId().',version=version+1 WHERE entity='.$this->entity.' AND rowid='.(int)$labelId.' AND version='.(int)$expectedVersion,'Unable to update label.');if($this->db->affected_rows($update)!==1)throw new InternalTaskConflictException('Label changed.');$this->activity(0,'task_label_definition_updated');$this->commit();return true;}catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function taskLabels($taskId)
    {
        $task=$this->tasks->requireReadableTask($taskId);return $this->rows('SELECT l.rowid,l.code,l.label_en,l.label_ar,l.label_zh,l.color,l.active,x.rowid link_id FROM '.MAIN_DB_PREFIX.'cpo_task_label_link x INNER JOIN '.MAIN_DB_PREFIX.'cpo_task_label l ON l.rowid=x.fk_label AND l.entity=x.entity WHERE x.entity='.$this->entity.' AND x.fk_task='.(int)$task['rowid'].' ORDER BY l.active DESC,l.code,l.rowid');
    }

    public function labelFingerprint($taskId)
    {
        $task=$this->tasks->requireReadableTask($taskId);return $this->labelFingerprintUnlocked((int)$task['rowid']);
    }

    public function setTaskLabels($taskId,array $labelIds,$expectedFingerprint)
    {
        $labelIds=array_values(array_map('intval',$labelIds));if(count($labelIds)!==count(array_unique($labelIds)))throw new RuntimeException('Duplicate task label.');sort($labelIds);if(count($labelIds)>20)throw new RuntimeException('Task label limit reached.');$this->begin();try{$task=$this->lockedReadableTask($taskId);if(!$this->canAssignLabels($task))throw new InternalTaskAccessException('Task access denied.');$links=$this->rows('SELECT rowid,fk_label FROM '.MAIN_DB_PREFIX.'cpo_task_label_link WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' ORDER BY rowid FOR UPDATE');$fingerprint=hash('sha256',implode(',',array_map('intval',array_column($links,'rowid'))));if(!hash_equals($fingerprint,(string)$expectedFingerprint))throw new InternalTaskConflictException('Task labels changed.');$existing=array_map('intval',array_column($links,'fk_label'));
            foreach($labelIds as $id){$label=$this->one('SELECT rowid,active FROM '.MAIN_DB_PREFIX.'cpo_task_label WHERE entity='.$this->entity.' AND rowid='.$id.' FOR UPDATE');if(!$label||((int)$label['active']!==1&&!in_array($id,$existing,true)))throw new InternalTaskAccessException('Task access denied.');}
            foreach(array_diff($existing,$labelIds) as $id)$this->must('DELETE FROM '.MAIN_DB_PREFIX.'cpo_task_label_link WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid'].' AND fk_label='.(int)$id,'Unable to update task labels.');
            foreach(array_diff($labelIds,$existing) as $id)$this->must('INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_label_link(entity,fk_task,fk_label,fk_user_creat,date_creation) VALUES('.$this->entity.','.(int)$task['rowid'].','.(int)$id.','.$this->auth->actorId().','.$this->q($this->now()).')','Unable to update task labels.');$newFingerprint=$this->labelFingerprintUnlocked((int)$task['rowid']);$this->activity((int)$task['rowid'],'task_labels_updated');$this->commit();return $newFingerprint;
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function inbox($cursorDate='',$cursorId=0,$limit=50)
    {
        if(!$this->auth->isInternalActive())throw new InternalTaskAccessException('Task access denied.');$limit=max(1,min(50,(int)$limit));$where='n.entity='.$this->entity.' AND n.fk_user='.$this->auth->actorId()." AND n.notification_type='mention' AND t.status<>'deleted' AND ".$this->auth->readableSql('t');if($cursorDate!==''&&$cursorId>0)$where.=' AND (n.date_creation<'.$this->q($cursorDate).' OR (n.date_creation='.$this->q($cursorDate).' AND n.rowid<'.(int)$cursorId.'))';
        return $this->rows('SELECT n.rowid,n.fk_task,n.date_seen,n.date_creation,t.ref task_ref,u.login author_login,u.firstname author_firstname,u.lastname author_lastname FROM '.MAIN_DB_PREFIX.'cpo_task_notification n INNER JOIN '.MAIN_DB_PREFIX.'cpo_task t ON t.rowid=n.fk_task AND t.entity=n.entity INNER JOIN '.MAIN_DB_PREFIX.'cpo_task_comment c ON c.rowid=n.fk_comment AND c.entity=n.entity AND c.fk_task=n.fk_task LEFT JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid=c.fk_user_creat AND u.statut=1 AND COALESCE(u.fk_soc,0)=0 AND (u.entity=c.entity OR (u.entity=0 AND u.admin=1)) WHERE '.$where.' ORDER BY n.date_creation DESC,n.rowid DESC LIMIT '.$limit);
    }

    public function unreadCount()
    {
        if(!$this->auth->isInternalActive())return 0;return $this->count('SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpo_task_notification n INNER JOIN '.MAIN_DB_PREFIX.'cpo_task t ON t.rowid=n.fk_task AND t.entity=n.entity WHERE n.entity='.$this->entity.' AND n.fk_user='.$this->auth->actorId()." AND n.notification_type='mention' AND n.date_seen IS NULL AND t.status<>'deleted' AND ".$this->auth->readableSql('t'));
    }

    public function markRead($taskId,$notificationId)
    {
        $this->begin();try{$task=$this->lockedReadableTask($taskId);$notification=$this->one('SELECT rowid,fk_task FROM '.MAIN_DB_PREFIX."cpo_task_notification WHERE entity=".$this->entity.' AND fk_user='.$this->auth->actorId().' AND fk_task='.(int)$task['rowid'].' AND rowid='.(int)$notificationId." AND notification_type='mention' FOR UPDATE");if(!$notification)throw new InternalTaskAccessException('Task access denied.');$this->must('UPDATE '.MAIN_DB_PREFIX.'cpo_task_notification SET date_seen=COALESCE(date_seen,'.$this->q($this->now()).') WHERE entity='.$this->entity.' AND fk_user='.$this->auth->actorId().' AND fk_task='.(int)$task['rowid'].' AND rowid='.(int)$notificationId." AND notification_type='mention'",'Unable to update notification.');$this->commit();return (int)$task['rowid'];}catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function counters(array $taskIds)
    {
        $this->auth->requireBoard();$taskIds=array_values(array_unique(array_filter(array_map('intval',$taskIds))));if(!$taskIds)return array();$in=implode(',',$taskIds);$authorized=array_map('intval',array_column($this->rows('SELECT t.rowid FROM '.MAIN_DB_PREFIX.'cpo_task t WHERE t.entity='.$this->entity." AND t.status<>'deleted' AND t.rowid IN(".$in.') AND '.$this->auth->readableSql('t').' ORDER BY t.rowid'),'rowid'));$expected=$taskIds;sort($authorized);sort($expected);if($authorized!==$expected)throw new InternalTaskAccessException('Task access denied.');$out=array();foreach($taskIds as $id)$out[$id]=array('comments'=>0,'checklist_done'=>0,'checklist_total'=>0,'labels'=>array());
        foreach($this->rows('SELECT fk_task,COUNT(*) total FROM '.MAIN_DB_PREFIX.'cpo_task_comment WHERE entity='.$this->entity.' AND fk_task IN('.$in.') GROUP BY fk_task') as $r)$out[(int)$r['fk_task']]['comments']=(int)$r['total'];
        foreach($this->rows('SELECT c.fk_task,SUM(i.completed=1) done_count,COUNT(i.rowid) total_count FROM '.MAIN_DB_PREFIX.'cpo_task_checklist c LEFT JOIN '.MAIN_DB_PREFIX.'cpo_task_checklist_item i ON i.entity=c.entity AND i.fk_checklist=c.rowid AND i.active=1 WHERE c.entity='.$this->entity.' AND c.active=1 AND c.fk_task IN('.$in.') GROUP BY c.fk_task') as $r){$out[(int)$r['fk_task']]['checklist_done']=(int)$r['done_count'];$out[(int)$r['fk_task']]['checklist_total']=(int)$r['total_count'];}
        foreach($this->rows('SELECT x.fk_task,l.rowid,l.code,l.label_en,l.label_ar,l.label_zh,l.color,l.active FROM '.MAIN_DB_PREFIX.'cpo_task_label_link x INNER JOIN '.MAIN_DB_PREFIX.'cpo_task_label l ON l.rowid=x.fk_label AND l.entity=x.entity WHERE x.entity='.$this->entity.' AND x.fk_task IN('.$in.') ORDER BY l.code,l.rowid') as $r)$out[(int)$r['fk_task']]['labels'][]=$r;return $out;
    }

    private function commentById($taskId,$commentId){$rows=$this->listComments($taskId,'',0,50);foreach($rows as $row)if((int)$row['rowid']===(int)$commentId)return $row;return array('rowid'=>(int)$commentId);}
    private function labelFingerprintUnlocked($taskId){$ids=array_map('intval',array_column($this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task_label_link WHERE entity='.$this->entity.' AND fk_task='.(int)$taskId.' ORDER BY rowid'),'rowid'));return hash('sha256',implode(',',$ids));}
    private function orderFingerprintRows(array $rows){$canonical=array();usort($rows,function($a,$b){$position=(int)$a['position']<=>(int)$b['position'];return $position?:((int)$a['rowid']<=>(int)$b['rowid']);});foreach($rows as $row)$canonical[]=(int)$row['rowid'].':'.(int)$row['version'].':'.(int)$row['position'];return hash('sha256',implode(',',$canonical));}
    private function eligibleMentionIds(array $task,array $ids){$eligible=array();foreach($ids as $id){$actor=$this->candidateActor($id);if(!$actor)throw new InternalTaskAccessException('Task access denied.');$auth=new InternalTaskAuthorizationService($this->db,$actor,$this->entity);if(!$auth->canReadTask($task))throw new InternalTaskAccessException('Task access denied.');$eligible[]=(int)$id;}return $eligible;}
    private function candidateActor($id){if(!class_exists('User')&&defined('DOL_DOCUMENT_ROOT'))require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';if(class_exists('User')){$candidate=new User($this->db);if($candidate->fetch((int)$id)<=0)return null;if(method_exists($candidate,'getrights'))$candidate->getrights();return $candidate;}return null;}
    private function lockedReadableTask($id){$task=$this->tasks->getTask((int)$id,true);if(!$task)throw new InternalTaskAccessException('Task access denied.');$this->auth->requireRead($task);return $task;}
    private function requireChecklist(array $task){if(!$this->canChecklist($task))throw new InternalTaskAccessException('Task access denied.');}
    private function activeItemCount($taskId){return $this->count('SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpo_task_checklist_item i INNER JOIN '.MAIN_DB_PREFIX.'cpo_task_checklist c ON c.rowid=i.fk_checklist AND c.entity=i.entity WHERE c.entity='.$this->entity.' AND c.fk_task='.(int)$taskId.' AND c.active=1 AND i.active=1');}
    private function retainedItemCount($taskId){return $this->count('SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpo_task_checklist_item i INNER JOIN '.MAIN_DB_PREFIX.'cpo_task_checklist c ON c.rowid=i.fk_checklist AND c.entity=i.entity WHERE c.entity='.$this->entity.' AND c.fk_task='.(int)$taskId);}
    private function commentText($value,$max,$required){$value=str_replace(array("\r\n","\r"),"\n",(string)$value);return $this->validatedText($value,$max,$required,true);}
    private function singleLine($value,$max,$required){$value=(string)$value;if(strpos($value,"\n")!==false||strpos($value,"\r")!==false)throw new RuntimeException('Single-line text required.');return $this->validatedText($value,$max,$required,false);}
    private function validatedText($value,$max,$required,$allowLf){if(!function_exists('mb_check_encoding')||!function_exists('mb_strlen'))throw new RuntimeException('UTF-8 validation is unavailable.');if(!mb_check_encoding($value,'UTF-8'))throw new RuntimeException('Invalid UTF-8.');$forbidden=$allowLf?'/[\x00-\x09\x0B-\x1F\x7F-\x{009F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u':'/[\x00-\x1F\x7F-\x{009F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';if(preg_match($forbidden,$value))throw new RuntimeException('Unsupported control character.');$value=trim($value);$length=mb_strlen($value,'UTF-8');if(($required&&$length===0)||$length>$max)throw new RuntimeException('Invalid text length.');return $value;}
    private function activity($taskId,$code){$this->must('INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity(entity,fk_operation,fk_task,action_code,message,visibility,fk_user,date_action) VALUES('.$this->entity.',NULL,'.($taskId>0?(int)$taskId:'NULL').','.$this->q($code).',NULL,\'internal\','.$this->auth->actorId().','.$this->q($this->now()).')','Unable to save task activity.');}
    private function rows($sql){$out=array();$res=$this->db->query($sql);if(!$res)throw new RuntimeException('Unable to read task collaboration.');while($row=$this->db->fetch_object($res))$out[]=get_object_vars($row);return $out;}
    private function one($sql){$rows=$this->rows($sql);return $rows?$rows[0]:null;}
    private function count($sql){$row=$this->one($sql);return $row?(int)$row['count_rows']:0;}
    private function must($sql,$message){$res=$this->db->query($sql);if(!$res)throw new RuntimeException($message);return $res;}
    private function q($value){return "'".$this->db->escape((string)$value)."'";}
    private function now(){return date('Y-m-d H:i:s');}
    private function begin(){if(!$this->db->begin())throw new RuntimeException('Unable to begin collaboration transaction.');}
    private function commit(){if(!$this->db->commit())throw new RuntimeException('Unable to commit collaboration transaction.');}
    private function rollback(){$this->db->rollback();}
}
