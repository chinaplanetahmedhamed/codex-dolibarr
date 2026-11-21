<?php
/**
 * M061-R01 shipment consistency repair engine.
 *
 * The engine is deliberately separate from the release wrapper. It only
 * repairs unambiguous status/container-count facts inside one active entity,
 * writes private audit events, and never invokes customer email services.
 */

require_once __DIR__.'/chinaplanetlogistics.lib.php';
require_once __DIR__.'/shipment_customer.lib.php';

function cpl_m061_repair_scalar_date($value)
{
    if ($value === null || $value === '' || (is_numeric($value) && (int) $value <= 0)) return '';
    if (is_numeric($value)) return date('Y-m-d H:i:s', (int) $value);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}(?::\d{2})?))?$/', trim((string) $value), $matches)) {
        return $matches[1].' '.(!empty($matches[2]) ? (strlen($matches[2]) === 5 ? $matches[2].':00' : $matches[2]) : '00:00:00');
    }
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? '' : date('Y-m-d H:i:s', $timestamp);
}

function cpl_m061_repair_candidate_from_row($row, $entity, $now)
{
    $id = isset($row->rowid) ? (int) $row->rowid : 0;
    if ($id <= 0 || (int) $row->entity !== (int) $entity) {
        return array('kind'=>'ambiguous', 'id'=>$id, 'errors'=>array('record_scope_invalid'));
    }

    $normalized = chinaplanetlogistics_normalize_operational_facts(
        (int) $row->fk_statut,
        isset($row->date_atd) ? $row->date_atd : null,
        isset($row->date_ata) ? $row->date_ata : null,
        isset($row->container_no) ? $row->container_no : '',
        isset($row->nb_containers) ? (int) $row->nb_containers : 0,
        $now
    );
    if (empty($normalized['ok'])) {
        return array('kind'=>'ambiguous', 'id'=>$id, 'errors'=>array_values($normalized['errors']));
    }
    if (empty($normalized['changes'])) return array('kind'=>'unchanged', 'id'=>$id);

    return array(
        'kind'=>'candidate',
        'record'=>array(
            'id'=>$id,
            'before_status'=>(int) $row->fk_statut,
            'after_status'=>(int) $normalized['status'],
            'before_container_count'=>(int) $row->nb_containers,
            'after_container_count'=>(int) $normalized['container_count'],
            'date_atd'=>cpl_m061_repair_scalar_date(isset($row->date_atd) ? $row->date_atd : null),
            'date_ata'=>cpl_m061_repair_scalar_date(isset($row->date_ata) ? $row->date_ata : null),
            'tms'=>cpl_m061_repair_scalar_date(isset($row->tms) ? $row->tms : null),
        ),
    );
}

function cpl_m061_repair_hash_payload($plan)
{
    return array(
        'format'=>'m061-r01-plan-v1',
        'entity'=>(int) $plan['entity'],
        'candidates'=>array_values($plan['candidates']),
        'ambiguous'=>array_values($plan['ambiguous']),
    );
}

function cpl_m061_repair_data_hash($plan)
{
    return hash('sha256', json_encode(cpl_m061_repair_hash_payload($plan), JSON_UNESCAPED_SLASHES));
}

