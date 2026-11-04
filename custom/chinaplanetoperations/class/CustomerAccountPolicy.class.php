<?php
require_once __DIR__.'/InternalTaskWorkspaceService.class.php';
require_once __DIR__.'/InternalTaskAuthorizationService.class.php';
/** Finance rights supplement native scope; Boards grants never imply finance rights. */
class CustomerAccountPolicy
{
    private $db; private $actor; private $entity;
    public function __construct($db, $actor, $entity) { $this->db=$db; $this->actor=$actor; $this->entity=(int)$entity; }
    public function projectSql() {return (new InternalTaskWorkspaceService($this->db,$this->actor,$this->entity))->projectSql('p');}
    public function actorId() { return (int)$this->actor->id; }
    public function allowed($action)
    {
        return !empty($this->actor->id) && empty($this->actor->socid) && empty($this->actor->fk_soc)
            && (!isset($this->actor->statut) || (int)$this->actor->statut===1)
            && (!isset($this->actor->entity) || (int)$this->actor->entity===$this->entity || (!empty($this->actor->admin)&&(int)$this->actor->entity===0))
            && (!empty($this->actor->admin) || $this->actor->hasRight('chinaplanetoperations', 'account_'.$action));
    }
    public function requireRight($action) { if (!$this->allowed($action)) throw new RuntimeException('Finance access denied.',403); }
    private function one($sql) { $r=$this->db->query($sql); if(!$r)throw new RuntimeException('Account scope unavailable.');return $this->db->fetch_object($r); }
    public function customer($customer)
    {
        $this->requireRight('read'); $ws=new InternalTaskWorkspaceService($this->db,$this->actor,$this->entity);
        $r=$this->one('SELECT s.rowid,s.nom FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid='.(int)$customer.' AND '.$ws->customerSql('s'));
        if(!$r)throw new RuntimeException('Customer access denied.',403);return (array)$r;
    }
    public function scope($customer, $project=0, $task=0, $historical=false)
    {
        $this->customer($customer);
        if($task&&!$project)throw new RuntimeException('A card allocation requires its project.',422);
        if($project) {
            (new InternalTaskWorkspaceService($this->db,$this->actor,$this->entity))->project((int)$project,(int)$customer);
        }
        if($task) {
            $record=$this->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_task WHERE entity='.$this->entity.' AND rowid='.(int)$task);
            // Deleted operational tasks retain financial attribution under finance + native project ACLs.
            // No current task fields are returned from the ledger. New allocations still require a live readable card.
            if(!$historical || ($record && $record->status!=='deleted')){
                if(!$record || $record->status==='deleted')throw new RuntimeException('Task access denied.',403);
                (new InternalTaskAuthorizationService($this->db,$this->actor,$this->entity))->requireRead((array)$record);
            }
            // Historical financial attribution remains fixed after operational card movement.
            if(!$historical) {
                $r=$this->one('SELECT bc.fk_task FROM '.MAIN_DB_PREFIX.'cpo_board_card bc INNER JOIN '.MAIN_DB_PREFIX.'cpo_project_board pb ON pb.entity=bc.entity AND pb.fk_board=bc.fk_board WHERE bc.entity='.$this->entity.' AND bc.fk_task='.(int)$task.' AND pb.fk_project='.(int)$project);
                if(!$r)throw new RuntimeException('Card and project must match.',422);
                $m=$this->one('SELECT m.fk_project,m.fk_native_task,n.entity native_entity,n.fk_projet native_project FROM '.MAIN_DB_PREFIX.'cpo_project_task m LEFT JOIN '.MAIN_DB_PREFIX.'projet_task n ON n.rowid=m.fk_native_task AND n.entity=m.entity WHERE m.entity='.$this->entity.' AND m.fk_task='.(int)$task);
                if($m&&((int)$m->fk_project!==(int)$project||(int)$m->native_entity!==$this->entity||(int)$m->native_project!==(int)$project))throw new RuntimeException('Native task association mismatch.',422);
            }
        }
    }
}
