<?php
/** M115 additive shared customer appearance. Native customer/project/task rows remain unchanged. */
class InternalTaskWorkspaceAppearanceMigration
{
    private $db;
    public function __construct($db){$this->db=$db;}
    public function apply(){
        if(!$this->db->query('SELECT entity,rowid FROM '.MAIN_DB_PREFIX.'cpo_board LIMIT 0'))throw new RuntimeException('Install Boards first.');
        $sql=str_replace('CREATE TABLE llx_','CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX,file_get_contents(__DIR__.'/../sql/llx_cpo_customer_workspace_appearance.sql'));
        if(!$this->db->query($sql))throw new RuntimeException('Customer appearance storage installation failed.');
        $r=$this->db->query("SHOW TABLE STATUS WHERE Name='".$this->db->escape(MAIN_DB_PREFIX.'cpo_customer_workspace_appearance')."'");$m=$r?$this->db->fetch_object($r):null;if(!$m||strtolower($m->Engine)!=='innodb')throw new RuntimeException('Transactional customer appearance storage required.');
        if(!$this->db->query('SELECT entity,fk_soc,background,mime_type,image_hash,image_data,version,fk_user_modif,date_modified FROM '.MAIN_DB_PREFIX.'cpo_customer_workspace_appearance LIMIT 0'))throw new RuntimeException('Customer appearance schema mismatch.');
        $r=$this->db->query('SHOW INDEX FROM '.MAIN_DB_PREFIX.'cpo_customer_workspace_appearance');$keys=[];while($r&&($k=$this->db->fetch_object($r)))if(!(int)$k->Non_unique)$keys[$k->Key_name][(int)$k->Seq_in_index]=$k->Column_name;$found=false;foreach($keys as $cols){ksort($cols);if(array_values($cols)===['entity','fk_soc'])$found=true;}if(!$found)throw new RuntimeException('Customer appearance uniqueness mismatch.');
    }
}
