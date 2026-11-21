<?php
/** Add only leftblock hook registration; never reinitialize the module or grant rights. */
class InternalTaskBoardsShortcutMigration
{
    private $db;private $entity;
    public function __construct($db,$entity){$this->db=$db;$this->entity=(int)$entity;}
    public function apply(){
        $r=$this->db->query("SELECT rowid,value FROM ".MAIN_DB_PREFIX."const WHERE name='MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS' AND entity=".$this->entity);$row=$r?$this->db->fetch_object($r):null;
        if(!$row)throw new RuntimeException('Existing Operations hook registration missing. Inspect the entity before installing the shortcut.');
        $hooks=json_decode($row->value,true);$legacy=!is_array($hooks);if($legacy){if(!preg_match('/^[a-zA-Z0-9_:]+$/D',$row->value))throw new RuntimeException('Unsupported hook registration format.');$hooks=explode(':',$row->value);}
        if(!in_array('leftblock',$hooks,true)){$hooks[]='leftblock';if(!$this->db->query("UPDATE ".MAIN_DB_PREFIX."const SET value='".$this->db->escape($legacy?implode(':',$hooks):json_encode($hooks))."' WHERE rowid=".(int)$row->rowid.' AND entity='.$this->entity))throw new RuntimeException('Shortcut registration failed.');}
    }
}
