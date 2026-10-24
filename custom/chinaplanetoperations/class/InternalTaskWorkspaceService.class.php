<?php
require_once __DIR__.'/InternalTaskBoardLifecycle.class.php';
require_once __DIR__.'/InternalTaskBoardCounts.class.php';
/** Bounded customer -> native project -> existing board grouping.
 * Feature flag disabled: all legacy board/task paths are unchanged.
 * Native customer/project rights supplement existing board/card membership.
 */
class InternalTaskWorkspaceService
{
    private $db;
    private $actor;
    private $entity;
    private $ids = false;
    public function __construct($db, $actor, $entity) { $this->db=$db; $this->actor=$actor; $this->entity=(int)$entity; }
    public static function enabled() { return !empty($GLOBALS['conf']->global->CPO_CUSTOMER_WORKSPACES_ENABLED); }
    private function rows($sql) { $r=$this->db->query($sql); if(!$r)throw new RuntimeException('Customer workspace schema unavailable.'); $rows=array(); while($o=$this->db->fetch_object($r))$rows[]=(array)$o; return $rows; }
    private function admin() { return !empty($this->actor->admin) && empty($this->actor->socid) && empty($this->actor->fk_soc); }
    private function internal() { return !empty($this->actor->id) && empty($this->actor->socid) && empty($this->actor->fk_soc) && (!isset($this->actor->statut)||(int)$this->actor->statut===1) && (!isset($this->actor->entity)||(int)$this->actor->entity===$this->entity||($this->admin()&&(int)$this->actor->entity===0)); }
    private function right(...$args) { return $this->internal() && ($this->admin() || $this->actor->hasRight(...$args)); }
    public function canCreate() { return $this->internal() && $this->admin(); }
    public function customerSql($alias='s') {
        if(!$this->right('societe','lire'))return '1=0';
        $sql=$alias.'.entity='.$this->entity;
        if(!$this->right('societe','client','voir'))$sql.=' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_commerciaux ws_sc WHERE ws_sc.fk_soc='.$alias.'.rowid AND ws_sc.fk_user='.(int)$this->actor->id.')';
        return $sql;
    }
    public function projectSql($alias='p') {
        if(!$this->right('projet','lire')&&!$this->right('projet','all','lire'))return '1=0';
        $sql=$alias.'.entity='.$this->entity;
        if(!$this->right('projet','all','lire')) {
            if($this->ids===false){require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';$p=new Project($this->db);$this->ids=array_map('intval',array_keys($p->getProjectsAuthorizedForUser($this->actor,0,0)));}
            $sql.=' AND '.$alias.'.rowid IN ('.($this->ids?implode(',',$this->ids):'0').')';
        }
        return $sql.' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe ws_s WHERE ws_s.rowid='.$alias.'.fk_soc AND '.$this->customerSql('ws_s').')';
    }
    public function project($id, $customer=0, $requireEnabled=false) {
        if(!self::enabled())throw new InternalTaskAccessException('Customer workspaces are unavailable.');
        $where='p.rowid='.(int)$id.' AND '.$this->projectSql('p');
        if($customer)$where.=' AND p.fk_soc='.(int)$customer;
        if($requireEnabled)$where.=' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_extrafields ef WHERE ef.fk_object=p.fk_soc AND ef.cpo_board_workspace=1)';
        $r=$this->rows('SELECT p.rowid,p.ref,p.title,p.fk_soc,(SELECT ws_name.nom FROM '.MAIN_DB_PREFIX.'societe ws_name WHERE ws_name.entity=p.entity AND ws_name.rowid=p.fk_soc) customer_name FROM '.MAIN_DB_PREFIX.'projet p WHERE '.$where);
        if(!$r)throw new InternalTaskAccessException('Project access denied.');return $r[0];
    }
    public function boardProject($board) {
        if(!self::enabled())return null;
        $r=$this->rows('SELECT fk_project FROM '.MAIN_DB_PREFIX.'cpo_project_board WHERE entity='.$this->entity.' AND fk_board='.(int)$board);
        return $r?$this->project($r[0]['fk_project']):null;
    }
    public function taskScopeSql($alias) {
        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$alias))throw new InvalidArgumentException('Invalid task alias.');
        if(!self::enabled())return '1=1';
        // No broad legacy fallback for a grouped board, including direct card grants.
        return 'NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'cpo_board_card ws_bc INNER JOIN '.MAIN_DB_PREFIX.'cpo_project_board ws_pb ON ws_pb.entity=ws_bc.entity AND ws_pb.fk_board=ws_bc.fk_board WHERE ws_bc.entity='.$alias.'.entity AND ws_bc.fk_task='.$alias.'.rowid AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'projet ws_p WHERE ws_p.rowid=ws_pb.fk_project AND '.$this->projectSql('ws_p').'))';
    }
    public function taskAllowed($id) {
        if(!self::enabled())return true;
        $r=$this->rows('SELECT t.rowid FROM '.MAIN_DB_PREFIX.'cpo_task t WHERE t.entity='.$this->entity.' AND t.rowid='.(int)$id.' AND '.$this->taskScopeSql('t'));return (bool)$r;
    }
    private function boardVisibleSql($alias='b') {
        return $this->admin()?'1=1':'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'cpo_board_member ws_bm WHERE ws_bm.entity='.$alias.'.entity AND ws_bm.fk_board='.$alias.'.rowid AND ws_bm.fk_user='.(int)$this->actor->id.')';
    }
    public function customers($q='') {
        if(!self::enabled()||!$this->internal())throw new InternalTaskAccessException('Customer workspace access denied.');
        $sql='SELECT s.rowid,s.nom FROM '.MAIN_DB_PREFIX.'societe s INNER JOIN '.MAIN_DB_PREFIX.'societe_extrafields ef ON ef.fk_object=s.rowid AND ef.cpo_board_workspace=1 WHERE '.$this->customerSql('s').' AND s.client IN (1,3)';
        if($q!=='')$sql.=" AND s.nom LIKE '%".$this->db->escape(mb_substr($q,0,100))."%'";
        // Ordinary staff only discover spaces with an authorized native project.
        if(!$this->canCreate())$sql.=' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'projet p WHERE p.fk_soc=s.rowid AND '.$this->projectSql('p').')';
        $rows=$this->rows($sql.' ORDER BY s.nom,s.rowid LIMIT 200');
        foreach($rows as &$s){$totals=$this->totals($s['rowid']);$s=array_merge($s,$totals);}
        return array('customers'=>$rows,'can_create'=>$this->canCreate(),'can_create_customer'=>(bool)$this->actor->hasRight('societe','creer'));
    }
    private function openCountSql($board) {return InternalTaskBoardCounts::expression($this->entity,$board,false);}
    public function totals($socid){
        if(!self::enabled()||!$this->internal())throw new InternalTaskAccessException('Customer workspace access denied.');
        $state= !empty($GLOBALS['conf']->global->CPO_BOARD_COMPLETION_ENABLED)?'(SELECT COALESCE(MAX(completed),0) FROM '.MAIN_DB_PREFIX.'cpo_board_completion cc WHERE cc.entity=b.entity AND cc.fk_board=b.rowid)':'0';
        $from=' FROM '.MAIN_DB_PREFIX.'projet p LEFT JOIN '.MAIN_DB_PREFIX.'cpo_project_board pb ON pb.entity=p.entity AND pb.fk_project=p.rowid LEFT JOIN '.MAIN_DB_PREFIX.'cpo_board b ON b.entity=pb.entity AND b.rowid=pb.fk_board AND '.$this->boardVisibleSql('b').' AND '.InternalTaskBoardLifecycle::visibleSql('b.entity','b.rowid').' WHERE p.fk_soc='.(int)$socid.' AND '.$this->projectSql('p');
        $r=$this->rows('SELECT COUNT(*) projects,COALESCE(SUM(b.rowid IS NOT NULL),0) boards,COALESCE(SUM(CASE WHEN b.rowid IS NOT NULL THEN '.$this->openCountSql('b.rowid').' ELSE 0 END),0) open_tasks,COALESCE(SUM(CASE WHEN b.rowid IS NOT NULL THEN '.InternalTaskBoardCounts::expression($this->entity,'b.rowid',true).' ELSE 0 END),0) done_tasks,COALESCE(SUM(CASE WHEN b.rowid IS NOT NULL AND '.$state.'=1 THEN 1 ELSE 0 END),0) done_boards,COALESCE(SUM(CASE WHEN b.rowid IS NOT NULL AND '.$state.'=0 THEN 1 ELSE 0 END),0) open_boards'.$from);
        return array_map('intval',$r[0]);
    }
    public function projects($socid) {
        if(!self::enabled()||!$this->internal())throw new InternalTaskAccessException('Customer workspace access denied.');
        $s=$this->rows('SELECT s.rowid,s.nom FROM '.MAIN_DB_PREFIX.'societe s INNER JOIN '.MAIN_DB_PREFIX.'societe_extrafields ef ON ef.fk_object=s.rowid AND ef.cpo_board_workspace=1 WHERE s.rowid='.(int)$socid.' AND '.$this->customerSql('s'));
        if(!$s)throw new InternalTaskAccessException('Customer workspace access denied.');
        $ps=$this->rows('SELECT p.rowid,p.ref,p.title,p.fk_statut,b.rowid board_id,b.title board_title,b.archived board_archived,CASE WHEN b.rowid IS NULL THEN 0 ELSE '.$this->openCountSql('b.rowid').' END open_tasks FROM '.MAIN_DB_PREFIX.'projet p LEFT JOIN '.MAIN_DB_PREFIX.'cpo_project_board pb ON pb.entity=p.entity AND pb.fk_project=p.rowid LEFT JOIN '.MAIN_DB_PREFIX.'cpo_board b ON b.entity=pb.entity AND b.rowid=pb.fk_board AND '.$this->boardVisibleSql('b').' AND '.InternalTaskBoardLifecycle::visibleSql('b.entity','b.rowid').' WHERE p.fk_soc='.(int)$socid.' AND '.$this->projectSql('p').' ORDER BY p.rowid DESC LIMIT 200');
        foreach($ps as &$p){$p['board']=$p['board_id']?array('rowid'=>$p['board_id'],'title'=>$p['board_title'],'archived'=>$p['board_archived']):null;unset($p['board_id'],$p['board_title'],$p['board_archived']);}
        return array('customer'=>$s[0],'projects'=>$ps,'can_create'=>$this->canCreate(),'can_create_project'=>(bool)$this->actor->hasRight('projet','creer'));
    }
    public function enableCustomer($id) {
        if(!$this->canCreate()||!self::enabled())throw new InternalTaskAccessException('Administrator required.');
        $r=$this->rows('SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid='.(int)$id.' AND s.client IN (1,3) AND '.$this->customerSql('s'));if(!$r)throw new InternalTaskAccessException('Customer access denied.');
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';$soc=new Societe($this->db);if($soc->fetch((int)$id)<=0)throw new RuntimeException('Customer unavailable.');$soc->fetch_optionals();$soc->array_options['options_cpo_board_workspace']=1;if($soc->insertExtraFields()<0)throw new RuntimeException('Unable to enable customer workspace.');return array('id'=>(int)$id);
    }
    public function customerOptions($q='') {
        if(!$this->canCreate())throw new InternalTaskAccessException('Administrator required.');
        return $this->rows('SELECT s.rowid,s.nom FROM '.MAIN_DB_PREFIX.'societe s WHERE s.client IN (1,3) AND '.$this->customerSql('s')." AND s.nom LIKE '%".$this->db->escape(mb_substr($q,0,100))."%' ORDER BY s.nom LIMIT 100");
    }
    public function link($board,$project) {
        // Called within the existing create_board transaction; unique keys prevent duplicate boards.
        $p=$this->project($project,0,true);
        $sql='INSERT INTO '.MAIN_DB_PREFIX.'cpo_project_board(entity,fk_project,fk_board) VALUES('.$this->entity.','.(int)$p['rowid'].','.(int)$board.')';
        if(!$this->db->query($sql))throw new InternalTaskConflictException('This project already has a board. Reload the customer workspace.');
    }
    public function assertMove($from,$to) {
        $a=$this->boardProject($from);$b=$this->boardProject($to);
        if(($a?(int)$a['rowid']:0)!==($b?(int)$b['rowid']:0))throw new InternalTaskAccessException('Lists cannot move between different project scopes.');
    }
}
