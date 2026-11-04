<?php
require_once __DIR__.'/InternalTaskService.class.php';
/** Private customer-container appearance; never uses customer IDs as board IDs. */
class InternalTaskWorkspaceAppearance
{
    private $db;private $actor;private $entity;
    public function __construct($db,$actor,$entity){$this->db=$db;$this->actor=$actor;$this->entity=(int)$entity;require_once __DIR__.'/InternalTaskWorkspaceGalleryService.class.php';$auth=new InternalTaskAuthorizationService($db,$actor,$entity);if(!InternalTaskWorkspaceGalleryService::enabled()||!InternalTaskBoardPolicy::enabled()||!$auth->canOpenBoard())throw new InternalTaskAccessException('Workspace appearance unavailable.');}
    private function query($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Unable to save workspace appearance.');return $r;}
    private function authorize($id,$write=false){return (new InternalTaskWorkspaceGalleryService($this->db,$this->actor,$this->entity))->requireCustomer($id,$write);}
    public function metadata($id,$authorize=true){if($authorize)$this->authorize($id);$r=$this->query('SELECT background,image_hash background_image,version appearance_version FROM '.MAIN_DB_PREFIX.'cpo_customer_workspace_appearance WHERE entity='.$this->entity.' AND fk_soc='.(int)$id);$o=$this->db->fetch_object($r);return $o?(array)$o:['background'=>'#182b4e','background_image'=>null,'appearance_version'=>0];}
    private function uploadedImage(array $file){
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new RuntimeException('Choose a valid image.');
        $size=filesize($file['tmp_name']);if($size<1||$size>5*1024*1024)throw new LengthException('Choose an image smaller than 5 MB.');$info=@getimagesize($file['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!$info||!in_array($mime,['image/jpeg','image/png','image/webp'],true)||$info['mime']!==$mime||$info[0]>10000||$info[1]>10000||$info[0]*$info[1]>40000000)throw new RuntimeException('Use a JPG, PNG or WebP image up to 40 megapixels.');$bytes=file_get_contents($file['tmp_name']);return ['mime'=>$mime,'hash'=>hash('sha256',$bytes),'data'=>base64_encode($bytes)];
    }
    public function save($id,$version,$mode,$color,array $file=[]){
        $this->authorize($id,true);if(!in_array($mode,['color','image'],true)||!preg_match('/^#[0-9a-f]{6}$/iD',(string)$color))throw new RuntimeException('Choose a valid workspace background.');$image=null;if($mode==='image'&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$image=$this->uploadedImage($file);
        $depth=(int)$this->db->transaction_opened;$point='cpo_appearance_'.bin2hex(random_bytes(6));$live=false;
        try{if(!$this->db->begin())throw new RuntimeException('Appearance transaction unavailable.');$this->query('SAVEPOINT '.$point);$live=true;$this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity='.$this->entity.' AND rowid='.(int)$id.' FOR UPDATE');$this->authorize($id,true);
            $r=$this->query('SELECT version,image_hash FROM '.MAIN_DB_PREFIX.'cpo_customer_workspace_appearance WHERE entity='.$this->entity.' AND fk_soc='.(int)$id.' FOR UPDATE');$old=$this->db->fetch_object($r);if((int)($old->version??0)!==(int)$version)throw new InternalTaskConflictException('Workspace appearance changed. Reload and retry.');if($mode==='image'&&!$image&&empty($old->image_hash))throw new RuntimeException('Choose a background image.');
            $sql='INSERT INTO '.MAIN_DB_PREFIX.'cpo_customer_workspace_appearance(entity,fk_soc,background,version,fk_user_modif,date_modified) VALUES('.$this->entity.','.(int)$id.",'".$this->db->escape(strtolower($color))."',1,".(int)$this->actor->id.",'".$this->db->escape($this->db->idate(dol_now()))."') ON DUPLICATE KEY UPDATE background=VALUES(background),version=version+1,fk_user_modif=VALUES(fk_user_modif),date_modified=VALUES(date_modified)";$this->query($sql);
            if($mode==='color')$this->query('UPDATE '.MAIN_DB_PREFIX.'cpo_customer_workspace_appearance SET image_hash=NULL,image_data=NULL,mime_type=NULL WHERE entity='.$this->entity.' AND fk_soc='.(int)$id);
            elseif($image)$this->query("UPDATE ".MAIN_DB_PREFIX."cpo_customer_workspace_appearance SET image_hash='".$image['hash']."',mime_type='".$image['mime']."',image_data='".$this->db->escape($image['data'])."' WHERE entity=".$this->entity.' AND fk_soc='.(int)$id);
            $this->query('RELEASE SAVEPOINT '.$point);$live=false;if(!$this->db->commit())throw new RuntimeException('Unable to confirm appearance save.');return $this->metadata($id);
        }catch(Throwable $e){if($live){$this->db->query('ROLLBACK TO SAVEPOINT '.$point);$this->db->query('RELEASE SAVEPOINT '.$point);}while($this->db->transaction_opened>$depth){$before=$this->db->transaction_opened;$this->db->rollback();if($this->db->transaction_opened>=$before)break;}throw $e;}
    }
    public function read($id){$this->authorize($id);$r=$this->query('SELECT mime_type,image_data FROM '.MAIN_DB_PREFIX.'cpo_customer_workspace_appearance WHERE entity='.$this->entity.' AND fk_soc='.(int)$id);$row=$this->db->fetch_object($r);if(!$row||!$row->image_data)throw new InternalTaskAccessException('Workspace background unavailable.');return ['mime'=>$row->mime_type,'bytes'=>base64_decode($row->image_data,true)];}
}
