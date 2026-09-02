<?php

class PublicRequestPublicationException extends RuntimeException {}

/** Customer-safe replies/files with path containment and explicit publication. */
class PublicRequestPublicationService
{
    const MAX_BYTES = 10485760;
    private $db;
    private $entity;
    private $root;
    private $allowed = array('application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp');

    public function __construct($db, $entity = null, $root = null)
    {
        global $conf;
        $this->db=$db; $this->entity=$entity===null?(int)$conf->entity:(int)$entity;
        $this->root=$root===null?rtrim((string)$conf->chinaplanetoperations->dir_output,'/').'/public_requests':rtrim((string)$root,'/');
        if ($this->root==='') throw new PublicRequestPublicationException('publication_storage_not_configured');
    }

    public function publishReply($requestId,$body,$user)
    {
        $body=trim(strip_tags((string)$body));
        if ($body==='' || mb_strlen($body)>10000) throw new PublicRequestPublicationException('publication_reply_invalid');
        return $this->insert($requestId,'reply',$body,null,$user);
    }

    public function publishUpload($requestId,array $upload,$user)
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'] ?? '')) throw new PublicRequestPublicationException('publication_upload_invalid');
        $size=(int)($upload['size'] ?? 0); if($size<1 || $size>self::MAX_BYTES) throw new PublicRequestPublicationException('publication_upload_size');
        $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=(string)$finfo->file($upload['tmp_name']);
        if(!isset($this->allowed[$mime])) throw new PublicRequestPublicationException('publication_upload_type');
        $request=$this->request($requestId); if(!$request || empty($request['fk_soc'])) throw new PublicRequestPublicationException('qualification_required');
        $dir=$this->root.'/'.$this->entity.'/'.$request['ref'];
        if(!is_dir($dir) && dol_mkdir($dir,'','0750')<0) throw new PublicRequestPublicationException('publication_storage_failed');
        $rootReal=realpath($this->root);$dirReal=realpath($dir);
        if(!$rootReal || !$dirReal || ($dirReal!==$rootReal && strpos($dirReal,$rootReal.DIRECTORY_SEPARATOR)!==0)) throw new PublicRequestPublicationException('publication_storage_failed');
        $storage=bin2hex(random_bytes(16)).'.'.$this->allowed[$mime]; $target=$dir.'/'.$storage;
        if(!move_uploaded_file($upload['tmp_name'],$target)) throw new PublicRequestPublicationException('publication_storage_failed');
        chmod($target,0640);
        try { return $this->insert($requestId,'file','',array('original_name'=>$this->filename($upload['name'] ?? 'file'),'storage_key'=>$this->entity.'/'.$request['ref'].'/'.$storage,'mime_type'=>$mime,'file_size'=>$size,'file_sha256'=>hash_file('sha256',$target)),$user); }
        catch(Throwable $e){ @unlink($target); throw $e; }
    }

    public function customerItems($requestId,$socid)
    {
        $sql="SELECT p.public_token,p.item_type,p.body_public,p.original_name,p.mime_type,p.file_size,p.date_creation FROM ".MAIN_DB_PREFIX."cpo_public_request_publication p INNER JOIN ".MAIN_DB_PREFIX."cpo_public_request r ON r.rowid=p.fk_request AND r.entity=p.entity WHERE p.entity=".$this->entity." AND p.fk_request=".(int)$requestId." AND r.fk_soc=".(int)$socid." AND r.request_customer_visible=1 AND p.customer_visible=1 AND p.status=1 ORDER BY p.date_creation,p.rowid";
        $res=$this->db->query($sql);$rows=array();if($res)while($o=$this->db->fetch_object($res))$rows[]=(array)$o;return $rows;
    }

    public function authorizedDownload($token,$socid)
    {
        if(!preg_match('/^[a-f0-9]{32}$/',(string)$token))return null;
        $sql="SELECT p.original_name,p.storage_key,p.mime_type,p.file_size,p.file_sha256 FROM ".MAIN_DB_PREFIX."cpo_public_request_publication p INNER JOIN ".MAIN_DB_PREFIX."cpo_public_request r ON r.rowid=p.fk_request AND r.entity=p.entity WHERE p.entity=".$this->entity." AND p.public_token='".$this->db->escape($token)."' AND p.item_type='file' AND p.customer_visible=1 AND p.status=1 AND r.request_customer_visible=1 AND r.fk_soc=".(int)$socid." LIMIT 1";
        $res=$this->db->query($sql);$o=$res?$this->db->fetch_object($res):null;if(!$o)return null;
        $relative=(string)$o->storage_key;if($relative==='' || strpos($relative,'..')!==false || $relative[0]==='/' || !preg_match('#^[A-Za-z0-9._/-]+$#',$relative))return null;
        $path=$this->root.'/'.$relative;$rootReal=realpath($this->root);$fileReal=realpath($path);
        if(!$rootReal || !$fileReal || !is_file($fileReal) || strpos($fileReal,$rootReal.DIRECTORY_SEPARATOR)!==0 || is_link($fileReal))return null;
        if(!hash_equals((string)$o->file_sha256,(string)hash_file('sha256',$fileReal)))return null;
        return array('path'=>$fileReal,'name'=>$this->filename($o->original_name),'mime'=>(string)$o->mime_type,'size'=>(int)$o->file_size);
    }

    private function insert($requestId,$type,$body,$file,$user)
    {
        $request=$this->request($requestId);if(!$request || empty($request['fk_soc']))throw new PublicRequestPublicationException('qualification_required');
        $token=bin2hex(random_bytes(16));$f=$file?:array();
        $q=function($v){return $v===null||$v===''?'NULL':"'".$this->db->escape((string)$v)."'";};
        $sql="INSERT INTO ".MAIN_DB_PREFIX."cpo_public_request_publication(entity,fk_request,public_token,item_type,body_public,original_name,storage_key,mime_type,file_size,file_sha256,customer_visible,status,fk_user_creat,date_creation) VALUES(".$this->entity.",".(int)$requestId.",'".$token."','".$type."',".$q($body).",".$q($f['original_name']??null).",".$q($f['storage_key']??null).",".$q($f['mime_type']??null).",".(isset($f['file_size'])?(int)$f['file_size']:'NULL').",".$q($f['file_sha256']??null).",1,1,".(int)$user->id.",'".$this->db->idate(dol_now())."')";
        if(!$this->db->query($sql))throw new PublicRequestPublicationException('publication_save_failed');return $token;
    }
    private function request($id){$res=$this->db->query("SELECT rowid,ref,fk_soc FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND rowid=".(int)$id);$o=$res?$this->db->fetch_object($res):null;return $o?(array)$o:null;}
    private function filename($value){$name=basename(str_replace('\\','/',(string)$value));$name=preg_replace('/[^A-Za-z0-9._ -]+/u','_',strip_tags($name));return substr($name?:'download',0,180);}
}
