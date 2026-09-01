<?php

/**
 * M074 quality-control domain service.
 *
 * Customer projections are deliberately not exposed from this class until an
 * immutable publication exists. All mutable work remains staff-only.
 */
class QcService
{
    const SNAPSHOT_SECTION = 'qc_source_snapshot';
    const STATE_SECTION = 'qc_plan_state';

    private $db;
    private $entity;

    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
    }

    public static function states()
    {
        return array('draft', 'locked', 'field_complete', 'submitted', 'reviewed', 'published');
    }

    public function createSourceSnapshot($operationId, $userId, $manageTransaction = true)
    {
        if ($manageTransaction) $this->db->begin();
        try {
            $operation = $this->operationForUpdate($operationId);
            if ((string) $operation->service_type !== 'quality_control') throw new RuntimeException('QC plan requires a quality-control operation.');
            if ((int) $operation->fk_soc <= 0 || (int) $operation->fk_commande <= 0) throw new RuntimeException('QC plan requires a customer and Sales Order.');
            if ($this->sectionExists($operationId, self::SNAPSHOT_SECTION, true)) throw new RuntimeException('QC source snapshot already exists.');

            $order = $this->orderForUpdate((int) $operation->fk_commande);
            if (!$order || (int) $order->entity !== $this->entity || (int) $order->fk_soc !== (int) $operation->fk_soc) throw new RuntimeException('Sales Order, customer and entity must match.');
            if ((int) $operation->fk_project > 0) $this->assertProjectOwnership((int) $operation->fk_project, (int) $operation->fk_soc);
            if ((int) $operation->fk_project > 0 && !empty($order->fk_projet) && (int) $order->fk_projet !== (int) $operation->fk_project) throw new RuntimeException('Sales Order and QC project must match.');

            $lines = $this->orderLines((int) $operation->fk_commande);
            if (!$lines) throw new RuntimeException('Sales Order has no lines to inspect.');
            $assets = $this->snapshotApprovedAssets($operation);
            foreach($lines as&$line){$line['assets']=array_values(array_filter($assets,function($asset)use($line){return isset($asset['snapshot_line_key'])&&$asset['snapshot_line_key']===$line['line_key'];}));}unset($line);
            $service = $this->section($operationId, 'service');
            $publicationFields = array();
            foreach (array('customer_requirements','product_name','model_no','product_details','customer_notes','customer_warnings','inspection_standard','aql_level','sample_size','critical_limit','major_limit','minor_limit','corrective_action') as $code) {
                $value = $this->scalar($service, $code);
                if ($value !== '') $publicationFields[$code] = $value;
            }
            $snapshot = array(
                'schema_version' => 1,
                'created_at' => $this->now(),
                'entity' => $this->entity,
                'operation_id' => (int) $operation->rowid,
                'operation_ref' => (string) $operation->ref,
                'operation_title' => (string) $operation->title,
                'customer_id' => (int) $operation->fk_soc,
                'order_id' => (int) $operation->fk_commande,
                'order_ref' => (string) $order->ref,
                'project_id' => (int) $operation->fk_project ?: null,
                'inspector_id' => (int) $operation->fk_user_assign ?: null,
                'specifications' => array(
                    'product_name' => $this->scalar($service, 'product_name'),
                    'model_no' => $this->scalar($service, 'model_no'),
                    'product_details' => $this->scalar($service, 'product_details'),
                    'customer_requirements' => $this->scalar($service, 'customer_requirements'),
                    'customer_warnings' => $this->scalar($service, 'customer_warnings'),
                    'inspection_standard' => $this->scalar($service, 'inspection_standard'),
                ),
                // This is the only operation-level source publication may use.
                // It prevents later edits to the mutable operation/service rows
                // from changing a reviewed customer report.
                'publication_safe' => array(
                    'operation' => array('ref'=>(string)$operation->ref, 'title'=>(string)$operation->title),
                    'public_note' => trim((string)$operation->note_public) !== '' ? substr((string)$operation->note_public, 0, 20000) : null,
                    'fields' => $publicationFields,
                ),
                'lines' => $lines,
                'assets' => $assets,
            );
            $snapshot['snapshot_sha256'] = hash('sha256', $this->canonicalJson($snapshot));
            $this->insertSection($operationId, self::SNAPSHOT_SECTION, $snapshot, $userId);
            $this->insertSection($operationId, self::STATE_SECTION, array(
                'schema_version' => 1,
                'state' => 'draft',
                'plan_revision' => 1,
                'package_revision' => 0,
                'inspector_id' => (int) $operation->fk_user_assign ?: null,
                'aql' => array('enabled' => false),
                'mutation_receipts' => array(),
                'created_at' => $this->now(),
            ), $userId);
            $this->log($operationId, 'qc_snapshot_created', 'QC source snapshot created', $userId);
            if ($manageTransaction) $this->db->commit();
            return $snapshot;
        } catch (Throwable $e) {
            if ($manageTransaction) $this->db->rollback();
            throw $e;
        }
    }

    public function sourceSnapshot($operationId)
    {
        $snapshot=$this->section($operationId,self::SNAPSHOT_SECTION);if(!$snapshot)return array();$stored=(string)($snapshot['snapshot_sha256']??'');$payload=$snapshot;unset($payload['snapshot_sha256']);$actual=hash('sha256',$this->canonicalJson($payload));if(!preg_match('/^[a-f0-9]{64}$/',$stored)||!hash_equals($stored,$actual))throw new RuntimeException('QC source snapshot integrity check failed.');return$snapshot;
    }

    public function planState($operationId, $forUpdate = false)
    {
        $state = $this->section($operationId, self::STATE_SECTION, $forUpdate);
        return $state ?: null;
    }

    public function addManualCheckpoint($operationId, array $data, $userId)
    {
        return $this->addCheckpoint($operationId, array_merge($data, array('source_type' => 'manual', 'record_kind' => 'requirement')), $userId, false);
    }

    public function addFinding($operationId, array $data, $userId)
    {
        return $this->addCheckpoint($operationId, array_merge($data, array('source_type' => 'manual', 'record_kind' => 'finding', 'scope_type' => 'inspection', 'mandatory' => 0)), $userId, true);
    }

    /** Create a linked fresh QC plan when requirements change after locking. */
    public function createRequirementRevision($operationId, $reason, $userId)
    {
        $reason=trim((string)$reason);if(strlen($reason)<10||strlen($reason)>2000)throw new RuntimeException('Requirement revision reason must be between 10 and 2000 characters.');
        require_once __DIR__.'/OperationsService.class.php';
        $this->db->begin();
        try {
            $operation=$this->operationForUpdate($operationId);$state=$this->requireState($operationId,array('locked','field_complete','submitted','reviewed','published'),true);$snapshot=$this->sourceSnapshot($operationId);
            $fields=array_merge(isset($snapshot['specifications'])&&is_array($snapshot['specifications'])?$snapshot['specifications']:array(),isset($snapshot['publication_safe']['fields'])&&is_array($snapshot['publication_safe']['fields'])?$snapshot['publication_safe']['fields']:array());
            $newId=(new OperationsService($this->db))->create(array('service_type'=>'quality_control','title'=>(string)($snapshot['operation_title']??$operation->title).' · revised','fk_soc'=>(int)$operation->fk_soc,'fk_project'=>(int)$operation->fk_project,'fk_commande'=>(int)$operation->fk_commande,'fk_shipment'=>(int)$operation->fk_shipment,'fk_user_assign'=>(int)$operation->fk_user_assign,'priority'=>(string)$operation->priority,'waiting_on'=>'internal','next_action'=>'Prepare revised QC requirements','date_deadline'=>$operation->date_deadline,'note_private'=>'Linked requirement revision of '.$operation->ref.': '.$reason,'note_public'=>(string)($snapshot['publication_safe']['public_note']??''),'customer_visible'=>0,'service_data'=>$fields),$userId,false);
            $link=array('schema_version'=>1,'previous_operation_id'=>(int)$operationId,'previous_operation_ref'=>(string)$operation->ref,'reason'=>$reason,'created_at'=>$this->now(),'created_by'=>(int)$userId);
            $this->insertSection($newId,'qc_revision_link',$link,$userId);
            $state['superseded_by_operation_id']=$newId;$state['superseded_at']=$this->now();$state['superseded_reason']=$reason;$this->replaceSection($operationId,self::STATE_SECTION,$state,$userId);
            $this->createRevisionSnapshot($newId,$snapshot,$userId);
            $this->log($operationId,'qc_requirement_revision_created','Linked revised QC plan created: '.$newId,$userId);$this->db->commit();return$newId;
        } catch(Throwable$e){$this->db->rollback();throw$e;}
    }

    public function generateOrderLineCheckpoints($operationId, $userId)
    {
        $this->db->begin();
        try {
            $this->operationForUpdate($operationId); $this->requireState($operationId, array('draft'), true); $snapshot=$this->sourceSnapshot($operationId);$created=0;
            foreach(isset($snapshot['lines'])&&is_array($snapshot['lines'])?$snapshot['lines']:array()as$line){$ref=(string)$line['line_key'];if($this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int)$operationId." AND source_type='order' AND source_ref=".$this->q($ref))>0)continue;$label=trim((string)$line['product_ref'].' '.(string)$line['label']);$expected=trim((string)$line['description']);$expected.=($expected!==''?"\n":'').'Ordered quantity: '.(string)$line['quantity'].' '.(string)$line['unit'];$this->insertGeneratedCheckpoint($operationId,array('client_uuid'=>$this->uuidFromSeed('order:'.$operationId.':'.$ref),'source_type'=>'order','source_ref'=>$ref,'scope_type'=>'order_line','snapshot_line_key'=>$ref,'section_code'=>'ordered_products','section_label'=>'Ordered products','requirement_text'=>'Verify the inspected product matches Sales Order line: '.$label,'expected_value'=>$expected,'tolerance_text'=>null,'mandatory'=>1,'min_evidence'=>1,'position'=>(($this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int)$operationId)+1)*10)),$userId);$created++;}
            $this->log($operationId,'qc_order_checkpoints_generated','Generated '.$created.' QC checkpoints from the frozen Sales Order',$userId);$this->db->commit();return$created;
        }catch(Throwable$e){$this->db->rollback();throw$e;}
    }

    public function importApprovedTemplate($operationId, $templateId, $userId)
    {
        require_once __DIR__.'/QcTemplateService.class.php';$template=(new QcTemplateService())->template($templateId);$this->db->begin();
        try{$this->operationForUpdate($operationId);$this->requireState($operationId,array('draft'),true);$snapshot=$this->sourceSnapshot($operationId);$created=0;$index=0;foreach($template['checkpoints']as$definition){$index++;$scope=(string)($definition['scope_type']??'inspection');$targets=$scope==='each_order_line'?(isset($snapshot['lines'])?$snapshot['lines']:array()):array(array('line_key'=>null,'product_ref'=>'','label'=>''));foreach($targets as$line){$sourceRef=(string)$template['id'].':'.$index.':'.(string)($line['line_key']??'inspection');if($this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int)$operationId." AND source_type='template' AND source_ref=".$this->q($sourceRef))>0)continue;$requirement=trim((string)($definition['requirement_text']??''));if($scope==='each_order_line')$requirement.=' · '.trim((string)$line['product_ref'].' '.(string)$line['label']);$normalized=$this->normalizeCheckpoint($operationId,array('client_uuid'=>$this->uuidFromSeed('template:'.$operationId.':'.$sourceRef),'scope_type'=>$scope==='each_order_line'?'order_line':'inspection','snapshot_line_key'=>$line['line_key']??null,'section_code'=>$definition['section_code']??'general','section_label'=>$definition['section_label']??(string)$template['label'],'requirement_text'=>$requirement,'expected_value'=>$definition['expected_value']??null,'tolerance_text'=>$definition['tolerance_text']??null,'mandatory'=>isset($definition['mandatory'])?(int)!empty($definition['mandatory']):1,'min_evidence'=>$definition['min_evidence']??0),false);$normalized['source_type']='template';$normalized['source_ref']=$sourceRef;$this->insertGeneratedCheckpoint($operationId,$normalized,$userId);$created++;}}$this->log($operationId,'qc_template_imported','Imported approved QC template '.$template['id'].' with '.$created.' checkpoints',$userId);$this->db->commit();return$created;}catch(Throwable$e){$this->db->rollback();throw$e;}
    }

    public function acceptContractProposal($operationId,array$proposal,$userId)
    {
        $sourceRef=trim((string)($proposal['source_ref']??''));$page=(int)($proposal['source_page']??0);$confidence=(float)($proposal['source_confidence']??-1);if($sourceRef===''||strlen($sourceRef)>255||$page<=0||$confidence<0||$confidence>1)throw new RuntimeException('Contract proposal source page and confidence are required.');$this->db->begin();try{$this->operationForUpdate($operationId);$this->requireState($operationId,array('draft'),true);$proposal['client_uuid']=$proposal['client_uuid']??$this->uuidFromSeed('proposal:'.$operationId.':'.$sourceRef.':'.$page.':'.($proposal['requirement_text']??''));$normalized=$this->normalizeCheckpoint($operationId,$proposal,false);$normalized['source_type']='contract_ai';$normalized['source_ref']=$sourceRef;$id=$this->insertGeneratedCheckpoint($operationId,$normalized,$userId);$sql='UPDATE '.MAIN_DB_PREFIX.'cpo_qc_checkpoint SET source_page='.$page.',source_confidence='.$this->q(number_format($confidence,5,'.','')).' WHERE entity='.$this->entity.' AND fk_operation='.(int)$operationId.' AND rowid='.$id;if(!$this->db->query($sql))$this->fail();$this->log($operationId,'qc_contract_proposal_accepted','Accepted contract proposal from '.$sourceRef.' page '.$page,$userId);$this->db->commit();return$this->checkpoint($operationId,$id);}catch(Throwable$e){$this->db->rollback();throw$e;}
    }

    public function updateDraftCheckpoint($operationId, $checkpointId, array $data, $expectedRevision, $userId)
    {
        $this->db->begin();
        try {
            $this->operationForUpdate($operationId);
            $this->requireState($operationId, array('draft'), true);
            $checkpoint = $this->checkpointForUpdate($operationId, $checkpointId);
            if ((string) $checkpoint->source_type !== 'manual' || (string) $checkpoint->record_kind !== 'requirement') throw new RuntimeException('Only draft manual checkpoints may be edited here.');
            if ((int) $checkpoint->revision !== (int) $expectedRevision) throw new QcConflictException('Checkpoint revision conflict.');
            $normalized = $this->normalizeCheckpoint($operationId, $data, false);
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'cpo_qc_checkpoint SET scope_type='.$this->q($normalized['scope_type']).',snapshot_line_key='.$this->nullable($normalized['snapshot_line_key']).',section_code='.$this->q($normalized['section_code']).',section_label='.$this->q($normalized['section_label']).',requirement_text='.$this->q($normalized['requirement_text']).',expected_value='.$this->nullable($normalized['expected_value']).',tolerance_text='.$this->nullable($normalized['tolerance_text']).',mandatory='.(int) $normalized['mandatory'].',min_evidence='.(int) $normalized['min_evidence'].',position='.(int) $normalized['position'].',revision=revision+1,fk_user_modif='.(int) $userId.' WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND rowid='.(int) $checkpointId.' AND revision='.(int) $expectedRevision;
            $update = $this->db->query($sql);
            if (!$update || $this->db->affected_rows($update) !== 1) throw new QcConflictException('Checkpoint revision conflict.');
            $this->log($operationId, 'qc_checkpoint_updated', 'Manual QC checkpoint updated', $userId);
            $this->db->commit();
            return $this->checkpoint($operationId, $checkpointId);
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function duplicateManualCheckpoint($operationId, $checkpointId, $clientUuid, $userId)
    {
        $checkpoint = $this->checkpoint($operationId, $checkpointId);
        if (!$checkpoint || (string) $checkpoint['source_type'] !== 'manual' || (string) $checkpoint['record_kind'] !== 'requirement') throw new RuntimeException('Manual checkpoint not found.');
        return $this->addManualCheckpoint($operationId, array(
            'client_uuid' => $clientUuid,
            'scope_type' => $checkpoint['scope_type'],
            'snapshot_line_key' => $checkpoint['snapshot_line_key'],
            'section_code' => $checkpoint['section_code'],
            'section_label' => $checkpoint['section_label'],
            'requirement_text' => $checkpoint['requirement_text'],
            'expected_value' => $checkpoint['expected_value'],
            'tolerance_text' => $checkpoint['tolerance_text'],
            'mandatory' => (int) $checkpoint['mandatory'],
            'min_evidence' => (int) $checkpoint['min_evidence'],
            'position' => (int) $checkpoint['position'] + 1,
        ), $userId);
    }

    public function removeDraftCheckpoint($operationId, $checkpointId, $userId)
    {
        $this->db->begin();
        try {
            $this->operationForUpdate($operationId);
            $this->requireState($operationId, array('draft'), true);
            $checkpoint = $this->checkpointForUpdate($operationId, $checkpointId);
            if ((string) $checkpoint->source_type !== 'manual' || (string) $checkpoint->record_kind !== 'requirement') throw new RuntimeException('Only draft manual checkpoints may be removed.');
            $evidence = $this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'cpo_qc_evidence WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND fk_checkpoint='.(int) $checkpointId);
            if ($evidence > 0) throw new RuntimeException('Checkpoint with evidence cannot be removed.');
            if (!$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND rowid='.(int) $checkpointId)) $this->fail();
            $this->log($operationId, 'qc_checkpoint_removed', 'Manual QC checkpoint removed', $userId);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function reorderDraftCheckpoints($operationId, array $checkpointIds, $userId)
    {
        $ids = array_values(array_unique(array_map('intval', $checkpointIds)));
        $this->db->begin();
        try {
            $this->operationForUpdate($operationId);
            $this->requireState($operationId, array('draft'), true);
            $current = $this->checkpoints($operationId);
            $currentIds = array_map(function ($row) { return (int) $row['rowid']; }, $current);
            sort($currentIds); $sorted = $ids; sort($sorted);
            if ($currentIds !== $sorted) throw new RuntimeException('Checkpoint order must include every checkpoint exactly once.');
            foreach ($ids as $position => $id) {
                if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_qc_checkpoint SET position='.(($position + 1) * 10).',revision=revision+1,fk_user_modif='.(int) $userId.' WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND rowid='.(int) $id)) $this->fail();
            }
            $this->log($operationId, 'qc_checkpoints_reordered', 'QC checkpoints reordered', $userId);
            $this->db->commit();
            return $this->checkpoints($operationId);
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function assignInspector($operationId, $inspectorId, $userId)
    {
        $this->db->begin();
        try {
            $this->operationForUpdate($operationId);
            $state = $this->requireState($operationId, array('draft'), true);
            $res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.(int) $inspectorId.' AND entity IN (0,'.$this->entity.') AND statut=1 AND COALESCE(fk_soc,0)=0');
            if (!$res || $this->db->num_rows($res) !== 1) throw new RuntimeException('Inspector must be an active internal user.');
            if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_operation SET fk_user_assign='.(int) $inspectorId.',fk_user_modif='.(int) $userId.' WHERE entity='.$this->entity.' AND rowid='.(int) $operationId)) $this->fail();
            $state['inspector_id'] = (int) $inspectorId;
            $this->replaceSection($operationId, self::STATE_SECTION, $state, $userId);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function configureAql($operationId, $enabled, $lotSize, array $aqlByClass, $criticalZeroTolerance, $userId)
    {
        $this->db->begin();
        try {
            $this->operationForUpdate($operationId); $state = $this->requireState($operationId, array('draft'), true);
            if (!$enabled) $state['aql'] = array('enabled' => false);
            else {
                require_once __DIR__.'/QcAqlService.class.php';
                $calculation = (new QcAqlService())->calculate($lotSize, $aqlByClass, $criticalZeroTolerance);
                $state['aql'] = array('enabled' => true, 'calculation' => $calculation, 'configured_at' => $this->now(), 'configured_by' => (int) $userId);
            }
            $this->replaceSection($operationId, self::STATE_SECTION, $state, $userId); $this->db->commit(); return $state['aql'];
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function lockPlan($operationId, $userId)
    {
        $this->db->begin();
        try {
            $operation = $this->operationForUpdate($operationId);
            $state = $this->requireState($operationId, array('draft'), true);
            if ((int) $operation->fk_user_assign <= 0) throw new RuntimeException('Assign an inspector before locking the plan.');
            if ($this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND record_kind=\'requirement\'') <= 0) throw new RuntimeException('Add at least one checkpoint before locking the plan.');
            $state['state'] = 'locked';
            $state['package_revision'] = (int) $state['package_revision'] + 1;
            $state['inspector_id'] = (int) $operation->fk_user_assign;
            $state['locked_at'] = $this->now();
            $state['locked_by'] = (int) $userId;
            $this->replaceSection($operationId, self::STATE_SECTION, $state, $userId);
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_operation SET stage='planned',progress=30,next_action='Perform QC inspection',fk_user_modif=".(int) $userId.' WHERE entity='.$this->entity.' AND rowid='.(int) $operationId)) $this->fail();
            $this->log($operationId, 'qc_plan_locked', 'QC field package locked at revision '.(int) $state['package_revision'], $userId);
            $this->db->commit();
            return $state;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function checkpoints($operationId)
    {
        return $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' ORDER BY position,rowid');
    }

    public function checkpoint($operationId, $checkpointId)
    {
        $rows = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND rowid='.(int) $checkpointId.' LIMIT 1');
        return $rows ? $rows[0] : null;
    }

    public function fieldPackage($operationId, $actorId)
    {
        $operation = $this->operationForRead($operationId);
        if ((int) $operation->fk_user_assign !== (int) $actorId) throw new RuntimeException('QC inspection is not assigned to this user.');
        $state = $this->requireState($operationId, array('locked', 'field_complete', 'submitted'), false);
        return array(
            'operation' => array('id' => (int) $operation->rowid, 'ref' => (string) $operation->ref, 'title' => (string) $operation->title),
            'state' => $state,
            'source_snapshot' => $this->sourceSnapshot($operationId),
            'checkpoints' => $this->checkpoints($operationId),
            'server_time' => $this->now(),
        );
    }

    public function saveCheckpointResult($operationId, $checkpointId, array $data, $expectedRevision, $mutationId, $actorId)
    {
        $this->db->begin();
        try {
            $operation = $this->operationForUpdate($operationId);
            if ((int) $operation->fk_user_assign !== (int) $actorId) throw new RuntimeException('QC inspection is not assigned to this user.');
            $state = $this->requireState($operationId, array('locked', 'field_complete'), true);
            $checkpoint = $this->checkpointForUpdate($operationId, $checkpointId);
            $mutationId = $this->mutationId($mutationId); $payloadHash = hash('sha256', $this->canonicalJson($data));
            $receipts = $this->receipts((string) $checkpoint->mutation_receipts_json);
            $known = $this->knownReceipt($receipts, $mutationId);
            if ($known) {
                if (!hash_equals((string) $known['payload_sha256'], $payloadHash)) throw new QcConflictException('Mutation ID was reused with a different payload.');
                $this->db->commit(); return $this->checkpoint($operationId, $checkpointId);
            }
            if ((int) $checkpoint->revision !== (int) $expectedRevision) throw new QcConflictException('Checkpoint revision conflict.');
            $result = strtolower(trim(isset($data['result_code']) ? (string) $data['result_code'] : 'pending'));
            $severity = strtolower(trim(isset($data['severity']) ? (string) $data['severity'] : 'none'));
            if (!in_array($result, array('pending', 'pass', 'fail', 'not_applicable'), true)) throw new RuntimeException('Invalid QC result.');
            if (!in_array($severity, array('none', 'critical', 'major', 'minor'), true)) throw new RuntimeException('Invalid QC severity.');
            $observation = $this->optionalText($data, 'observation', 30000);
            $publicNote = $this->optionalText($data, 'public_note', 20000);
            $internalNote = $this->optionalText($data, 'internal_note', 20000);
            if (in_array($result, array('pass', 'fail'), true) && $observation === null) throw new RuntimeException('Pass or fail requires an observation.');
            if ($result === 'fail' && $severity === 'none') throw new RuntimeException('Failed checkpoints require a defect severity.');
            if ($result === 'not_applicable' && ($internalNote === null || $severity !== 'none')) throw new RuntimeException('N/A requires a rationale and severity none.');
            if ($result === 'pass' && $severity !== 'none') throw new RuntimeException('Passed checkpoints must use severity none.');
            $receipts[] = array('mutation_id' => $mutationId, 'payload_sha256' => $payloadHash, 'accepted_revision' => (int) $checkpoint->revision + 1, 'accepted_at' => $this->now());
            if (count($receipts) > 64) $receipts = array_slice($receipts, -64);
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'cpo_qc_checkpoint SET observation='.$this->nullable($observation).',result_code='.$this->q($result).',severity='.$this->q($severity).',public_note='.$this->nullable($publicNote).',internal_note='.$this->nullable($internalNote).',sample_size='.$this->nullableInt($data, 'sample_size').',defects_critical='.$this->nullableInt($data, 'defects_critical').',defects_major='.$this->nullableInt($data, 'defects_major').',defects_minor='.$this->nullableInt($data, 'defects_minor').',mutation_receipts_json='.$this->q($this->json($receipts)).',revision=revision+1,fk_user_modif='.(int) $actorId.' WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND rowid='.(int) $checkpointId.' AND revision='.(int) $expectedRevision;
            $update = $this->db->query($sql); if (!$update || $this->db->affected_rows($update) !== 1) throw new QcConflictException('Checkpoint revision conflict.');
            $incomplete = $this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX."cpo_qc_checkpoint WHERE entity=".$this->entity.' AND fk_operation='.(int) $operationId." AND record_kind='requirement' AND mandatory=1 AND result_code='pending'");
            $state['state'] = $incomplete === 0 ? 'field_complete' : 'locked';
            $state['last_mutation_at'] = $this->now();
            $this->replaceSection($operationId, self::STATE_SECTION, $state, $actorId);
            $this->db->commit();
            return $this->checkpoint($operationId, $checkpointId);
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function submitInspection($operationId, $packageRevision, $actorId, $acknowledgeInternalOnly = false)
    {
        $this->db->begin();
        try {
            $operation = $this->operationForUpdate($operationId);
            if ((int) $operation->fk_user_assign !== (int) $actorId) throw new RuntimeException('QC inspection is not assigned to this user.');
            $state = $this->requireState($operationId, array('field_complete'), true);
            if ((int) $state['package_revision'] !== (int) $packageRevision) throw new QcConflictException('Field package revision conflict.');
            $rows = $this->checkpoints($operationId); $observedSample = 0; $defects = array('critical'=>0,'major'=>0,'minor'=>0);
            foreach ($rows as $row) {
                $observedSample = max($observedSample, (int) $row['sample_size']); $defects['critical'] += (int) $row['defects_critical']; $defects['major'] += (int) $row['defects_major']; $defects['minor'] += (int) $row['defects_minor'];
                if ((int) $row['mandatory'] !== 1 || (string) $row['record_kind'] !== 'requirement') continue;
                if (!in_array($row['result_code'], array('pass', 'fail', 'not_applicable'), true)) throw new RuntimeException('Every mandatory checkpoint must be complete.');
                if (in_array($row['result_code'], array('pass', 'fail'), true) && trim((string) $row['observation']) === '') throw new RuntimeException('Completed checkpoint is missing an observation.');
                if ($row['result_code'] === 'fail' && $row['severity'] === 'none') throw new RuntimeException('Failed checkpoint is missing severity.');
                if ($row['result_code'] === 'not_applicable' && (trim((string) $row['internal_note']) === '' || $row['severity'] !== 'none')) throw new RuntimeException('N/A checkpoint is missing its rationale.');
                $evidence = $this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX."cpo_qc_evidence WHERE entity=".$this->entity.' AND fk_operation='.(int) $operationId.' AND fk_checkpoint='.(int) $row['rowid']." AND upload_status IN ('finalized','internal_only')");
                if ($evidence < (int) $row['min_evidence']) throw new RuntimeException('A mandatory checkpoint is missing required evidence.');
            }
            if (!empty($state['aql']['enabled'])) {
                $classes = isset($state['aql']['calculation']['classes']) && is_array($state['aql']['calculation']['classes']) ? $state['aql']['calculation']['classes'] : array(); $requiredSample = 0; $outcome = 'accept';
                foreach ($classes as $class => $plan) { $requiredSample = max($requiredSample, (int) $plan['sample_size']); if (isset($defects[$class]) && $defects[$class] >= (int) $plan['reject']) $outcome = 'reject'; }
                if ($requiredSample <= 0 || $observedSample < $requiredSample) throw new RuntimeException('Recorded AQL sample size is below the calculated requirement.');
                $state['aql']['observed'] = array('sample_size'=>$observedSample,'critical'=>$defects['critical'],'major'=>$defects['major'],'minor'=>$defects['minor']); $state['aql']['outcome'] = $outcome;
            }
            if ($this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX."cpo_qc_evidence WHERE entity=".$this->entity.' AND fk_operation='.(int) $operationId." AND upload_status IN ('pending','uploading','processing','reprocessing')") > 0) throw new RuntimeException('Pending evidence uploads must finish before submission.');
            $internalOnly = $this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX."cpo_qc_evidence WHERE entity=".$this->entity.' AND fk_operation='.(int) $operationId." AND upload_status='internal_only'");
            if ($internalOnly > 0 && !$acknowledgeInternalOnly) throw new RuntimeException('Internal-only video evidence requires inspector acknowledgement before submission.');
            if ($internalOnly > 0) {
                $state['internal_only_evidence_acknowledged_at'] = $this->now();
                $state['internal_only_evidence_acknowledged_by'] = (int) $actorId;
                $state['internal_only_evidence_acknowledged_count'] = $internalOnly;
            }
            $state['state'] = 'submitted'; $state['submitted_at'] = $this->now(); $state['submitted_by'] = (int) $actorId;
            $this->replaceSection($operationId, self::STATE_SECTION, $state, $actorId);
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_operation SET stage='supervisor_review',progress=85,next_action='Supervisor QC review',fk_user_modif=".(int) $actorId.' WHERE entity='.$this->entity.' AND rowid='.(int) $operationId)) $this->fail();
            $this->log($operationId, 'qc_submitted', 'QC field inspection submitted for review', $actorId);
            $this->db->commit(); return $state;
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function reviewFinding($operationId, $checkpointId, $disposition, $note, $reviewerId)
    {
        $allowed = array('approved_customer' => 1, 'approved_internal' => 0, 'rejected' => 0);
        if (!isset($allowed[$disposition])) throw new RuntimeException('Invalid finding disposition.');
        $this->db->begin();
        try {
            $this->operationForUpdate($operationId); $this->requireState($operationId, array('submitted'), true);
            $row = $this->checkpointForUpdate($operationId, $checkpointId);
            if ((string) $row->record_kind !== 'finding') throw new RuntimeException('QC finding not found.');
            if ((int) $row->fk_user_creat === (int) $reviewerId) throw new RuntimeException('Inspector cannot review their own finding.');
            if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_qc_checkpoint SET review_status='.$this->q($disposition).',review_note='.$this->q(trim((string) $note)).',customer_visible='.(int) $allowed[$disposition].',fk_user_review='.(int) $reviewerId.',date_review='.$this->q($this->now()).' WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND rowid='.(int) $checkpointId)) $this->fail();
            $this->db->commit(); return true;
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function markReviewed($operationId, $reviewerId)
    {
        $this->db->begin();
        try {
            $this->operationForUpdate($operationId); $state = $this->requireState($operationId, array('submitted'), true);
            if ($this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX."cpo_qc_evidence WHERE entity=".$this->entity.' AND fk_operation='.(int) $operationId." AND upload_status IN ('internal_only','reprocessing')") > 0) throw new RuntimeException('Internal-only video evidence must be safely reprocessed before supervisor review can complete.');
            if ($this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX."cpo_qc_checkpoint WHERE entity=".$this->entity.' AND fk_operation='.(int) $operationId." AND record_kind='finding' AND review_status='pending'") > 0) throw new RuntimeException('Every unexpected finding requires a disposition.');
            if ($this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX."cpo_qc_evidence WHERE entity=".$this->entity.' AND fk_operation='.(int) $operationId." AND upload_status='finalized' AND review_status='pending'") > 0) throw new RuntimeException('Every finalized evidence file requires a disposition.');
            $state['review_package_sha256']=$this->reviewPackageHash($operationId);
            $state['state'] = 'reviewed'; $state['reviewed_at'] = $this->now(); $state['reviewed_by'] = (int) $reviewerId;
            $this->replaceSection($operationId, self::STATE_SECTION, $state, $reviewerId);
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_operation SET stage='ready_to_publish',progress=90,next_action='Publish QC report',fk_user_modif=".(int) $reviewerId.' WHERE entity='.$this->entity.' AND rowid='.(int) $operationId)) $this->fail();
            $this->db->commit(); return $state;
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    /** Open a controlled publication-only revision without unlocking field evidence or results. */
    public function preparePublicationRevision($operationId, $reason, $reviewerId)
    {
        $reason = trim((string) $reason);
        if (strlen($reason) < 10 || strlen($reason) > 2000) throw new RuntimeException('Publication revision reason must be between 10 and 2000 characters.');
        $this->db->begin();
        try {
            $this->operationForUpdate($operationId); $state = $this->requireState($operationId, array('published'), true);
            $state['state'] = 'reviewed'; $state['reviewed_at'] = $this->now(); $state['reviewed_by'] = (int) $reviewerId;
            $state['publication_revision_reason'] = $reason; $state['publication_revision_requested_at'] = $this->now();
            $this->replaceSection($operationId, self::STATE_SECTION, $state, $reviewerId);
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_operation SET stage='ready_to_publish',progress=90,next_action='Publish corrected QC version',fk_user_modif=".(int)$reviewerId.' WHERE entity='.$this->entity.' AND rowid='.(int)$operationId)) $this->fail();
            $this->log($operationId, 'qc_publication_revision', 'QC publication revision requested: '.$reason, $reviewerId);
            $this->db->commit(); return $state;
        } catch (Throwable $e) { $this->db->rollback(); throw $e; }
    }

    private function addCheckpoint($operationId, array $data, $userId, $allowFinding)
    {
        $this->db->begin();
        try {
            $operation = $this->operationForUpdate($operationId);
            $state = $this->requireState($operationId, $allowFinding ? array('locked', 'field_complete') : array('draft'), true);
            if ($allowFinding && (int) $operation->fk_user_assign !== (int) $userId) throw new RuntimeException('Only the assigned inspector may add a field finding.');
            $normalized = $this->normalizeCheckpoint($operationId, $data, $allowFinding);
            $existing = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND client_uuid='.$this->q($normalized['client_uuid']).' LIMIT 1');
            if ($existing) {
                $old=$existing[0];$same=(string)$old['record_kind']===(string)$normalized['record_kind']&&(string)$old['scope_type']===(string)$normalized['scope_type']&&(string)$old['snapshot_line_key']===(string)$normalized['snapshot_line_key']&&(string)$old['section_code']===(string)$normalized['section_code']&&(string)$old['requirement_text']===(string)$normalized['requirement_text']&&(string)$old['expected_value']===(string)$normalized['expected_value']&&(string)$old['tolerance_text']===(string)$normalized['tolerance_text'];
                if(!$same)throw new QcConflictException('Checkpoint UUID was reused with different content.');
                $this->db->commit();return$old;
            }
            $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpo_qc_checkpoint(entity,fk_operation,client_uuid,record_kind,source_type,scope_type,snapshot_line_key,section_code,section_label,requirement_text,expected_value,tolerance_text,mandatory,min_evidence,position,review_status,customer_visible,fk_user_creat,date_creation) VALUES('.$this->entity.','.(int) $operationId.','.$this->q($normalized['client_uuid']).','.$this->q($normalized['record_kind']).','.$this->q($normalized['source_type']).','.$this->q($normalized['scope_type']).','.$this->nullable($normalized['snapshot_line_key']).','.$this->q($normalized['section_code']).','.$this->q($normalized['section_label']).','.$this->q($normalized['requirement_text']).','.$this->nullable($normalized['expected_value']).','.$this->nullable($normalized['tolerance_text']).','.(int) $normalized['mandatory'].','.(int) $normalized['min_evidence'].','.(int) $normalized['position'].',\'pending\',0,'.(int) $userId.','.$this->q($this->now()).')';
            if (!$this->db->query($sql)) $this->fail();
            $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_qc_checkpoint');
            if ($allowFinding && (string) $state['state'] === 'field_complete') {
                $state['state'] = 'locked';
                $this->replaceSection($operationId, self::STATE_SECTION, $state, $userId);
            }
            $this->log($operationId, $allowFinding ? 'qc_finding_added' : 'qc_checkpoint_added', $allowFinding ? 'Unexpected field finding added' : 'Manual QC checkpoint added', $userId);
            $this->db->commit();
            return $this->checkpoint($operationId, $id);
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function insertGeneratedCheckpoint($operationId,array$data,$userId)
    {
        $sql='INSERT INTO '.MAIN_DB_PREFIX.'cpo_qc_checkpoint(entity,fk_operation,client_uuid,record_kind,source_type,source_ref,scope_type,snapshot_line_key,section_code,section_label,requirement_text,expected_value,tolerance_text,mandatory,min_evidence,position,review_status,customer_visible,fk_user_creat,date_creation) VALUES('.$this->entity.','.(int)$operationId.','.$this->q($data['client_uuid']).',\'requirement\','.$this->q($data['source_type']).','.$this->nullable($data['source_ref']).','.$this->q($data['scope_type']).','.$this->nullable($data['snapshot_line_key']).','.$this->q($data['section_code']).','.$this->q($data['section_label']).','.$this->q($data['requirement_text']).','.$this->nullable($data['expected_value']).','.$this->nullable($data['tolerance_text']).','.(int)$data['mandatory'].','.(int)$data['min_evidence'].','.(int)$data['position'].',\'pending\',0,'.(int)$userId.','.$this->q($this->now()).')';if(!$this->db->query($sql))$this->fail();return(int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_qc_checkpoint');
    }

    private function normalizeCheckpoint($operationId, array $data, $finding)
    {
        $uuid = strtolower(trim(isset($data['client_uuid']) ? (string) $data['client_uuid'] : ''));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) throw new RuntimeException('A valid checkpoint client UUID is required.');
        $scope = $finding ? 'inspection' : (isset($data['scope_type']) ? (string) $data['scope_type'] : 'inspection');
        if (!in_array($scope, array('inspection', 'order_line'), true)) throw new RuntimeException('Invalid checkpoint scope.');
        $lineKey = $scope === 'order_line' ? trim(isset($data['snapshot_line_key']) ? (string) $data['snapshot_line_key'] : '') : '';
        if ($scope === 'order_line' && !$this->snapshotHasLine($operationId, $lineKey)) throw new RuntimeException('Checkpoint order-line scope is not in the frozen snapshot.');
        $requirement = trim(isset($data['requirement_text']) ? (string) $data['requirement_text'] : '');
        if ($requirement === '' || strlen($requirement) > 20000) throw new RuntimeException('Checkpoint requirement is required and must be at most 20,000 bytes.');
        $sectionCode = strtolower(trim(isset($data['section_code']) ? (string) $data['section_code'] : 'general'));
        if (!preg_match('/^[a-z0-9_]{1,64}$/', $sectionCode)) throw new RuntimeException('Invalid checkpoint section code.');
        $sectionLabel = trim(isset($data['section_label']) ? (string) $data['section_label'] : 'General');
        if ($sectionLabel === '' || strlen($sectionLabel) > 255) throw new RuntimeException('Checkpoint section label is required.');
        return array(
            'client_uuid' => $uuid,
            'record_kind' => $finding ? 'finding' : 'requirement',
            'source_type' => 'manual',
            'scope_type' => $scope,
            'snapshot_line_key' => $lineKey !== '' ? $lineKey : null,
            'section_code' => $sectionCode,
            'section_label' => $sectionLabel,
            'requirement_text' => $requirement,
            'expected_value' => $this->optionalText($data, 'expected_value', 20000),
            'tolerance_text' => $this->optionalText($data, 'tolerance_text', 20000),
            'mandatory' => $finding ? 0 : (!isset($data['mandatory']) || !empty($data['mandatory']) ? 1 : 0),
            'min_evidence' => max(0, min(20, isset($data['min_evidence']) ? (int) $data['min_evidence'] : 0)),
            'position' => max(0, isset($data['position']) ? (int) $data['position'] : (($this->count('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId) + 1) * 10)),
        );
    }

    private function snapshotHasLine($operationId, $lineKey)
    {
        if ($lineKey === '') return false;
        $snapshot = $this->sourceSnapshot($operationId);
        foreach (isset($snapshot['lines']) && is_array($snapshot['lines']) ? $snapshot['lines'] : array() as $line) if (isset($line['line_key']) && hash_equals((string) $line['line_key'], (string) $lineKey)) return true;
        return false;
    }

    private function uuidFromSeed($seed){$hex=substr(hash('sha256',(string)$seed),0,32);$hex[12]='4';$hex[16]='8';return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);}

    private function operationForUpdate($operationId)
    {
        $res = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_operation WHERE entity='.$this->entity.' AND rowid='.(int) $operationId.' AND status=1 FOR UPDATE');
        $operation = $res ? $this->db->fetch_object($res) : null;
        if (!$operation) throw new RuntimeException('QC operation not found.');
        if ((string) $operation->service_type !== 'quality_control') throw new RuntimeException('Operation is not quality control.');
        return $operation;
    }

    private function operationForRead($operationId)
    {
        $res = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_operation WHERE entity='.$this->entity.' AND rowid='.(int) $operationId.' AND status=1');
        $operation = $res ? $this->db->fetch_object($res) : null;
        if (!$operation || (string) $operation->service_type !== 'quality_control') throw new RuntimeException('QC operation not found.');
        return $operation;
    }

    private function orderForUpdate($orderId)
    {
        $res = $this->db->query('SELECT rowid,entity,fk_soc,fk_projet,ref,date_commande,tms FROM '.MAIN_DB_PREFIX.'commande WHERE rowid='.(int) $orderId.' AND entity='.$this->entity.' FOR UPDATE');
        return $res ? $this->db->fetch_object($res) : null;
    }

    private function orderLines($orderId)
    {
        $sql = 'SELECT l.rowid,l.fk_product,l.description,l.qty,l.product_type,l.rang,p.ref product_ref,p.label product_label,p.description product_description,u.code unit_code';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'commandedet l LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=l.fk_product AND p.entity='.$this->entity;
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_units u ON u.rowid=l.fk_unit WHERE l.fk_commande='.(int) $orderId.' ORDER BY l.rang,l.rowid';
        $rows = array(); $res = $this->db->query($sql);
        while ($res && ($line = $this->db->fetch_object($res))) {
            $lineKey = 'order-line-'.(int) $line->rowid;
            $rows[] = array(
                'line_key' => $lineKey,
                'source_line_id' => (int) $line->rowid,
                'position' => (int) $line->rang,
                'product_id' => !empty($line->fk_product) ? (int) $line->fk_product : null,
                'product_ref' => (string) $line->product_ref,
                'label' => (string) ($line->product_label ?: $line->description),
                'description' => trim((string) ($line->description ?: $line->product_description)),
                'model_specification' => trim((string) $line->description),
                'quantity' => (string) $line->qty,
                'unit' => (string) $line->unit_code,
                'assets' => array(),
            );
        }
        return $rows;
    }

    private function assertProjectOwnership($projectId, $socid)
    {
        $res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE rowid='.(int) $projectId.' AND entity='.$this->entity.' AND fk_soc='.(int) $socid.' FOR UPDATE');
        if (!$res || $this->db->num_rows($res) !== 1) throw new RuntimeException('Project, customer and entity must match.');
    }

    /** Copy approved operation specifications/images into immutable QC source storage. */
    private function snapshotApprovedAssets($operation)
    {
        global$conf;$rows=$this->rows('SELECT rowid,document_type,original_name,stored_name,mime_type,file_size,version_no,date_review FROM '.MAIN_DB_PREFIX.'cpo_document WHERE entity='.$this->entity.' AND fk_operation='.(int)$operation->rowid." AND is_current=1 AND review_status IN ('approved','final_received') ORDER BY document_type,rowid");$out=array();if(!$rows)return$out;$sourceRoot=realpath(rtrim((string)$conf->chinaplanetoperations->dir_output,'/').'/documents/'.$this->entity.'/'.(string)$operation->ref);if(!$sourceRoot||!is_dir($sourceRoot)||is_link($sourceRoot))throw new RuntimeException('Approved QC source storage is unavailable.');$targetRoot=rtrim((string)$conf->chinaplanetoperations->dir_output,'/').'/qc/'.$this->entity.'/sources/'.(int)$operation->rowid;if(!is_dir($targetRoot)&&!mkdir($targetRoot,0700,true))throw new RuntimeException('Unable to create immutable QC source storage.');if(is_link($targetRoot)||!chmod($targetRoot,0700))throw new RuntimeException('Unsafe immutable QC source storage.');$targetReal=realpath($targetRoot);if(!$targetReal)throw new RuntimeException('Unable to resolve immutable QC source storage.');$allowed=array('application/pdf','image/jpeg','image/png','image/webp','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        foreach($rows as$row){if(!in_array((string)$row['mime_type'],$allowed,true))continue;if(basename((string)$row['stored_name'])!==(string)$row['stored_name'])throw new RuntimeException('Approved QC source asset has an unsafe storage name.');$source=realpath($sourceRoot.'/'.(string)$row['stored_name']);if(!$source||!is_file($source)||is_link($source)||strpos($source,rtrim($sourceRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)!==0||(int)filesize($source)!==(int)$row['file_size'])throw new RuntimeException('Approved QC source asset failed snapshot verification.');$sha=hash_file('sha256',$source);$extension=strtolower(pathinfo((string)$row['original_name'],PATHINFO_EXTENSION));$stored=$sha.($extension!==''?'.'.preg_replace('/[^a-z0-9]/','',$extension):'');$target=$targetReal.'/'.$stored;if(!is_file($target)){if(!copy($source,$target)||!chmod($target,0600)){@unlink($target);throw new RuntimeException('Unable to freeze approved QC source asset.');}}if((int)filesize($target)!==(int)$row['file_size']||!hash_equals($sha,(string)hash_file('sha256',$target)))throw new RuntimeException('Frozen QC source asset verification failed.');$lineKey=null;if(preg_match('/^order_line_(\d+)$/',(string)$row['document_type'],$m))$lineKey='order-line-'.(int)$m[1];$out[]=array('source'=>'cpo_document','source_id'=>(int)$row['rowid'],'source_version'=>(int)$row['version_no'],'document_type'=>(string)$row['document_type'],'name'=>basename((string)$row['original_name']),'mime_type'=>(string)$row['mime_type'],'size_bytes'=>(int)$row['file_size'],'sha256'=>$sha,'stored_name'=>$stored,'snapshot_line_key'=>$lineKey,'approved_at'=>(string)$row['date_review']);}
        return$out;
    }

    /** Clone the exact prior frozen source package for a linked requirement revision. */
    private function createRevisionSnapshot($newOperationId,array$previous,$userId)
    {
        $operation=$this->operationForUpdate($newOperationId);if($this->sectionExists($newOperationId,self::SNAPSHOT_SECTION,true))throw new RuntimeException('QC revision snapshot already exists.');$assets=$this->copyRevisionAssets(isset($previous['assets'])&&is_array($previous['assets'])?$previous['assets']:array(),(int)$previous['operation_id'],$newOperationId);$snapshot=$previous;unset($snapshot['snapshot_sha256']);$snapshot['created_at']=$this->now();$snapshot['operation_id']=(int)$operation->rowid;$snapshot['operation_ref']=(string)$operation->ref;$snapshot['operation_title']=(string)$operation->title;$snapshot['inspector_id']=(int)$operation->fk_user_assign?:null;$snapshot['assets']=$assets;if(!isset($snapshot['publication_safe'])||!is_array($snapshot['publication_safe']))throw new RuntimeException('Prior QC publication-safe snapshot is missing.');$snapshot['publication_safe']['operation']=array('ref'=>(string)$operation->ref,'title'=>(string)$operation->title);foreach(isset($snapshot['lines'])&&is_array($snapshot['lines'])?$snapshot['lines']:array()as$index=>$line)$snapshot['lines'][$index]['assets']=array_values(array_filter($assets,function($asset)use($line){return isset($asset['snapshot_line_key'])&&$asset['snapshot_line_key']===($line['line_key']??null);}));$snapshot['snapshot_sha256']=hash('sha256',$this->canonicalJson($snapshot));$this->insertSection($newOperationId,self::SNAPSHOT_SECTION,$snapshot,$userId);$this->insertSection($newOperationId,self::STATE_SECTION,array('schema_version'=>1,'state'=>'draft','plan_revision'=>1,'package_revision'=>0,'inspector_id'=>(int)$operation->fk_user_assign?:null,'aql'=>array('enabled'=>false),'mutation_receipts'=>array(),'created_at'=>$this->now()),$userId);$this->log($newOperationId,'qc_revision_snapshot_created','QC revision cloned the prior immutable source snapshot',$userId);
    }

    private function copyRevisionAssets(array$assets,$sourceOperationId,$targetOperationId)
    {
        global$conf;if(!$assets)return array();$base=rtrim((string)$conf->chinaplanetoperations->dir_output,'/').'/qc/'.$this->entity.'/sources';$sourceRoot=realpath($base.'/'.(int)$sourceOperationId);if(!$sourceRoot||!is_dir($sourceRoot)||is_link($sourceRoot))throw new RuntimeException('Prior immutable QC source storage is unavailable.');$target=$base.'/'.(int)$targetOperationId;if(!is_dir($target)&&!mkdir($target,0700,true))throw new RuntimeException('Unable to create revised QC source storage.');if(is_link($target)||!chmod($target,0700))throw new RuntimeException('Unsafe revised QC source storage.');$targetRoot=realpath($target);foreach($assets as$asset){$name=(string)($asset['stored_name']??'');$sha=(string)($asset['sha256']??'');$size=(int)($asset['size_bytes']??-1);if(basename($name)!==$name||!preg_match('/^[a-f0-9]{64}$/',$sha))throw new RuntimeException('Prior QC source asset metadata is invalid.');$source=realpath($sourceRoot.'/'.$name);if(!$source||!is_file($source)||is_link($source)||strpos($source,rtrim($sourceRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)!==0||filesize($source)!==$size||!hash_equals($sha,(string)hash_file('sha256',$source)))throw new RuntimeException('Prior QC source asset failed revision verification.');$copy=$targetRoot.'/'.$name;if(!is_file($copy)&&(!copy($source,$copy)||!chmod($copy,0600)))throw new RuntimeException('Unable to copy revised QC source asset.');if(filesize($copy)!==$size||!hash_equals($sha,(string)hash_file('sha256',$copy)))throw new RuntimeException('Revised QC source asset verification failed.');}return$assets;
    }

    private function reviewPackageHash($operationId){$snapshot=$this->sourceSnapshot($operationId);$package=array('snapshot_sha256'=>(string)$snapshot['snapshot_sha256'],'checkpoints'=>array(),'evidence'=>array());foreach($this->rows('SELECT client_uuid,record_kind,section_code,section_label,requirement_text,expected_value,tolerance_text,observation,result_code,severity,public_note,review_status,customer_visible,position,revision FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int)$operationId.' ORDER BY rowid')as$row)$package['checkpoints'][]=$row;foreach($this->rows('SELECT client_uuid,fk_checkpoint,evidence_type,caption,upload_status,review_status,customer_visible,original_sha256,safe_mime,safe_size,safe_sha256,revision FROM '.MAIN_DB_PREFIX.'cpo_qc_evidence WHERE entity='.$this->entity.' AND fk_operation='.(int)$operationId." AND upload_status='finalized' ORDER BY rowid")as$row)$package['evidence'][]=$row;return hash('sha256',$this->canonicalJson($package));}

    private function checkpointForUpdate($operationId, $checkpointId)
    {
        $res = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_qc_checkpoint WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND rowid='.(int) $checkpointId.' FOR UPDATE');
        $row = $res ? $this->db->fetch_object($res) : null;
        if (!$row) throw new RuntimeException('QC checkpoint not found.');
        return $row;
    }

    private function requireState($operationId, array $allowed, $forUpdate)
    {
        $state = $this->planState($operationId, $forUpdate);
        if (!$state || !isset($state['state']) || !in_array($state['state'], $allowed, true)) throw new RuntimeException('QC plan state does not allow this action.');
        if (!empty($state['superseded_by_operation_id'])) throw new RuntimeException('QC plan was superseded by a linked requirement revision.');
        return $state;
    }

    private function sectionExists($operationId, $code, $forUpdate)
    {
        $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_operation_data WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND section_code='.$this->q($code);
        if ($forUpdate) $sql .= ' FOR UPDATE';
        $res = $this->db->query($sql);
        if (!$res) $this->fail();
        return $this->db->num_rows($res) > 0;
    }

    private function section($operationId, $code, $forUpdate = false)
    {
        $sql = 'SELECT data_json FROM '.MAIN_DB_PREFIX.'cpo_operation_data WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND section_code='.$this->q($code);
        if ($forUpdate) $sql .= ' FOR UPDATE';
        $res = $this->db->query($sql); $row = $res ? $this->db->fetch_object($res) : null;
        $data = $row ? json_decode((string) $row->data_json, true) : array();
        return is_array($data) ? $data : array();
    }

    private function insertSection($operationId, $code, array $data, $userId)
    {
        $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpo_operation_data(entity,fk_operation,section_code,data_json,fk_user_modif) VALUES('.$this->entity.','.(int) $operationId.','.$this->q($code).','.$this->q($this->json($data)).','.(int) $userId.')';
        if (!$this->db->query($sql)) $this->fail();
    }

    private function replaceSection($operationId, $code, array $data, $userId)
    {
        $sql = 'UPDATE '.MAIN_DB_PREFIX.'cpo_operation_data SET data_json='.$this->q($this->json($data)).',fk_user_modif='.(int) $userId.' WHERE entity='.$this->entity.' AND fk_operation='.(int) $operationId.' AND section_code='.$this->q($code);
        $update = $this->db->query($sql);
        if (!$update || $this->db->affected_rows($update) !== 1) throw new RuntimeException('QC plan state update failed.');
    }

    private function canonicalJson(array $data)
    {
        $normalize = function ($value) use (&$normalize) {
            if (!is_array($value)) return $value;
            if (array_keys($value) !== range(0, count($value) - 1)) { ksort($value, SORT_STRING); }
            foreach ($value as $key => $item) $value[$key] = $normalize($item);
            return $value;
        };
        return $this->json($normalize($data));
    }

    private function json(array $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) throw new RuntimeException('Unable to encode QC data.');
        return $json;
    }

    private function scalar(array $data, $key)
    {
        return isset($data[$key]) && !is_array($data[$key]) && !is_object($data[$key]) ? substr((string) $data[$key], 0, 20000) : '';
    }

    private function optionalText(array $data, $key, $limit)
    {
        $value = trim(isset($data[$key]) ? (string) $data[$key] : '');
        if (strlen($value) > $limit) throw new RuntimeException('QC text field is too long.');
        return $value !== '' ? $value : null;
    }

    private function nullableInt(array $data, $key)
    {
        if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null) return 'NULL';
        $value = (int) $data[$key]; if ($value < 0) throw new RuntimeException('QC counts cannot be negative.');
        return (string) $value;
    }

    private function mutationId($value)
    {
        $value = strtolower(trim((string) $value));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) throw new RuntimeException('A valid mutation UUID is required.');
        return $value;
    }

    private function receipts($json)
    {
        $rows = json_decode((string) $json, true); return is_array($rows) ? array_values($rows) : array();
    }

    private function knownReceipt(array $receipts, $mutationId)
    {
        foreach ($receipts as $receipt) if (isset($receipt['mutation_id']) && hash_equals((string) $receipt['mutation_id'], (string) $mutationId)) return $receipt;
        return null;
    }

    private function rows($sql)
    {
        $rows = array(); $res = $this->db->query($sql);
        if (!$res) return $rows;
        while ($row = $this->db->fetch_object($res)) $rows[] = get_object_vars($row);
        return $rows;
    }

    private function count($sql)
    {
        $res = $this->db->query($sql); $row = $res ? $this->db->fetch_object($res) : null;
        return $row ? (int) $row->total : 0;
    }

    private function log($operationId, $action, $message, $userId)
    {
        $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity(entity,fk_operation,fk_task,action_code,message,visibility,fk_user,date_action) VALUES('.$this->entity.','.(int) $operationId.',NULL,'.$this->q($action).','.$this->q($message).',\'internal\','.(int) $userId.','.$this->q($this->now()).')';
        if (!$this->db->query($sql)) $this->fail();
    }

    private function q($value) { return "'".$this->db->escape((string) $value)."'"; }
    private function nullable($value) { return $value === null || $value === '' ? 'NULL' : $this->q($value); }
    private function now() { return $this->db->idate(dol_now()); }
    private function fail() { throw new RuntimeException($this->db->lasterror()); }
}

class QcConflictException extends RuntimeException {}
