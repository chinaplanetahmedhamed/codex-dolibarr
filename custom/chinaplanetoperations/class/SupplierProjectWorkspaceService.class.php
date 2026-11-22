<?php
require_once __DIR__.'/InternalTaskBoardsService.class.php';

/** Internal supplier relationships over native entities; no schema or finance projections. */
class SupplierProjectWorkspaceService
{
    private $db, $actor, $entity, $scope, $auth;
    public function __construct($db,$actor,int $entity){$this->db=$db;$this->actor=$actor;$this->entity=$entity;$this->scope=new InternalTaskWorkspaceService($db,$actor,$entity);$this->auth=new InternalTaskAuthorizationService($db,$actor,$entity);}
    private function rows(string $sql):array{$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Supplier links unavailable.');$out=[];while($o=$this->db->fetch_object($r))$out[]=(array)$o;return $out;}
    private function query(string $sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Unable to save supplier links.');return $r;}
    private function rollbackCommand():void{
        // Entry points require depth zero; native services may throw before unwinding their own begin().
        while(!empty($this->db->transaction_opened)){
            $depth=(int)$this->db->transaction_opened;
            if(!$this->db->rollback()||(int)$this->db->transaction_opened>=$depth)break;
        }
    }
    private function q($s):string{return "'".$this->db->escape((string)$s)."'";}
    private function browse():void{if(!(new SupplierWorkspaceService($this->db,$this->actor,$this->entity))->canBrowse())throw new InternalTaskAccessException('Supplier access denied.');}
    public function project(int $id):array{$this->browse();return $this->scope->project($id);}
    private function supplier(int $id,bool $lock=false):array{
        $this->browse();$r=$this->rows('SELECT s.rowid,s.nom FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid='.$id.' AND s.fournisseur=1 AND '.$this->scope->customerSql('s').($lock?' FOR UPDATE':''));
        if(!$r)throw new InternalTaskAccessException('Supplier access denied.');return $r[0];
    }
    private function card(int $id,bool $write=false):array{
        $this->browse();$p=MAIN_DB_PREFIX;
        $r=$this->rows("SELECT t.*,bc.fk_board,pb.fk_project FROM {$p}cpo_task t JOIN {$p}cpo_board_card bc ON bc.entity=t.entity AND bc.fk_task=t.rowid JOIN {$p}cpo_project_board pb ON pb.entity=bc.entity AND pb.fk_board=bc.fk_board WHERE t.entity=".$this->entity." AND t.rowid=$id");
        if(count($r)!==1||!$this->auth->canReadTask($r[0])||($write&&!$this->auth->canEditTask($r[0])))throw new InternalTaskAccessException('Customer project card access denied.');
        (new InternalTaskBoardsService($this->db,$this->actor,$this->entity))->board((int)$r[0]['fk_board'],$write?'editor':'viewer');$this->project((int)$r[0]['fk_project']);
        if($write&&!empty($r[0]['date_archived']))throw new InternalTaskAccessException('Reopen the card first.');return $r[0];
    }
    private function relations(string $type,int $id,string $target,string $tag):array{return $this->rows('SELECT rowid,fk_target,relationtype FROM '.MAIN_DB_PREFIX.'element_element WHERE sourcetype='.$this->q($type).' AND fk_source='.$id.' AND targettype='.$this->q($target).' AND relationtype='.$this->q($tag).' ORDER BY fk_target');}
    private function link(string $type,int $id,string $target,int $to,string $tag):void{
        $p=MAIN_DB_PREFIX;$r=$this->rows("SELECT rowid,relationtype FROM {$p}element_element WHERE sourcetype=".$this->q($type)." AND fk_source=$id AND targettype=".$this->q($target)." AND fk_target=$to");
        if($r){if((string)$r[0]['relationtype']!==$tag)throw new InternalTaskConflictException('This native relationship is managed elsewhere.');return;}
        $this->query("INSERT INTO {$p}element_element(fk_source,sourcetype,fk_target,targettype,relationtype) VALUES ($id,".$this->q($type).",$to,".$this->q($target).','.$this->q($tag).')');
    }
    public static function boardMarkerSql(string $entity,string $board):string{
        foreach([$entity,$board] as $v)if(!preg_match('/^(?:[0-9]+|[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*)$/D',$v))throw new InvalidArgumentException('Invalid board scope.');
        return 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX."element_element sp_mark WHERE sp_mark.sourcetype='cpo_board' AND sp_mark.fk_source=$board AND sp_mark.relationtype='cpo_supplier_project')";
    }
    /** Raw marker detection is separate from authorization, so denied contexts never become General Boards. */
    public function boardContext(int $board):?array{
        $p=MAIN_DB_PREFIX;$links=$this->rows("SELECT fk_target,targettype FROM {$p}element_element WHERE sourcetype='cpo_board' AND fk_source=$board AND relationtype='cpo_supplier_project'");if(!$links)return null;
        $this->browse();$projects=array_values(array_filter($links,fn($r)=>$r['targettype']==='project'));$suppliers=array_values(array_filter($links,fn($r)=>$r['targettype']==='societe'));
        if(count($links)!==2||count($projects)!==1||count($suppliers)!==1)throw new InternalTaskAccessException('Supplier board association needs review.');
        $b=$this->rows("SELECT rowid FROM {$p}cpo_board WHERE entity=".$this->entity." AND rowid=$board");if(!$b)throw new InternalTaskAccessException('Supplier board access denied.');
        $project=$this->project((int)$projects[0]['fk_target']);$supplier=$this->supplier((int)$suppliers[0]['fk_target']);
        return ['kind'=>'project','rowid'=>(int)$project['rowid'],'ref'=>$project['ref'],'project_id'=>(int)$project['rowid'],'project_title'=>$project['title'],'customer_id'=>(int)$project['fk_soc'],'supplier_id'=>(int)$supplier['rowid'],'supplier_name'=>$supplier['nom'],'workspace_enabled'=>1];
    }
    private function findBoard(int $project,int $supplier,bool $requireAvailable=true):?int{
        $p=MAIN_DB_PREFIX;$rows=$this->rows("SELECT b.rowid FROM {$p}cpo_board b JOIN {$p}element_element ep ON ep.sourcetype='cpo_board' AND ep.fk_source=b.rowid AND ep.targettype='project' AND ep.fk_target=$project AND ep.relationtype='cpo_supplier_project' JOIN {$p}element_element es ON es.sourcetype='cpo_board' AND es.fk_source=b.rowid AND es.targettype='societe' AND es.fk_target=$supplier AND es.relationtype='cpo_supplier_project' WHERE b.entity=".$this->entity);
        if(count($rows)>1)throw new InternalTaskConflictException('Duplicate supplier project boards need review.');if(!$rows)return null;$id=(int)$rows[0]['rowid'];$this->boardContext($id);if($requireAvailable&&(new InternalTaskBoardLifecycle($this->db,$this->actor,$this->entity))->deleted($id))throw new InternalTaskConflictException('Restore the deleted supplier project Board before linking this supplier again.');return $id;
    }
    public function forProject(int $project):array{
        $this->project($project);$out=[];foreach($this->relations('project',$project,'societe','cpo_supplier') as $rel){$supplier=$this->supplier((int)$rel['fk_target']);$board=$this->findBoard($project,(int)$supplier['rowid'],false);
            if($board&&!$this->auth->isAdministrator()&&!(new InternalTaskBoardPolicy($this->db,$this->actor->id,$this->entity))->role($board))$board=null;
            $deleted=$board&&(new InternalTaskBoardLifecycle($this->db,$this->actor,$this->entity))->deleted($board);$out[]=$supplier+['board_id'=>$deleted?null:$board,'board_deleted'=>(bool)$deleted];}return $out;
    }
    public function forCard(int $task):array{
        $card=$this->card($task);$out=[];foreach($this->relations('cpo_task',$task,'societe','cpo_supplier') as $rel){$s=$this->supplier((int)$rel['fk_target']);$board=$this->findBoard((int)$card['fk_project'],(int)$s['rowid'],false);
            if($board&&!$this->auth->isAdministrator()&&!(new InternalTaskBoardPolicy($this->db,$this->actor->id,$this->entity))->role($board))$board=null;
            $deleted=$board&&(new InternalTaskBoardLifecycle($this->db,$this->actor,$this->entity))->deleted($board);$out[]=$s+['board_id'=>$deleted?null:$board,'board_deleted'=>(bool)$deleted];}
        return ['project_id'=>(int)$card['fk_project'],'version'=>(int)$card['version'],'suppliers'=>$out,'can_edit'=>$this->auth->isAdministrator()&&$this->auth->canEditTask($card)&&empty($card['date_archived'])];
    }
    public function options(int $project,string $query,int $page=1):array{
        $this->project($project);$query=trim(mb_substr($query,0,100));$offset=(max(1,min(10000,$page))-1)*50;$like=$this->q('%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%');
        return $this->rows('SELECT s.rowid,s.nom FROM '.MAIN_DB_PREFIX.'societe s WHERE s.fournisseur=1 AND '.$this->scope->customerSql('s')." AND s.nom LIKE $like ESCAPE '!' ORDER BY s.nom,s.rowid LIMIT 51 OFFSET $offset");
    }
    public function linkBoard(int $board,int $project,int $supplier):void{
        if(empty($this->db->transaction_opened))throw new RuntimeException('Board mapping requires a transaction.');
        $this->project($project);$this->supplier($supplier);$this->link('cpo_board',$board,'project',$project,'cpo_supplier_project');$this->link('cpo_board',$board,'societe',$supplier,'cpo_supplier_project');
    }
    public function lockBoardScope(int $project,int $supplier):array{
        if(!$this->auth->isAdministrator())throw new InternalTaskAccessException('Administrator required to create supplier boards.');
        $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.$this->entity.' AND rowid='.$project.' FOR UPDATE');$p=$this->project($project);$s=$this->supplier($supplier,true);
        if(!$this->relations('project',$project,'societe','cpo_supplier'))throw new InternalTaskAccessException('Link the supplier to the project first.');
        $linked=false;foreach($this->relations('project',$project,'societe','cpo_supplier') as $r)if((int)$r['fk_target']===$supplier)$linked=true;if(!$linked)throw new InternalTaskAccessException('Supplier is not linked to this project.');
        if($this->findBoard($project,$supplier))throw new InternalTaskConflictException('Supplier project board already exists.');return ['project'=>$p,'supplier'=>$s];
    }
    protected function createBoard(array $project,array $supplier,int $team):int{
        $r=(new InternalTaskBoardsService($this->db,$this->actor,$this->entity))->mutate('create_board',['title'=>mb_substr(preg_replace('/[<>\x00-\x1f]/u',' ',$supplier['nom'].' · '.$project['ref']),0,255),'team'=>$team,'background'=>'#182b4e','supplier_project'=>(int)$project['rowid'],'supplier'=>(int)$supplier['rowid'],'request'=>bin2hex(random_bytes(24))]);return (int)$r['id'];
    }
    private function audit(int $task,string $message):void{$this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity(entity,fk_task,fk_user,action_code,message,date_action) VALUES('.$this->entity.','.($task?:'NULL').','.(int)$this->actor->id.",'supplier_links_updated',".$this->q($message).','.$this->q(date('Y-m-d H:i:s')).')');}
    public function change(int $project,int $task,int $supplier,bool $add,int $version):array{
        if(!$this->auth->isAdministrator())throw new InternalTaskAccessException('Administrator required for supplier linking and Board creation.');$this->project($project);
        if(!empty($this->db->transaction_opened))throw new RuntimeException('Supplier linking requires a top-level command.');if(!$this->db->begin())throw new RuntimeException('Unable to start supplier linking.');
        try{
            $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.$this->entity.' AND rowid='.$project.' FOR UPDATE');$supplierRecord=$this->supplier($supplier,true);
            $p=MAIN_DB_PREFIX;$card=$task?$this->card($task,true):null;if($card&&(int)$card['fk_project']!==$project)throw new InternalTaskConflictException('The card project changed.');
            $boards=$this->rows("SELECT b.* FROM {$p}cpo_board b JOIN {$p}cpo_project_board pb ON pb.entity=b.entity AND pb.fk_board=b.rowid WHERE b.entity=".$this->entity." AND pb.fk_project=$project FOR UPDATE");if(count($boards)!==1||!empty($boards[0]['archived']))throw new InternalTaskAccessException('Open the customer project Board first.');$board=$boards[0];
            (new InternalTaskBoardsService($this->db,$this->actor,$this->entity))->board((int)$board['rowid'],'manager');
            if($task)$this->query("SELECT rowid FROM {$p}cpo_task WHERE entity=".$this->entity." AND rowid=$task FOR UPDATE");
            $current=$task?(int)$this->card($task,true)['version']:(int)$board['version'];if($current!==$version)throw new InternalTaskConflictException('Supplier links changed. Reload and retry.');
            $type=$task?'cpo_task':'project';$id=$task?:$project;
            if($add){
                $this->link('project',$project,'societe',$supplier,'cpo_supplier');if($task)$this->link('cpo_task',$task,'societe',$supplier,'cpo_supplier');
                if(!$this->rows("SELECT fk_supplier FROM {$p}cpo_supplier_workspace WHERE entity=".$this->entity." AND fk_supplier=$supplier"))$this->query("INSERT INTO {$p}cpo_supplier_workspace(entity,fk_supplier) VALUES (".$this->entity.",$supplier)");
                $this->findBoard($project,$supplier)??$this->createBoard($this->project($project),$this->supplier($supplier),(int)$board['fk_usergroup']);
            }else{
                if(!$task){$refs=$this->linkedCards($project,$supplier);if($refs)throw new InternalTaskConflictException('Remove the supplier from project cards first.');}
                $this->query("DELETE FROM {$p}element_element WHERE sourcetype=".$this->q($type)." AND fk_source=$id AND targettype='societe' AND fk_target=$supplier AND relationtype='cpo_supplier'");
            }
            if($task)$this->query("UPDATE {$p}cpo_task SET version=version+1 WHERE entity=".$this->entity." AND rowid=$task AND version=$version");
            $this->query("UPDATE {$p}cpo_board SET version=version+1 WHERE entity=".$this->entity.' AND rowid='.(int)$board['rowid']);$this->audit($task,($add?'Linked supplier ':'Unlinked supplier ').$supplierRecord['nom'].'; project '.$this->project($project)['ref']);
            $result=$task?$this->forCard($task):$this->projectPanel($project);
            if(!$this->db->commit())throw new RuntimeException('Unable to commit supplier linking.');return $result;
        }catch(Throwable $e){$this->rollbackCommand();throw $e;}
    }
    public function linkedCards(int $project,int $supplier):array{
        $this->project($project);$this->supplier($supplier);$p=MAIN_DB_PREFIX;$rows=$this->rows("SELECT t.* FROM {$p}cpo_task t JOIN {$p}cpo_board_card bc ON bc.entity=t.entity AND bc.fk_task=t.rowid JOIN {$p}cpo_project_board pb ON pb.entity=bc.entity AND pb.fk_board=bc.fk_board JOIN {$p}element_element ee ON ee.sourcetype='cpo_task' AND ee.fk_source=t.rowid AND ee.targettype='societe' AND ee.fk_target=$supplier AND ee.relationtype='cpo_supplier' WHERE t.entity=".$this->entity." AND pb.fk_project=$project ORDER BY t.rowid");
        $out=[];foreach($rows as $r)if($this->auth->canReadTask($r))$out[]=['rowid'=>(int)$r['rowid'],'title'=>$r['title'],'ref'=>$r['ref']];return $out;
    }
    public function supplierProjects(int $supplier):array{
        $this->supplier($supplier);$p=MAIN_DB_PREFIX;$rows=$this->rows("SELECT p.rowid,p.ref,p.title FROM {$p}projet p JOIN {$p}element_element ep ON ep.targettype='project' AND ep.fk_target=p.rowid AND ep.sourcetype='cpo_board' AND ep.relationtype='cpo_supplier_project' JOIN {$p}cpo_board b ON b.rowid=ep.fk_source AND b.entity=p.entity JOIN {$p}element_element es ON es.sourcetype='cpo_board' AND es.fk_source=b.rowid AND es.targettype='societe' AND es.fk_target=$supplier AND es.relationtype='cpo_supplier_project' WHERE ".$this->scope->projectSql('p').' ORDER BY p.rowid DESC');
        $out=[];foreach($rows as $r){$board=$this->findBoard((int)$r['rowid'],$supplier,false);if(!$board)continue;try{(new InternalTaskBoardsService($this->db,$this->actor,$this->entity))->board($board);}catch(InternalTaskAccessException $e){continue;}$r['board_id']=$board;$out[]=$r;}return $out;
    }

    public function projectPanel(int $project):array
    {
        $this->project($project);
        $rows=$this->rows('SELECT b.rowid FROM '.MAIN_DB_PREFIX.'cpo_board b JOIN '.MAIN_DB_PREFIX.'cpo_project_board pb ON pb.entity=b.entity AND pb.fk_board=b.rowid WHERE b.entity='.$this->entity.' AND pb.fk_project='.$project);
        if(count($rows)!==1)throw new InternalTaskAccessException('Customer project Board access denied.');
        $board=(new InternalTaskBoardsService($this->db,$this->actor,$this->entity))->board((int)$rows[0]['rowid']);
        return ['project_id'=>$project,'version'=>(int)$board['version'],'suppliers'=>$this->forProject($project),'can_edit'=>$this->auth->isAdministrator()&&empty($board['archived'])];
    }

    private function orderCreator():bool
    {
        return $this->auth->isDolibarrAdministrator()||$this->actor->hasRight('fournisseur','commande','creer')||$this->actor->hasRight('supplier_order','creer');
    }

    public function boardPanel(int $id):array
    {
        $board=(new InternalTaskBoardsService($this->db,$this->actor,$this->entity))->board($id);
        $ctx=$this->boardContext($id);
        if(!$ctx)throw new InternalTaskAccessException('Supplier project Board required.');
        $orders=$this->rows('SELECT rowid,ref,fk_statut FROM '.MAIN_DB_PREFIX.'commande_fournisseur WHERE entity='.$this->entity.' AND fk_soc='.(int)$ctx['supplier_id'].' AND fk_projet='.(int)$ctx['project_id'].' ORDER BY rowid DESC');
        return ['context'=>$ctx,'cards'=>$this->linkedCards($ctx['project_id'],$ctx['supplier_id']),'orders'=>$orders,'can_create_order'=>$this->orderCreator()&&in_array($board['role'],['manager','editor'],true)&&!empty($board['can_edit'])&&empty($board['archived'])];
    }

    /** Native create owns numbering, triggers and totals. Never validate or create finance records here. */
    protected function createNativeDraft(int $project,int $supplier):int
    {
        require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
        $extra=new ExtraFields($this->db);
        if($extra->fetch_name_optionals_label('commande_fournisseur')<0)throw new RuntimeException('Order fields unavailable.');
        // Mandatory custom inputs need the native form; never silently invent their values.
        foreach(($extra->attributes['commande_fournisseur']['required']??[]) as $required){
            if($required)throw new InternalTaskConflictException('This installation requires additional order fields. Complete a supplier order in the native purchase order form.');
        }
        global $conf;
        $order=new CommandeFournisseur($this->db);
        $order->entity=$this->entity;$order->socid=$supplier;$order->fk_project=$project;
        $order->date=function_exists('dol_now')?dol_now():time();$order->date_commande=$order->date;
        $order->multicurrency_code=$conf->currency;$order->lines=[];
        $id=$order->create($this->actor);
        if($id<=0)throw new RuntimeException('Unable to create native draft order.');
        return (int)$id;
    }

    public function createDraft(int $board,string $request):array
    {
        if(!preg_match('/^[a-f0-9]{48}$/D',$request))throw new InvalidArgumentException('Draft request identity required.');
        $state=$this->boardPanel($board);
        if(!$state['can_create_order'])throw new InternalTaskAccessException('Draft order creation denied.');
        if(!empty($this->db->transaction_opened))throw new RuntimeException('Draft creation requires a top-level command.');
        $ctx=$state['context'];$project=(int)$ctx['project_id'];$supplier=(int)$ctx['supplier_id'];$p=MAIN_DB_PREFIX;
        $tag='cpo_draft_'.substr(hash('sha256',$this->entity.':'.$board.':'.$request),0,48);
        if(!$this->db->begin())throw new RuntimeException('Unable to start draft creation.');
        try{
            $this->query("SELECT rowid FROM {$p}projet WHERE entity=".$this->entity." AND rowid=$project FOR UPDATE");
            $current=$this->boardPanel($board);
            if(!$current['can_create_order']||$current['context']!==$ctx)throw new InternalTaskAccessException('Supplier Board context changed.');
            $this->supplier($supplier,true);
            $saved=$this->relations('project',$project,'order_supplier',$tag);
            if(count($saved)>1)throw new InternalTaskConflictException('Draft request needs review.');
            $id=$saved?(int)$saved[0]['fk_target']:$this->createNativeDraft($project,$supplier);
            $orders=$this->rows("SELECT rowid,ref,fk_statut FROM {$p}commande_fournisseur WHERE entity=".$this->entity." AND rowid=$id AND fk_soc=$supplier AND fk_projet=$project");
            if(count($orders)!==1||(!$saved&&(int)$orders[0]['fk_statut']!==0))throw new RuntimeException('Native draft context verification failed.');
            if(!$saved){$this->link('project',$project,'order_supplier',$id,$tag);$this->audit(0,'Created draft supplier order '.$id.'; project '.$project.'; supplier '.$supplier);}
            if(!$this->db->commit())throw new RuntimeException('Unable to commit draft creation.');
            return ['id'=>$id,'ref'=>$orders[0]['ref'],'reused'=>(bool)$saved];
        }catch(Throwable $e){$this->rollbackCommand();throw $e;}
    }
}
