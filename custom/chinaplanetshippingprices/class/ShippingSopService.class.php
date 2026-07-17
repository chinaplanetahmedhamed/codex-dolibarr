<?php

class ShippingSopService
{
    private $db;
    private $entity;

    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
    }

    public function getCategories($includeInactive = false)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop_category WHERE entity=".$this->entity;
        if (!$includeInactive) $sql .= " AND status=1";
        $sql .= " ORDER BY status DESC,position,label";
        return $this->fetchAll($sql);
    }

    public function saveCategory(array $data, $userId, $id = 0)
    {
        $code = strtolower(trim((string) ($data['code'] ?? '')));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code);
        $label = trim((string) ($data['label'] ?? ''));
        if ($code === '' || $label === '') throw new RuntimeException('Category code and label are required.');
        $values = "code=".$this->q($code).",label=".$this->q($label).",label_ar=".$this->qNullable($data['label_ar'] ?? '').",description=".$this->qNullable($data['description'] ?? '').",position=".(int) ($data['position'] ?? 100).",fk_user_modif=".(int) $userId;
        if ($id > 0) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_sop_category SET ".$values." WHERE rowid=".(int) $id." AND entity=".$this->entity;
        } else {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_sop_category SET entity=".$this->entity.",".$values.",status=1,fk_user_creat=".(int) $userId.",date_creation=".$this->q($this->now());
        }
        if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
        return $id > 0 ? $id : (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetsp_sop_category');
    }

    public function setCategoryStatus($id, $status, $userId)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_sop_category SET status=".($status ? 1 : 0).",fk_user_modif=".(int) $userId." WHERE rowid=".(int) $id." AND entity=".$this->entity;
        if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
    }

    public function create(array $data, $userId)
    {
        $ref = strtoupper(trim((string) ($data['ref'] ?? '')));
        if (!preg_match('/^[A-Z0-9][A-Z0-9._\/-]{2,63}$/', $ref)) throw new RuntimeException('Reference must contain 3–64 letters, numbers, dots, slashes, underscores or dashes.');
        $this->validateData($data);
        $this->db->begin();
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_sop(entity,ref,status,latest_revision,fk_user_creat,fk_user_modif,date_creation) VALUES(".$this->entity.",".$this->q($ref).",0,1,".(int) $userId.",".(int) $userId.",".$this->q($this->now()).")";
        if (!$this->db->query($sql)) return $this->failTransaction();
        $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetsp_sop');
        if (!$this->insertRevision($id, 1, $data, $userId, 0)) return $this->failTransaction();
        $this->db->commit();
        return $id;
    }

    public function saveDraft($id, array $data, $userId)
    {
        $this->validateData($data);
        $master = $this->getMaster($id);
        if (!$master || (int) $master->status === -1) throw new RuntimeException('SOP not found.');
        $this->db->begin();
        $latest = $this->getRevisionObject($id, (int) $master->latest_revision);
        if ($latest && (int) $latest->revision_status === 0) {
            if (!$this->updateRevision((int) $latest->rowid, $data, $userId)) return $this->failTransaction();
            $revision = (int) $latest->revision_no;
        } else {
            $revision = (int) $master->latest_revision + 1;
            if (!$this->insertRevision($id, $revision, $data, $userId, 0)) return $this->failTransaction();
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_sop SET latest_revision=".$revision.",fk_user_modif=".(int) $userId." WHERE rowid=".(int) $id." AND entity=".$this->entity)) return $this->failTransaction();
        }
        $this->db->commit();
        return $revision;
    }

    public function publish($id, $userId)
    {
        $master = $this->getMaster($id);
        if (!$master || (int) $master->status === -1) throw new RuntimeException('SOP not found.');
        $latest = $this->getRevisionObject($id, (int) $master->latest_revision);
        if (!$latest || (int) $latest->revision_status !== 0) throw new RuntimeException('There is no draft revision to publish.');
        $this->db->begin();
        $base = MAIN_DB_PREFIX."chinaplanetsp_sop_revision";
        if (!$this->db->query("UPDATE ".$base." SET revision_status=9,fk_user_modif=".(int) $userId." WHERE entity=".$this->entity." AND fk_sop=".(int) $id." AND revision_status=1")) return $this->failTransaction();
        if (!$this->db->query("UPDATE ".$base." SET revision_status=1,fk_user_modif=".(int) $userId." WHERE rowid=".(int) $latest->rowid." AND entity=".$this->entity)) return $this->failTransaction();
        if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_sop SET status=1,published_revision=".(int) $latest->revision_no.",fk_user_modif=".(int) $userId." WHERE rowid=".(int) $id." AND entity=".$this->entity)) return $this->failTransaction();
        $this->db->commit();
    }

    public function archive($id, $userId)
    {
        $this->setMasterStatus($id, 9, $userId, "published_revision IS NOT NULL");
    }

    public function restore($id, $userId)
    {
        $this->setMasterStatus($id, 1, $userId, "published_revision IS NOT NULL AND status=9");
    }

    public function delete($id, $userId)
    {
        $this->setMasterStatus($id, -1, $userId, "1=1");
    }

    public function restoreRevision($id, $revisionNo, $userId, $changeSummary = '')
    {
        $master = $this->getMaster($id);
        $source = $this->getRevisionObject($id, $revisionNo);
        if (!$master || !$source || (int) $master->status === -1) throw new RuntimeException('Revision not found.');
        $data = $this->objectToData($source);
        $data['change_summary'] = trim($changeSummary) ?: 'Restored from revision '.$revisionNo;
        $newRevision = (int) $master->latest_revision + 1;
        $this->db->begin();
        if (!$this->insertRevision($id, $newRevision, $data, $userId, 0)) return $this->failTransaction();
        if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_sop SET latest_revision=".$newRevision.",fk_user_modif=".(int) $userId." WHERE rowid=".(int) $id." AND entity=".$this->entity)) return $this->failTransaction();
        $this->db->commit();
        $this->publish($id, $userId);
        return $newRevision;
    }

    public function get($id, $includeNonActive = false, $revisionNo = 0)
    {
        $whereRevision = $revisionNo > 0 ? "r.revision_no=".(int) $revisionNo : ($includeNonActive ? "r.revision_no=s.latest_revision" : "r.revision_no=s.published_revision");
        $sql = $this->selectSql()." WHERE s.entity=".$this->entity." AND s.rowid=".(int) $id." AND ".$whereRevision;
        if (!$includeNonActive) $sql .= " AND s.status=1 AND r.revision_status=1";
        $rows = $this->fetchAll($sql);
        return $rows ? $rows[0] : null;
    }

    public function getRevisions($id)
    {
        $sql = "SELECT r.*,u.login user_login FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop_revision r LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid=r.fk_user_modif WHERE r.entity=".$this->entity." AND r.fk_sop=".(int) $id." ORDER BY r.revision_no DESC";
        return $this->fetchAll($sql);
    }

    public function search(array $filters = array(), $includeNonActive = false, $limit = 100)
    {
        $sql = $this->selectSql()." WHERE s.entity=".$this->entity;
        $sql .= $includeNonActive ? " AND r.revision_no=s.latest_revision" : " AND s.status=1 AND r.revision_no=s.published_revision AND r.revision_status=1";
        if (isset($filters['status']) && $filters['status'] !== '' && $includeNonActive) $sql .= " AND s.status=".(int) $filters['status'];
        foreach (array('country_code','pol','pod','shipping_line','service_type','container_type','incoterm','cargo_type') as $field) {
            if (!empty($filters[$field])) $sql .= " AND r.".$field."=".$this->q($filters[$field]);
        }
        if (!empty($filters['category'])) $sql .= " AND r.fk_category=".(int) $filters['category'];
        if (!empty($filters['q'])) {
            $term = '%'.$this->db->escape(trim($filters['q'])).'%';
            $q = "'".$term."'";
            $sql .= " AND (s.ref LIKE ".$q." OR r.title LIKE ".$q." OR r.title_ar LIKE ".$q." OR r.applicability LIKE ".$q." OR r.procedure_steps LIKE ".$q." OR r.required_documents LIKE ".$q." OR r.warnings LIKE ".$q.")";
        }
        $sql .= " ORDER BY FIELD(s.status,1,0,9,-1),r.effective_date DESC,s.ref LIMIT ".max(1, min(250, (int) $limit));
        return $this->fetchAll($sql);
    }

    public function suggest(array $context, $limit = 5)
    {
        $rows = $this->search(array(), false, 250);
        $documents = array_map('strtolower', (array) ($context['documents'] ?? array()));
        foreach ($rows as &$row) {
            $score = 0;
            foreach (array('country_code'=>5,'pod'=>5,'shipping_line'=>3,'service_type'=>3,'container_type'=>2,'incoterm'=>4,'cargo_type'=>5) as $field => $weight) {
                $sopValue = strtolower(trim((string) ($row[$field] ?? '')));
                $value = strtolower(trim((string) ($context[$field] ?? '')));
                if ($sopValue === '' || $sopValue === 'all') $score += 1;
                elseif ($value !== '' && $sopValue === $value) $score += $weight;
                else $score -= 20;
            }
            $codes = array_filter(array_map('trim', explode(',', strtolower((string) ($row['document_codes'] ?? '')))));
            foreach ($codes as $code) $score += in_array($code, $documents, true) ? 8 : -10;
            if (in_array('cargox', $documents, true) && $row['category_code'] === 'cargox_egypt') $score += 12;
            if (in_array('saber', $documents, true) && $row['category_code'] === 'saber_saudi') $score += 12;
            if (strtolower((string) ($context['incoterm'] ?? '')) === 'exw' && $row['category_code'] === 'exw') $score += 10;
            if (in_array(strtolower((string) ($context['cargo_type'] ?? '')), array('battery','dangerous_goods','chemical','oil','powder'), true) && in_array($row['category_code'], array('battery_cargo','dangerous_goods','special_cargo'), true)) $score += 10;
            $row['_score'] = $score;
        }
        unset($row);
        usort($rows, function ($a, $b) { return $b['_score'] <=> $a['_score']; });
        return array_slice(array_values(array_filter($rows, function ($row) { return $row['_score'] > 0; })), 0, max(1, (int) $limit));
    }

    public function getStats()
    {
        $rows = $this->fetchAll("SELECT COUNT(*) total,SUM(status=1) active_total,SUM(status=0) draft_total,SUM(status=9) archived_total FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop WHERE entity=".$this->entity." AND status<>-1");
        return $rows ? $rows[0] : array('total'=>0,'active_total'=>0,'draft_total'=>0,'archived_total'=>0);
    }

    public static function decodeList($value)
    {
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) return array_values(array_filter(array_map('trim', $decoded), 'strlen'));
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $value)), 'strlen'));
    }

    private function validateData(array $data)
    {
        if (trim((string) ($data['title'] ?? '')) === '') throw new RuntimeException('Title is required.');
        if (!in_array($data['service_type'] ?? 'all', array('all','direct','transit'), true)) throw new RuntimeException('Invalid service type.');
        if (!empty($data['country_code']) && !preg_match('/^[A-Z]{2}$/', strtoupper($data['country_code']))) throw new RuntimeException('Invalid country code.');
    }

    private function revisionAssignments(array $data, $userId)
    {
        $listFields = array('required_information','procedure_steps','required_documents','warnings');
        foreach ($listFields as $field) $data[$field] = json_encode(self::decodeList($data[$field] ?? ''), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $fields = array('title','title_ar','country_code','pol','pod','shipping_line','service_type','container_type','incoterm','cargo_type','document_codes','applicability','required_information','procedure_steps','required_documents','warnings','escalation_instructions','internal_notes','responsible_department','effective_date','change_summary');
        $out = array();
        foreach ($fields as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($field === 'country_code') $value = strtoupper($value);
            if ($field === 'service_type' && $value === '') $value = 'all';
            $out[] = $field.'='.$this->qNullable($value);
        }
        $out[] = 'fk_category='.(!empty($data['fk_category']) ? (int) $data['fk_category'] : 'NULL');
        $out[] = 'fk_user_modif='.(int) $userId;
        return implode(',', $out);
    }

    private function insertRevision($id, $revision, array $data, $userId, $status)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_sop_revision SET entity=".$this->entity.",fk_sop=".(int) $id.",revision_no=".(int) $revision.",revision_status=".(int) $status.",".$this->revisionAssignments($data, $userId).",fk_user_creat=".(int) $userId.",date_creation=".$this->q($this->now());
        return (bool) $this->db->query($sql);
    }

    private function updateRevision($rowId, array $data, $userId)
    {
        return (bool) $this->db->query("UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_sop_revision SET ".$this->revisionAssignments($data, $userId)." WHERE rowid=".(int) $rowId." AND entity=".$this->entity." AND revision_status=0");
    }

    private function getMaster($id)
    {
        $res = $this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop WHERE rowid=".(int) $id." AND entity=".$this->entity);
        return $res ? $this->db->fetch_object($res) : null;
    }

    private function getRevisionObject($id, $revision)
    {
        $res = $this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop_revision WHERE entity=".$this->entity." AND fk_sop=".(int) $id." AND revision_no=".(int) $revision);
        return $res ? $this->db->fetch_object($res) : null;
    }

    private function objectToData($object)
    {
        $data = get_object_vars($object);
        $data['fk_category'] = $data['fk_category'] ?? '';
        return $data;
    }

    private function setMasterStatus($id, $status, $userId, $extraWhere)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_sop SET status=".(int) $status.",fk_user_modif=".(int) $userId." WHERE rowid=".(int) $id." AND entity=".$this->entity." AND ".$extraWhere;
        if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
    }

    private function selectSql()
    {
        return "SELECT s.rowid sop_id,s.ref,s.status,s.latest_revision,s.published_revision,s.fk_user_creat,s.fk_user_modif,s.date_creation,s.tms,r.*,r.rowid revision_id,c.code category_code,c.label category_label,c.label_ar category_label_ar,uc.login created_by,um.login updated_by FROM ".MAIN_DB_PREFIX."chinaplanetsp_sop s JOIN ".MAIN_DB_PREFIX."chinaplanetsp_sop_revision r ON r.fk_sop=s.rowid AND r.entity=s.entity LEFT JOIN ".MAIN_DB_PREFIX."chinaplanetsp_sop_category c ON c.rowid=r.fk_category AND c.entity=s.entity LEFT JOIN ".MAIN_DB_PREFIX."user uc ON uc.rowid=s.fk_user_creat LEFT JOIN ".MAIN_DB_PREFIX."user um ON um.rowid=s.fk_user_modif";
    }

    private function fetchAll($sql)
    {
        $out = array();
        $res = $this->db->query($sql);
        if (!$res) throw new RuntimeException($this->db->lasterror());
        while ($object = $this->db->fetch_object($res)) $out[] = get_object_vars($object);
        return $out;
    }

    private function q($value) { return "'".$this->db->escape((string) $value)."'"; }
    private function qNullable($value) { return trim((string) $value) === '' ? 'NULL' : $this->q($value); }
    private function now() { return $this->db->idate(dol_now()); }
    private function failTransaction() { $error = $this->db->lasterror(); $this->db->rollback(); throw new RuntimeException($error); }
}
