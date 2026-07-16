<?php

class OperationsService
{
    private $db;
    private $entity;

    public function __construct($db)
    {
        global $conf;
        $this->db = $db;
        $this->entity = (int) $conf->entity;
    }

    public static function serviceTypes()
    {
        return array('customer_intake','order','sourcing','purchasing','factory_audit','quality_control','shipping_inquiry','shipping_booking','loading','customs','claim','general');
    }

    public static function stages()
    {
        return array('new'=>0,'review'=>10,'waiting_information'=>20,'planned'=>30,'in_progress'=>50,'waiting_external'=>60,'reporting'=>75,'supervisor_review'=>85,'ready_to_publish'=>90,'published'=>95,'corrective_action'=>80,'completed'=>100,'on_hold'=>40,'cancelled'=>0);
    }

    public static function taskStatuses()
    {
        return array('todo'=>0,'in_progress'=>50,'waiting'=>50,'review'=>85,'done'=>100,'cancelled'=>0);
    }

    public static function serviceFields($type)
    {
        $common = array('customer_contact','contact_channel','contact_links','customer_requirements');
        $map = array(
            'order'=>array('supplier','product_summary','quantity','target_price','order_value','currency','payment_terms','production_deadline','customer_warnings'),
            'factory_audit'=>array('factory_name','factory_address','audit_mode','audit_scope','audit_date','business_license','certificates','production_capacity','quality_system','social_compliance','findings','advice','report_status'),
            'quality_control'=>array('factory_name','product_name','model_no','product_details','customer_notes','customer_warnings','inspection_standard','aql_level','sample_size','critical_limit','major_limit','minor_limit','inspection_result','corrective_action'),
            'shipping_inquiry'=>array('pol','pod','destination_country','shipping_line','container_type','incoterm','cargo_type','cargo_weight','cargo_cbm','dangerous_goods','required_services'),
            'shipping_booking'=>array('forwarder','shipper','consignee','notify_party','pol','pod','final_destination','etd','eta','incoterm','load_type','container_request','cargo_summary','cartons','net_weight','gross_weight','cbm','hs_code','dangerous_goods','free_time','shipping_order_no','carrier_booking_no','shipping_line','vessel','voyage','cutoff_si','cutoff_cy','bl_no','mbl_no','hbl_no','container_numbers','seal_numbers','booking_status','document_status','loading_status','financial_status','original_document_status'),
            'loading'=>array('warehouse','loading_date','container_numbers','seal_numbers','cartons','gross_weight','cbm','loading_status','evidence_status'),
            'customs'=>array('country','platform_name','external_reference','submission_status','submission_date','completion_date','proof_reference'),
            'claim'=>array('claim_type','incident_date','amount','currency','responsible_party','evidence_summary','resolution'),
        );
        return array_values(array_unique(array_merge($common, isset($map[$type]) ? $map[$type] : array('details','result'))));
    }

    public static function bookingRequestFields()
    {
        return array('customer_contact','contact_channel','contact_links','customer_requirements','forwarder','shipper','consignee','notify_party','pol','pod','final_destination','etd','eta','incoterm','load_type','container_request','cargo_summary','cartons','net_weight','gross_weight','cbm','hs_code','dangerous_goods','free_time');
    }

    public static function bookingConfirmationFields()
    {
        return array('shipping_order_no','carrier_booking_no','shipping_line','vessel','voyage','cutoff_si','cutoff_cy','bl_no','mbl_no','hbl_no','container_numbers','seal_numbers','booking_status','document_status','loading_status','financial_status','original_document_status');
    }

