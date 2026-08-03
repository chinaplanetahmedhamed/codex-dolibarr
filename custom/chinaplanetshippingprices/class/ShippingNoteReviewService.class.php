<?php

require_once __DIR__.'/ShippingNoteTranslationService.class.php';

/** Entity-scoped correction workflow for automatically translated source notes. */
class ShippingNoteReviewService
{
    private $db;
    private $user;
    private $entity;

    public function __construct($db, $user, $entity = null)
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

    public function mutate($batchId, $sourceRow, $action, $notesEn)
    {
        if (!$this->canReview()) throw new RuntimeException('Translation review requires Import and View Cost rights.');
        $batchId = (int) $batchId;
        $sourceRow = (int) $sourceRow;
        if ($batchId <= 0 || $sourceRow <= 0 || !in_array($action, array('save_translation','approve_translation','reset_translation'), true)) {
            throw new RuntimeException('Invalid translation review request.');
        }
        $notesEn = ShippingNoteTranslationService::safeText((string) $notesEn, ShippingNoteTranslationService::MAX_OUTPUT_CHARS);
        if ($action !== 'reset_translation' && $notesEn === '') throw new RuntimeException('English translation is required.');

        $this->db->begin();
        $sql = "SELECT rowid,status FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE rowid=".$batchId." AND entity=".$this->entity." FOR UPDATE";
        $res = $this->db->query($sql);
        $batch = $res ? $this->db->fetch_object($res) : null;
        if (!$batch || !in_array((int) $batch->status, array(0, 2), true)) return $this->rollback('This import batch cannot be changed.');
        if ($action === 'reset_translation' && (int) $batch->status !== 0) return $this->rollback('A published translation cannot be returned to review. Save the corrected English text instead.');

        $sql = "SELECT rowid,translation_source_hash,source_notes FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate";
        $sql .= " WHERE entity=".$this->entity." AND fk_batch=".$batchId." AND source_row=".$sourceRow." FOR UPDATE";
        $res = $this->db->query($sql);
        $count = 0; $hash = null; $sourceNote = '';
        if ($res) while ($row = $this->db->fetch_object($res)) {
            if (trim((string) $row->source_notes) === '') return $this->rollback('This source row has no reviewable note.');
            if ($sourceNote === '') $sourceNote = (string) $row->source_notes;
            elseif ($sourceNote !== (string) $row->source_notes) return $this->rollback('Source-row text is inconsistent.');
            if ($hash === null) $hash = (string) $row->translation_source_hash;
            elseif ($hash !== (string) $row->translation_source_hash) return $this->rollback('Source-row translation evidence is inconsistent.');
            $count++;
        }
        if ($count <= 0) return $this->rollback('Source row was not found in this draft batch.');
        if ($action !== 'reset_translation') {
            $validationError = ShippingNoteTranslationService::validateEnglishTranslation($sourceNote, $notesEn);
            if ($validationError !== '') return $this->rollback('English translation validation failed: '.$validationError.'.');
        }

        if ($action === 'approve_translation' || $action === 'save_translation') {
            $set = "notes_en='".$this->db->escape($notesEn)."',public_notes='".$this->db->escape($notesEn)."',translation_status='approved',translation_error=NULL,";
            $set .= "translation_provider='staff',translation_model=NULL,fk_user_translation_review=".(int) $this->user->id.",date_translation_review='".$this->db->idate(dol_now())."'";
        } else {
            $set = "public_notes=NULL,translation_status='review',translation_error=NULL,fk_user_translation_review=NULL,date_translation_review=NULL";
        }
        $sql = "UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_rate SET ".$set." WHERE entity=".$this->entity." AND fk_batch=".$batchId." AND source_row=".$sourceRow;
        if (!$this->db->query($sql)) return $this->rollback($this->db->lasterror());
        $this->db->commit();
        return array('affected'=>$count, 'status'=>$action === 'reset_translation' ? 'review' : 'approved');
    }

    public function listGroups($batchId, $page = 0, $pageSize = 25)
    {
        if (!$this->canReview()) return array('groups'=>array(), 'total'=>0, 'page'=>0, 'pages'=>0);
        $batchId = (int) $batchId;
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

    private function rollback($message)
    {
        $this->db->rollback();
        throw new RuntimeException($message);
    }
}
