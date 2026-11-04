<?php
require_once __DIR__.'/InternalTaskProjectTaskService.class.php';
/** Independent board completion. Task completion uses the existing task status. */
class InternalTaskCompletionService
{
    private $db;private $actor;private $entity;
    public function __construct($db,$actor,$entity){$this->db=$db;$this->actor=$actor;$this->entity=(int)$entity;}
    public static function enabled(){return !empty($GLOBALS['conf']->global->CPO_BOARD_COMPLETION_ENABLED);}
    private function query($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Completion could not be saved.');return $r;}
    public function state($board){if(!self::enabled())return false;$r=$this->query('SELECT completed FROM '.MAIN_DB_PREFIX.'cpo_board_completion WHERE entity='.$this->entity.' AND fk_board='.(int)$board);$o=$this->db->fetch_object($r);return $o&&!empty($o->completed);}
    public function board($board,$done){if(!self::enabled())throw new InternalTaskAccessException('Board completion is unavailable.');$this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_board_completion(entity,fk_board,completed,fk_user_modif,date_modified) VALUES('.$this->entity.','.(int)$board.','.(int)$done.','.(int)$this->actor->id.",'".$this->db->escape($this->db->idate(dol_now()))."') ON DUPLICATE KEY UPDATE completed=VALUES(completed),fk_user_modif=VALUES(fk_user_modif),date_modified=VALUES(date_modified)");}
    /** Native update/trigger, no project close or access/contact changes. Caller owns a savepoint. */
    public function nativeTask($task,$done){
        if(!InternalTaskProjectTaskService::enabled())return;
        $r=$this->query('SELECT fk_project,fk_native_task FROM '.MAIN_DB_PREFIX.'cpo_project_task WHERE entity='.$this->entity.' AND fk_task='.(int)$task.' FOR UPDATE');$map=$this->db->fetch_object($r);if(!$map)return;
        (new InternalTaskProjectTaskService($this->db,$this->actor,$this->entity))->requireProject($map->fk_project);
        $r=$this->query('SELECT * FROM '.MAIN_DB_PREFIX.'projet_task WHERE entity='.$this->entity.' AND rowid='.(int)$map->fk_native_task.' AND fk_projet='.(int)$map->fk_project.' FOR UPDATE');$locked=$this->db->fetch_object($r);if(!$locked)throw new InternalTaskConflictException('Native task association changed.');
        require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';$native=new Task($this->db);
        if($native->fetch((int)$map->fk_native_task)<=0||(int)$native->entity!==$this->entity||(int)$native->fk_project!==(int)$map->fk_project)throw new InternalTaskConflictException('Native task association changed.');
        if(in_array((int)$native->status,[Task::STATUS_TRANSFERRED,Task::STATUS_CANCELED],true))throw new RuntimeException('Review the transferred or canceled native task before changing completion.');
        // Task::fetch is a snapshot read under InnoDB REPEATABLE READ, even after
        // acquiring a current-read row lock. A broad Task::update could therefore
        // overwrite newer notes/time/fields. Update ONLY approved completion fields.
        foreach(['ref','label','description','note_private','note_public','duration_effective','planned_workload','budget_amount','priority','billable','rang','fk_task_parent'] as $field)$native->$field=$locked->$field;
        $native->fk_project=(int)$locked->fk_projet;$native->status=(int)$locked->fk_statut;$native->date_c=$this->db->jdate($locked->datec);$native->date_start=$this->db->jdate($locked->dateo);$native->date_end=$this->db->jdate($locked->datee);
        $extra=$this->query('SELECT * FROM '.MAIN_DB_PREFIX.'projet_task_extrafields WHERE fk_object='.(int)$map->fk_native_task.' FOR UPDATE');$options=$this->db->fetch_object($extra);$native->array_options=[];if($options)foreach((array)$options as $name=>$value)if(!in_array($name,['rowid','tms','fk_object','import_key','entity'],true))$native->array_options['options_'.$name]=$value;
        if(in_array($native->status,[Task::STATUS_TRANSFERRED,Task::STATUS_CANCELED],true))throw new RuntimeException('Review the transferred or canceled native task before changing completion.');
        $native->progress=$done?100:0;$native->status=$done?Task::STATUS_CLOSED:Task::STATUS_VALIDATED;
        $this->query('UPDATE '.MAIN_DB_PREFIX.'projet_task SET progress='.(int)$native->progress.',fk_statut='.(int)$native->status.' WHERE entity='.$this->entity.' AND rowid='.(int)$map->fk_native_task.' AND fk_projet='.(int)$map->fk_project);
        // Keep native TASK_MODIFY integrations. Do not invoke broad Task::update,
        // extra-field writes, or its optional automatic project-close policy.
        if($native->call_trigger('TASK_MODIFY',$this->actor)<0)throw new RuntimeException('Native task completion trigger failed. No completion was saved.');

    }
    public function task($id,$version,$done){
        if(!self::enabled())throw new InternalTaskAccessException('Task completion is unavailable.');
        $tasks=new InternalTaskService($this->db,$this->actor,$this->entity);$task=$tasks->requireReadableTask($id);
        if(!$tasks->authorization()->canManageTask($task)||!empty($task['date_archived'])||in_array($task['status'],['cancelled','deleted'],true))throw new InternalTaskAccessException('Task completion requires task management permission.');
        $depth=(int)$this->db->transaction_opened;$savepoint='cpo_completion_'.bin2hex(random_bytes(6));$live=false;
        try{if(!$this->db->begin())throw new RuntimeException('Completion transaction unavailable.');$this->query('SAVEPOINT '.$savepoint);$live=true;
            $r=$this->query('SELECT status,version FROM '.MAIN_DB_PREFIX.'cpo_task WHERE entity='.$this->entity.' AND rowid='.(int)$id.' FOR UPDATE');$current=$this->db->fetch_object($r);if(!$current||(int)$current->version!==(int)$version)throw new InternalTaskConflictException('Task changed. Reload before completing it.');
            if(($current->status==='done')===(bool)$done)throw new InternalTaskConflictException('Task completion already changed. Reload.');
            $this->nativeTask($id,$done);
            $stages=new InternalTaskStageService($this->db,$this->actor,$this->entity);$stage=$stages->systemStage($done?'done':'work',false);if(!$stage)throw new RuntimeException('Task stages unavailable.');
            $this->query('UPDATE '.MAIN_DB_PREFIX.'cpo_task SET status='.($done?"'done'":"'in_progress'").',fk_stage='.(int)$stage['rowid'].',progress='.($done?100:0).',date_completed='.($done?"'".$this->db->escape($this->db->idate(dol_now()))."'":'NULL').',waiting_reason=NULL,waiting_on=NULL,date_followup=NULL,version=version+1,fk_user_modif='.(int)$this->actor->id.' WHERE entity='.$this->entity.' AND rowid='.(int)$id);
            $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity(entity,fk_task,action_code,message,visibility,fk_user,date_action) VALUES('.$this->entity.','.(int)$id.",".($done?"'task_completed'":"'task_reopened'").",'', 'internal',".(int)$this->actor->id.",'".$this->db->escape($this->db->idate(dol_now()))."')");
            $this->query('RELEASE SAVEPOINT '.$savepoint);$live=false;if(!$this->db->commit())throw new RuntimeException('Unable to confirm task completion.');
        }catch(Throwable $e){if($live){try{$this->db->query('ROLLBACK TO SAVEPOINT '.$savepoint);$this->db->query('RELEASE SAVEPOINT '.$savepoint);}catch(Throwable $ignored){}}try{while($this->db->transaction_opened>$depth){$prior=$this->db->transaction_opened;$this->db->rollback();if($this->db->transaction_opened>=$prior)break;}}catch(Throwable $ignored){}throw $e;}
    }
}
