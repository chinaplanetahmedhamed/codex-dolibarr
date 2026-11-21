<?php
/** M114 independent board completion. Never deletes a project, task or board row. */
class InternalTaskCompletionMigration
{
    private $db;
    public function __construct($db){$this->db=$db;}
    public function apply(){
        if(!$this->db->query('SELECT entity,rowid FROM '.MAIN_DB_PREFIX.'cpo_board LIMIT 0'))throw new RuntimeException('Install Boards first.');
        $sql=str_replace('CREATE TABLE llx_','CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX,file_get_contents(__DIR__.'/../sql/llx_cpo_board_completion.sql'));
        if(!$this->db->query($sql))throw new RuntimeException('Board recovery storage installation failed.');
        $r=$this->db->query("SHOW TABLE STATUS WHERE Name='".$this->db->escape(MAIN_DB_PREFIX.'cpo_board_completion')."'");$m=$r?$this->db->fetch_object($r):null;if(!$m||strtolower($m->Engine)!=='innodb')throw new RuntimeException('Transactional board recovery storage required.');
        if(!$this->db->query('SELECT entity,fk_board,completed,fk_user_modif,date_modified FROM '.MAIN_DB_PREFIX.'cpo_board_completion LIMIT 0'))throw new RuntimeException('Board recovery schema mismatch.');
        $r=$this->db->query('SHOW INDEX FROM '.MAIN_DB_PREFIX.'cpo_board_completion');$keys=[];while($r&&($k=$this->db->fetch_object($r)))if(!(int)$k->Non_unique)$keys[$k->Key_name][(int)$k->Seq_in_index]=$k->Column_name;$found=false;foreach($keys as $cols){ksort($cols);if(array_values($cols)===['entity','fk_board'])$found=true;}if(!$found)throw new RuntimeException('Board recovery uniqueness mismatch.');
    }
}
