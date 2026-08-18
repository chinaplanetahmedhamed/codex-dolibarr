<?php

require_once __DIR__.'/InternalTaskAuthorizationService.class.php';

if (!class_exists('InternalTaskConflictException')) { class InternalTaskConflictException extends RuntimeException {} }

/** Entity-scoped configurable presentation stages for the internal task board. */
class InternalTaskStageService
{
    private $db;
    private $actor;
    private $entity;
    private $auth;

    public function __construct($db, $actor = null, $entity = null)
    {
        global $conf;
        $this->db = $db;
        $this->actor = is_object($actor) ? $actor : (isset($GLOBALS['user']) ? $GLOBALS['user'] : new stdClass());
        $this->entity = $entity === null ? (int) $conf->entity : (int) $entity;
        $this->auth = new InternalTaskAuthorizationService($db, $this->actor, $this->entity);
    }

    public function listStages($includeInactive = false)
    {
        $this->auth->requireBoard();
        $sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'cpo_task_stage WHERE entity='.$this->entity;
        if (!$includeInactive) $sql .= " AND active=1 AND behavior<>'legacy'";
        $sql .= " ORDER BY FIELD(behavior,'todo','work','waiting','review','done','legacy'),position,rowid";
        $rows = $this->rows($sql);
        foreach ($rows as &$row) $row['label'] = $this->localizedLabel($row);
        unset($row);
        return $rows;
    }

    public function configVersion()
    {
        $this->auth->requireBoard();
        $rows = $this->rows('SELECT rowid,version,active,position FROM '.MAIN_DB_PREFIX.'cpo_task_stage WHERE entity='.$this->entity.' ORDER BY rowid');
        $parts = array();
        foreach ($rows as $row) $parts[] = (int)$row['rowid'].':'.(int)$row['version'].':'.(int)$row['active'].':'.(int)$row['position'];
        return hash('sha256', implode('|', $parts));
    }

    public function lockStage($stageId)
    {
        $res = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_task_stage WHERE entity='.$this->entity.' AND rowid='.(int)$stageId.' FOR UPDATE');
        $row = $res ? $this->db->fetch_object($res) : null;
        return $row ? get_object_vars($row) : null;
    }

    public function systemStage($behavior, $lock = false)
    {
        $allowed = array('todo','work','waiting','review','done','legacy');
        if (!in_array($behavior, $allowed, true)) throw new RuntimeException('Invalid task stage behavior.');
        $sql = 'SELECT * FROM '.MAIN_DB_PREFIX.'cpo_task_stage WHERE entity='.$this->entity.' AND behavior='.$this->q($behavior).' AND is_system=1';
        if ($behavior !== 'legacy') $sql .= ' AND active=1';
        $sql .= ' ORDER BY position,rowid LIMIT 1'.($lock ? ' FOR UPDATE' : '');
        $res = $this->db->query($sql);
        $row = $res ? $this->db->fetch_object($res) : null;
        return $row ? get_object_vars($row) : null;
    }

