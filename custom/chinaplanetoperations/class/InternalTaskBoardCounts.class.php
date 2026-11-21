<?php
/** Counts describe unarchived cards in active lists with unarchived canonical tasks. */
class InternalTaskBoardCounts
{
    public static function expression($entity,$board,$done=false,$readable='1=1'){
        if(!preg_match('/^(?:[0-9]+|[a-zA-Z][a-zA-Z0-9_]*\.[a-zA-Z][a-zA-Z0-9_]*)$/D',(string)$board))throw new InvalidArgumentException('Invalid count scope.');
        return '(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'cpo_board_card ws_c INNER JOIN '.MAIN_DB_PREFIX.'cpo_task ws_t ON ws_t.entity=ws_c.entity AND ws_t.rowid=ws_c.fk_task INNER JOIN '.MAIN_DB_PREFIX.'cpo_board_list ws_l ON ws_l.entity=ws_c.entity AND ws_l.fk_board=ws_c.fk_board AND ws_l.rowid=ws_c.fk_list WHERE ws_c.entity='.(int)$entity.' AND ws_c.fk_board='.$board.' AND ws_c.archived=0 AND ws_l.archived=0 AND ws_t.date_archived IS NULL AND '.($done?"ws_t.status='done'":"ws_t.status NOT IN ('done','cancelled','deleted')").' AND '.$readable.')';
    }
}
