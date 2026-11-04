<?php
/** Explicit additive M108 migration. Never run on a page read or module init. */
class InternalTaskProjectTaskMigration
{
    private $db;
    public function __construct($db){$this->db=$db;}
    public function apply(){
        foreach(['projet_task','cpo_task','cpo_board_card'] as $name){
            $r=$this->db->query("SHOW TABLE STATUS WHERE Name='".$this->db->escape(MAIN_DB_PREFIX.$name)."'");$m=$r?$this->db->fetch_object($r):null;
            if(!$m||strtolower($m->Engine)!=='innodb')throw new RuntimeException('Transactional native project/task storage required.');
        }
        $sql=str_replace('CREATE TABLE llx_','CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX,file_get_contents(__DIR__.'/../sql/llx_cpo_project_task.sql'));
        if(!$this->db->query($sql))throw new RuntimeException('Native project task mapping installation failed.');
        $r=$this->db->query("SHOW TABLE STATUS WHERE Name='".$this->db->escape(MAIN_DB_PREFIX.'cpo_project_task')."'");$m=$r?$this->db->fetch_object($r):null;if(!$m||strtolower($m->Engine)!=='innodb')throw new RuntimeException('Transactional native task mapping required.');
        if(!$this->db->query('SELECT entity,fk_task,fk_project,fk_native_task,fk_user_creat,date_creation FROM '.MAIN_DB_PREFIX.'cpo_project_task LIMIT 0'))throw new RuntimeException('Native project task mapping schema mismatch.');
        $r=$this->db->query('SHOW INDEX FROM '.MAIN_DB_PREFIX.'cpo_project_task');$keys=[];while($r&&($k=$this->db->fetch_object($r)))if(!(int)$k->Non_unique)$keys[$k->Key_name][(int)$k->Seq_in_index]=$k->Column_name;
        foreach([['entity','fk_task'],['entity','fk_native_task']] as $want){$found=false;foreach($keys as $cols){ksort($cols);if(array_values($cols)===$want)$found=true;}if(!$found)throw new RuntimeException('Native project task mapping uniqueness required.');}
    }
}
