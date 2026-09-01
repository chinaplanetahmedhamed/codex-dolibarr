<?php

$socket = getenv('M061_DB_SOCKET');
$name = getenv('M061_DB_NAME') ?: 'dolibarr_m061_test';
if (!$socket || getenv('M061_DB_ALLOW_RECREATE') !== '1' || !preg_match('/^dolibarr_m061_[a-z0-9_]+$/', $name)) {
    fwrite(STDERR, "M061_REPAIR_RUNTIME=FAIL REASON=ISOLATED_DB_CONTEXT_REQUIRED\n");
    exit(64);
}

define('MAIN_DB_PREFIX', 'llx_');
function dol_now() { return strtotime('2026-08-19 12:00:00'); }

class M061RepairDb
{
    public $db;
    public $failAudit = false;
    public function __construct($socket, $name)
    {
        $this->db = mysqli_init();
        if (!$this->db->real_connect(null, 'root', '', $name, 0, $socket)) throw new RuntimeException('isolated DB unavailable');
    }
    public function query($sql)
    {
        if ($this->failAudit && stripos($sql, 'INSERT INTO llx_cpl_shipment_event') === 0) return false;
        return $this->db->query($sql);
    }
    public function fetch_object($result) { return $result instanceof mysqli_result ? $result->fetch_object() : null; }
    public function num_rows($result) { return $result instanceof mysqli_result ? $result->num_rows : 0; }
    public function escape($value) { return $this->db->real_escape_string((string) $value); }
    public function begin() { return $this->db->begin_transaction(); }
    public function commit() { return $this->db->commit(); }
    public function rollback() { return $this->db->rollback(); }
    public function affected_rows($result = null) { return $this->db->affected_rows; }
    public function idate($value) { return date('Y-m-d H:i:s', (int) $value); }
}