    public function createWorkStage(array $data, $expectedConfig)
    {
        $this->requireManage();
        $this->begin();
        try {
            $this->lockAll();
            $this->assertConfig($expectedConfig);
            $code = strtolower(trim((string)(isset($data['code']) ? $data['code'] : '')));
            if (!preg_match('/^[a-z0-9_]{1,64}$/', $code)) throw new RuntimeException('Invalid task stage code.');
            $labels = $this->labels($data);
            $color = $this->color(isset($data['color']) ? $data['color'] : '');
            $res = $this->db->query('SELECT COALESCE(MAX(position),0) max_position FROM '.MAIN_DB_PREFIX."cpo_task_stage WHERE entity=".$this->entity." AND behavior='work'");
            $row = $res ? $this->db->fetch_object($res) : null;
            $position = min(10000, max(10, ($row ? (int)$row->max_position : 0) + 10));
            $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_stage(entity,code,label_en,label_ar,label_zh,color,position,behavior,is_system,active,version,fk_user_creat,fk_user_modif,date_creation) VALUES(';
            $sql .= $this->entity.','.$this->q($code).','.$this->q($labels['label_en']).','.$this->q($labels['label_ar']).','.$this->q($labels['label_zh']).','.$this->q($color).','.$position.",'work',0,1,1,".$this->actorId().','.$this->actorId().','.$this->q($this->now()).')';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to create task stage.');
            $id = (int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_task_stage');
            $this->audit('task_stage_created', $code);
            $this->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateStage($stageId, $expectedVersion, array $data)
    {
        $this->requireManage();
        $this->begin();
        try {
            $stage = $this->lockStage($stageId);
            if (!$stage || (int)$stage['version'] !== (int)$expectedVersion) throw new InternalTaskConflictException('Task stage changed. Refresh and retry.');
            $labels = $this->labels($data);
            $color = $this->color(isset($data['color']) ? $data['color'] : '');
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'cpo_task_stage SET label_en='.$this->q($labels['label_en']).',label_ar='.$this->q($labels['label_ar']).',label_zh='.$this->q($labels['label_zh']).',color='.$this->q($color).',fk_user_modif='.$this->actorId().',version=version+1';
            $sql .= ' WHERE entity='.$this->entity.' AND rowid='.(int)$stageId.' AND version='.(int)$expectedVersion;
            $this->cas($sql);
            $this->audit('task_stage_updated', (string)$stage['code']);
            $this->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function reorder(array $stageIds, $expectedConfig)
    {
        $this->requireManage();
        $stageIds = array_values(array_map('intval', $stageIds));
        if (!$stageIds || count($stageIds) !== count(array_unique($stageIds))) throw new RuntimeException('Invalid task stage order.');
        $this->begin();
        try {
            $rows = $this->lockAll();
            $this->assertConfig($expectedConfig, $rows);
            $expected = array();
            foreach ($rows as $row) if ((string)$row['behavior'] === 'work' && (int)$row['active'] === 1) $expected[] = (int)$row['rowid'];
            sort($expected); $submitted = $stageIds; sort($submitted);
            if ($expected !== $submitted) throw new RuntimeException('Task stage order is incomplete.');
            $position = 100;
            foreach ($stageIds as $id) {
                if ($position > 10000) throw new RuntimeException('Task stage position is out of range.');
                if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_task_stage SET position='.$position.',fk_user_modif='.$this->actorId().',version=version+1 WHERE entity='.$this->entity.' AND rowid='.$id." AND behavior='work' AND active=1")) throw new RuntimeException('Unable to reorder task stages.');
                $position += 10;
            }
            $this->audit('task_stage_reordered', implode(',', $stageIds));
            $this->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function setActive($stageId, $expectedVersion, $active, $expectedConfig)
    {
        $this->requireManage();
        $this->begin();
        try {
            $stage = $this->lockStage($stageId);
            if (!$stage) throw new InternalTaskAccessException('Task access denied.');
            if ((int)$stage['version'] !== (int)$expectedVersion) throw new InternalTaskConflictException('Task stage changed. Refresh and retry.');
            $this->lockAll();
            $this->assertConfig($expectedConfig);
            if ((int)$stage['is_system'] === 1 || (string)$stage['behavior'] !== 'work') throw new RuntimeException('Protected task stage cannot be changed.');
            $active = $active ? 1 : 0;
            if ((int)$stage['active'] === $active) { $this->commit(); return true; }
            if (!$active) {
                $res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task WHERE entity='.$this->entity.' AND fk_stage='.(int)$stageId." AND status<>'deleted' LIMIT 1 FOR UPDATE");
                if ($res && $this->db->fetch_object($res)) throw new RuntimeException('Task stage is in use.');
            }
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'cpo_task_stage SET active='.$active.',fk_user_modif='.$this->actorId().',version=version+1 WHERE entity='.$this->entity.' AND rowid='.(int)$stageId.' AND version='.(int)$expectedVersion;
            $this->cas($sql);
            $this->audit($active ? 'task_stage_activated' : 'task_stage_deactivated', (string)$stage['code']);
            $this->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function requireManage()
    {
        if (!$this->auth->isAdministrator()) throw new InternalTaskAccessException('Task access denied.');
    }

    private function lockAll()
    {
        return $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_task_stage WHERE entity='.$this->entity.' ORDER BY rowid FOR UPDATE');
    }

    private function assertConfig($expected, $lockedRows = null)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', (string)$expected)) throw new InternalTaskConflictException('Task stage configuration changed.');
        $rows = is_array($lockedRows) ? $lockedRows : $this->rows('SELECT rowid,version,active,position FROM '.MAIN_DB_PREFIX.'cpo_task_stage WHERE entity='.$this->entity.' ORDER BY rowid');
        $parts = array();
        foreach ($rows as $row) $parts[] = (int)$row['rowid'].':'.(int)$row['version'].':'.(int)$row['active'].':'.(int)$row['position'];
        if (!hash_equals((string)$expected, hash('sha256', implode('|', $parts)))) throw new InternalTaskConflictException('Task stage configuration changed.');
    }

    private function labels(array $data)
    {
        $out = array();
        foreach (array('label_en','label_ar','label_zh') as $key) {
            $value = trim(strip_tags((string)(isset($data[$key]) ? $data[$key] : '')));
            if ($value === '' || preg_match('/[\x00-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $value)) throw new RuntimeException('Invalid task stage label.');
            $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($length > 255) throw new RuntimeException('Task stage label is too long.');
            $out[$key] = $value;
        }
        return $out;
    }

    private function color($value)
    {
        $value = trim((string)$value);
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) throw new RuntimeException('Invalid task stage color.');
        return strtoupper($value);
    }

    private function localizedLabel(array $stage)
    {
        $locale = !empty($GLOBALS['langs']->defaultlang) ? (string)$GLOBALS['langs']->defaultlang : 'en_US';
        if (strpos($locale, 'ar') === 0) return (string)$stage['label_ar'];
        if (strpos($locale, 'zh') === 0) return (string)$stage['label_zh'];
        return (string)$stage['label_en'];
    }

    private function audit($code, $message)
    {
        $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity(entity,fk_operation,fk_task,action_code,message,visibility,fk_user,date_action) VALUES(';
        $sql .= $this->entity.',NULL,NULL,'.$this->q($code).','.$this->q(substr((string)$message,0,500)).",'internal',".$this->actorId().','.$this->q($this->now()).')';
        if (!$this->db->query($sql)) throw new RuntimeException('Unable to save task stage audit.');
    }

    private function actorId() { return max(0, (int)$this->auth->actorId()); }
    private function q($value) { return "'".$this->db->escape((string)$value)."'"; }
    private function now() { return date('Y-m-d H:i:s'); }
    private function begin() { if (!$this->db->begin()) throw new RuntimeException('Unable to begin task stage transaction.'); }
    private function commit() { if (!$this->db->commit()) throw new RuntimeException('Unable to commit task stage transaction.'); }
    private function cas($sql) { $res=$this->db->query($sql); if(!$res)throw new RuntimeException('Unable to update task stage.'); if($this->db->affected_rows($res)!==1)throw new InternalTaskConflictException('Task stage changed. Refresh and retry.'); }
    private function rows($sql) { $out=array();$res=$this->db->query($sql);if(!$res)throw new RuntimeException('Unable to read task stages.');while($row=$this->db->fetch_object($res))$out[]=get_object_vars($row);return $out; }
}
