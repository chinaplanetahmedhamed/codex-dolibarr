<?php

require_once __DIR__.'/ShippingNoteTranslationService.class.php';

/** Restricted read-only source/provenance review for imported carrier notes. */
class ShippingNoteReviewService
{
    private $db;
    private $user;
    private $entity;

    public function __construct($db, $user, $entity = null, $translator = null)
    {
        $this->db = $db;
        $this->user = $user;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
    }

    public function canReview()
    {
        if (!empty($this->user->socid)) return false;
        if (!empty($this->user->admin)) return true;
        return $this->user->hasRight('chinaplanetshippingprices', 'import') && $this->user->hasRight('chinaplanetshippingprices', 'viewcost');
    }

    /** Translation mutation is outside the M046-C01 release contract. */
    public function mutate($batchId, $sourceRow, $action, $notesEn)
    {
        if (!$this->canReview()) throw new RuntimeException('Translation review requires Import and View Cost rights.');
        if (!ShippingNoteTranslationService::automaticTranslationEnabled()) throw new RuntimeException('Carrier-note translation is disabled by release policy.');
        throw new RuntimeException('Translation mutation requires a separately approved Mission.');
    }

    /** Automatic translation is outside the M046-C01 release contract. */
    public function autoTranslatePending($batchId, $limit = 8, $timeBudget = 18.0)
    {
        if (!$this->canReview()) throw new RuntimeException('Automatic translation requires Import and View Cost rights.');
        if (!ShippingNoteTranslationService::automaticTranslationEnabled()) throw new RuntimeException('Carrier-note translation is disabled by release policy.');
        throw new RuntimeException('Automatic translation requires a separately approved Mission.');
    }

    /** Entity-scoped, paged, read-only carrier-note source/provenance projection. */
    public function listGroups($batchId, $page = 0, $pageSize = 25)
    {
        if (!$this->canReview()) return array('groups'=>array(), 'total'=>0, 'page'=>0, 'pages'=>0);
        $batchId = (int) $batchId;
        if ($batchId <= 0) return array('groups'=>array(), 'total'=>0, 'page'=>0, 'pages'=>0);
        $pageSize = max(1, min(100, (int) $pageSize));
        $page = max(0, (int) $page);
        $where = " entity=".$this->entity." AND fk_batch=".$batchId." AND source_notes IS NOT NULL AND source_notes<>''";
        $res = $this->db->query("SELECT COUNT(DISTINCT source_row) total FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE".$where);
        $row = $res ? $this->db->fetch_object($res) : null;
        $total = $row ? (int) $row->total : 0;
        $pages = $total > 0 ? (int) ceil($total / $pageSize) : 0;
        if ($pages > 0 && $page >= $pages) $page = $pages - 1;
        $sql = "SELECT source_row,MIN(source_notes_raw) source_notes_raw,MIN(source_notes) source_notes,MIN(notes_en) notes_en,";
        $sql .= "MIN(translation_status) translation_status,MIN(translation_provider) translation_provider,MIN(translation_model) translation_model,";
        $sql .= "MIN(translation_error) translation_error,MIN(translation_source_hash) translation_source_hash,";
        $sql .= "COUNT(*) rate_count,COUNT(DISTINCT translation_source_hash) hash_count,COUNT(DISTINCT translation_status) status_count";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE".$where." GROUP BY source_row ORDER BY source_row";
        $sql .= " LIMIT ".$pageSize." OFFSET ".($page * $pageSize);
        $res = $this->db->query($sql);
        $groups = array();
        if ($res) while ($group = $this->db->fetch_object($res)) $groups[] = $group;
        return array('groups'=>$groups, 'total'=>$total, 'page'=>$page, 'pages'=>$pages);
    }
}
