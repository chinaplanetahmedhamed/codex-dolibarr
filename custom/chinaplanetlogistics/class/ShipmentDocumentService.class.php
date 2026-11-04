<?php
require_once __DIR__.'/ShipmentDocumentMigration.class.php';
require_once __DIR__.'/../../chinaplanetcustomercenter/lib/portal.lib.php';
class ShipmentDocumentAccessException extends RuntimeException {}
class ShipmentDocumentConflictException extends RuntimeException {}
/** M099: one shipment-owned document, immutable stored versions, two UI adapters. */
class ShipmentDocumentService
{
    private $db; private $actor; private $entity; private $schemaVerified=false; private $storageVerified=[];
    public static function enabled(){global $conf;return !empty($conf->global->CPO_SHIPMENT_CARDS_ENABLED)||!empty($conf->global->CPL_SHARED_DOCUMENTS_ENABLED);}
    public function __construct($db,$actor,$entity=null){global $conf;$this->db=$db;$this->actor=$actor;$this->entity=$entity===null?(int)$conf->entity:(int)$entity;}
    public function ensureReady(){if(!self::enabled())throw new ShipmentDocumentAccessException('Shared shipment documents are unavailable.');if(!$this->schemaVerified){if(!ShipmentDocumentMigration::ready($this->db))throw new ShipmentDocumentAccessException('Shared shipment documents are unavailable.');$this->schemaVerified=true;}}
    private $scopes=[];
    /** Dolibarr nesting is a counter, not a savepoint. Protect callers' outer transactions explicitly. */
    private function beginScope(){
        $depth=(int)$this->db->transaction_opened;$name='m099_'.bin2hex(random_bytes(8));
        if(!$this->db->begin())throw new RuntimeException('Transaction unavailable.');
        if($depth>0&&!$this->db->query('SAVEPOINT '.$name)){$this->db->rollback();throw new RuntimeException('Transaction savepoint unavailable.');}
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
    private function query($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Shipment document operation failed.');return $r;}
    private function one($sql){$r=$this->query($sql);$o=$this->db->fetch_object($r);return $o?(array)$o:null;}
    private function rows($sql){$r=$this->query($sql);$out=[];while($o=$this->db->fetch_object($r))$out[]=(array)$o;return $out;}
    private function now(){return $this->db->idate(dol_now());}
    private function external(){return !empty($this->actor->socid)||!empty($this->actor->fk_soc);}
    private function right($name){return !empty($this->actor->admin)||$this->actor->hasRight('chinaplanetlogistics',$name)||$this->actor->hasRight('chinaplanetcustomercenter',$name);}
    private function actorReady(){
        if(empty($this->actor->id)||!method_exists($this->actor,'hasRight'))return false;
        $o=$this->one('SELECT rowid,entity,admin,fk_soc FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.(int)$this->actor->id.' AND statut=1');
        return $o&&((int)$o['entity']===$this->entity||((int)$o['entity']===0&&!empty($o['admin'])&&!empty($this->actor->admin)))
            && (int)$o['fk_soc']===(int)($this->actor->socid??$this->actor->fk_soc??0);
    }
    public function requireShipment($id,$action='read'){
        $this->ensureReady();if(!$this->actorReady())throw new ShipmentDocumentAccessException('Shipment access denied.');
        $s=$this->one('SELECT rowid,ref,entity,fk_soc,fk_project FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.$this->entity.' AND rowid='.(int)$id);
        if(!$s)throw new ShipmentDocumentAccessException('Shipment access denied.');if(empty($this->storageVerified[(int)$id])){ShipmentDocumentMigration::assertStorageRootsUnambiguous($this->db,(int)$id);$this->storageVerified[(int)$id]=true;}
        if($this->external()){
            if((int)$s['fk_soc']!==(int)($this->actor->socid??$this->actor->fk_soc??0)||!($this->actor->hasRight('chinaplanetcustomercenter','read')||$this->actor->hasRight('chinaplanetlogistics','read'))||!in_array($action,['read','customer_upload'],true))throw new ShipmentDocumentAccessException('Shipment access denied.');
        }else {
            $this->requireCustomer($s['fk_soc']);if(!empty($s['fk_project']))$this->requireProject($s['fk_project'],$s['fk_soc']);
            if($action==='read'){
                if(!$this->right('read'))throw new ShipmentDocumentAccessException('Shipment access denied.');
            }elseif(!$this->right($action==='archive'?'delete':'write'))throw new ShipmentDocumentAccessException('Shipment access denied.');
        }
        return $s;
    }
    public function requireCustomer($id){
        if(!$this->actorReady()||$this->external()||(!$this->actor->admin&&!$this->actor->hasRight('societe','lire')))throw new ShipmentDocumentAccessException('Customer access denied.');
        $sql='SELECT rowid FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid='.(int)$id.' AND s.entity='.$this->entity.'';
        if(empty($this->actor->admin)&&!$this->actor->hasRight('societe','client','voir'))$sql.=' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_commerciaux sc WHERE sc.fk_soc=s.rowid AND sc.fk_user='.(int)$this->actor->id.')';
        if(!$this->one($sql))throw new ShipmentDocumentAccessException('Customer access denied.');
    }
    public function requireProject($id,$customerId){
        if(!$this->actorReady()||$this->external()||(empty($this->actor->admin)&&!$this->actor->hasRight('projet','lire')&&!$this->actor->hasRight('projet','all','lire')))throw new ShipmentDocumentAccessException('Project access denied.');
        if(!$this->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.$this->entity.' AND rowid='.(int)$id.' AND fk_soc='.(int)$customerId))throw new ShipmentDocumentAccessException('Project access denied.');
        if(empty($this->actor->admin)&&!$this->actor->hasRight('projet','all','lire')){require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';$p=new Project($this->db);$allowed=$p->getProjectsAuthorizedForUser($this->actor,0,0);if(!isset($allowed[(int)$id]))throw new ShipmentDocumentAccessException('Project access denied.');}
    }
    public function can($shipmentId,$action){try{$this->requireShipment($shipmentId,$action);return true;}catch(ShipmentDocumentAccessException $e){return false;}}
    public function document($id,$action='read',$archived=false){
        $this->ensureReady();$d=$this->one('SELECT d.*,v.rowid version_id,v.version_no,v.storage_key,v.original_name,v.mime_type,v.file_size,v.file_sha256,v.item_kind,v.source_origin FROM '.MAIN_DB_PREFIX.'cpl_shared_document d JOIN '.MAIN_DB_PREFIX.'cpl_shared_document_version v ON v.rowid=d.current_version_id AND v.fk_document=d.rowid AND v.entity=d.entity WHERE d.entity='.$this->entity.' AND d.rowid='.(int)$id);
        if(!$d||(!$archived&&$d['status']!=='active'))throw new ShipmentDocumentAccessException('Shipment document access denied.');
        $this->requireShipment($d['fk_shipment'],$action);
        if($this->external())throw new ShipmentDocumentAccessException('Use the published customer document.');
        return $d;
    }
    public function documents($shipmentId,$archived=false){
        $this->requireShipment($shipmentId);if($this->external())throw new ShipmentDocumentAccessException('Use the published customer document.');
        return $this->rows('SELECT d.*,v.rowid version_id,v.version_no,v.storage_key,v.original_name,v.mime_type,v.file_size,v.file_sha256,v.item_kind,v.source_origin FROM '.MAIN_DB_PREFIX.'cpl_shared_document d JOIN '.MAIN_DB_PREFIX.'cpl_shared_document_version v ON v.rowid=d.current_version_id AND v.entity=d.entity AND v.fk_document=d.rowid WHERE d.entity='.$this->entity.' AND d.fk_shipment='.(int)$shipmentId.($archived?'':" AND d.status='active'").' ORDER BY d.rowid DESC');
    }
    /** Internal path lookup for existing UI routes. Unknown/unregistered paths fail closed. */
    public function byStorage($shipmentId,$key,$currentOnly=true){
        $s=$this->requireShipment($shipmentId);$path=cpcc_portal_resolve_shipment_doc_path($shipmentId,$s['ref'],$key);
        if($path==='')throw new ShipmentDocumentAccessException('Shipment document access denied.');
        $key=cpcc_portal_gallery_storage_key($path);
        $d=$this->one('SELECT d.rowid,d.current_version_id,d.status,v.rowid version_id FROM '.MAIN_DB_PREFIX.'cpl_shared_document d JOIN '.MAIN_DB_PREFIX.'cpl_shared_document_version v ON v.fk_document=d.rowid AND v.entity=d.entity WHERE d.entity='.$this->entity.' AND d.fk_shipment='.(int)$shipmentId.' AND v.storage_key='.$this->q($key));
        if(!$d||($currentOnly&&($d['status']!=='active'||(int)$d['current_version_id']!==(int)$d['version_id'])))throw new ShipmentDocumentAccessException('Shipment document access denied.');
        return $this->document($d['rowid'],'read',!$currentOnly);
    }
    private function lockShipment($id){$r=$this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.$this->entity.' AND rowid='.(int)$id.' FOR UPDATE');if(!$this->db->fetch_object($r))throw new ShipmentDocumentAccessException('Shipment access denied.');}
    private function lockDocument($id,$revision,$action='write',$archived=false){
        $d=$this->document($id,$action,$archived);$this->lockShipment($d['fk_shipment']);
        $l=$this->one('SELECT revision,status,current_version_id FROM '.MAIN_DB_PREFIX.'cpl_shared_document WHERE entity='.$this->entity.' AND rowid='.(int)$id.' FOR UPDATE');
        if(!$l||(int)$l['revision']!==(int)$revision)throw new ShipmentDocumentConflictException('This document changed. Reload and retry.');
        return $this->document($id,$action,$archived);
    }
    private function event($documentId,$versionId,$action){$this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpl_shared_document_event(entity,fk_document,fk_version,action_code,fk_user,date_creation) VALUES('.$this->entity.','.(int)$documentId.','.((int)$versionId?:'NULL').','.$this->q($action).','.(int)$this->actor->id.','.$this->q($this->now()).')');}
    private function insertVersion($documentId,$number,$key,$name,$meta,$origin,$created=null){
        $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpl_shared_document_version(entity,fk_document,version_no,storage_key,original_name,mime_type,file_size,file_sha256,item_kind,source_origin,fk_user_creat,date_creation) VALUES('.$this->entity.','.(int)$documentId.','.(int)$number.','.$this->q($key).','.$this->q($name).','.$this->q($meta['mime']).','.(int)$meta['size'].','.$this->q($meta['sha256']).','.$this->q($meta['kind']).','.$this->q($origin).','.(int)$this->actor->id.','.$this->q($created?:$this->now()).')');
        return (int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpl_shared_document_version');
    }
    /** Import metadata only. Existing bytes, paths, publication IDs and confirmations are untouched. */
    public function registerExisting($shipmentId,$key){
        $s=$this->requireShipment($shipmentId,'write');$path=cpcc_portal_resolve_shipment_doc_path($shipmentId,$s['ref'],$key);
        if($path===''||is_link($path))throw new ShipmentDocumentAccessException('Shipment document access denied.');
        $key=cpcc_portal_gallery_storage_key($path);$meta=cpcc_portal_gallery_file_metadata((object)$s,$key,cpcc_portal_staff_upload_max_bytes());
        if(empty($meta['ok']))throw new RuntimeException('Existing document could not be registered.');
        $this->beginScope();try{
            $this->lockShipment($shipmentId);
            $existing=$this->one('SELECT v.fk_document,d.fk_shipment FROM '.MAIN_DB_PREFIX.'cpl_shared_document_version v JOIN '.MAIN_DB_PREFIX.'cpl_shared_document d ON d.rowid=v.fk_document AND d.entity=v.entity WHERE v.entity='.$this->entity.' AND v.storage_key='.$this->q($key));
            if($existing&&(int)$existing['fk_shipment']!==(int)$shipmentId)throw new ShipmentDocumentAccessException('Document storage ownership conflict.');
            if($existing){$this->commitScope();return (int)$existing['fk_document'];}
            $pub=$this->one('SELECT source_origin FROM '.MAIN_DB_PREFIX.'cpl_shipment_public_document WHERE entity='.$this->entity.' AND fk_shipment='.(int)$shipmentId.' AND storage_key='.$this->q($key));
            $origin=$pub&&$pub['source_origin']==='customer'?'customer':'staff';$created=$this->db->idate(filemtime($path));
            $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpl_shared_document(entity,fk_shipment,display_name,fk_user_creat,fk_user_modif,date_creation) VALUES('.$this->entity.','.(int)$shipmentId.','.$this->q($meta['original_name']).','.(int)$this->actor->id.','.(int)$this->actor->id.','.$this->q($created).')');
            $id=(int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpl_shared_document');
            $v=$this->insertVersion($id,1,$key,$meta['original_name'],['mime'=>$meta['mime_type'],'size'=>$meta['file_size'],'sha256'=>$meta['file_sha256'],'kind'=>$meta['item_kind']],$origin,$created);
            $this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shared_document SET current_version_id='.$v.' WHERE entity='.$this->entity.' AND rowid='.$id);$this->event($id,$v,'registered');$this->commitScope();return $id;
        }catch(Throwable $e){$this->rollbackScope();throw $e;}
    }
    protected function validUploadedSource($path){return is_uploaded_file($path);}
    protected function moveUploadedSource($from,$to){return move_uploaded_file($from,$to);}
    public function upload($shipmentId,array $file,$replaceId=0,$revision=0,$maxBytes=0){
        $this->requireShipment($shipmentId,$this->external()?'customer_upload':'write');
        if($this->external()&&$replaceId)throw new ShipmentDocumentAccessException('Shipment access denied.');
        if(in_array((int)($file['error']??UPLOAD_ERR_NO_FILE),[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new LengthException('File exceeds the upload limit.');
        if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||empty($file['tmp_name'])||!$this->validUploadedSource($file['tmp_name']))throw new RuntimeException('Upload was rejected.');
        $limit=$this->external()?cpcc_portal_upload_max_bytes():cpcc_portal_staff_upload_max_bytes();if($maxBytes>0)$limit=min($limit,(int)$maxBytes);
        $meta=cpcc_portal_validate_shipment_upload($file['name']??'',$file['tmp_name'],$file['size']??0,$limit);
        if(empty($meta['ok'])){if(is_file($file['tmp_name'])&&filesize($file['tmp_name'])>$limit)throw new LengthException('File exceeds the upload limit.');throw new RuntimeException('File content or name is not allowed.');}
        $root=DOL_DATA_ROOT.'/chinaplanetlogistics';$dir=cpcc_portal_shipment_doc_root($shipmentId);
        if((!is_dir($root)&&!dol_mkdir($root))||(!is_dir($dir)&&!dol_mkdir($dir))||is_link($dir)||!cpcc_portal_path_is_within($dir,$root))throw new RuntimeException('Document storage is unavailable.');
        $stored='DOC-'.bin2hex(random_bytes(20)).'.'.strtolower(pathinfo($meta['name'],PATHINFO_EXTENSION));$target=$dir.'/'.$stored;$key=basename($dir).'/'.$stored;
        $moved=false;$this->beginScope();try{
            $this->lockShipment($shipmentId);$old=null;
            if($replaceId){$old=$this->lockDocument($replaceId,$revision);if((int)$old['fk_shipment']!==(int)$shipmentId)throw new ShipmentDocumentAccessException('Shipment document access denied.');$id=(int)$replaceId;$number=(int)$old['version_no']+1;}
            else{$this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpl_shared_document(entity,fk_shipment,display_name,fk_user_creat,fk_user_modif,date_creation) VALUES('.$this->entity.','.(int)$shipmentId.','.$this->q($meta['name']).','.(int)$this->actor->id.','.(int)$this->actor->id.','.$this->q($this->now()).')');$id=(int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpl_shared_document');$number=1;}
            if(file_exists($target)||!$this->moveUploadedSource($file['tmp_name'],$target))throw new RuntimeException('Document could not be stored.');$moved=true;if(!chmod($target,0640))throw new RuntimeException('Document permissions could not be set.');
            $hash=hash_file('sha256',$target);if(!$hash)throw new RuntimeException('Document fingerprint failed.');
            $v=$this->insertVersion($id,$number,$key,$meta['name'],['mime'=>$meta['mime'],'size'=>$meta['size'],'sha256'=>$hash,'kind'=>cpcc_portal_gallery_item_kind($meta['mime'])],$this->external()?'customer':'staff');
            $this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shared_document SET current_version_id='.$v.',display_name='.$this->q($meta['name']).',revision=revision+'.($old?1:0).',fk_user_modif='.(int)$this->actor->id.' WHERE entity='.$this->entity.' AND rowid='.$id);
            // Old publication continues to serve immutable old bytes until explicit new publication.
            $this->event($id,$v,$old?'replaced':'uploaded');$this->commitScope();
            return ['id'=>$id,'versionId'=>$v,'version'=>$number,'revision'=>$old?(int)$old['revision']+1:1,'file'=>$stored,'storageKey'=>$key];
        }catch(Throwable $e){$this->rollbackScope();if($moved&&is_file($target)&&!unlink($target))throw new RuntimeException('Document cleanup requires operator attention.',0,$e);throw $e;}
    }
    public function rename($id,$revision,$name){
        $name=cpcc_portal_validate_shipment_filename($name);if($name==='')throw new RuntimeException('File name is not allowed.');
        $this->beginScope();try{$d=$this->lockDocument($id,$revision);if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!==strtolower(pathinfo($d['original_name'],PATHINFO_EXTENSION)))throw new RuntimeException('Keep the document file extension.');
            $this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shared_document SET display_name='.$this->q($name).',revision=revision+1,fk_user_modif='.(int)$this->actor->id.' WHERE entity='.$this->entity.' AND rowid='.(int)$id);
            $this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shipment_public_document SET original_name='.$this->q($name).',fk_user_modif='.(int)$this->actor->id.' WHERE entity='.$this->entity.' AND fk_shipment='.(int)$d['fk_shipment'].' AND storage_key='.$this->q($d['storage_key']));
            $this->event($id,$d['version_id'],'renamed');$this->commitScope();return (int)$revision+1;
        }catch(Throwable $e){$this->rollbackScope();throw $e;}
    }
    public function archive($id,$revision,$restore=false){
        $this->beginScope();try{$d=$this->lockDocument($id,$revision,'archive',true);$expected=$restore?'archived':'active';if($d['status']!==$expected)throw new ShipmentDocumentConflictException('Document state changed. Reload and retry.');
            $this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shared_document SET status='.$this->q($restore?'active':'archived').',revision=revision+1,fk_user_modif='.(int)$this->actor->id.' WHERE entity='.$this->entity.' AND rowid='.(int)$id);
            if(!$restore)$this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shipment_public_document p JOIN '.MAIN_DB_PREFIX.'cpl_shared_document_version v ON v.storage_key=p.storage_key AND v.entity=p.entity SET p.status=0,p.customer_visible=0,p.fk_user_modif='.(int)$this->actor->id.' WHERE p.entity='.$this->entity.' AND p.fk_shipment='.(int)$d['fk_shipment'].' AND v.fk_document='.(int)$id);
            $this->event($id,$d['version_id'],$restore?'restored':'archived');$this->commitScope();return (int)$revision+1;
        }catch(Throwable $e){$this->rollbackScope();throw $e;}
    }
    public function versions($id){$d=$this->document($id,'read',true);return $this->rows('SELECT rowid,version_no,original_name,mime_type,file_size,file_sha256,item_kind,date_creation FROM '.MAIN_DB_PREFIX.'cpl_shared_document_version WHERE entity='.$this->entity.' AND fk_document='.(int)$id.' ORDER BY version_no DESC');}
    public function content($id,$versionId=0){
        $d=$this->document($id,'read',$versionId>0);$v=$versionId?$this->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpl_shared_document_version WHERE entity='.$this->entity.' AND fk_document='.(int)$id.' AND rowid='.(int)$versionId):$d;
        if(!$v)throw new ShipmentDocumentAccessException('Shipment document access denied.');$s=$this->requireShipment($d['fk_shipment']);$path=cpcc_portal_resolve_shipment_doc_path($d['fk_shipment'],$s['ref'],$v['storage_key']);
        if($path===''||is_link($path)||(int)filesize($path)!==(int)$v['file_size']||!hash_equals($v['file_sha256'],(string)hash_file('sha256',$path)))throw new ShipmentDocumentAccessException('Shipment document access denied.');
        return ['path'=>$path,'name'=>$versionId?$v['original_name']:$d['display_name'],'mime'=>$v['mime_type'],'size'=>(int)$v['file_size'],'kind'=>$v['item_kind']];
    }
    public function publicationState($id){
        $d=$this->document($id,'read',true);$rows=$this->rows('SELECT v.rowid version_id,v.version_no,p.rowid publication_id,p.customer_visible,p.status,p.response_status FROM '.MAIN_DB_PREFIX.'cpl_shared_document_version v JOIN '.MAIN_DB_PREFIX.'cpl_shipment_public_document p ON p.entity=v.entity AND p.storage_key=v.storage_key AND p.fk_shipment='.(int)$d['fk_shipment'].' WHERE v.entity='.$this->entity.' AND v.fk_document='.(int)$id.' AND p.customer_visible=1 AND p.status=1 ORDER BY v.version_no DESC');
        return $rows;
    }
    public function publishStorage($shipmentId,$key,$origin='staff'){
        $s=$this->requireShipment($shipmentId,$this->external()?'customer_upload':'write');
        $path=cpcc_portal_resolve_shipment_doc_path($shipmentId,$s['ref'],$key);if($path==='')throw new ShipmentDocumentAccessException('Shipment document access denied.');$key=cpcc_portal_gallery_storage_key($path);
        $r=$this->one('SELECT d.rowid,d.current_version_id,d.status,v.rowid version_id,v.source_origin,v.fk_user_creat FROM '.MAIN_DB_PREFIX.'cpl_shared_document d JOIN '.MAIN_DB_PREFIX.'cpl_shared_document_version v ON v.fk_document=d.rowid AND v.entity=d.entity WHERE d.entity='.$this->entity.' AND d.fk_shipment='.(int)$shipmentId.' AND v.storage_key='.$this->q($key));
        if(!$r||$r['status']!=='active'||(int)$r['current_version_id']!==(int)$r['version_id']||($this->external()&&($r['source_origin']!=='customer'||(int)$r['fk_user_creat']!==(int)$this->actor->id||$origin!=='customer')))throw new ShipmentDocumentAccessException('Shipment document access denied.');
        $this->beginScope();try{$this->lockShipment($shipmentId);
            $fresh=$this->one('SELECT current_version_id,status FROM '.MAIN_DB_PREFIX.'cpl_shared_document WHERE entity='.$this->entity.' AND rowid='.(int)$r['rowid'].' FOR UPDATE');if(!$fresh||$fresh['status']!=='active'||(int)$fresh['current_version_id']!==(int)$r['version_id'])throw new ShipmentDocumentConflictException('Document changed. Reload and retry.');
            $this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shipment_public_document p JOIN '.MAIN_DB_PREFIX.'cpl_shared_document_version v ON v.entity=p.entity AND v.storage_key=p.storage_key SET p.customer_visible=0,p.status=0,p.fk_user_modif='.(int)$this->actor->id.' WHERE p.entity='.$this->entity.' AND p.fk_shipment='.(int)$shipmentId.' AND v.fk_document='.(int)$r['rowid'].' AND v.rowid<>'.(int)$r['version_id']);
            $previous=$this->one('SELECT * FROM '.MAIN_DB_PREFIX.'cpl_shipment_public_document WHERE entity='.$this->entity.' AND fk_shipment='.(int)$shipmentId.' AND storage_key='.$this->q($key));
            if($previous&&!empty($previous['status'])&&!empty($previous['customer_visible'])){$this->commitScope();return ['ok'=>true,'id'=>(int)$previous['rowid'],'error'=>''];}
            $result=cpcc_portal_gallery_publish_legacy($this->db,(object)$s,$this->actor,$key,$origin);if(empty($result['ok']))throw new RuntimeException('Document publication failed.');
            if($previous){$sets=[];foreach(['response_status','customer_upload_status','response_note','fk_user_response','date_response'] as $field)if(array_key_exists($field,$previous))$sets[]=$field.'='.($previous[$field]===null?'NULL':$this->q($previous[$field]));if($sets)$this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shipment_public_document SET '.implode(',',$sets).' WHERE entity='.$this->entity.' AND rowid='.(int)$result['id']);}

            $d=$this->one('SELECT display_name FROM '.MAIN_DB_PREFIX.'cpl_shared_document WHERE rowid='.(int)$r['rowid'].' AND entity='.$this->entity);
            $this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shipment_public_document SET original_name='.$this->q($d['display_name']).' WHERE rowid='.(int)$result['id'].' AND entity='.$this->entity);
            $this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shared_document SET revision=revision+1,fk_user_modif='.(int)$this->actor->id.' WHERE entity='.$this->entity.' AND rowid='.(int)$r['rowid']);
            $this->event($r['rowid'],$r['version_id'],'published');$this->commitScope();return $result;
        }catch(Throwable $e){$this->rollbackScope();throw $e;}
    }
    public function publishDocument($id,$revision){
        $this->beginScope();try{$d=$this->lockDocument($id,$revision);$out=$this->publishStorage($d['fk_shipment'],$d['storage_key'],'staff');$this->commitScope();return $out;}catch(Throwable $e){$this->rollbackScope();throw $e;}
    }
    public function unpublishDocument($id,$revision){
        $this->beginScope();try{$d=$this->lockDocument($id,$revision);$this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shipment_public_document p JOIN '.MAIN_DB_PREFIX.'cpl_shared_document_version v ON v.storage_key=p.storage_key AND v.entity=p.entity SET p.status=0,p.customer_visible=0,p.fk_user_modif='.(int)$this->actor->id.' WHERE p.entity='.$this->entity.' AND p.fk_shipment='.(int)$d['fk_shipment'].' AND v.fk_document='.(int)$id);$this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shared_document SET revision=revision+1 WHERE entity='.$this->entity.' AND rowid='.(int)$id);$this->event($id,$d['version_id'],'unpublished');$this->commitScope();return ['revision'=>(int)$revision+1];}catch(Throwable $e){$this->rollbackScope();throw $e;}
    }
    public function unpublishPublication($shipmentId,$publicationId){
        $this->requireShipment($shipmentId,$this->external()?'customer_upload':'write');
        $p=$this->one('SELECT p.rowid,v.fk_document,v.rowid version_id,v.source_origin,v.fk_user_creat FROM '.MAIN_DB_PREFIX.'cpl_shipment_public_document p JOIN '.MAIN_DB_PREFIX.'cpl_shared_document_version v ON v.storage_key=p.storage_key AND v.entity=p.entity JOIN '.MAIN_DB_PREFIX.'cpl_shared_document d ON d.rowid=v.fk_document AND d.entity=v.entity AND d.fk_shipment=p.fk_shipment WHERE p.entity='.$this->entity.' AND p.fk_shipment='.(int)$shipmentId.' AND p.rowid='.(int)$publicationId);
        if(!$p||($this->external()&&($p['source_origin']!=='customer'||(int)$p['fk_user_creat']!==(int)$this->actor->id)))throw new ShipmentDocumentAccessException('Publication access denied.');
        $this->beginScope();try{$this->lockShipment($shipmentId);$this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shipment_public_document SET status=0,customer_visible=0,fk_user_modif='.(int)$this->actor->id.' WHERE entity='.$this->entity.' AND rowid='.(int)$publicationId);$this->query('UPDATE '.MAIN_DB_PREFIX.'cpl_shared_document SET revision=revision+1 WHERE entity='.$this->entity.' AND rowid='.(int)$p['fk_document']);$this->event($p['fk_document'],$p['version_id'],'unpublished');$this->commitScope();return ['ok'=>true,'error'=>''];}catch(Throwable $e){$this->rollbackScope();throw $e;}
    }
    /** Display-only event stream; customer comments are not copied into staff task activity. */
    public function events($shipmentId){$this->requireShipment($shipmentId);if($this->external())throw new ShipmentDocumentAccessException('Shipment access denied.');return $this->rows('SELECT e.rowid,e.fk_document document_id,e.fk_version version_id,e.action_code,e.date_creation,u.firstname,u.lastname FROM '.MAIN_DB_PREFIX.'cpl_shared_document_event e JOIN '.MAIN_DB_PREFIX.'cpl_shared_document d ON d.rowid=e.fk_document AND d.entity=e.entity LEFT JOIN '.MAIN_DB_PREFIX.'user u ON u.rowid=e.fk_user AND (u.entity=e.entity OR (u.entity=0 AND u.admin=1)) AND u.statut=1 WHERE e.entity='.$this->entity.' AND d.fk_shipment='.(int)$shipmentId.' ORDER BY e.rowid DESC LIMIT 50');}
}
