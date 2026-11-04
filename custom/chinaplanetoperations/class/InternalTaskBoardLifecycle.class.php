<?php
require_once __DIR__.'/InternalTaskWorkspaceService.class.php';

/** Recoverable presentation-layer removal; native records and scopes stay intact. */
class InternalTaskBoardLifecycle
{
    private $db;private $actor;private $entity;
    public function __construct($db,$actor,$entity){$this->db=$db;$this->actor=$actor;$this->entity=(int)$entity;}
    public static function enabled(){return !empty($GLOBALS['conf']->global->CPO_BOARD_DELETE_ENABLED);}
    public static function visibleSql($entity,$board){
        foreach([$entity,$board] as $part)if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/D',$part))throw new InvalidArgumentException('Invalid board visibility expression.');
        return self::enabled()?'NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'cpo_board_deleted bd_del WHERE bd_del.entity='.$entity.' AND bd_del.fk_board='.$board.')':'1=1';
    }
    private function query($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Board recovery storage unavailable.');return $r;}
    public function deleted($board){if(!self::enabled())return false;$r=$this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_board_deleted WHERE entity='.$this->entity.' AND fk_board='.(int)$board);return (bool)$this->db->fetch_object($r);}
    public function change($board,$remove){
        if(!self::enabled()||empty($this->db->transaction_opened))throw new InternalTaskAccessException('Board removal is unavailable.');
        // Called only after the existing manager/native edit/CAS checks in BoardsService.
        if($remove)$this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_board_deleted(entity,fk_board,fk_user_delete,date_deleted) VALUES('.$this->entity.','.(int)$board.','.(int)$this->actor->id.",'".$this->db->escape(date('Y-m-d H:i:s'))."')");
        else{if(!$this->deleted($board))throw new InternalTaskConflictException('This board is no longer deleted. Reload the gallery.');$this->query('DELETE FROM '.MAIN_DB_PREFIX.'cpo_board_deleted WHERE entity='.$this->entity.' AND fk_board='.(int)$board);}
        // Deliberately keep cpo_project_board, all card placements, memberships,
        // comments/files and every native projet/projet_task row unchanged.
    }
    public function gallery(){
        if(!self::enabled())throw new InternalTaskAccessException('Deleted boards are unavailable.');$auth=new InternalTaskAuthorizationService($this->db,$this->actor,$this->entity);$auth->requireBoard();$policy=new InternalTaskBoardPolicy($this->db,$auth->actorId(),$this->entity);
        $r=$this->query('SELECT b.rowid,b.title,b.version,b.archived,b.background,d.date_deleted FROM '.MAIN_DB_PREFIX.'cpo_board_deleted d INNER JOIN '.MAIN_DB_PREFIX.'cpo_board b ON b.entity=d.entity AND b.rowid=d.fk_board WHERE b.entity='.$this->entity.' ORDER BY d.date_deleted DESC,b.rowid DESC');$boards=[];
        while($b=$this->db->fetch_object($r)){
            if(!$auth->isAdministrator()&&$policy->role($b->rowid,true)!=='manager')continue;
            if(!$auth->isAdministrator()&&!$auth->hasRight('task_update_own')&&!$auth->hasRight('task_manage_team'))continue;
            try{(new InternalTaskWorkspaceService($this->db,$this->actor,$this->entity))->boardProject($b->rowid);}catch(InternalTaskAccessException $e){continue;}$boards[]=(array)$b;
        }return ['boards'=>$boards,'deleted'=>true];
    }
    public function projectBoard($project){
        if(!self::enabled())return null;(new InternalTaskWorkspaceService($this->db,$this->actor,$this->entity))->project($project);
        foreach($this->gallery()['boards'] as $b){$r=$this->query('SELECT fk_project FROM '.MAIN_DB_PREFIX.'cpo_project_board WHERE entity='.$this->entity.' AND fk_board='.(int)$b['rowid'].' AND fk_project='.(int)$project);if($this->db->fetch_object($r))return $b;}return null;
    }
}
