<?php
/** Explicit M109 storage only; does not enable flags or grant rights. */
class InternalTaskWorkspaceGalleryMigration
{
    private $db;
    public function __construct($db){$this->db=$db;}
    public function apply(){
        if(!$this->db->query('SELECT entity,fk_project,fk_board FROM '.MAIN_DB_PREFIX.'cpo_project_board LIMIT 0'))throw new RuntimeException('Install customer/project workspaces first.');
        foreach(['cpo_customer_workspace_meta'=>['entity','fk_soc'],'cpo_customer_workspace_pref'=>['entity','fk_soc','fk_user']] as $name=>$unique){
            $sql=str_replace('CREATE TABLE llx_','CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX,file_get_contents(__DIR__.'/../sql/llx_'.$name.'.sql'));
            if(!$this->db->query($sql))throw new RuntimeException('Workspace gallery installation failed.');
            $r=$this->db->query("SHOW TABLE STATUS WHERE Name='".$this->db->escape(MAIN_DB_PREFIX.$name)."'");$m=$r?$this->db->fetch_object($r):null;if(!$m||strtolower($m->Engine)!=='innodb')throw new RuntimeException('Transactional gallery storage required.');
            $r=$this->db->query('SHOW INDEX FROM '.MAIN_DB_PREFIX.$name);$keys=[];while($r&&($k=$this->db->fetch_object($r)))if(!(int)$k->Non_unique)$keys[$k->Key_name][(int)$k->Seq_in_index]=$k->Column_name;$found=false;foreach($keys as $cols){ksort($cols);if(array_values($cols)===$unique)$found=true;}if(!$found)throw new RuntimeException('Workspace gallery uniqueness mismatch.');
        }
        if(!$this->db->query('SELECT entity,fk_soc,priority,version,fk_user_modif FROM '.MAIN_DB_PREFIX.'cpo_customer_workspace_meta LIMIT 0')||!$this->db->query('SELECT entity,fk_soc,fk_user,starred,position FROM '.MAIN_DB_PREFIX.'cpo_customer_workspace_pref LIMIT 0'))throw new RuntimeException('Workspace gallery schema mismatch.');
    }
}