function cpl_m061_repair_plan_hash($plan)
{
    $payload = array(
        'data_sha256'=>cpl_m061_repair_data_hash($plan),
        'execution_id'=>(string) $plan['execution_id'],
        'generated_at'=>(string) $plan['generated_at'],
    );
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function cpl_m061_repair_plan_from_rows($rows, $entity, $now = null)
{
    $entity = (int) $entity;
    $now = $now === null ? time() : (int) $now;
    $candidates = array();
    $ambiguous = array();
    foreach ($rows as $row) {
        $result = cpl_m061_repair_candidate_from_row($row, $entity, $now);
        if ($result['kind'] === 'candidate') $candidates[] = $result['record'];
        if ($result['kind'] === 'ambiguous') $ambiguous[] = array('id'=>(int) $result['id'], 'errors'=>array_values($result['errors']));
    }
    usort($candidates, function ($a, $b) { return $a['id'] <=> $b['id']; });
    usort($ambiguous, function ($a, $b) { return $a['id'] <=> $b['id']; });
    $plan = array(
        'format'=>'m061-r01-plan-v1',
        'mission'=>'M061-R01',
        'entity'=>$entity,
        'execution_id'=>'m061-'.bin2hex(random_bytes(12)),
        'generated_at'=>gmdate('Y-m-d\TH:i:s\Z', $now),
        'candidate_count'=>count($candidates),
        'ambiguous_count'=>count($ambiguous),
        'candidates'=>$candidates,
        'ambiguous'=>$ambiguous,
    );
    $plan['data_sha256'] = cpl_m061_repair_data_hash($plan);
    $plan['plan_sha256'] = cpl_m061_repair_plan_hash($plan);
    return $plan;
}

function cpl_m061_repair_validate_plan($plan, $entity)
{
    if (!is_array($plan) || !isset($plan['format'], $plan['entity'], $plan['execution_id'], $plan['generated_at'], $plan['candidates'], $plan['ambiguous'], $plan['data_sha256'], $plan['plan_sha256'])) return false;
    if ($plan['format'] !== 'm061-r01-plan-v1' || (int) $plan['entity'] !== (int) $entity) return false;
    if (!preg_match('/^m061-[a-f0-9]{24}$/', (string) $plan['execution_id'])) return false;
    if (!is_array($plan['candidates']) || !is_array($plan['ambiguous'])) return false;
    if (!hash_equals((string) $plan['data_sha256'], cpl_m061_repair_data_hash($plan))) return false;
    if (!hash_equals((string) $plan['plan_sha256'], cpl_m061_repair_plan_hash($plan))) return false;
    $ids = array();
    foreach ($plan['candidates'] as $candidate) {
        $required = array('id', 'before_status', 'after_status', 'before_container_count', 'after_container_count', 'date_atd', 'date_ata', 'tms');
        foreach ($required as $key) if (!array_key_exists($key, $candidate)) return false;
        $id = (int) $candidate['id'];
        if ($id <= 0 || isset($ids[$id])) return false;
        $ids[$id] = true;
    }
    return true;
}

function cpl_m061_repair_scan_rows($db, $entity, $forUpdate = false)
{
    $sql = 'SELECT rowid,entity,fk_soc,container_no,nb_containers,date_atd,date_ata,fk_statut,tms';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.(int) $entity.' ORDER BY rowid';
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $resql = $db->query($sql);
    if (!$resql) throw new RuntimeException('SCAN_FAILED');
    $rows = array();
    while ($row = $db->fetch_object($resql)) $rows[] = $row;
    return $rows;
}

function cpl_m061_repair_scan_plan($db, $entity, $now = null)
{
    return cpl_m061_repair_plan_from_rows(cpl_m061_repair_scan_rows($db, $entity), $entity, $now);
}

function cpl_m061_repair_event_token($plan, $phase = 'apply')
{
    $phase = $phase === 'rollback' ? 'rollback' : 'apply';
    return 'M061-R01:'.(string) $plan['plan_sha256'].':'.$phase;
}

function cpl_m061_repair_load_row($db, $entity, $id, $forUpdate = false)
{
    $sql = 'SELECT rowid,entity,fk_soc,container_no,nb_containers,date_atd,date_ata,fk_statut,tms';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE rowid='.(int) $id.' AND entity='.(int) $entity;
    if ($forUpdate) $sql .= ' FOR UPDATE';
    $resql = $db->query($sql);
    if (!$resql || $db->num_rows($resql) !== 1) return null;
    return $db->fetch_object($resql);
}

function cpl_m061_repair_row_matches($row, $candidate, $state, $checkTimestamp = false)
{
    $prefix = $state === 'after' ? 'after_' : 'before_';
    return $row
        && (int) $row->fk_statut === (int) $candidate[$prefix.'status']
        && (int) $row->nb_containers === (int) $candidate[$prefix.'container_count']
        && cpl_m061_repair_scalar_date($row->date_atd) === (string) $candidate['date_atd']
        && cpl_m061_repair_scalar_date($row->date_ata) === (string) $candidate['date_ata']
        && (!$checkTimestamp || cpl_m061_repair_scalar_date($row->tms) === (string) $candidate['tms']);
}

function cpl_m061_repair_event_exists($db, $entity, $candidate, $token)
{
    $sql = 'SELECT COUNT(*) count_rows FROM '.MAIN_DB_PREFIX.'cpl_shipment_event';
    $sql .= ' WHERE entity='.(int) $entity.' AND fk_shipment='.(int) $candidate['id'];
    $sql .= " AND customer_visible=0 AND reason_public='".$db->escape($token)."'";
    $sql .= " AND event_type IN ('status_changed','data_correction')";
    $resql = $db->query($sql);
    $row = $resql ? $db->fetch_object($resql) : null;
    return $row && (int) $row->count_rows === 1;
}

function cpl_m061_repair_verify_plan($db, $entity, $plan)
{
    if (!cpl_m061_repair_validate_plan($plan, $entity)) return array('ok'=>false, 'reason'=>'PLAN_INVALID');
    $applyToken = cpl_m061_repair_event_token($plan, 'apply');
    $rollbackToken = cpl_m061_repair_event_token($plan, 'rollback');
    $failures = array();
    foreach ($plan['candidates'] as $candidate) {
        $row = cpl_m061_repair_load_row($db, $entity, $candidate['id']);
        if (!cpl_m061_repair_row_matches($row, $candidate, 'after')) $failures[] = array('id'=>(int) $candidate['id'], 'reason'=>'STATE_NOT_APPLIED');
        elseif (!cpl_m061_repair_event_exists($db, $entity, $candidate, $applyToken)) $failures[] = array('id'=>(int) $candidate['id'], 'reason'=>'AUDIT_EVENT_MISSING');
        elseif (cpl_m061_repair_event_exists($db, $entity, $candidate, $rollbackToken)) $failures[] = array('id'=>(int) $candidate['id'], 'reason'=>'PLAN_ALREADY_ROLLED_BACK');
    }
    return array('ok'=>empty($failures), 'candidate_count'=>count($plan['candidates']), 'failures'=>$failures);
}

function cpl_m061_repair_is_rolled_back($db, $entity, $plan)
{
    if (!cpl_m061_repair_validate_plan($plan, $entity)) return false;
    $rollbackToken = cpl_m061_repair_event_token($plan, 'rollback');
    foreach ($plan['candidates'] as $candidate) {
        $row = cpl_m061_repair_load_row($db, $entity, $candidate['id']);
        if (!cpl_m061_repair_row_matches($row, $candidate, 'before') || !cpl_m061_repair_event_exists($db, $entity, $candidate, $rollbackToken)) return false;
    }
    return true;
}

function cpl_m061_repair_apply_plan($db, $entity, $plan, $user, $now = null)
{
    if (!cpl_m061_repair_validate_plan($plan, $entity)) throw new RuntimeException('PLAN_INVALID');
    $now = $now === null ? time() : (int) $now;
    foreach ($plan['candidates'] as $candidate) {
        if (cpl_m061_repair_event_exists($db, $entity, $candidate, cpl_m061_repair_event_token($plan, 'rollback'))) throw new RuntimeException('PLAN_ALREADY_ROLLED_BACK');
    }
    $verified = cpl_m061_repair_verify_plan($db, $entity, $plan);
    if (!empty($verified['ok'])) return array('ok'=>true, 'already_applied'=>true, 'changed'=>0);
    $currentPlan = cpl_m061_repair_scan_plan($db, $entity, $now);
    if (!hash_equals((string) $plan['data_sha256'], (string) $currentPlan['data_sha256'])) throw new RuntimeException('PLAN_STALE');
    $token = cpl_m061_repair_event_token($plan, 'apply');
    $db->begin();
    try {
        foreach ($plan['candidates'] as $candidate) {
            $row = cpl_m061_repair_load_row($db, $entity, $candidate['id'], true);
            if (!cpl_m061_repair_row_matches($row, $candidate, 'before', true)) throw new RuntimeException('PLAN_STALE_'.$candidate['id']);
            if (cpl_m061_repair_event_exists($db, $entity, $candidate, $token)) throw new RuntimeException('AUDIT_COLLISION_'.$candidate['id']);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'chinaplanetlogistics SET fk_statut='.(int) $candidate['after_status'];
            $sql .= ',nb_containers='.(int) $candidate['after_container_count'];
            $sql .= ' WHERE rowid='.(int) $candidate['id'].' AND entity='.(int) $entity;
            $update = $db->query($sql);
            if (!$update || $db->affected_rows($update) !== 1) throw new RuntimeException('UPDATE_FAILED_'.$candidate['id']);

            $shipment = (object) array('id'=>(int) $candidate['id'], 'entity'=>(int) $entity, 'fk_soc'=>(int) $row->fk_soc);
            $statusChanged = (int) $candidate['before_status'] !== (int) $candidate['after_status'];
            $event = array(
                'event_type'=>$statusChanged ? 'status_changed' : 'data_correction',
                'milestone_code'=>cpl_status_milestone((int) $candidate['after_status']),
                'status_from'=>(int) $candidate['before_status'],
                'status_to'=>(int) $candidate['after_status'],
                'event_at'=>$now,
                'source_type'=>'system',
                'reason_public'=>$token,
                'note_public'=>'',
                'customer_visible'=>0,
            );
            if (!cpl_shipment_event_insert($db, $shipment, $user, $event)) throw new RuntimeException('AUDIT_INSERT_FAILED_'.$candidate['id']);
        }
        if ($db->commit() <= 0) throw new RuntimeException('COMMIT_FAILED');
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
    return array('ok'=>true, 'already_applied'=>false, 'changed'=>count($plan['candidates']));
}

function cpl_m061_repair_rollback_plan($db, $entity, $plan, $user, $now = null)
{
    if (!cpl_m061_repair_validate_plan($plan, $entity)) throw new RuntimeException('PLAN_INVALID');
    if (cpl_m061_repair_is_rolled_back($db, $entity, $plan)) return array('ok'=>true, 'already_rolled_back'=>true, 'changed'=>0);
    $verified = cpl_m061_repair_verify_plan($db, $entity, $plan);
    if (empty($verified['ok'])) throw new RuntimeException('ROLLBACK_STATE_DRIFT');
    $rollbackToken = cpl_m061_repair_event_token($plan, 'rollback');
    $now = $now === null ? time() : (int) $now;
    $db->begin();
    try {
        foreach ($plan['candidates'] as $candidate) {
            $row = cpl_m061_repair_load_row($db, $entity, $candidate['id'], true);
            if (!cpl_m061_repair_row_matches($row, $candidate, 'after')) throw new RuntimeException('ROLLBACK_STATE_DRIFT_'.$candidate['id']);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'chinaplanetlogistics SET fk_statut='.(int) $candidate['before_status'];
            $sql .= ',nb_containers='.(int) $candidate['before_container_count'];
            $sql .= ' WHERE rowid='.(int) $candidate['id'].' AND entity='.(int) $entity;
            $update = $db->query($sql);
            if (!$update || $db->affected_rows($update) !== 1) throw new RuntimeException('ROLLBACK_UPDATE_FAILED_'.$candidate['id']);

            $shipment = (object) array('id'=>(int) $candidate['id'], 'entity'=>(int) $entity, 'fk_soc'=>(int) $row->fk_soc);
            $event = array(
                'event_type'=>'data_correction',
                'milestone_code'=>cpl_status_milestone((int) $candidate['before_status']),
                'status_from'=>(int) $candidate['after_status'],
                'status_to'=>(int) $candidate['before_status'],
                'event_at'=>$now,
                'source_type'=>'system',
                'reason_public'=>$rollbackToken,
                'note_public'=>'',
                'customer_visible'=>0,
            );
            if (!cpl_shipment_event_insert($db, $shipment, $user, $event)) throw new RuntimeException('ROLLBACK_AUDIT_INSERT_FAILED_'.$candidate['id']);
        }
        if ($db->commit() <= 0) throw new RuntimeException('ROLLBACK_COMMIT_FAILED');
    } catch (Throwable $exception) {
        $db->rollback();
        throw $exception;
    }
    return array('ok'=>true, 'already_rolled_back'=>false, 'changed'=>count($plan['candidates']));
}
