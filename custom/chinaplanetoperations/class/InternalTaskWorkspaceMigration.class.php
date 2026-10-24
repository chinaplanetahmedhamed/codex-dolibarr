<?php
/** Explicit additive migration only. Never run by a page read or module hook. */
class InternalTaskWorkspaceMigration
{
    private $db;
    public function __construct($db){$this->db=$db;}
    public function apply(){
        $sql=file_get_contents(__DIR__.'/../sql/llx_cpo_project_board.sql');
        $sql=str_replace('CREATE TABLE llx_','CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX,$sql);
        if(!$this->db->query($sql))throw new RuntimeException('Workspace mapping installation failed.');
        require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';$ef=new ExtraFields($this->db);
        $ef->fetch_name_optionals_label('societe');
        if(empty($ef->attributes['societe']['type']['cpo_board_workspace'])){
            $r=$ef->addExtraField('cpo_board_workspace','Customer board workspace / مساحة عمل للعميل','boolean',120,'','societe',0,0,'0','',1,'$user->admin','1','Group this customer projects in Boards. Internal workspace only; grants no customer access.','','', '', '1');
            if($r<0)throw new RuntimeException('Customer workspace option installation failed.');
        }
        if(($ef->attributes['societe']['type']['cpo_board_workspace']??'boolean')!=='boolean')throw new RuntimeException('Workspace extra field type mismatch.');
        $status=$this->db->query("SHOW TABLE STATUS WHERE Name='".$this->db->escape(MAIN_DB_PREFIX.'cpo_project_board')."'");$meta=$status?$this->db->fetch_object($status):null;
        if(!$meta||strtolower($meta->Engine)!=='innodb')throw new RuntimeException('Transactional workspace storage required.');
        $keys=$this->db->query('SHOW INDEX FROM '.MAIN_DB_PREFIX.'cpo_project_board');$unique=array();while($keys&&($key=$this->db->fetch_object($keys)))if(!(int)$key->Non_unique)$unique[$key->Key_name][(int)$key->Seq_in_index]=$key->Column_name;
        foreach(array(array('entity','fk_project'),array('entity','fk_board')) as $expected){$found=false;foreach($unique as $cols){ksort($cols);if(array_values($cols)===$expected)$found=true;}if(!$found)throw new RuntimeException('Workspace unique key mismatch.');}
        if(!$this->db->query('SELECT entity,fk_project,fk_board FROM '.MAIN_DB_PREFIX.'cpo_project_board LIMIT 0')||!$this->db->query('SELECT fk_object,cpo_board_workspace FROM '.MAIN_DB_PREFIX.'societe_extrafields LIMIT 0'))throw new RuntimeException('Workspace schema mismatch.');
    }
}