$admin = mysqli_init();
if (!$admin->real_connect(null, 'root', '', '', 0, $socket)) {
    fwrite(STDERR, "M061_REPAIR_RUNTIME=FAIL REASON=ISOLATED_DB_UNAVAILABLE\n");
    exit(1);
}
$quotedName = '`'.str_replace('`', '``', $name).'`';
$failures = array();
try {
    $admin->query('DROP DATABASE IF EXISTS '.$quotedName);
    if (!$admin->query('CREATE DATABASE '.$quotedName.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) throw new RuntimeException('database create failed');
    $db = new M061RepairDb($socket, $name);
    $db->query("CREATE TABLE llx_chinaplanetlogistics (rowid INT PRIMARY KEY, entity INT NOT NULL, fk_soc INT NOT NULL, container_no VARCHAR(50), nb_containers INT NOT NULL DEFAULT 1, date_atd DATE NULL, date_ata DATE NULL, fk_statut INT NOT NULL DEFAULT 0, tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $db->query("CREATE TABLE llx_cpl_shipment_event (rowid BIGINT AUTO_INCREMENT PRIMARY KEY, entity INT NOT NULL, fk_shipment INT NOT NULL, fk_soc INT NOT NULL, event_type VARCHAR(32) NOT NULL, milestone_code VARCHAR(32) NOT NULL, status_from INT NULL, status_to INT NULL, event_at DATETIME NOT NULL, planned_at DATETIME NULL, actual_at DATETIME NULL, date_value_old DATETIME NULL, date_value_new DATETIME NULL, source_type VARCHAR(32) NOT NULL, source_label_public VARCHAR(128) NOT NULL, note_public TEXT NULL, reason_public TEXT NULL, customer_visible TINYINT NOT NULL, fk_user_creat INT NOT NULL, date_creation DATETIME NOT NULL, tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $db->query("INSERT INTO llx_chinaplanetlogistics VALUES (1,1,41,'CONT-1',0,'2026-08-18',NULL,1,NOW()),(2,1,41,'',1,'2026-08-17','2026-08-18',2,NOW()),(3,1,41,'CONT-3',0,NULL,NULL,4,NOW()),(4,1,41,'',1,'2026-08-19','2026-08-18',2,NOW()),(5,1,41,'CONT-5',2,'2026-08-17','2026-08-18',5,NOW())");

    require_once dirname(__DIR__, 2).'/custom/chinaplanetlogistics/lib/m061_repair.lib.php';
    $now = strtotime('2026-08-19 12:00:00');
    $plan = cpl_m061_repair_scan_plan($db, 1, $now);
    if ($plan['candidate_count'] !== 3 || array_column($plan['candidates'], 'id') !== array(1, 2, 3)) $failures[] = 'dry-run candidate set';
    if ($plan['ambiguous_count'] !== 1 || $plan['ambiguous'][0]['id'] !== 4) $failures[] = 'ambiguous exclusion';

    $user = (object) array('id'=>9);
    $applied = cpl_m061_repair_apply_plan($db, 1, $plan, $user, $now);
    if (empty($applied['ok']) || $applied['changed'] !== 3 || !empty($applied['already_applied'])) $failures[] = 'first apply';
    $verified = cpl_m061_repair_verify_plan($db, 1, $plan);
    if (empty($verified['ok'])) $failures[] = 'verify after apply';
    $events = $db->query("SELECT COUNT(*) total,SUM(customer_visible) visible,SUM(event_type='data_correction') corrections FROM llx_cpl_shipment_event")->fetch_object();
    if ((int) $events->total !== 3 || (int) $events->visible !== 0 || (int) $events->corrections !== 1) $failures[] = 'private audit events';
    $secondApply = cpl_m061_repair_apply_plan($db, 1, $plan, $user, $now);
    if (empty($secondApply['already_applied']) || $secondApply['changed'] !== 0) $failures[] = 'apply idempotency';

    $rolledBack = cpl_m061_repair_rollback_plan($db, 1, $plan, $user, $now);
    if (empty($rolledBack['ok']) || $rolledBack['changed'] !== 3 || !cpl_m061_repair_is_rolled_back($db, 1, $plan)) $failures[] = 'rollback';
    $secondRollback = cpl_m061_repair_rollback_plan($db, 1, $plan, $user, $now);
    if (empty($secondRollback['already_rolled_back']) || $secondRollback['changed'] !== 0) $failures[] = 'rollback idempotency';
    $auditAfterRollback = $db->query("SELECT COUNT(*) total,SUM(customer_visible) visible FROM llx_cpl_shipment_event")->fetch_object();
    if ((int) $auditAfterRollback->total !== 6 || (int) $auditAfterRollback->visible !== 0) $failures[] = 'rollback audit retention';

    $freshPlan = cpl_m061_repair_scan_plan($db, 1, $now);
    $db->query("UPDATE llx_chinaplanetlogistics SET container_no='' WHERE rowid=1");
    try {
        cpl_m061_repair_apply_plan($db, 1, $freshPlan, $user, $now);
        $failures[] = 'stale plan accepted';
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'PLAN_STALE') $failures[] = 'stale plan reason';
    }
    $db->query("UPDATE llx_chinaplanetlogistics SET container_no='CONT-1' WHERE rowid=1");

    $failurePlan = cpl_m061_repair_scan_plan($db, 1, $now);
    $db->failAudit = true;
    try {
        cpl_m061_repair_apply_plan($db, 1, $failurePlan, $user, $now);
        $failures[] = 'audit failure accepted';
    } catch (RuntimeException $exception) {
        if (strpos($exception->getMessage(), 'AUDIT_INSERT_FAILED_') !== 0) $failures[] = 'audit failure reason';
    }
    $db->failAudit = false;
    foreach ($failurePlan['candidates'] as $candidate) {
        if (!cpl_m061_repair_row_matches(cpl_m061_repair_load_row($db, 1, $candidate['id']), $candidate, 'before')) $failures[] = 'transaction rollback after audit failure';
    }
    $eventsAfterFailure = $db->query("SELECT COUNT(*) total FROM llx_cpl_shipment_event")->fetch_object();
    if ((int) $eventsAfterFailure->total !== 6) $failures[] = 'failed transaction audit leakage';
} catch (Throwable $exception) {
    $failures[] = $exception->getMessage();
} finally {
    $admin->query('DROP DATABASE IF EXISTS '.$quotedName);
}

if ($failures) {
    fwrite(STDERR, "M061_REPAIR_RUNTIME=FAIL\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}
echo "M061_REPAIR_RUNTIME=PASS\n";
