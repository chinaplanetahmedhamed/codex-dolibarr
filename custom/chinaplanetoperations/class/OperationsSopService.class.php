<?php

class OperationsSopService
{
    const TRANSLATION_DRAFT = 0;
    const TRANSLATION_REVIEW = 1;
    const TRANSLATION_APPROVED = 2;

    private $db;
    private $entity;

    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
    }

    public static function supportedLanguages()
    {
        return array(
            'en_US'=>array('label'=>'English', 'short'=>'EN', 'dir'=>'ltr'),
            'ar_SA'=>array('label'=>'العربية', 'short'=>'AR', 'dir'=>'rtl'),
            'zh_CN'=>array('label'=>'中文', 'short'=>'ZH', 'dir'=>'ltr'),
        );
    }

    public static function normalizeLanguage($language)
    {
        $language = str_replace('-', '_', trim((string) $language));
        foreach (array_keys(self::supportedLanguages()) as $supported) {
            if (strcasecmp($language, $supported) === 0 || strcasecmp(substr($language, 0, 2), substr($supported, 0, 2)) === 0) return $supported;
        }
        return 'en_US';
    }

    public static function decodeList($value)
    {
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) return array_values(array_filter(array_map('trim', $decoded), 'strlen'));
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $value)), 'strlen'));
    }

    public function requiredLanguages()
    {
        $configured = !empty($GLOBALS['conf']->global->CHINAPLANETOPERATIONS_REQUIRED_LANGS) ? $GLOBALS['conf']->global->CHINAPLANETOPERATIONS_REQUIRED_LANGS : 'en_US,ar_SA,zh_CN';
        $supported = self::supportedLanguages();
        $out = array();
        foreach (explode(',', $configured) as $language) {
            $normalized = self::normalizeLanguage($language);
            if (isset($supported[$normalized])) $out[$normalized] = $normalized;
        }
        return array_values($out ?: array('en_US'));
    }

    public function getCategories($language = 'en_US', $includeInactive = false)
    {
        $language = self::normalizeLanguage($language);
        $sql = "SELECT c.*,COALESCE(NULLIF(t.label,''),NULLIF(f.label,''),c.code) label,COALESCE(t.description,f.description) description FROM ".MAIN_DB_PREFIX."cpo_sop_category c LEFT JOIN ".MAIN_DB_PREFIX."cpo_sop_category_lang t ON t.fk_category=c.rowid AND t.entity=c.entity AND t.lang_code=".$this->q($language)." LEFT JOIN ".MAIN_DB_PREFIX."cpo_sop_category_lang f ON f.fk_category=c.rowid AND f.entity=c.entity AND f.lang_code='en_US' WHERE c.entity=".$this->entity;
        if (!$includeInactive) $sql .= " AND c.status=1";
        $sql .= " ORDER BY c.status DESC,c.position,label";
        $rows = $this->fetchAll($sql);
        foreach ($rows as &$row) $row['translations'] = $this->getCategoryTranslations((int) $row['rowid']);
        unset($row);
        return $rows;
    }

    public function getCategoryTranslations($categoryId)
    {
        $rows = $this->fetchAll("SELECT lang_code,label,description FROM ".MAIN_DB_PREFIX."cpo_sop_category_lang WHERE entity=".$this->entity." AND fk_category=".(int)$categoryId);
        $out = array(); foreach ($rows as $row) $out[$row['lang_code']] = $row;
        return $out;
    }

    public function saveCategory(array $data, array $translations, $userId, $id = 0)
    {
        $code = strtolower(trim((string) ($data['code'] ?? '')));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code);
        if ($code === '') throw new RuntimeException('Category code is required.');
        foreach ($this->requiredLanguages() as $language) {
            if (trim((string) ($translations[$language]['label'] ?? '')) === '') throw new RuntimeException('Category label is required for '.$language.'.');
        }
        $this->db->begin();
        if ($id > 0) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_sop_category SET code=".$this->q($code).",position=".(int)($data['position'] ?? 100).",fk_user_modif=".(int)$userId." WHERE rowid=".(int)$id." AND entity=".$this->entity;
        } else {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_sop_category SET entity=".$this->entity.",code=".$this->q($code).",position=".(int)($data['position'] ?? 100).",status=1,fk_user_creat=".(int)$userId.",date_creation=".$this->q($this->now());
        }
        if (!$this->db->query($sql)) return $this->failTransaction();
        if ($id <= 0) $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_sop_category');
        foreach (self::supportedLanguages() as $language=>$meta) {
            $label = trim((string) ($translations[$language]['label'] ?? ''));
            if ($label === '') continue;
            $description = trim((string) ($translations[$language]['description'] ?? ''));
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_sop_category_lang SET entity=".$this->entity.",fk_category=".$id.",lang_code=".$this->q($language).",label=".$this->q($label).",description=".$this->qNullable($description)." ON DUPLICATE KEY UPDATE label=VALUES(label),description=VALUES(description)";
            if (!$this->db->query($sql)) return $this->failTransaction();
        }
        $this->db->commit();
        return $id;
    }

    public function setCategoryStatus($id, $status, $userId)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_sop_category SET status=".($status ? 1 : 0).",fk_user_modif=".(int)$userId." WHERE rowid=".(int)$id." AND entity=".$this->entity;
        if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
    }

    public function create(array $data, array $translations, $userId)
    {
        $ref = strtoupper(trim((string) ($data['ref'] ?? '')));
        if (!preg_match('/^[A-Z0-9][A-Z0-9._\/-]{2,63}$/', $ref)) throw new RuntimeException('Reference must contain 3-64 letters, numbers, dots, slashes, underscores or dashes.');
        $this->validateContext($data);
        $this->validateDraftTranslations($translations);
        $this->db->begin();
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_sop(entity,ref,status,latest_revision,fk_user_creat,fk_user_modif,date_creation) VALUES(".$this->entity.",".$this->q($ref).",0,1,".(int)$userId.",".(int)$userId.",".$this->q($this->now()).")";
        if (!$this->db->query($sql)) return $this->failTransaction();
        $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_sop');
        if (!$this->insertRevision($id, 1, $data, $translations, $userId, 0)) return $this->failTransaction();
        $this->db->commit();
        return $id;
    }

    public function saveDraft($id, array $data, array $translations, $userId)
    {
        $this->validateContext($data);
        $this->validateDraftTranslations($translations);
        $master = $this->getMaster($id);
        if (!$master || (int)$master->status === -1) throw new RuntimeException('SOP not found.');
        $this->db->begin();
        $latest = $this->getRevisionObject($id, (int)$master->latest_revision);
        if ($latest && (int)$latest->revision_status === 0) {
            if (!$this->updateRevision((int)$latest->rowid, $data, $translations, $userId)) return $this->failTransaction();
            $revisionNo = (int)$latest->revision_no;
        } else {
            $revisionNo = (int)$master->latest_revision + 1;
            if (!$this->insertRevision($id, $revisionNo, $data, $translations, $userId, 0)) return $this->failTransaction();
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_sop SET latest_revision=".$revisionNo.",fk_user_modif=".(int)$userId." WHERE rowid=".(int)$id." AND entity=".$this->entity)) return $this->failTransaction();
        }
        $this->db->commit();
        return $revisionNo;
    }

    public function publish($id, $userId)
    {
        $master = $this->getMaster($id);
        if (!$master || (int)$master->status === -1) throw new RuntimeException('SOP not found.');
        $revision = $this->getRevisionObject($id, (int)$master->latest_revision);
        if (!$revision || (int)$revision->revision_status !== 0) throw new RuntimeException('There is no draft revision to publish.');
        $translations = $this->getTranslationsByRevisionId((int)$revision->rowid);
        foreach ($this->requiredLanguages() as $language) {
            $translation = $translations[$language] ?? null;
            if (!$translation || trim((string)$translation['title']) === '' || count(self::decodeList($translation['procedure_steps'])) === 0) throw new RuntimeException('Title and procedure steps are required for '.$language.' before publication.');
        }
        $this->db->begin();
        if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_sop_revision SET revision_status=9,fk_user_modif=".(int)$userId." WHERE entity=".$this->entity." AND fk_sop=".(int)$id." AND revision_status=1")) return $this->failTransaction();
        if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_sop_revision SET revision_status=1,fk_user_modif=".(int)$userId." WHERE rowid=".(int)$revision->rowid." AND entity=".$this->entity)) return $this->failTransaction();
        if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_sop_revision_lang SET translation_status=2,fk_user_review=".(int)$userId.",date_review=".$this->q($this->now())." WHERE entity=".$this->entity." AND fk_revision=".(int)$revision->rowid." AND lang_code IN (".$this->quotedList($this->requiredLanguages()).")")) return $this->failTransaction();
        if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_sop SET status=1,published_revision=".(int)$revision->revision_no.",fk_user_modif=".(int)$userId." WHERE rowid=".(int)$id." AND entity=".$this->entity)) return $this->failTransaction();
        $this->db->commit();
    }

    public function archive($id, $userId) { $this->setMasterStatus($id, 9, $userId, 'published_revision IS NOT NULL'); }
    public function restore($id, $userId) { $this->setMasterStatus($id, 1, $userId, 'published_revision IS NOT NULL AND status=9'); }
    public function delete($id, $userId) { $this->setMasterStatus($id, -1, $userId, '1=1'); }

    public function restoreRevision($id, $revisionNo, $userId)
    {
        $master = $this->getMaster($id);
        $source = $this->getRevisionObject($id, $revisionNo);
        if (!$master || !$source || (int)$master->status === -1) throw new RuntimeException('Revision not found.');
        $translations = $this->getTranslationsByRevisionId((int)$source->rowid);
        foreach ($translations as &$translation) $translation['change_summary'] = 'Restored from revision '.(int)$revisionNo;
        unset($translation);
        $newRevision = (int)$master->latest_revision + 1;
        $this->db->begin();
        if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_sop_revision SET revision_status=9,fk_user_modif=".(int)$userId." WHERE entity=".$this->entity." AND fk_sop=".(int)$id." AND revision_status=0")) return $this->failTransaction();
        if (!$this->insertRevision($id, $newRevision, get_object_vars($source), $translations, $userId, 0)) return $this->failTransaction();
        if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_sop SET latest_revision=".$newRevision.",fk_user_modif=".(int)$userId." WHERE rowid=".(int)$id." AND entity=".$this->entity)) return $this->failTransaction();
        $this->db->commit();
        $this->publish($id, $userId);
        return $newRevision;
    }

    public function get($id, $language = 'en_US', $includeNonActive = false, $revisionNo = 0)
    {
        $language = self::normalizeLanguage($language);
        $revisionWhere = $revisionNo > 0 ? 'r.revision_no='.(int)$revisionNo : ($includeNonActive ? 'r.revision_no=s.latest_revision' : 'r.revision_no=s.published_revision');
        $sql = $this->selectSql($language)." WHERE s.entity=".$this->entity." AND s.rowid=".(int)$id." AND ".$revisionWhere;
        if (!$includeNonActive) $sql .= ' AND s.status=1 AND r.revision_status=1';
        $rows = $this->fetchAll($sql);
        if (!$rows) return null;
        $rows[0]['translations'] = $this->getTranslationsByRevisionId((int)$rows[0]['revision_id']);
        return $rows[0];
    }

    public function getRevisions($id)
    {
        $sql = "SELECT r.*,u.login user_login,(SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."cpo_sop_revision_lang tl WHERE tl.entity=r.entity AND tl.fk_revision=r.rowid AND tl.translation_status=2) approved_languages FROM ".MAIN_DB_PREFIX."cpo_sop_revision r LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=r.fk_user_modif WHERE r.entity=".$this->entity." AND r.fk_sop=".(int)$id." ORDER BY r.revision_no DESC";
        return $this->fetchAll($sql);
    }

    public function search(array $filters, $language = 'en_US', $includeNonActive = false, $limit = 200)
    {
        $language = self::normalizeLanguage($language);
        $sql = $this->selectSql($language)." WHERE s.entity=".$this->entity;
        $sql .= $includeNonActive ? ' AND r.revision_no=s.latest_revision' : ' AND s.status=1 AND r.revision_no=s.published_revision AND r.revision_status=1';
        if ($includeNonActive && isset($filters['status']) && $filters['status'] !== '') $sql .= ' AND s.status='.(int)$filters['status'];
        foreach (array('country_code','pol','pod','shipping_line','service_type','container_type','incoterm','cargo_type') as $field) if (!empty($filters[$field])) $sql .= ' AND r.'.$field.'='.$this->q($filters[$field]);
        if (!empty($filters['category'])) $sql .= ' AND r.fk_category='.(int)$filters['category'];
        if (!empty($filters['q'])) {
            $term = "'%".$this->db->escape(trim($filters['q']))."%'";
            $sql .= " AND (s.ref LIKE ".$term." OR EXISTS(SELECT 1 FROM ".MAIN_DB_PREFIX."cpo_sop_revision_lang sx WHERE sx.entity=s.entity AND sx.fk_revision=r.rowid AND (sx.title LIKE ".$term." OR sx.applicability LIKE ".$term." OR sx.procedure_steps LIKE ".$term." OR sx.warnings LIKE ".$term.")))";
        }
        $sql .= ' ORDER BY FIELD(s.status,1,0,9,-1),r.effective_date DESC,s.ref LIMIT '.max(1, min(250, (int)$limit));
        return $this->fetchAll($sql);
    }

    public function getStats()
    {
        $rows = $this->fetchAll("SELECT COUNT(*) total,SUM(status=1) active_total,SUM(status=0) draft_total,SUM(status=9) archived_total FROM ".MAIN_DB_PREFIX."cpo_sop WHERE entity=".$this->entity." AND status<>-1");
        return $rows ? $rows[0] : array('total'=>0,'active_total'=>0,'draft_total'=>0,'archived_total'=>0);
    }

    public function translationCompleteness(array $translations)
    {
        $out = array();
        foreach (self::supportedLanguages() as $language=>$meta) {
            $translation = $translations[$language] ?? array();
            $out[$language] = trim((string)($translation['title'] ?? '')) !== '' && count(self::decodeList($translation['procedure_steps'] ?? '')) > 0;
        }
        return $out;
    }

    private function validateContext(array $data)
    {
        if (!in_array($data['service_type'] ?? 'all', array('all','direct','transit','online','offline','online_offline'), true)) throw new RuntimeException('Invalid service type.');
        if (!empty($data['country_code']) && !preg_match('/^[A-Z]{2}$/', strtoupper($data['country_code']))) throw new RuntimeException('Invalid country code.');
    }

    private function validateDraftTranslations(array $translations)
    {
        if (trim((string)($translations['en_US']['title'] ?? '')) === '') throw new RuntimeException('English title is required for a draft.');
    }

    private function insertRevision($sopId, $revisionNo, array $data, array $translations, $userId, $status)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_sop_revision SET entity=".$this->entity.",fk_sop=".(int)$sopId.",revision_no=".(int)$revisionNo.",revision_status=".(int)$status.",".$this->contextAssignments($data).",fk_user_creat=".(int)$userId.",fk_user_modif=".(int)$userId.",date_creation=".$this->q($this->now());
        if (!$this->db->query($sql)) return false;
        $revisionId = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_sop_revision');
        return $this->replaceTranslations($revisionId, $translations, $userId);
    }

    private function updateRevision($revisionId, array $data, array $translations, $userId)
    {
        if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_sop_revision SET ".$this->contextAssignments($data).",fk_user_modif=".(int)$userId." WHERE rowid=".(int)$revisionId." AND entity=".$this->entity." AND revision_status=0")) return false;
        return $this->replaceTranslations($revisionId, $translations, $userId);
    }

    private function replaceTranslations($revisionId, array $translations, $userId)
    {
        foreach (self::supportedLanguages() as $language=>$meta) {
            $translation = $translations[$language] ?? array();
            $title = trim((string)($translation['title'] ?? ''));
            if ($title === '') {
                if (!$this->db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_sop_revision_lang WHERE entity=".$this->entity." AND fk_revision=".(int)$revisionId." AND lang_code=".$this->q($language))) return false;
                continue;
            }
            foreach (array('required_information','procedure_steps','required_documents','warnings') as $field) $translation[$field] = json_encode(self::decodeList($translation[$field] ?? ''), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $assignments = array('title='.$this->q($title));
            foreach (array('applicability','required_information','procedure_steps','required_documents','warnings','escalation_instructions','internal_notes','responsible_department','change_summary') as $field) $assignments[] = $field.'='.$this->qNullable(trim((string)($translation[$field] ?? '')));
            $assignments[] = 'translation_status=1';
            $assignments[] = 'fk_user_review=NULL';
            $assignments[] = 'date_review=NULL';
            $assignments[] = 'fk_user_modif='.(int)$userId;
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_sop_revision_lang SET entity=".$this->entity.",fk_revision=".(int)$revisionId.",lang_code=".$this->q($language).",".implode(',', $assignments)." ON DUPLICATE KEY UPDATE ".implode(',', $assignments);
            if (!$this->db->query($sql)) return false;
        }
        return true;
    }

    private function contextAssignments(array $data)
    {
        $out = array();
        foreach (array('country_code','pol','pod','shipping_line','service_type','container_type','incoterm','cargo_type','document_codes','effective_date') as $field) {
            $value = trim((string)($data[$field] ?? ''));
            if ($field === 'country_code') $value = strtoupper($value);
            if ($field === 'service_type' && $value === '') $value = 'all';
            $out[] = $field.'='.$this->qNullable($value);
        }
        $out[] = 'fk_category='.(!empty($data['fk_category']) ? (int)$data['fk_category'] : 'NULL');
        return implode(',', $out);
    }

    private function getTranslationsByRevisionId($revisionId)
    {
        $rows = $this->fetchAll("SELECT * FROM ".MAIN_DB_PREFIX."cpo_sop_revision_lang WHERE entity=".$this->entity." AND fk_revision=".(int)$revisionId);
        $out = array(); foreach ($rows as $row) $out[$row['lang_code']] = $row;
        return $out;
    }

    private function getMaster($id)
    {
        $res = $this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."cpo_sop WHERE rowid=".(int)$id." AND entity=".$this->entity);
        return $res ? $this->db->fetch_object($res) : null;
    }

    private function getRevisionObject($id, $revisionNo)
    {
        $res = $this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."cpo_sop_revision WHERE entity=".$this->entity." AND fk_sop=".(int)$id." AND revision_no=".(int)$revisionNo);
        return $res ? $this->db->fetch_object($res) : null;
    }

    private function setMasterStatus($id, $status, $userId, $extraWhere)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_sop SET status=".(int)$status.",fk_user_modif=".(int)$userId." WHERE rowid=".(int)$id." AND entity=".$this->entity." AND ".$extraWhere;
        if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
    }

    private function selectSql($language)
    {
        $language = self::normalizeLanguage($language);
        return "SELECT s.rowid sop_id,s.ref,s.status,s.latest_revision,s.published_revision,s.fk_user_creat,s.fk_user_modif,s.date_creation,s.tms,r.*,r.rowid revision_id,COALESCE(NULLIF(t.title,''),f.title) title,COALESCE(t.applicability,f.applicability) applicability,COALESCE(t.required_information,f.required_information) required_information,COALESCE(t.procedure_steps,f.procedure_steps) procedure_steps,COALESCE(t.required_documents,f.required_documents) required_documents,COALESCE(t.warnings,f.warnings) warnings,COALESCE(t.escalation_instructions,f.escalation_instructions) escalation_instructions,COALESCE(t.internal_notes,f.internal_notes) internal_notes,COALESCE(t.responsible_department,f.responsible_department) responsible_department,COALESCE(t.change_summary,f.change_summary) change_summary,COALESCE(t.lang_code,f.lang_code) content_language,COALESCE(t.translation_status,f.translation_status,0) translation_status,c.code category_code,COALESCE(NULLIF(ct.label,''),cf.label,c.code) category_label,uc.login created_by,um.login updated_by,(SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."cpo_sop_revision_lang ac WHERE ac.entity=s.entity AND ac.fk_revision=r.rowid AND ac.translation_status=2) approved_languages FROM ".MAIN_DB_PREFIX."cpo_sop s JOIN ".MAIN_DB_PREFIX."cpo_sop_revision r ON r.fk_sop=s.rowid AND r.entity=s.entity LEFT JOIN ".MAIN_DB_PREFIX."cpo_sop_revision_lang t ON t.fk_revision=r.rowid AND t.entity=s.entity AND t.lang_code=".$this->q($language)." LEFT JOIN ".MAIN_DB_PREFIX."cpo_sop_revision_lang f ON f.fk_revision=r.rowid AND f.entity=s.entity AND f.lang_code='en_US' LEFT JOIN ".MAIN_DB_PREFIX."cpo_sop_category c ON c.rowid=r.fk_category AND c.entity=s.entity LEFT JOIN ".MAIN_DB_PREFIX."cpo_sop_category_lang ct ON ct.fk_category=c.rowid AND ct.entity=s.entity AND ct.lang_code=".$this->q($language)." LEFT JOIN ".MAIN_DB_PREFIX."cpo_sop_category_lang cf ON cf.fk_category=c.rowid AND cf.entity=s.entity AND cf.lang_code='en_US' LEFT JOIN ".MAIN_DB_PREFIX."user uc ON uc.rowid=s.fk_user_creat LEFT JOIN ".MAIN_DB_PREFIX."user um ON um.rowid=s.fk_user_modif";
    }

    private function fetchAll($sql)
    {
        $out = array(); $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException($this->db->lasterror());
        while ($object = $this->db->fetch_object($res)) $out[] = get_object_vars($object);
        return $out;
    }

    private function quotedList(array $values) { return implode(',', array_map(array($this, 'q'), $values)); }
    private function q($value) { return "'".$this->db->escape((string)$value)."'"; }
    private function qNullable($value) { return trim((string)$value) === '' ? 'NULL' : $this->q($value); }
    private function now() { return $this->db->idate(dol_now()); }
    private function failTransaction() { $error = $this->db->lasterror(); $this->db->rollback(); throw new RuntimeException($error); }
}
