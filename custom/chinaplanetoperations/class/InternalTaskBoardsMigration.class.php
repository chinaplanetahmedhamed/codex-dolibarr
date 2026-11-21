<?php
class InternalTaskBoardsMigration
{
    private $db;
    public function __construct($db){$this->db=$db;}
    public function apply(){foreach(explode(';',file_get_contents(__DIR__.'/../sql/llx_cpo_boards.sql')) as $sql){if(trim($sql)==='')continue;$sql=str_replace('CREATE TABLE llx_','CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX,$sql);if(!$this->db->query($sql))throw new RuntimeException('Boards installation failed.');}
        foreach(['board_background'=>'entity,fk_board,mime_type,image_hash,image_data','board_request'=>'entity,fk_user,request_hash,digest,fk_board','board'=>'rowid,entity,title,description,background,fk_usergroup,version,archived','board_member'=>'entity,fk_board,fk_user,role','board_list'=>'entity,fk_board,title,position,archived','board_card'=>'entity,fk_board,fk_list,fk_task,position,archived','card_grant'=>'entity,fk_task,fk_user,role','board_pref'=>'entity,fk_board,fk_user,starred,last_opened'] as $table=>$fields)if(!$this->db->query('SELECT '.$fields.' FROM '.MAIN_DB_PREFIX.'cpo_'.$table.' LIMIT 0'))throw new RuntimeException('Boards schema mismatch.');
        foreach(['board_background','board','board_member','board_list','board_card','card_grant','board_pref','board_request'] as $table){$r=$this->db->query("SHOW TABLE STATUS WHERE Name='".$this->db->escape(MAIN_DB_PREFIX.'cpo_'.$table)."'");$row=$r?$this->db->fetch_object($r):null;if(!$row||strtolower($row->Engine)!=='innodb')throw new RuntimeException('Boards require transactional InnoDB tables.');}
        foreach(['board_background'=>['entity','fk_board'],'board_member'=>['entity','fk_board','fk_user'],'board_card'=>['entity','fk_task'],'card_grant'=>['entity','fk_task','fk_user'],'board_pref'=>['entity','fk_board','fk_user'],'board_request'=>['entity','fk_user','request_hash']] as $table=>$expected){$r=$this->db->query('SHOW INDEX FROM '.MAIN_DB_PREFIX.'cpo_'.$table);$keys=[];while($r&&($row=$this->db->fetch_object($r)))if((int)$row->Non_unique===0)$keys[$row->Key_name][(int)$row->Seq_in_index]=$row->Column_name;$valid=false;foreach($keys as $key){ksort($key);if(array_values($key)===$expected)$valid=true;}if(!$valid)throw new RuntimeException('Boards unique index mismatch.');}

    }
}
