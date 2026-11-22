<?php
require_once __DIR__.'/InternalTaskWorkspaceService.class.php';

/** M108: create once in the native project. No backfill, grants or hidden sync. */
class InternalTaskProjectTaskService
{
    private $db;private $actor;private $entity;
    public function __construct($db,$actor,$entity){$this->db=$db;$this->actor=$actor;$this->entity=(int)$entity;}
    public static function enabled(){return InternalTaskWorkspaceService::enabled()&&!empty($GLOBALS['conf']->global->CPO_PROJECT_TASK_BRIDGE_ENABLED);}
    private function query($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Unable to link the native project task.');return $r;}
    public function requireProject($id){
        if(!self::enabled())throw new InternalTaskAccessException('Native project task linking is unavailable.');
        $scope=(new InternalTaskWorkspaceService($this->db,$this->actor,$this->entity))->project((int)$id);
        require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';$project=new Project($this->db);
        if($project->fetch((int)$id)<=0||(int)$project->entity!==$this->entity||(int)$project->socid!==(int)$scope['fk_soc'])throw new InternalTaskAccessException('Native project access denied.');
        // Match native project Task creation: global create right plus this project's write ACL.
        if(!$this->actor->hasRight('projet','creer')||$project->restrictedProjectArea($this->actor,'write')<=0)throw new InternalTaskAccessException('Native project task creation permission required.');
        if((int)$project->statut!==Project::STATUS_VALIDATED||empty($project->usage_task))throw new InternalTaskAccessException('Open the project and enable its task tracking before adding project cards.');
        return $project;
    }
    public function canCreate($project){try{$this->requireProject($project);return true;}catch(InternalTaskAccessException $e){return false;}}
    public function createForCard($id,$board,$projectId){
        $project=$this->requireProject($projectId);
        if(empty($this->db->transaction_opened))throw new RuntimeException('Project card creation requires an existing transaction.');
        $r=$this->query('SELECT t.rowid,t.entity,t.ref,t.title FROM '.MAIN_DB_PREFIX.'cpo_task t INNER JOIN '.MAIN_DB_PREFIX.'cpo_board_card c ON c.entity=t.entity AND c.fk_task=t.rowid INNER JOIN '.MAIN_DB_PREFIX.'cpo_project_board pb ON pb.entity=c.entity AND pb.fk_board=c.fk_board WHERE t.entity='.$this->entity.' AND t.rowid='.(int)$id.' AND c.fk_board='.(int)$board.' AND pb.fk_project='.(int)$projectId.' AND '.InternalTaskBoardLifecycle::visibleSql('c.entity','c.fk_board').' FOR UPDATE');$card=$this->db->fetch_object($r);
        if(!$card)throw new InternalTaskAccessException('Project card association mismatch.');
        $r=$this->query('SELECT m.fk_project,m.fk_native_task,n.fk_projet,n.entity native_entity FROM '.MAIN_DB_PREFIX.'cpo_project_task m LEFT JOIN '.MAIN_DB_PREFIX.'projet_task n ON n.rowid=m.fk_native_task AND n.entity=m.entity WHERE m.entity='.$this->entity.' AND m.fk_task='.(int)$id.' FOR UPDATE');$old=$this->db->fetch_object($r);
        if($old){if((int)$old->fk_project!==(int)$projectId||(int)$old->fk_projet!==(int)$projectId||(int)$old->native_entity!==$this->entity)throw new InternalTaskConflictException('The linked native task changed. Ask an administrator to review the association.');return (int)$old->fk_native_task;}
        require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';$native=new Task($this->db);
        $native->entity=$this->entity;$native->fk_project=(int)$projectId;$native->fk_task_parent=0;
        // Keep the card's existing unique reference. Native numbering counters stay unchanged.
        $native->ref=$card->ref;$native->label=$card->title;$native->description='';
        $native->progress=0;$native->status=Task::STATUS_VALIDATED;$native->billable=0;
        $nativeId=$native->create($this->actor);if($nativeId<=0)throw new RuntimeException('Native project task creation failed. The card was not saved.');
        $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_project_task(entity,fk_task,fk_project,fk_native_task,fk_user_creat,date_creation) VALUES('.$this->entity.','.(int)$id.','.(int)$projectId.','.(int)$nativeId.','.(int)$this->actor->id.",'".$this->db->escape(date('Y-m-d H:i:s'))."')");
        return (int)$nativeId;
    }
    /** Explicit project changes move the same card/native task; callers own the transaction. */
    public function moveCard($taskId,$sourceBoard,$projectId){
        $linked=$this->query("SELECT rowid FROM ".MAIN_DB_PREFIX."element_element WHERE sourcetype='cpo_task' AND fk_source=".(int)$taskId." AND targettype='societe' AND relationtype='cpo_supplier'");if($this->db->fetch_object($linked))throw new InternalTaskConflictException('Remove card supplier links before moving it to another project.');
        if(empty($this->db->transaction_opened))throw new RuntimeException('Project movement requires a transaction.');
        require_once __DIR__.'/InternalTaskBoardsService.class.php';
        $boards=new InternalTaskBoardsService($this->db,$this->actor,$this->entity);
        $source=$boards->board($sourceBoard,'editor',true);
        $targetProject=$this->requireProject($projectId);
        $sourceProject=$this->requireProject($source['workspace_project']['rowid']);
        $r=$this->query('SELECT fk_board FROM '.MAIN_DB_PREFIX.'cpo_project_board WHERE entity='.$this->entity.' AND fk_project='.(int)$projectId);$mapping=$this->db->fetch_object($r);
        if(!$mapping)throw new RuntimeException('Create an accessible board for the destination project first.');
        $destination=$boards->board($mapping->fk_board,'editor',true);
        if(!$source['can_edit']||!$destination['can_edit']||$source['archived']||$destination['archived'])throw new InternalTaskAccessException('Both project boards must be open and editable.');
        $r=$this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_board_list WHERE entity='.$this->entity.' AND fk_board='.(int)$mapping->fk_board.' AND archived=0 ORDER BY position,rowid LIMIT 1 FOR UPDATE');$list=$this->db->fetch_object($r);
        if(!$list)throw new RuntimeException('Add an active list to the destination board first.');
        $r=$this->query('SELECT m.fk_native_task,m.fk_project,n.fk_projet FROM '.MAIN_DB_PREFIX.'cpo_project_task m INNER JOIN '.MAIN_DB_PREFIX.'projet_task n ON n.rowid=m.fk_native_task AND n.entity=m.entity WHERE m.entity='.$this->entity.' AND m.fk_task='.(int)$taskId.' FOR UPDATE');$nativeMap=$this->db->fetch_object($r);
        if(!$nativeMap)throw new RuntimeException('This older card has no native task link. Review its association before moving it.');
        if((int)$nativeMap->fk_project!==(int)$source['workspace_project']['rowid']||(int)$nativeMap->fk_projet!==(int)$nativeMap->fk_project)throw new InternalTaskConflictException('The native task project changed. Review the association first.');
        $nativeId=(int)$nativeMap->fk_native_task;
        // Avoid moving time, child-task structures or contact/document permissions implicitly.
        foreach(['SELECT rowid FROM '.MAIN_DB_PREFIX."element_time WHERE elementtype='task' AND fk_element=".$nativeId.' LIMIT 1' ,
            'SELECT rowid FROM '.MAIN_DB_PREFIX.'projet_task WHERE fk_task_parent='.$nativeId.' OR (rowid='.$nativeId.' AND COALESCE(fk_task_parent,0)>0) LIMIT 1',
            "SELECT ec.rowid FROM ".MAIN_DB_PREFIX."element_contact ec INNER JOIN ".MAIN_DB_PREFIX."c_type_contact ct ON ct.rowid=ec.fk_c_type_contact WHERE ec.element_id=".$nativeId." AND ct.element='project_task' LIMIT 1",
            "SELECT rowid FROM ".MAIN_DB_PREFIX."element_element WHERE (sourcetype='project_task' AND fk_source=".$nativeId.") OR (targettype='project_task' AND fk_target=".$nativeId.') LIMIT 1',
            "SELECT rowid FROM ".MAIN_DB_PREFIX."ecm_files WHERE src_object_type='project_task' AND src_object_id=".$nativeId.' LIMIT 1'] as $sql)if($this->db->fetch_object($this->query($sql)))throw new RuntimeException('This native task has time, contacts, files or dependent records. Move it through a reviewed native project workflow.');
        require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';$native=new Task($this->db);if($native->fetch($nativeId)<=0)throw new RuntimeException('Native task unavailable.');
        require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';$root=$GLOBALS['conf']->project->multidir_output[$this->entity]??null;if($root){$folder=$root.'/'.dol_sanitizeFileName($sourceProject->ref).'/'.dol_sanitizeFileName($native->ref);if(is_dir($folder)&&dol_dir_list($folder,'files',1))throw new RuntimeException('Move the native task documents through a reviewed project workflow first.');}
        $oldUser=$GLOBALS['user'];$GLOBALS['user']=$this->actor;try{$result=$native->setProject((int)$projectId);}finally{$GLOBALS['user']=$oldUser;}
        if($result<=0)throw new RuntimeException('Native project task movement failed. Nothing was moved.');
        $this->query('UPDATE '.MAIN_DB_PREFIX.'cpo_project_task SET fk_project='.(int)$projectId.' WHERE entity='.$this->entity.' AND fk_task='.(int)$taskId);
        $this->query('UPDATE '.MAIN_DB_PREFIX.'cpo_board_card SET fk_board='.(int)$mapping->fk_board.',fk_list='.(int)$list->rowid.',position=2147483647 WHERE entity='.$this->entity.' AND fk_board='.(int)$sourceBoard.' AND fk_task='.(int)$taskId);
        $r=$this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_board_card WHERE entity='.$this->entity.' AND fk_list='.(int)$list->rowid.' ORDER BY position,rowid FOR UPDATE');$order=[];while($row=$this->db->fetch_object($r))$order[]=(int)$row->rowid;foreach($order as $index=>$placement)$this->query('UPDATE '.MAIN_DB_PREFIX.'cpo_board_card SET position='.(($index+1)*1024).' WHERE entity='.$this->entity.' AND rowid='.$placement);
        $this->query('UPDATE '.MAIN_DB_PREFIX.'cpo_board SET version=version+1 WHERE entity='.$this->entity.' AND rowid IN ('.(int)$sourceBoard.','.(int)$mapping->fk_board.')');
        return (int)$mapping->fk_board;
    }
}
