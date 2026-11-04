<?php
require_once __DIR__.'/InternalTaskService.class.php';
require_once __DIR__.'/../../chinaplanetlogistics/class/ShipmentDocumentService.class.php';
require_once __DIR__.'/../../chinaplanetlogistics/class/ShipmentContextService.class.php';
/** Task-owned references; reading a card never grants access to ERP objects or documents. */
class InternalTaskContextService
{
    private $db; private $actor; private $entity; private $tasks; private $auth; private $docs;
    public function __construct($db,$actor,$entity=null){global $conf;$this->db=$db;$this->actor=$actor;$this->entity=$entity===null?(int)$conf->entity:(int)$entity;$this->tasks=new InternalTaskService($db,$actor,$this->entity);$this->auth=$this->tasks->authorization();$this->docs=new ShipmentDocumentService($db,$actor,$this->entity);}
    private $scopes=[]; private $documentQuarantined=false;
    /** Dolibarr nesting is a counter, not a savepoint. Protect callers' outer transactions explicitly. */
    private function beginScope($document=false){
        $depth=(int)$this->db->transaction_opened;$name='m099_'.bin2hex(random_bytes(8));
        if(!$this->db->begin())throw new RuntimeException('Transaction unavailable.');
        if($depth>0&&!$this->db->query('SAVEPOINT '.$name)){if(!$document)$this->db->rollback();throw new RuntimeException('Transaction savepoint unavailable.');}
        $this->scopes[]=['depth'=>$depth,'name'=>$name];
    }
    private function commitScope(){
        $scope=end($this->scopes);if(!$scope)throw new RuntimeException('Transaction scope missing.');
        if($scope['depth']>0&&!$this->db->query('RELEASE SAVEPOINT '.$scope['name']))throw new RuntimeException('Transaction release failed.');
        if(!$this->db->commit())throw new RuntimeException('Transaction commit failed.');array_pop($this->scopes);
    }
    private function rollbackScope(){
        $scope=array_pop($this->scopes);if(!$scope)return;
        if($scope['depth']>0){if(!$this->db->query('ROLLBACK TO SAVEPOINT '.$scope['name']))throw new RuntimeException('Transaction recovery requires operator attention.');$this->db->query('RELEASE SAVEPOINT '.$scope['name']);}
        $this->db->rollback();
    }
    private function q($s){return "'".$this->db->escape((string)$s)."'";}
    private function query($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Task context operation failed.');return $r;}
    private function rows($sql){$r=$this->query($sql);$out=[];while($o=$this->db->fetch_object($r))$out[]=(array)$o;return $out;}
    private function one($sql){$r=$this->rows($sql);return $r?$r[0]:null;}
    private function task($id,$write=false){if($this->documentQuarantined)throw new RuntimeException('Document transaction requires reconciliation.');if(empty($GLOBALS['conf']->global->CPO_SHIPMENT_CARDS_ENABLED))throw new ShipmentDocumentAccessException('Operation cards are unavailable.');$this->docs->ensureReady();$t=$this->tasks->requireReadableTask($id);if($write&&!$this->auth->canEditTask($t))throw new InternalTaskAccessException('Task access denied.');return $t;}
    private $projectShipments=[];
    private function shipmentDefaults($customer,$project){
        $key=(int)$customer.':'.(int)$project;if(isset($this->projectShipments[$key]))return $this->projectShipments[$key];
        $out=['automatic'=>0,'choiceRequired'=>false];$rows=$this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.$this->entity.' AND fk_soc='.(int)$customer.' AND fk_project='.(int)$project.' ORDER BY rowid LIMIT 2');$visible=[];
        foreach($rows as $row)try{$this->shipment($row['rowid']);$visible[]=(int)$row['rowid'];}catch(ShipmentDocumentAccessException|InternalTaskAccessException $e){}
        // One visible shipment among several is still ambiguous: never guess or
        // reveal the identities/count of records the actor cannot read.
        if(count($rows)===1&&count($visible)===1)$out['automatic']=$visible[0];
        $out['choiceRequired']=count($visible)>1;return $this->projectShipments[$key]=$out;
    }
    public function inheritBoardContext($taskId,array $scope){
        $task=$this->task($taskId,true);if($this->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task_context WHERE entity='.$this->entity.' AND fk_task='.(int)$taskId))return;
        $this->project($scope['rowid'],$scope['fk_soc']);$defaults=$this->shipmentDefaults($scope['fk_soc'],$scope['rowid']);
        $this->save($taskId,0,(int)$scope['fk_soc'],(int)$scope['rowid'],$defaults['automatic']);
    }
    private function rawContext($task){$c=$this->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_task_context WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid']);if($c)return $c;
        $c=['version'=>0,'fk_soc'=>0,'fk_project'=>0,'fk_shipment'=>0,'fk_cover_document'=>0];
        if(!empty($task['fk_operation'])){$o=$this->one('SELECT fk_soc,fk_project,fk_shipment FROM '.MAIN_DB_PREFIX.'cpo_operation WHERE entity='.$this->entity.' AND rowid='.(int)$task['fk_operation']);if($o)$c=array_merge($c,$o);}
        if(empty($c['fk_soc'])&&empty($c['fk_project'])&&!empty($GLOBALS['conf']->global->CPO_PROJECT_TASK_BRIDGE_ENABLED)){
            $link=$this->one('SELECT fk_board FROM '.MAIN_DB_PREFIX.'cpo_board_card WHERE entity='.$this->entity.' AND fk_task='.(int)$task['rowid']);
            $scope=$link?(new InternalTaskWorkspaceService($this->db,$this->actor,$this->entity))->boardProject($link['fk_board']):null;
            if($scope){$defaults=$this->shipmentDefaults($scope['fk_soc'],$scope['rowid']);$c['fk_soc']=(int)$scope['fk_soc'];$c['fk_project']=(int)$scope['rowid'];$c['fk_shipment']=$defaults['automatic'];$c['inherited_from_board']=true;}
        }return $c;}
    private function customer($id){
        if(!$this->auth->isInternalActive()||(!$this->auth->isDolibarrAdministrator()&&!$this->actor->hasRight('societe','lire')))throw new InternalTaskAccessException('Customer access denied.');
        $sql='SELECT s.rowid,s.nom FROM '.MAIN_DB_PREFIX.'societe s WHERE s.entity='.$this->entity.' AND s.rowid='.(int)$id.'';
        if(!$this->auth->isDolibarrAdministrator()&&!$this->actor->hasRight('societe','client','voir'))$sql.=' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_commerciaux sc WHERE sc.fk_soc=s.rowid AND sc.fk_user='.(int)$this->actor->id.')';
        $c=$this->one($sql);if(!$c)throw new InternalTaskAccessException('Customer access denied.');return ['id'=>(int)$c['rowid'],'label'=>$c['nom']];
    }
    private function projectIds(){
        if(!$this->auth->isInternalActive()||(!$this->auth->isDolibarrAdministrator()&&!$this->actor->hasRight('projet','lire')&&!$this->actor->hasRight('projet','all','lire')))throw new InternalTaskAccessException('Project access denied.');
        if($this->auth->isDolibarrAdministrator()||$this->actor->hasRight('projet','all','lire'))return null;
        require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';$p=new Project($this->db);return array_map('intval',array_keys($p->getProjectsAuthorizedForUser($this->actor,0,0)));
    }
    private function project($id,$customer=0){$ids=$this->projectIds();if($ids!==null&&!in_array((int)$id,$ids,true))throw new InternalTaskAccessException('Project access denied.');
        $p=$this->one('SELECT rowid,ref,title,fk_soc FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.$this->entity.' AND rowid='.(int)$id);if(!$p||($customer&&(int)$p['fk_soc']!==(int)$customer))throw new InternalTaskAccessException('Project access denied.');
        if(!empty($p['fk_soc']))$this->customer($p['fk_soc']);return ['id'=>(int)$p['rowid'],'label'=>$p['ref'].' · '.$p['title'],'title'=>$p['title'],'ref'=>$p['ref'],'customerId'=>(int)$p['fk_soc']];}
    private function shipment($id){$s=$this->docs->requireShipment($id);$this->customer($s['fk_soc']);if(!empty($s['fk_project']))$this->project($s['fk_project'],$s['fk_soc']);$customer=$this->customer($s['fk_soc']);$project=!empty($s['fk_project'])?$this->project($s['fk_project'],$s['fk_soc']):null;return ['id'=>(int)$s['rowid'],'label'=>$s['ref'],'customerId'=>(int)$s['fk_soc'],'projectId'=>(int)$s['fk_project'],'customerLabel'=>$customer['label'],'projectLabel'=>$project?$project['label']:''];}
    /** Only called after task and native reference authorization; IDs are navigation hints. */
    private function documentsWorkspace(array $context)
    {
        $file=__DIR__.'/../../chinaplanetdocuments/class/DocumentsWorkspace.class.php';
        if (!empty($context['restricted']) || empty($context['customer']['id'])
            || !empty($this->actor->socid) || !empty($this->actor->fk_soc)
            || !isModEnabled('chinaplanetdocuments') || !is_file($file)) return null;
        require_once $file;
        if (!DocumentsWorkspace::canRead($this->actor)) return null;
        $params=['socid'=>(int)$context['customer']['id']];
        if (!empty($context['project']['id'])) $params['projectid']=(int)$context['project']['id'];
        if (!empty($context['shipment']['id'])) $params['shipmentid']=(int)$context['shipment']['id'];
        $params['returnto']=isset($params['shipmentid'])?'shipment':(isset($params['projectid'])?'project':'customer');
        global $langs;
        $langs->load('chinaplanetdocuments@chinaplanetdocuments');
        return ['url'=>DOL_URL_ROOT.'/custom/chinaplanetdocuments/index.php?'.http_build_query($params,'','&',PHP_QUERY_RFC3986),'label'=>$langs->trans('DocumentsWorkspace')];
    }
    public function get($id,$archived=false,$includeDocuments=true){
        $t=$this->task($id);$c=$this->rawContext($t);$context=['version'=>(int)$c['version'],'customer'=>null,'project'=>null,'shipment'=>null,'coverDocumentId'=>(int)$c['fk_cover_document'],'restricted'=>false,'inheritedFromBoard'=>!empty($c['inherited_from_board']),'shipmentChoiceRequired'=>false];
        $context['boardProjectId']=0;if(InternalTaskWorkspaceService::enabled()){$bp=$this->one('SELECT pb.fk_project FROM '.MAIN_DB_PREFIX.'cpo_board_card bc INNER JOIN '.MAIN_DB_PREFIX.'cpo_project_board pb ON pb.entity=bc.entity AND pb.fk_board=bc.fk_board WHERE bc.entity='.$this->entity.' AND bc.fk_task='.(int)$id);$context['boardProjectId']=(int)($bp['fk_project']??0);}
        try{if(!empty($c['fk_shipment'])){$s=$this->shipment($c['fk_shipment']);$context['shipment']=$s;$context['customer']=$this->customer($s['customerId']);if($s['projectId'])$context['project']=$this->project($s['projectId'],$s['customerId']);}
            else{if(!empty($c['fk_soc']))$context['customer']=$this->customer($c['fk_soc']);if(!empty($c['fk_project']))$context['project']=$this->project($c['fk_project'],$c['fk_soc']);}}
        catch(ShipmentDocumentAccessException $e){$context['restricted']=true;$context['customer']=$context['project']=$context['shipment']=null;}
        catch(InternalTaskAccessException $e){$context['restricted']=true;$context['customer']=$context['project']=$context['shipment']=null;}
        if(!$context['restricted']&&$context['customer']&&$context['project']&&!$context['shipment']&&!empty($GLOBALS['conf']->global->CPO_PROJECT_TASK_BRIDGE_ENABLED))$context['shipmentChoiceRequired']=$this->shipmentDefaults($context['customer']['id'],$context['project']['id'])['choiceRequired'];
        $read=!$context['restricted']&&!empty($context['shipment'])&&$this->auth->canUseTaskFiles($t);
        $permissions=['link'=>$this->auth->canEditTask($t),'readDocuments'=>$read,'uploadDocuments'=>$read&&$this->auth->canUseTaskFiles($t,true)&&$this->docs->can($c['fk_shipment'],'write'),'archiveDocuments'=>$read&&$this->auth->canEditTask($t)&&$this->docs->can($c['fk_shipment'],'archive')];
        // Detail-only projection: keep board summaries free of extra source queries.
        if($includeDocuments&&!$context['restricted']&&!empty($context['shipment']))$context['shipment']['tracking']=(new ShipmentContextService($this->db,$this->docs,$this->entity))->get($context['shipment']['id']);
        $out=['enabled'=>true,'context'=>$context,'permissions'=>$permissions,'documents'=>[],'events'=>[]];
        if($includeDocuments)$out['documentsWorkspace']=$this->documentsWorkspace($context);
        if($read&&$includeDocuments){foreach($this->docs->documents($c['fk_shipment'],$archived&&$permissions['archiveDocuments']) as $d)$out['documents'][]=$this->presentDocument($d,$id,$permissions);$out['events']=$this->docs->events($c['fk_shipment']);}
        return $out;
    }
    /** Safe light projection for task summaries; never queries document/version/event rows. */
    public function getReferences($id){$out=$this->get($id,false,false);unset($out['documents'],$out['events']);return $out;}
    private function presentDocument($d,$taskId,$permissions){$pubs=$this->docs->publicationState($d['rowid']);$published=$pubs?(int)$pubs[0]['version_no']:0;$url='context_api.php?action=download&task_id='.(int)$taskId.'&document_id='.(int)$d['rowid'];
        return ['id'=>(int)$d['rowid'],'revision'=>(int)$d['revision'],'version'=>(int)$d['version_no'],'versionId'=>(int)$d['version_id'],'name'=>$d['display_name'],'mime'=>$d['mime_type'],'size'=>(int)$d['file_size'],'kind'=>$d['item_kind'],'status'=>$d['status'],'sha256'=>$d['file_sha256'],'customerVisible'=>$published===(int)$d['version_no'],'publishedVersion'=>$published,'downloadUrl'=>$url,'previewUrl'=>$d['item_kind']==='image'&&$d['status']==='active'?str_replace('action=download','action=preview',$url):null,'actions'=>['download'=>$d['status']==='active','history'=>$d['status']==='active'||$permissions['archiveDocuments'],'rename'=>$d['status']==='active'&&$permissions['uploadDocuments'],'replace'=>$d['status']==='active'&&$permissions['uploadDocuments'],'archive'=>$d['status']==='active'&&$permissions['archiveDocuments'],'restore'=>$d['status']==='archived'&&$permissions['archiveDocuments']]];}
    public function save($id,$expected,$customerId,$projectId,$shipmentId,$coverOnly=false,$coverId=0){
        $t=$this->task($id,true);$this->beginScope();try{
            $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task WHERE entity='.$this->entity.' AND rowid='.(int)$id.' FOR UPDATE');$t=$this->task($id,true);$c=$this->rawContext($t);
            if((int)$expected!==(int)$c['version'])throw new InternalTaskConflictException('Card links changed. Reload and retry.');
            if($coverOnly){$customerId=(int)$c['fk_soc'];$projectId=(int)$c['fk_project'];$shipmentId=(int)$c['fk_shipment'];$this->requireDocuments($id,true);if($coverId){$d=$this->docs->document($coverId);if((int)$d['fk_shipment']!==$shipmentId||$d['item_kind']!=='image')throw new InternalTaskAccessException('Cover access denied.');}}
            else{$coverId=0;$customerId=(int)$customerId;$projectId=(int)$projectId;$shipmentId=(int)$shipmentId;
                if($shipmentId){$s=$this->shipment($shipmentId);if(($customerId&&$customerId!==$s['customerId'])||($projectId&&$projectId!==$s['projectId']))throw new RuntimeException('Shipment customer and project must match.');$customerId=$s['customerId'];$projectId=$s['projectId'];}
                if($projectId){$p=$this->project($projectId,$customerId);if(!$customerId)$customerId=$p['customerId'];}if($customerId)$this->customer($customerId);
                // Explicit card references override inherited operation links; the operation is untouched.
                if($shipmentId===(int)$c['fk_shipment'])$coverId=(int)$c['fk_cover_document'];
            }
            if(InternalTaskWorkspaceService::enabled()){
                $placements=$this->rows('SELECT fk_board FROM '.MAIN_DB_PREFIX.'cpo_board_card WHERE entity='.$this->entity.' AND fk_task='.(int)$id);
                $scope=$placements?(new InternalTaskWorkspaceService($this->db,$this->actor,$this->entity))->boardProject($placements[0]['fk_board']):null;
                if($scope){
                    if(!$projectId||!$customerId)throw new InternalTaskAccessException('A project card must keep its customer and project. Clear only its shipment, or choose another project.');
                    if((int)$scope['rowid']!==$projectId){
                        require_once __DIR__.'/InternalTaskProjectTaskService.class.php';
                        $movedBoard=(new InternalTaskProjectTaskService($this->db,$this->actor,$this->entity))->moveCard($id,$placements[0]['fk_board'],$projectId);
                    }elseif((int)$scope['fk_soc']!==$customerId)throw new InternalTaskAccessException('Customer must match the project board.');
                }
            }
            $set='fk_soc='.($customerId?:'NULL').',fk_project='.($projectId?:'NULL').',fk_shipment='.($shipmentId?:'NULL').',fk_cover_document='.($coverId?:'NULL').',fk_user_modif='.(int)$this->actor->id;
            if((int)$c['version']===0)$this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_context(entity,fk_task,fk_soc,fk_project,fk_shipment,fk_cover_document,version,fk_user_modif) VALUES('.$this->entity.','.(int)$id.','.($customerId?:'NULL').','.($projectId?:'NULL').','.($shipmentId?:'NULL').','.($coverId?:'NULL').',1,'.(int)$this->actor->id.')');
            else $this->query('UPDATE '.MAIN_DB_PREFIX.'cpo_task_context SET '.$set.',version=version+1 WHERE entity='.$this->entity.' AND fk_task='.(int)$id);
            $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity(entity,fk_task,fk_operation,action_code,message,visibility,fk_user,date_action) VALUES('.$this->entity.','.(int)$id.','.((int)$t['fk_operation']?:'NULL').",".$this->q($coverOnly?'shared_cover_changed':'task_context_changed').",'', 'internal',".(int)$this->actor->id.','.$this->q($this->db->idate(dol_now())).')');$this->commitScope();$result=$this->get($id);if(!empty($movedBoard))$result['movedBoardId']=$movedBoard;return $result;
        }catch(Throwable $e){$this->rollbackScope();throw $e;}
    }
    public function options($taskId,$type,$q='',$customerId=0,$projectId=0){
        $this->task($taskId,true);$q=trim((string)$q);if(mb_strlen($q)>100)throw new RuntimeException('Search is too long.');$like=$this->q('%'.$q.'%');$out=[];
        if($customerId)$this->customer($customerId);if($projectId)$this->project($projectId,$customerId);
        if($type==='customer'){$r=$this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity='.$this->entity.' AND status=1 AND client IN (1,3) AND nom LIKE '.$like.' ORDER BY nom LIMIT 100');foreach($r as $o)try{$out[]=$this->customer($o['rowid']);}catch(InternalTaskAccessException $e){}}
        elseif($type==='project'){$this->projectIds();$r=$this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.$this->entity.($customerId?' AND fk_soc='.(int)$customerId:'').' AND (ref LIKE '.$like.' OR title LIKE '.$like.') ORDER BY rowid DESC LIMIT 100');foreach($r as $o)try{$out[]=$this->project($o['rowid'],$customerId);}catch(InternalTaskAccessException $e){}}
        elseif($type==='shipment'){$r=$this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.$this->entity.($customerId?' AND fk_soc='.(int)$customerId:'').($projectId?' AND fk_project='.(int)$projectId:'').' AND (ref LIKE '.$like.' OR container_no LIKE '.$like.' OR bl_no LIKE '.$like.') ORDER BY rowid DESC LIMIT 100');foreach($r as $o)try{$out[]=$this->shipment($o['rowid']);}catch(ShipmentDocumentAccessException $e){}catch(InternalTaskAccessException $e){}}
        else throw new RuntimeException('Choose customer, project or shipment.');return $out;
    }
    private function requireDocuments($id,$write=false,$archive=false){$t=$this->task($id,$write||$archive);if(!$this->auth->canUseTaskFiles($t,$write))throw new InternalTaskAccessException('Task file access denied.');$c=$this->rawContext($t);if(empty($c['fk_shipment']))throw new InternalTaskAccessException('Link a shipment first.');$this->shipment($c['fk_shipment']);$this->docs->requireShipment($c['fk_shipment'],$archive?'archive':($write?'write':'read'));return $c;}
    private function ownedDocument($taskId,$documentId,$write=false,$archive=false,$history=false){$c=$this->requireDocuments($taskId,$write,$archive);$allowArchived=$archive||($history&&$this->auth->canEditTask($this->tasks->requireReadableTask($taskId))&&$this->docs->can($c['fk_shipment'],'archive'));$d=$this->docs->document($documentId,$archive?'archive':($write?'write':'read'),$allowArchived);if((int)$d['fk_shipment']!==(int)$c['fk_shipment'])throw new InternalTaskAccessException('Task document access denied.');return $d;}
    /** Serialize context mutations and document commands on the task row, then recheck access. */
    private function documentMutation($taskId,$command){
        if($this->documentQuarantined)throw new RuntimeException('Document transaction requires reconciliation.');
        $this->task($taskId,true);$startingDepth=(int)$this->db->transaction_opened;$commitAttempted=false;$startupConfirmed=false;
        try{$this->beginScope(true);$startupConfirmed=true;
            $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task WHERE entity='.$this->entity.' AND rowid='.(int)$taskId.' FOR UPDATE');
            $result=$command();$commitAttempted=true;$this->commitScope();return $result;
        }catch(Throwable $e){
            if(!$startupConfirmed||($commitAttempted&&is_array($result??null)&&isset($result['storageKey']))){
                $this->documentQuarantined=true;$retired=false;
                if($startingDepth===0){try{$retired=(bool)$this->db->close();}catch(Throwable $ignored){}
                    if(property_exists($this->db,'db'))$this->db->db=null;
                    if(property_exists($this->db,'connected'))$this->db->connected=false;
                }
                // Nested document success was provisional. An uncertain outer
                // commit must not become an ordinary retry response or cleanup.
                throw new ShipmentDocumentRecoveryException(['documentId'=>(int)($result['id']??0),'versionId'=>(int)($result['versionId']??0),'storageKey'=>$result['storageKey']??null,'outcome'=>'unknown','callerTransaction'=>$startingDepth>0,'connectionRetired'=>$retired],$e);
            }
            try{$this->rollbackScope();}catch(Throwable $recovery){$this->documentQuarantined=true;if($e instanceof ShipmentDocumentRecoveryException)throw $e;throw $recovery;}
            if($e instanceof ShipmentDocumentRecoveryException)$this->documentQuarantined=true;
            throw $e;
        }
    }
    public function upload($taskId,$file,$replace=0,$revision=0){
        $outerDepth=(int)$this->db->transaction_opened;
        $r=$this->documentMutation($taskId,function()use($taskId,$file,$replace,$revision){$c=$this->requireDocuments($taskId,true);if($replace)$this->ownedDocument($taskId,$replace,true);return $this->docs->upload($c['fk_shipment'],$file,$replace,$revision);});
        try{$out=$this->get($taskId);}catch(Throwable $e){
            $this->documentQuarantined=true;
            // The write was acknowledged; a failed display refresh must never
            // invite another upload or turn durable bytes into rollback cleanup.
            throw new ShipmentDocumentRecoveryException(['documentId'=>(int)$r['id'],'versionId'=>(int)$r['versionId'],'storageKey'=>$r['storageKey'],'outcome'=>$outerDepth>0?'unknown':'committed','callerTransaction'=>$outerDepth>0,'connectionRetired'=>false],$e);
        }
        $out['uploaded']=$r;unset($out['uploaded']['file'],$out['uploaded']['storageKey']);return $out;
    }
    public function rename($taskId,$documentId,$revision,$name){$this->documentMutation($taskId,function()use($taskId,$documentId,$revision,$name){$this->ownedDocument($taskId,$documentId,true);return $this->docs->rename($documentId,$revision,$name);});return $this->get($taskId);}
    public function archive($taskId,$documentId,$revision,$restore=false){$this->documentMutation($taskId,function()use($taskId,$documentId,$revision,$restore){$this->ownedDocument($taskId,$documentId,false,true);return $this->docs->archive($documentId,$revision,$restore);});return $this->get($taskId,true);}
    public function versions($taskId,$documentId){$this->ownedDocument($taskId,$documentId,false,false,true);$out=$this->docs->versions($documentId);foreach($out as &$v){$v['downloadUrl']='context_api.php?action=download&task_id='.(int)$taskId.'&document_id='.(int)$documentId.'&version_id='.(int)$v['rowid'];}return $out;}
    public function content($taskId,$documentId,$versionId=0){$this->ownedDocument($taskId,$documentId,false,false,$versionId>0);return $this->docs->content($documentId,$versionId);}
}
