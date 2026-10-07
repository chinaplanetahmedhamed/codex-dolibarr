<?php
/** M099 additive registry. Call explicitly before enabling the feature; never DDL from a request. */
class ShipmentDocumentMigration
{
    private $db;
    public function __construct($db){$this->db=$db;}
    public function apply(){
        foreach(explode(';',file_get_contents(__DIR__.'/../sql/llx_cpl_shared_document.sql')) as $sql){
            if(trim($sql)==='')continue;
            if(!$this->db->query(str_replace('llx_',MAIN_DB_PREFIX,$sql)))throw new RuntimeException('Shared document installation failed.');
        }
        if(!self::ready($this->db))throw new RuntimeException('Shared document schema mismatch.');
    }
    /** Never import or serve ambiguous folders; includes other entities and sanitized legacy refs. */
    public static function assertStorageRootsUnambiguous($db,$shipmentId=0){
        $r=$db->query('SELECT rowid,ref,entity FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics');if(!$r)throw new RuntimeException('Shipment storage preflight failed.');
        $owners=[];$collisions=[];
        while($s=$db->fetch_object($r)){foreach(cpcc_portal_shipment_doc_roots($s->rowid,$s->ref) as $root){$key=realpath($root)?:rtrim($root,'/');$owner=(int)$s->rowid;
            if(isset($owners[$key])&&$owners[$key]!==$owner){$collisions[$owner]=true;$collisions[$owners[$key]]=true;}else $owners[$key]=$owner;
        }}
        if($shipmentId?isset($collisions[(int)$shipmentId]):!empty($collisions))throw new ShipmentDocumentAccessException('Ambiguous shipment document folders require operator review.');
    }
    public static function ready($db){
        foreach(['cpl_shared_document'=>'entity,fk_shipment,current_version_id,revision,status',
          'cpl_shared_document_version'=>'entity,fk_document,version_no,storage_key,file_sha256,source_origin',
          'cpl_shared_document_event'=>'entity,fk_document,fk_version,action_code',
          'cpo_task_context'=>'entity,fk_task,fk_soc,fk_project,fk_shipment,fk_cover_document,version'] as $table=>$fields){
            if(!$db->query('SELECT '.$fields.' FROM '.MAIN_DB_PREFIX.$table.' LIMIT 0'))return false;
            $r=$db->query("SHOW TABLE STATUS WHERE Name='".$db->escape(MAIN_DB_PREFIX.$table)."'");$o=$r?$db->fetch_object($r):null;
            if(!$o||strtolower($o->Engine)!=='innodb')return false;
        }
        foreach(['cpl_shared_document_version'=>[['entity','fk_document','version_no'],['entity','storage_key']], 'cpo_task_context'=>[['entity','fk_task']]] as $table=>$required){
            $r=$db->query('SHOW INDEX FROM '.MAIN_DB_PREFIX.$table);if(!$r)return false;$indexes=[];
            while($o=$db->fetch_object($r))if(!(int)$o->Non_unique)$indexes[$o->Key_name][(int)$o->Seq_in_index]=$o->Column_name;
            foreach($required as $columns){$found=false;foreach($indexes as $index){ksort($index);if(array_values($index)===$columns)$found=true;}if(!$found)return false;}
        }
        return true;
    }
}
