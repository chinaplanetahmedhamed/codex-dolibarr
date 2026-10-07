<?php
/** Board access supplements native rights; no schema changes occur on requests. */
class InternalTaskBoardPolicy
{
    private $db; private $uid; private $entity;
    public function __construct($db,$uid,$entity){$this->db=$db;$this->uid=(int)$uid;$this->entity=(int)$entity;}
    public static function enabled(){global $conf;return !empty($conf->global->CPO_BOARDS_ENABLED);}
    private function rows($sql){$r=$this->db->query($sql);if(!$r)throw new RuntimeException('Boards schema is not installed.');$out=[];while($o=$this->db->fetch_object($r))$out[]=(array)$o;return $out;}
    public function role($board){$r=$this->rows('SELECT role FROM '.MAIN_DB_PREFIX.'cpo_board_member WHERE entity='.$this->entity.' AND fk_board='.(int)$board.' AND fk_user='.$this->uid);return $r?$r[0]['role']:null;}
    public function taskRole($task){$r=$this->rows('SELECT role FROM '.MAIN_DB_PREFIX.'cpo_card_grant WHERE entity='.$this->entity.' AND fk_task='.(int)$task.' AND fk_user='.$this->uid);return $r?$r[0]['role']:null;}
    /** null indicates an unchanged legacy task, false a mapped but inaccessible task. */
    public function access($task,$write=false){if(!self::enabled())return null;$r=$this->rows('SELECT fk_board FROM '.MAIN_DB_PREFIX.'cpo_board_card WHERE entity='.$this->entity.' AND fk_task='.(int)$task);if(!$r)return null;$role=$this->role($r[0]['fk_board']);$grant=$this->taskRole($task);return $write?in_array($role,['manager','editor'],true)||$grant==='editor':$role!==null||$grant!==null;}
    public function readableSql($alias,$legacy){if(!self::enabled())return $legacy;$p=MAIN_DB_PREFIX;$u=$this->uid;return '(('.$legacy.' AND NOT EXISTS (SELECT 1 FROM '.$p.'cpo_board_card bc WHERE bc.entity='.$alias.'.entity AND bc.fk_task='.$alias.'.rowid)) OR EXISTS (SELECT 1 FROM '.$p.'cpo_board_card bc INNER JOIN '.$p.'cpo_board_member bm ON bm.entity=bc.entity AND bm.fk_board=bc.fk_board AND bm.fk_user='.$u.' WHERE bc.entity='.$alias.'.entity AND bc.fk_task='.$alias.'.rowid) OR EXISTS (SELECT 1 FROM '.$p.'cpo_card_grant cg WHERE cg.entity='.$alias.'.entity AND cg.fk_task='.$alias.'.rowid AND cg.fk_user='.$u.'))';}
}