    public static function fieldOptions($field)
    {
        $options=array(
            'dangerous_goods'=>array('unknown','no','yes'),
            'audit_mode'=>array('online','onsite','combined'),
            'load_type'=>array('FCL','LCL'),
            'booking_status'=>array('draft','sent','under_review','changes_requested','accepted','confirmed','cancelled'),
            'document_status'=>array('not_received','draft_received','under_review','modification_requested','revised_received','approved','final_received'),
            'loading_status'=>array('waiting','container_assigned','loading_in_progress','loading_completed','evidence_received'),
            'financial_status'=>array('debit_note_not_received','debit_note_received','under_review','approved_for_payment','partially_paid','paid','payment_receipt_attached','payment_confirmed'),
            'original_document_status'=>array('being_prepared','electronic_available','platform_pending','platform_completed','originals_ready','sent_by_dhl','delivered','customer_confirmed'),
        );
        return isset($options[$field])?$options[$field]:array();
    }

    public function create(array $data, $userId)
    {
        $type = in_array($data['service_type'], self::serviceTypes(), true) ? $data['service_type'] : 'general';
        $this->db->begin();
        $ref = $this->nextRef($type);
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_operation(entity,ref,service_type,title,fk_soc,fk_project,fk_commande,fk_user_assign,priority,stage,progress,waiting_on,next_action,date_deadline,note_private,note_public,customer_visible,status,fk_user_creat,date_creation) VALUES(".
            $this->entity.",".$this->q($ref).",".$this->q($type).",".$this->q($data['title']).",".$this->n($data,'fk_soc').",".$this->n($data,'fk_project').",".$this->n($data,'fk_commande').",".$this->n($data,'fk_user_assign').",".$this->q($data['priority'] ?: 'normal').",'new',0,".$this->q($data['waiting_on'] ?: 'internal').",".$this->q($data['next_action']).",".$this->dateOrNull($data['date_deadline']).",".$this->q($data['note_private']).",".$this->q($data['note_public']).",".(!empty($data['customer_visible'])?1:0).",1,".(int)$userId.",".$this->q($this->now()).")";
        if (!$this->db->query($sql)) $this->fail();
        $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_operation');
        $this->saveSection($id, 'service', isset($data['service_data']) ? $data['service_data'] : array(), $userId, false);
        $this->log($id, 0, 'created', 'Operation created', 'internal', $userId, false);
        $this->createDefaultTasks($id, $type, $data, $userId);
        $this->db->commit();
        return $id;
    }

    public function update($id, array $data, $userId)
    {
        $operation = $this->get($id);
        if (!$operation) throw new RuntimeException('Operation not found');
        $stage = isset(self::stages()[$data['stage']]) ? $data['stage'] : $operation['stage'];
        $progress = self::stages()[$stage];
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_operation SET title=".$this->q($data['title']).",fk_soc=".$this->n($data,'fk_soc').",fk_project=".$this->n($data,'fk_project').",fk_commande=".$this->n($data,'fk_commande').",fk_user_assign=".$this->n($data,'fk_user_assign').",priority=".$this->q($data['priority']).",stage=".$this->q($stage).",progress=".(int)$progress.",waiting_on=".$this->q($data['waiting_on']).",next_action=".$this->q($data['next_action']).",date_deadline=".$this->dateOrNull($data['date_deadline']).",note_private=".$this->q($data['note_private']).",note_public=".$this->q($data['note_public']).",customer_visible=".(!empty($data['customer_visible'])?1:0).",fk_user_modif=".(int)$userId." WHERE rowid=".(int)$id." AND entity=".$this->entity;
        if (!$this->db->query($sql)) $this->fail();
        $this->saveSection($id, 'service', isset($data['service_data']) ? $data['service_data'] : array(), $userId);
        $this->log($id, 0, 'updated', 'Stage: '.$stage, 'internal', $userId);
        return true;
    }

    public function get($id)
    {
        $sql = "SELECT o.*,s.nom customer_name,u.login owner_login FROM ".MAIN_DB_PREFIX."cpo_operation o LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=o.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=o.fk_user_assign WHERE o.entity=".$this->entity." AND o.rowid=".(int)$id;
        $res = $this->db->query($sql); $obj = $res ? $this->db->fetch_object($res) : null;
        if (!$obj) return null;
        $row = get_object_vars($obj);
        $row['service_data'] = $this->getSection($id, 'service');
        return $row;
    }

    public function search(array $filters = array())
    {
        $where = array('o.entity='.$this->entity, 'o.status=1');
        if (!empty($filters['stage'])) $where[] = 'o.stage='.$this->q($filters['stage']);
        if (!empty($filters['service_type'])) $where[] = 'o.service_type='.$this->q($filters['service_type']);
        if (!empty($filters['owner'])) $where[] = 'o.fk_user_assign='.(int)$filters['owner'];
        if (!empty($filters['q'])) { $q='%'.$filters['q'].'%'; $where[]='(o.ref LIKE '.$this->q($q).' OR o.title LIKE '.$this->q($q).' OR s.nom LIKE '.$this->q($q).' OR o.next_action LIKE '.$this->q($q).')'; }
        $sql = "SELECT o.*,s.nom customer_name,u.login owner_login FROM ".MAIN_DB_PREFIX."cpo_operation o LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=o.fk_soc LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=o.fk_user_assign WHERE ".implode(' AND ',$where)." ORDER BY FIELD(o.priority,'urgent','high','normal','low'),o.date_deadline IS NULL,o.date_deadline,o.tms DESC LIMIT 500";
        return $this->rows($sql);
    }

    public function stats()
    {
        $stats = array('active'=>0,'overdue'=>0,'waiting'=>0,'tasks'=>0);
        $res=$this->db->query("SELECT COUNT(*) active,SUM(date_deadline<NOW() AND stage NOT IN ('completed','cancelled')) overdue,SUM(stage IN ('waiting_information','waiting_external')) waiting FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".$this->entity." AND status=1");
        if($res&&($o=$this->db->fetch_object($res))){$stats['active']=(int)$o->active;$stats['overdue']=(int)$o->overdue;$stats['waiting']=(int)$o->waiting;}
        $res=$this->db->query("SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."cpo_task WHERE entity=".$this->entity." AND status NOT IN ('done','cancelled')"); if($res&&($o=$this->db->fetch_object($res)))$stats['tasks']=(int)$o->total;
        return $stats;
    }

    public function createTask(array $data, $userId)
    {
        $ref=$this->nextTaskRef(); $status=isset(self::taskStatuses()[$data['status']])?$data['status']:'todo';
        $sql="INSERT INTO ".MAIN_DB_PREFIX."cpo_task(entity,ref,fk_operation,title,description,fk_user_assign,fk_user_creat,priority,status,progress,date_deadline,private_note,position,date_creation) VALUES(".$this->entity.",".$this->q($ref).",".$this->n($data,'fk_operation').",".$this->q($data['title']).",".$this->q($data['description']).",".$this->n($data,'fk_user_assign').",".(int)$userId.",".$this->q($data['priority']?:'normal').",".$this->q($status).",".(int)self::taskStatuses()[$status].",".$this->dateOrNull($data['date_deadline']).",".$this->q($data['private_note']).",0,".$this->q($this->now()).")";
        if(!$this->db->query($sql))$this->fail(); $id=(int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_task'); $this->log((int)($data['fk_operation']??0),$id,'task_created',$data['title'],'internal',$userId); return $id;
    }

    public function moveTask($id,$status,$userId)
    {
        if(!isset(self::taskStatuses()[$status]))throw new RuntimeException('Invalid task status');
        $res=$this->db->query("SELECT fk_operation FROM ".MAIN_DB_PREFIX."cpo_task WHERE entity=".$this->entity." AND rowid=".(int)$id);$task=$res?$this->db->fetch_object($res):null;if(!$task)throw new RuntimeException('Task not found');
        $completed=$status==='done'?$this->q($this->now()):'NULL';
        if(!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_task SET status=".$this->q($status).",progress=".(int)self::taskStatuses()[$status].",date_completed=".$completed." WHERE entity=".$this->entity." AND rowid=".(int)$id))$this->fail();
        $this->log((int)$task->fk_operation,$id,'task_moved',$status,'internal',$userId); return true;
    }

    public function tasks($operationId=0)
    {
        $where='t.entity='.$this->entity; if($operationId)$where.=' AND t.fk_operation='.(int)$operationId;
        return $this->rows("SELECT t.*,o.ref operation_ref,o.title operation_title,u.login owner_login FROM ".MAIN_DB_PREFIX."cpo_task t LEFT JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=t.fk_operation AND o.entity=t.entity LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=t.fk_user_assign WHERE ".$where." ORDER BY t.position,t.date_deadline IS NULL,t.date_deadline,t.tms DESC");
    }

    public function activities($operationId){return $this->rows("SELECT a.*,u.login FROM ".MAIN_DB_PREFIX."cpo_activity a LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=a.fk_user WHERE a.entity=".$this->entity." AND a.fk_operation=".(int)$operationId." ORDER BY a.date_action DESC,a.rowid DESC LIMIT 100");}

    public function saveMessageDraft($operationId,array $data,$userId)
    {
        $sql="INSERT INTO ".MAIN_DB_PREFIX."cpo_message(entity,fk_operation,channel,direction,sender,recipients,subject,body,status,fk_user_creat,date_creation) VALUES(".$this->entity.",".(int)$operationId.",'email','outbound',".$this->q($data['sender']).",".$this->q($data['recipients']).",".$this->q($data['subject']).",".$this->q($data['body']).",'draft',".(int)$userId.",".$this->q($this->now()).")";if(!$this->db->query($sql))$this->fail();$this->log($operationId,0,'email_draft','Email draft: '.$data['subject'],'internal',$userId);return (int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_message');
    }
    public function messages($operationId){return $this->rows("SELECT m.*,u.login FROM ".MAIN_DB_PREFIX."cpo_message m LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=m.fk_user_creat WHERE m.entity=".$this->entity." AND m.fk_operation=".(int)$operationId." ORDER BY m.date_creation DESC");}

    public function addDocument($operationId, array $file, $type, $visibility, $userId)
    {
        global $conf;
        $operation=$this->get($operationId);if(!$operation)throw new RuntimeException('Operation not found');
        if(empty($file['tmp_name'])||!is_uploaded_file($file['tmp_name']))throw new RuntimeException('No valid uploaded file');
        if((int)$file['size']<=0||(int)$file['size']>50*1024*1024)throw new RuntimeException('File exceeds the 50 MB limit');
        $original=basename((string)$file['name']);$extension=strtolower(pathinfo($original,PATHINFO_EXTENSION));$allowed=array('pdf','xlsx','xls','docx','doc','jpg','jpeg','png','webp','mp4','mov','csv');if(!in_array($extension,$allowed,true))throw new RuntimeException('File type is not allowed');
        $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($file['tmp_name']);$blocked=array('text/x-php','application/x-php','text/html','application/x-httpd-php');if(in_array($mime,$blocked,true))throw new RuntimeException('Executable files are not allowed');
        $visibility=in_array($visibility,array('internal','forwarder','customer_after_approval'),true)?$visibility:'internal';$type=preg_replace('/[^a-z0-9_\-]/','',strtolower($type))?:'other';
        $this->db->begin();$res=$this->db->query("SELECT rowid,version_no FROM ".MAIN_DB_PREFIX."cpo_document WHERE entity=".$this->entity." AND fk_operation=".(int)$operationId." AND document_type=".$this->q($type)." AND is_current=1 ORDER BY version_no DESC LIMIT 1 FOR UPDATE");$parent=$res?$this->db->fetch_object($res):null;$version=$parent?(int)$parent->version_no+1:1;
        $dir=$conf->chinaplanetoperations->dir_output.'/documents/'.$this->entity.'/'.$operation['ref'];if(!is_dir($dir)&&!dol_mkdir($dir)){$this->db->rollback();throw new RuntimeException('Cannot create document directory');}$stored=bin2hex(random_bytes(16)).'.'.$extension;$target=$dir.'/'.$stored;if(!move_uploaded_file($file['tmp_name'],$target)){$this->db->rollback();throw new RuntimeException('Cannot store uploaded file');}@chmod($target,0640);
        if($parent)$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_document SET is_current=0,review_status='superseded' WHERE entity=".$this->entity." AND rowid=".(int)$parent->rowid);
        $sql="INSERT INTO ".MAIN_DB_PREFIX."cpo_document(entity,fk_operation,document_type,original_name,stored_name,mime_type,file_size,version_no,parent_document_id,visibility,review_status,is_current,fk_user_creat,date_creation) VALUES(".$this->entity.",".(int)$operationId.",".$this->q($type).",".$this->q($original).",".$this->q($stored).",".$this->q($mime).",".(int)$file['size'].",".$version.",".($parent?(int)$parent->rowid:'NULL').",".$this->q($visibility).",'under_review',1,".(int)$userId.",".$this->q($this->now()).")";if(!$this->db->query($sql)){@unlink($target);$this->fail();}$id=(int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_document');$this->log($operationId,0,'document_uploaded',$type.' v'.$version,'internal',$userId,false);$this->db->commit();return $id;
    }
    public function documents($operationId){return $this->rows("SELECT d.*,u.login uploader_login,r.login reviewer_login FROM ".MAIN_DB_PREFIX."cpo_document d LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=d.fk_user_creat LEFT JOIN ".MAIN_DB_PREFIX."user r ON r.rowid=d.fk_user_review WHERE d.entity=".$this->entity." AND d.fk_operation=".(int)$operationId." ORDER BY d.document_type,d.version_no DESC");}
    public function getDocument($documentId){$rows=$this->rows("SELECT d.*,o.ref operation_ref FROM ".MAIN_DB_PREFIX."cpo_document d JOIN ".MAIN_DB_PREFIX."cpo_operation o ON o.rowid=d.fk_operation AND o.entity=d.entity WHERE d.entity=".$this->entity." AND d.rowid=".(int)$documentId);return $rows?$rows[0]:null;}
    public function reviewDocument($documentId,$status,$comment,$userId){$allowed=array('under_review','modification_requested','approved','final_received','rejected');if(!in_array($status,$allowed,true))throw new RuntimeException('Invalid document status');$document=$this->getDocument($documentId);if(!$document)throw new RuntimeException('Document not found');if(!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_document SET review_status=".$this->q($status).",review_comment=".$this->q($comment).",fk_user_review=".(int)$userId.",date_review=".$this->q($this->now())." WHERE entity=".$this->entity." AND rowid=".(int)$documentId))$this->fail();$this->log((int)$document['fk_operation'],0,'document_reviewed',$document['document_type'].' '.$status,'internal',$userId);return true;}

    private function createDefaultTasks($operationId,$type,$data,$userId)
    {
        $templates=array(
            'factory_audit'=>array('Collect factory documents','Complete online audit','Schedule onsite visit','Upload evidence','Prepare audit report','Supervisor review'),
            'quality_control'=>array('Confirm inspection requirements','Prepare QC checklist and AQL','Confirm factory appointment','Perform inspection','Upload evidence and results','Prepare QC report','Supervisor review'),
            'shipping_booking'=>array('Validate cargo and parties','Prepare booking request','Send booking request','Receive booking confirmation','Review shipping documents','Collect loading evidence','Review debit note','Link shipment tracking','Process original/customer documents'),
            'order'=>array('Confirm customer requirements','Confirm supplier and quotation','Record payment terms','Follow production','Prepare packing list and invoice','Arrange QC/shipping'),
        );
        $items=isset($templates[$type])?$templates[$type]:array('Review requirements','Execute work','Supervisor review','Publish result');
        foreach($items as $title)$this->createTask(array('fk_operation'=>$operationId,'title'=>$title,'description'=>'','fk_user_assign'=>$data['fk_user_assign']??0,'priority'=>$data['priority']??'normal','status'=>'todo','date_deadline'=>'','private_note'=>''),$userId);
    }

    private function saveSection($id,$code,array $data,$userId,$log=true){$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$sql="INSERT INTO ".MAIN_DB_PREFIX."cpo_operation_data(entity,fk_operation,section_code,data_json,fk_user_modif) VALUES(".$this->entity.",".(int)$id.",".$this->q($code).",".$this->q($json).",".(int)$userId.") ON DUPLICATE KEY UPDATE data_json=VALUES(data_json),fk_user_modif=VALUES(fk_user_modif)";if(!$this->db->query($sql))$this->fail();}
    private function getSection($id,$code){$res=$this->db->query("SELECT data_json FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".$this->entity." AND fk_operation=".(int)$id." AND section_code=".$this->q($code));$o=$res?$this->db->fetch_object($res):null;$data=$o?json_decode($o->data_json,true):array();return is_array($data)?$data:array();}
    private function log($operationId,$taskId,$action,$message,$visibility,$userId,$transaction=true){$sql="INSERT INTO ".MAIN_DB_PREFIX."cpo_activity(entity,fk_operation,fk_task,action_code,message,visibility,fk_user,date_action) VALUES(".$this->entity.",".($operationId?(int)$operationId:'NULL').",".($taskId?(int)$taskId:'NULL').",".$this->q($action).",".$this->q($message).",".$this->q($visibility).",".(int)$userId.",".$this->q($this->now()).")";if(!$this->db->query($sql))$this->fail();}
    private function nextRef($type){$prefix=array('shipping_booking'=>'SB','factory_audit'=>'FA','quality_control'=>'QC','order'=>'OR','shipping_inquiry'=>'SI')[$type]??'OP';$year=date('Y');$like='CP-'.$prefix.'-'.$year.'-%';$res=$this->db->query("SELECT ref FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".$this->entity." AND ref LIKE ".$this->q($like)." ORDER BY ref DESC LIMIT 1 FOR UPDATE");$o=$res?$this->db->fetch_object($res):null;$number=$o?(int)substr($o->ref,-6)+1:1;return 'CP-'.$prefix.'-'.$year.'-'.str_pad((string)$number,6,'0',STR_PAD_LEFT);}
    private function nextTaskRef(){$year=date('Y');$like='CP-TK-'.$year.'-%';$res=$this->db->query("SELECT ref FROM ".MAIN_DB_PREFIX."cpo_task WHERE entity=".$this->entity." AND ref LIKE ".$this->q($like)." ORDER BY ref DESC LIMIT 1");$o=$res?$this->db->fetch_object($res):null;$number=$o?(int)substr($o->ref,-6)+1:1;return 'CP-TK-'.$year.'-'.str_pad((string)$number,6,'0',STR_PAD_LEFT);}
    private function rows($sql){$out=array();$res=$this->db->query($sql);if(!$res)return $out;while($o=$this->db->fetch_object($res))$out[]=get_object_vars($o);return $out;}
    private function q($value){return "'".$this->db->escape((string)$value)."'";}
    private function n($data,$key){return !empty($data[$key])?(int)$data[$key]:'NULL';}
    private function dateOrNull($value){if(empty($value))return 'NULL';$ts=strtotime($value);return $ts?$this->q(date('Y-m-d H:i:s',$ts)):'NULL';}
    private function now(){return $this->db->idate(dol_now());}
    private function fail(){$error=$this->db->lasterror();$this->db->rollback();throw new RuntimeException($error);}
}
