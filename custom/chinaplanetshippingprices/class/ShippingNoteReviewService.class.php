<?php

require_once __DIR__.'/ShippingNoteTranslationService.class.php';

/** Entity-scoped correction workflow for automatically translated source notes. */
class ShippingNoteReviewService
{
    private $db;
    private $user;
    private $entity;
    private $translator;

    public function __construct($db, $user, $entity = null, $translator = null)
    {
        $this->db = $db;
        $this->user = $user;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
        $this->translator = $translator ?: new ShippingNoteTranslationService();
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

    /**
     * Retry a bounded set of untranslated source rows. Valid provider output is
     * accepted automatically; staff review remains an optional correction.
     */
    public function autoTranslatePending($batchId, $limit = 8, $timeBudget = 18.0)
    {
        if (!$this->canReview()) throw new RuntimeException('Automatic translation requires Import and View Cost rights.');
        $batchId = (int) $batchId;
        $limit = max(1, min(20, (int) $limit));
        $timeBudget = max(1.0, min(20.0, (float) $timeBudget));
        if ($batchId <= 0) throw new RuntimeException('Invalid translation batch.');

        $res = $this->db->query("SELECT status FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE rowid=".$batchId." AND entity=".$this->entity);
        $batch = $res ? $this->db->fetch_object($res) : null;
        if (!$batch || (int) $batch->status !== 0) throw new RuntimeException('Automatic translation is available only for draft batches.');

        $sql = "SELECT source_row,MIN(source_notes) source_notes,MIN(translation_source_hash) source_hash,";
        $sql .= "COUNT(DISTINCT source_notes) note_count,COUNT(DISTINCT translation_source_hash) hash_count";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".$this->entity." AND fk_batch=".$batchId;
        $sql .= " AND source_notes IS NOT NULL AND source_notes<>'' AND translation_status<>'approved'";
        $sql .= " GROUP BY source_row ORDER BY source_row LIMIT ".$limit;
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException($this->db->lasterror());
        $groups = array();
        while ($row = $this->db->fetch_object($res)) $groups[] = $row;

        $startedAt = microtime(true);
        $summary = array('selected'=>count($groups), 'attempted'=>0, 'approved'=>0, 'failed'=>0, 'remaining'=>0, 'stalled'=>false);
        foreach ($groups as $group) {
            $remainingSeconds = $timeBudget - (microtime(true) - $startedAt);
            if ($remainingSeconds <= 0.05) { $summary['stalled'] = true; break; }
            if ((int) $group->note_count !== 1 || (int) $group->hash_count !== 1) {
                $summary['failed']++; $summary['stalled'] = true; break;
            }
            $source = ShippingNoteTranslationService::normalizeSource((string) $group->source_notes);
            $expectedHash = hash('sha256', $source);
            if ($source === '' || !hash_equals($expectedHash, (string) $group->source_hash)) {
                $summary['failed']++; $summary['stalled'] = true; break;
            }

            $translation = $this->translator->translate($source, $remainingSeconds);
            if (!empty($translation['attempted'])) $summary['attempted']++;
            $status = (string) ($translation['status'] ?? 'failed');
            if (!in_array($status, array('approved','pending','failed'), true)) $status = 'failed';

            $this->db->begin();
            $lockSql = "SELECT status FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE rowid=".$batchId." AND entity=".$this->entity." FOR UPDATE";
            $lockRes = $this->db->query($lockSql);
            $lockedBatch = $lockRes ? $this->db->fetch_object($lockRes) : null;
            if (!$lockedBatch || (int) $lockedBatch->status !== 0) return $this->rollback('Draft status changed during automatic translation.');

            $rowSql = "SELECT source_notes,translation_source_hash,translation_status FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate";
            $rowSql .= " WHERE entity=".$this->entity." AND fk_batch=".$batchId." AND source_row=".(int) $group->source_row." FOR UPDATE";
            $rowRes = $this->db->query($rowSql);
            $rowCount = 0; $alreadyApproved = false;
            if ($rowRes) while ($rate = $this->db->fetch_object($rowRes)) {
                if ((string) $rate->source_notes !== $source || !hash_equals($expectedHash, (string) $rate->translation_source_hash)) {
                    return $this->rollback('Source note changed during automatic translation.');
                }
                if ((string) $rate->translation_status === 'approved') $alreadyApproved = true;
                $rowCount++;
            }
            if ($rowCount <= 0) return $this->rollback('Source row disappeared during automatic translation.');
            if ($alreadyApproved) { $this->db->rollback(); continue; }

            if ($status === 'approved') {
                $notesEn = ShippingNoteTranslationService::safeText((string) ($translation['notes_en'] ?? ''), ShippingNoteTranslationService::MAX_OUTPUT_CHARS);
                $validationError = ShippingNoteTranslationService::validateEnglishTranslation($source, $notesEn);
                if ($validationError !== '') {
                    $status = 'failed';
                    $translation['error'] = $validationError;
                }
            }
            if ($status === 'approved') {
                $set = "notes_en='".$this->db->escape($notesEn)."',public_notes='".$this->db->escape($notesEn)."',translation_status='approved',translation_error=NULL,";
                $set .= "translation_provider=".$this->sqlNullable($translation['provider'] ?? '').",translation_model=".$this->sqlNullable($translation['model'] ?? '').",";
                $set .= "fk_user_translation_review=NULL,date_translation_review=NULL";
            } else {
                $set = "notes_en=NULL,public_notes=NULL,translation_status='".$this->db->escape($status)."',";
                $set .= "translation_provider=".$this->sqlNullable($translation['provider'] ?? '').",translation_model=".$this->sqlNullable($translation['model'] ?? '').",";
                $set .= "translation_error=".$this->sqlNullable($translation['error'] ?? 'provider_unavailable').",fk_user_translation_review=NULL,date_translation_review=NULL";
            }
            $update = "UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_rate SET ".$set." WHERE entity=".$this->entity." AND fk_batch=".$batchId." AND source_row=".(int) $group->source_row." AND translation_status<>'approved'";
            if (!$this->db->query($update)) return $this->rollback($this->db->lasterror());
            $this->db->commit();

            if ($status === 'approved') {
                $summary['approved']++;
            } else {
                $summary['failed']++;
                $summary['stalled'] = true;
                break;
            }
        }

        $remainingSql = "SELECT COUNT(DISTINCT source_row) remaining FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate";
        $remainingSql .= " WHERE entity=".$this->entity." AND fk_batch=".$batchId." AND source_notes IS NOT NULL AND source_notes<>'' AND translation_status<>'approved'";
        $remainingRes = $this->db->query($remainingSql);
        $remainingRow = $remainingRes ? $this->db->fetch_object($remainingRes) : null;
        $summary['remaining'] = $remainingRow ? (int) $remainingRow->remaining : 0;
        if ($summary['remaining'] > 0 && $summary['approved'] === 0) $summary['stalled'] = true;
        return $summary;
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

    private function sqlNullable($value)
    {
        $value = trim((string) $value);
        return $value === '' ? 'NULL' : "'".$this->db->escape($value)."'";
    }
}
