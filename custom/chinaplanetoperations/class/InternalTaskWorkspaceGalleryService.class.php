<?php
require_once __DIR__.'/InternalTaskWorkspaceService.class.php';
require_once __DIR__.'/InternalTaskProjectDeadline.class.php';
require_once __DIR__.'/InternalTaskCompletionService.class.php';
require_once __DIR__.'/InternalTaskWorkspaceAppearance.class.php';

/** Shared customer priority; private per-user favorites and ordering. */
class InternalTaskWorkspaceGalleryService
{
    private $db;private $actor;private $entity;private $workspace;
    public function __construct($db,$actor,$entity){$this->db=$db;$this->actor=$actor;$this->entity=(int)$entity;$this->workspace=new InternalTaskWorkspaceService($db,$actor,$entity);}
    public static function enabled(){return InternalTaskWorkspaceService::enabled()&&!empty($GLOBALS['conf']->global->CPO_WORKSPACE_GALLERY_ENABLED);}
    public static function priorities(){return ['urgent','high','normal','low'];}
    private function query($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Unable to save customer workspace preferences.');return $r;}
    private function requireEnabled(){if(!self::enabled())throw new InternalTaskAccessException('Workspace gallery is unavailable.');}
    private function customer($id){
        $this->requireEnabled();$r=$this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity='.$this->entity.' AND rowid='.(int)$id.' AND client IN (1,3)');if(!$this->db->fetch_object($r))throw new InternalTaskAccessException('Customer workspace access denied.');
        $data=$this->workspace->projects((int)$id);if(!$this->workspace->canCreate()&&!$data['projects'])throw new InternalTaskAccessException('Customer workspace access denied.');return $data;
    }
    public function requireCustomer($id,$write=false){$data=$this->customer($id);if($write&&!$this->workspace->canCreate()&&!$this->actor->hasRight('societe','creer'))throw new InternalTaskAccessException('Customer editing permission required.');return $data['customer'];}
    public function customers($q=''){
        $data=$this->workspace->customers($q);if(!self::enabled())return $data;
        $ids=array_map('intval',array_column($data['customers'],'rowid'));$meta=[];
        if($ids){$r=$this->query('SELECT s.rowid,COALESCE(m.priority,\'normal\') priority,COALESCE(m.version,0) priority_version,COALESCE(p.starred,0) starred,COALESCE(p.position,2147483647) position FROM '.MAIN_DB_PREFIX.'societe s LEFT JOIN '.MAIN_DB_PREFIX.'cpo_customer_workspace_meta m ON m.entity=s.entity AND m.fk_soc=s.rowid LEFT JOIN '.MAIN_DB_PREFIX.'cpo_customer_workspace_pref p ON p.entity=s.entity AND p.fk_soc=s.rowid AND p.fk_user='.(int)$this->actor->id.' WHERE s.entity='.$this->entity.' AND s.rowid IN ('.implode(',',$ids).')');while($o=$this->db->fetch_object($r))$meta[(int)$o->rowid]=(array)$o;}
        foreach($data['customers'] as $i=>&$c){$c=array_merge($c,$meta[(int)$c['rowid']]??[]);$appearance=(new InternalTaskWorkspaceAppearance($this->db,$this->actor,$this->entity))->metadata($c['rowid'],false);$c=array_merge($c,$appearance);$c['original_order']=$i;$c['can_manage_priority']=$this->workspace->canCreate()||$this->actor->hasRight('societe','creer');}unset($c);
        usort($data['customers'],fn($a,$b)=>(int)$a['position']<=>(int)$b['position']?:$a['original_order']<=>$b['original_order']);foreach($data['customers'] as &$c)unset($c['original_order']);unset($c);
        $data['enhanced']=true;return $data;
    }
    public function projects($id){
        $data=self::enabled()?$this->customer($id):$this->workspace->projects($id);if(!self::enabled())return $data;
        // Reuse the actual board's existing preferences and background, never customer IDs as board IDs.
        foreach($data['projects'] as &$p)if($p['board']){
            $r=$this->query('SELECT b.background,COALESCE(p.starred,0) starred,(SELECT bg.image_hash FROM '.MAIN_DB_PREFIX.'cpo_board_background bg WHERE bg.entity=b.entity AND bg.fk_board=b.rowid) background_image FROM '.MAIN_DB_PREFIX.'cpo_board b LEFT JOIN '.MAIN_DB_PREFIX.'cpo_board_pref p ON p.entity=b.entity AND p.fk_board=b.rowid AND p.fk_user='.(int)$this->actor->id.' WHERE b.entity='.$this->entity.' AND b.rowid='.(int)$p['board']['rowid']);$b=$this->db->fetch_object($r);if($b)$p['board']=array_merge($p['board'],(array)$b);
        }unset($p);if(InternalTaskBoardLifecycle::enabled()){foreach($data['projects'] as &$p)if(!$p['board'])$p['removed_board']=(new InternalTaskBoardLifecycle($this->db,$this->actor,$this->entity))->projectBoard($p['rowid']);unset($p);}$projectIds=array_map('intval',array_column($data['projects'],'rowid'));$dates=[];
        if($projectIds){$r=$this->query('SELECT rowid,datee,fk_statut FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.$this->entity.' AND rowid IN ('.implode(',',$projectIds).')');while($row=$this->db->fetch_object($r))$dates[(int)$row->rowid]=$row;}
        if(InternalTaskCompletionService::enabled())foreach($data['projects'] as &$project){$project['done_tasks']=0;if($project['board']){$project['board']['completed']=(new InternalTaskCompletionService($this->db,$this->actor,$this->entity))->state($project['board']['rowid']);$auth=new InternalTaskAuthorizationService($this->db,$this->actor,$this->entity);$r=$this->query('SELECT '.InternalTaskBoardCounts::expression($this->entity,(int)$project['board']['rowid'],true).' n');$project['done_tasks']=(int)$this->db->fetch_object($r)->n;}}unset($project);
        foreach($data['projects'] as &$project){$native=$dates[(int)$project['rowid']]??null;$project['deadline']=InternalTaskProjectDeadline::summarize($native?$this->db->jdate($native->datee):null,$native->fk_statut??0);}unset($project);
        $data['appearance']=(new InternalTaskWorkspaceAppearance($this->db,$this->actor,$this->entity))->metadata($id);$data['can_manage_appearance']=$this->workspace->canCreate()||$this->actor->hasRight('societe','creer');$data['enhanced']=true;return $data;
    }
    public function mutate($action,array $data){
        $this->requireEnabled();if(!$this->db->begin())throw new RuntimeException('Unable to start preference save.');
        try{
            if($action==='workspace_reorder'){
                $ids=$data['ids']??[];if(!is_array($ids)||!$ids||count($ids)>200)throw new RuntimeException('Select the visible customer order.');foreach($ids as $id)if(!is_int($id)||$id<=0)throw new RuntimeException('Invalid workspace identity.');if(count(array_unique($ids))!==count($ids))throw new RuntimeException('Duplicate workspace identity.');
                $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.(int)$this->actor->id.' FOR UPDATE');$all=array_map('intval',array_column($this->customers()['customers'],'rowid'));if(array_diff($ids,$all))throw new InternalTaskAccessException('Customer workspace access denied.');
                // Reordering a filtered subset preserves all other customers' slots.
                $next=$ids;foreach($all as $index=>$id)if(in_array($id,$ids,true))$all[$index]=array_shift($next);
                foreach($all as $index=>$id)$this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_customer_workspace_pref(entity,fk_soc,fk_user,position) VALUES('.$this->entity.','.$id.','.(int)$this->actor->id.','.$index.') ON DUPLICATE KEY UPDATE position=VALUES(position)');
            }else{
                $id=(int)($data['customer']??0);$this->customer($id);
                if($action==='workspace_star'){$star=!empty($data['starred'])?1:0;$this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_customer_workspace_pref(entity,fk_soc,fk_user,starred) VALUES('.$this->entity.','.$id.','.(int)$this->actor->id.','.$star.') ON DUPLICATE KEY UPDATE starred=VALUES(starred)');}
                elseif($action==='workspace_priority'){
                    if(!$this->workspace->canCreate()&&!$this->actor->hasRight('societe','creer'))throw new InternalTaskAccessException('Customer editing permission required for shared priority.');
                    $priority=$data['priority']??'';$version=filter_var($data['version']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);if(!in_array($priority,self::priorities(),true)||$version===false)throw new RuntimeException('Select a valid priority and version.');
                    $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity='.$this->entity.' AND rowid='.$id.' FOR UPDATE');$r=$this->query('SELECT version FROM '.MAIN_DB_PREFIX.'cpo_customer_workspace_meta WHERE entity='.$this->entity.' AND fk_soc='.$id.' FOR UPDATE');$old=$this->db->fetch_object($r);if((int)($old->version??0)!==$version)throw new InternalTaskConflictException('Workspace priority changed. Reload before saving.');
                    $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_customer_workspace_meta(entity,fk_soc,priority,version,fk_user_modif,date_creation) VALUES('.$this->entity.','.$id.",'".$this->db->escape($priority)."',1,".(int)$this->actor->id.",'".$this->db->escape(date('Y-m-d H:i:s'))."') ON DUPLICATE KEY UPDATE priority=VALUES(priority),version=version+1,fk_user_modif=VALUES(fk_user_modif)");
                }else throw new RuntimeException('Unknown workspace preference action.');
            }
            if(!$this->db->commit())throw new RuntimeException('Unable to confirm preference save.');return ['saved'=>true];
        }catch(Throwable $e){$this->db->rollback();throw $e;}
    }
}
