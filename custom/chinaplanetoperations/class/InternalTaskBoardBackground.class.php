<?php
require_once __DIR__.'/InternalTaskBoardsService.class.php';
/** Private raster backgrounds, bounded to 5 MB; no public URLs or executable formats. */
class InternalTaskBoardBackground
{
    private $db; private $actor; private $entity;
    public function __construct($db,$actor){global $conf;$this->db=$db;$this->actor=$actor;$this->entity=(int)$conf->entity;}
    public function upload($board,$version,array $file,$description,$color){
        $svc=new InternalTaskBoardsService($this->db,$this->actor);
        $b=$svc->board($board,'manager');if(!$b['can_edit'])throw new InternalTaskAccessException('Board editing is required.');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new RuntimeException('Choose a valid image.');
        $size=filesize($file['tmp_name']);if($size<1||$size>5*1024*1024)throw new LengthException('Choose an image smaller than 5 MB.');
        $info=@getimagesize($file['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!$info||!in_array($mime,['image/jpeg','image/png','image/webp'],true)||$info['mime']!==$mime||$info[0]>10000||$info[1]>10000||$info[0]*$info[1]>40000000)throw new RuntimeException('Use a JPG, PNG or WebP image up to 40 megapixels.');
        if(!is_string($description)||!mb_check_encoding($description,'UTF-8')||mb_strlen($description)>4000||preg_match('/[<>\x00-\x09\x0b\x0c\x0e-\x1f]/u',$description)||!preg_match('/^#[0-9a-f]{6}$/iD',$color))throw new RuntimeException('Invalid board settings.');
        $bytes=file_get_contents($file['tmp_name']);$hash=hash('sha256',$bytes);$data=base64_encode($bytes);
        $this->db->begin();try{
            $b=$svc->board($board,'manager',true);if((int)$b['version']!==(int)$version)throw new InternalTaskConflictException('Board changed. Reload and try again.');if($b['archived'])throw new RuntimeException('Reopen the board first.');
            $sql='INSERT INTO '.MAIN_DB_PREFIX.'cpo_board_background(entity,fk_board,mime_type,image_hash,image_data) VALUES('.$this->entity.','.(int)$board.",'".$this->db->escape($mime)."','".$hash."','".$this->db->escape($data)."') ON DUPLICATE KEY UPDATE mime_type=VALUES(mime_type),image_hash=VALUES(image_hash),image_data=VALUES(image_data)";
            if(!$this->db->query($sql)||!$this->db->query('UPDATE '.MAIN_DB_PREFIX."cpo_board SET description='".$this->db->escape(trim($description))."',background='".$this->db->escape(strtolower($color))."',version=version+1 WHERE entity=".$this->entity.' AND rowid='.(int)$board))throw new RuntimeException('Unable to save background.');
            if(!$this->db->commit())throw new RuntimeException('Unable to save background.');return ['id'=>(int)$board,'version'=>(int)$version+1];
        }catch(Throwable $e){$this->db->rollback();throw $e;}
    }
    public function read($board){
        (new InternalTaskBoardsService($this->db,$this->actor))->board($board);
        $r=$this->db->query('SELECT mime_type,image_data FROM '.MAIN_DB_PREFIX.'cpo_board_background WHERE entity='.$this->entity.' AND fk_board='.(int)$board);
        $row=$r?$this->db->fetch_object($r):null;if(!$row)throw new InternalTaskAccessException('Background unavailable.');return ['mime'=>$row->mime_type,'bytes'=>base64_decode($row->image_data,true)];
    }
}
