<?php

require_once __DIR__.'/../lib/financial.lib.php';

/**
 * Allocation control for customer funds already recorded by native Dolibarr.
 *
 * This service never creates a bank movement, payment, supplier payment, FX
 * movement or accounting entry. Confirmation references an existing validated
 * customer payment. Allocation records reserve/attribute that money without
 * counting the native cash receipt a second time.
 */
class CustomerFundsService
{
    private $db;
    private $entity;

    public function __construct($db, $entity = 0)
    {
        global $conf;
        $this->db = $db;
        $this->entity = (int) $entity > 0 ? (int) $entity : (!empty($conf->entity) ? (int) $conf->entity : 1);
    }

    public static function receiptStatuses()
    {
        return array('pending', 'confirmed', 'reversed');
    }

    public static function allocationStatuses()
    {
        return array('reserved', 'settled', 'reversed');
    }

    public static function targetTypes()
    {
        return array('operation', 'shipment', 'project', 'customer_invoice');
    }

    public static function summarize($receiptAmount, array $allocations)
    {
        $summary = array('confirmed'=>(float) $receiptAmount, 'reserved'=>0.0, 'settled'=>0.0, 'unallocated'=>(float) $receiptAmount);
        foreach ($allocations as $allocation) {
            $status = isset($allocation['status']) ? (string) $allocation['status'] : '';
            $amount = max(0.0, (float) (isset($allocation['amount']) ? $allocation['amount'] : 0));
            if ($status === 'reserved') $summary['reserved'] += $amount;
            if ($status === 'settled') $summary['settled'] += $amount;
        }
        $summary['unallocated'] = max(0.0, $summary['confirmed'] - $summary['reserved'] - $summary['settled']);
        return $summary;
    }

    public function createPendingReceipt(array $data, $userId)
    {
        $socid = (int) ($data['fk_soc'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);
        $currency = cpcc_financial_currency($data['currency_code'] ?? '', '');
        $date = trim((string) ($data['receipt_date'] ?? ''));
        $requestKey = strtolower(trim((string) ($data['request_key'] ?? '')));
        if ($socid <= 0 || !$this->customerExists($socid)) throw new RuntimeException('Customer does not belong to the active entity.');
        if ($amount <= 0) throw new RuntimeException('Receipt amount must be greater than zero.');
        if ($currency === '') throw new RuntimeException('Receipt currency is invalid.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new RuntimeException('Receipt date is invalid.');
        $this->assertRequestKey($requestKey);
        $payloadHash = hash('sha256', json_encode(array($socid, $date, $this->decimal($amount), $currency, (string) ($data['reference'] ?? ''), (string) ($data['note_private'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $lockName = $this->lock('m077r', $requestKey);
        $started = false;
        try {
            $this->db->begin(); $started = true;
            $existing = $this->one("SELECT rowid,payload_hash FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND request_key=".$this->q($requestKey)." FOR UPDATE");
            if ($existing) {
                if (!hash_equals((string) $existing->payload_hash, $payloadHash)) throw new RuntimeException('Receipt request payload changed.');
                $this->db->commit(); $started = false; $this->unlock($lockName);
                return (int) $existing->rowid;
            }
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_customer_receipt(entity,fk_soc,receipt_date,amount,currency_code,reference,status,request_key,payload_hash,note_private,fk_user_creat,date_creation) VALUES(";
            $sql .= $this->entity.",".$socid.",".$this->q($date).",".$this->decimal($amount).",".$this->q($currency).",".$this->nullable($data['reference'] ?? '').",'pending',".$this->q($requestKey).",".$this->q($payloadHash).",".$this->nullable($data['note_private'] ?? '').",".(int) $userId.",".$this->q($this->now()).")";
            if (!$this->db->query($sql)) $this->fail('Unable to create pending receipt.');
            $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpcc_customer_receipt');
            $this->event($socid, 'receipt', $id, 'receipt_created', '', 'pending', 'Pending receipt registered; no native cash was created.', $userId);
            $this->db->commit(); $started = false; $this->unlock($lockName);
            return $id;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            $this->unlock($lockName);
            throw $e;
        }
    }

    public function confirmReceipt($receiptId, $nativePaymentId, $userId)
    {
        $this->db->begin();
        try {
            $receipt = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $receiptId." FOR UPDATE");
            if (!$receipt) throw new RuntimeException('Receipt was not found.');
            if ((string) $receipt->status === 'confirmed') {
                if ((string) $receipt->native_source_type !== 'customer_payment' || (int) $receipt->native_source_id !== (int) $nativePaymentId) throw new RuntimeException('Receipt is already confirmed with different evidence.');
                $this->db->commit(); return true;
            }
            if ((string) $receipt->status !== 'pending') throw new RuntimeException('Only a pending receipt can be confirmed.');
            $evidence = $this->validatedCustomerPayment((int) $nativePaymentId);
            if (!$evidence || (int) $evidence['fk_soc'] !== (int) $receipt->fk_soc) throw new RuntimeException('Native payment does not belong to this customer.');
            if ((string) $evidence['currency'] !== (string) $receipt->currency_code) throw new RuntimeException('Native payment currency does not match the receipt.');
            if (!$this->sameAmount((float) $evidence['amount'], (float) $receipt->amount)) throw new RuntimeException('Native payment amount does not match the receipt.');
            $duplicate = $this->one("SELECT rowid FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND native_source_type='customer_payment' AND native_source_id=".(int) $nativePaymentId." AND rowid<>".(int) $receiptId." FOR UPDATE");
            if ($duplicate) throw new RuntimeException('Native payment is already registered as a managed receipt.');
            $sql = "UPDATE ".MAIN_DB_PREFIX."cpcc_customer_receipt SET status='confirmed',native_source_type='customer_payment',native_source_id=".(int) $nativePaymentId.",fk_user_confirm=".(int) $userId.",fk_user_modif=".(int) $userId.",date_confirmation=".$this->q($this->now())." WHERE entity=".$this->entity." AND rowid=".(int) $receiptId;
            if (!$this->db->query($sql)) $this->fail('Unable to confirm receipt.');
            $this->event((int) $receipt->fk_soc, 'receipt', (int) $receiptId, 'receipt_confirmed', 'pending', 'confirmed', 'Linked to validated native customer payment #'.(int) $nativePaymentId.'; no duplicate cash movement created.', $userId);
            $this->db->commit(); return true;
        } catch (Throwable $e) {
            $this->db->rollback(); throw $e;
        }
    }

    public function reserve($receiptId, array $data, $userId)
    {
        $targetType = strtolower(trim((string) ($data['target_type'] ?? '')));
        $targetId = (int) ($data['target_id'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);
        $requestKey = strtolower(trim((string) ($data['request_key'] ?? '')));
        if (!in_array($targetType, self::targetTypes(), true) || $targetId <= 0) throw new RuntimeException('Allocation target is invalid.');
        if ($amount <= 0) throw new RuntimeException('Allocation amount must be greater than zero.');
        $this->assertRequestKey($requestKey);
        $lockName = $this->lock('m077a', $requestKey);
        $started = false;
        try {
            $this->db->begin(); $started = true;
            $receipt = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $receiptId." FOR UPDATE");
            if (!$receipt || (string) $receipt->status !== 'confirmed') throw new RuntimeException('Allocation requires a confirmed receipt.');
            $target = $this->validateTarget($targetType, $targetId, (int) $receipt->fk_soc);
            $payloadHash = hash('sha256', json_encode(array((int) $receiptId, $targetType, $targetId, $this->decimal($amount), (string) $receipt->currency_code, (string) ($data['note_private'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $existing = $this->one("SELECT rowid,payload_hash FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND request_key=".$this->q($requestKey)." FOR UPDATE");
            if ($existing) {
                if (!hash_equals((string) $existing->payload_hash, $payloadHash)) throw new RuntimeException('Allocation request payload changed.');
                $this->db->commit(); $started = false; $this->unlock($lockName);
                return (int) $existing->rowid;
            }
            $used = $this->lockedAmountTotal("SELECT amount FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND fk_receipt=".(int) $receiptId." AND status IN ('reserved','settled') FOR UPDATE");
            if ((float) $used + $amount > (float) $receipt->amount + 0.00000001) throw new RuntimeException('Allocation exceeds unallocated confirmed funds.');
            $projectId = isset($target['fk_project']) ? (int) $target['fk_project'] : 0;
            $operationId = $targetType === 'operation' ? $targetId : (isset($target['fk_operation']) ? (int) $target['fk_operation'] : 0);
            $shipmentId = $targetType === 'shipment' ? $targetId : (isset($target['fk_shipment']) ? (int) $target['fk_shipment'] : 0);
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_fund_allocation(entity,fk_receipt,fk_soc,target_type,target_id,fk_project,fk_operation,fk_shipment,amount,currency_code,status,request_key,payload_hash,note_private,fk_user_creat,date_creation) VALUES(";
            $sql .= $this->entity.",".(int) $receiptId.",".(int) $receipt->fk_soc.",".$this->q($targetType).",".$targetId.",".$this->intOrNull($projectId).",".$this->intOrNull($operationId).",".$this->intOrNull($shipmentId).",".$this->decimal($amount).",".$this->q((string) $receipt->currency_code).",'reserved',".$this->q($requestKey).",".$this->q($payloadHash).",".$this->nullable($data['note_private'] ?? '').",".(int) $userId.",".$this->q($this->now()).")";
            if (!$this->db->query($sql)) $this->fail('Unable to reserve customer funds.');
            $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpcc_fund_allocation');
            $this->event((int) $receipt->fk_soc, 'allocation', $id, 'funds_reserved', '', 'reserved', $targetType.' #'.$targetId, $userId);
            $this->db->commit(); $started = false; $this->unlock($lockName);
            return $id;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            $this->unlock($lockName); throw $e;
        }
    }

    public function settle($allocationId, $financialEntryId, $userId)
    {
        $this->db->begin();
        try {
            $allocation = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND rowid=".(int) $allocationId." FOR UPDATE");
            if (!$allocation) throw new RuntimeException('Allocation was not found.');
            if ((string) $allocation->status === 'settled') {
                if ((string) $allocation->disbursement_type !== 'financial_entry' || (int) $allocation->disbursement_id !== (int) $financialEntryId) throw new RuntimeException('Allocation is already settled with different evidence.');
                $this->db->commit(); return true;
            }
            if ((string) $allocation->status !== 'reserved') throw new RuntimeException('Only reserved funds can be settled.');
            $entry = $this->one("SELECT rowid,fk_soc,amount,currency_code,direction,status FROM ".MAIN_DB_PREFIX."cpcc_financial_entry WHERE entity=".$this->entity." AND rowid=".(int) $financialEntryId." FOR UPDATE");
            if (!$entry || (int) $entry->fk_soc !== (int) $allocation->fk_soc || (string) $entry->direction !== 'D' || (int) $entry->status !== 1) throw new RuntimeException('Settlement requires a posted customer debit.');
            if ((string) cpcc_financial_currency($entry->currency_code, '') !== (string) $allocation->currency_code) throw new RuntimeException('Settlement currency does not match the allocation.');
            $already = $this->lockedAmountTotal("SELECT amount FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND disbursement_type='financial_entry' AND disbursement_id=".(int) $financialEntryId." AND status='settled' AND rowid<>".(int) $allocationId." FOR UPDATE");
            if ((float) $already + (float) $allocation->amount > (float) $entry->amount + 0.00000001) throw new RuntimeException('Settlements exceed the posted debit.');
            $sql = "UPDATE ".MAIN_DB_PREFIX."cpcc_fund_allocation SET status='settled',disbursement_type='financial_entry',disbursement_id=".(int) $financialEntryId.",fk_user_settle=".(int) $userId.",date_settlement=".$this->q($this->now())." WHERE entity=".$this->entity." AND rowid=".(int) $allocationId;
            if (!$this->db->query($sql)) $this->fail('Unable to settle allocation.');
            $this->event((int) $allocation->fk_soc, 'allocation', (int) $allocationId, 'funds_settled', 'reserved', 'settled', 'Linked to posted debit #'.(int) $financialEntryId, $userId);
            $this->db->commit(); return true;
        } catch (Throwable $e) {
            $this->db->rollback(); throw $e;
        }
    }

    public function reverseAllocation($allocationId, $reason, $userId)
    {
        $reason = trim((string) $reason);
        if ($reason === '') throw new RuntimeException('Reversal reason is required.');
        $this->db->begin();
        try {
            $allocation = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND rowid=".(int) $allocationId." FOR UPDATE");
            if (!$allocation) throw new RuntimeException('Allocation was not found.');
            if ((string) $allocation->status === 'reversed') { $this->db->commit(); return true; }
            $from = (string) $allocation->status;
            if ($from !== 'reserved') throw new RuntimeException('Only an unsettled reservation can be reversed. Reverse the posted debit in Dolibarr before correcting a settled attribution.');
            $sql = "UPDATE ".MAIN_DB_PREFIX."cpcc_fund_allocation SET status='reversed',fk_user_reverse=".(int) $userId.",date_reversal=".$this->q($this->now()).",note_private=CONCAT(COALESCE(note_private,''),".$this->q("\nREVERSAL: ".$reason).") WHERE entity=".$this->entity." AND rowid=".(int) $allocationId;
            if (!$this->db->query($sql)) $this->fail('Unable to reverse allocation.');
            $this->event((int) $allocation->fk_soc, 'allocation', (int) $allocationId, 'allocation_reversed', $from, 'reversed', $reason, $userId);
            $this->db->commit(); return true;
        } catch (Throwable $e) {
            $this->db->rollback(); throw $e;
        }
    }

    private function validatedCustomerPayment($paymentId)
    {
        if ($paymentId <= 0) return null;
        $base = function_exists('getDolGlobalString') ? cpcc_financial_currency(getDolGlobalString('MAIN_MONNAIE'), 'EUR') : 'EUR';
        $currencyExpr = "CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN UPPER(f.multicurrency_code) ELSE ".$this->q($base)." END";
        $amountExpr = "CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END";
        $sql = "SELECT MIN(f.fk_soc) fk_soc,COUNT(DISTINCT f.fk_soc) customer_count,MIN(".$currencyExpr.") currency,COUNT(DISTINCT ".$currencyExpr.") currency_count,SUM(".$amountExpr.") amount FROM ".MAIN_DB_PREFIX."paiement p INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture AND f.entity=p.entity WHERE p.entity=".$this->entity." AND p.rowid=".$paymentId." AND ".cpcc_financial_payment_status_sql('p')." GROUP BY p.rowid";
        $row = $this->one($sql);
        if (!$row || (int) $row->customer_count !== 1 || (int) $row->currency_count !== 1 || (float) $row->amount <= 0) return null;
        return array('fk_soc'=>(int) $row->fk_soc, 'currency'=>cpcc_financial_currency($row->currency, ''), 'amount'=>(float) $row->amount);
    }

    private function validateTarget($type, $id, $socid)
    {
        $map = array(
            'operation'=>array('cpo_operation', 'rowid', 'fk_soc', 'fk_project', 'fk_shipment'),
            'shipment'=>array('chinaplanetlogistics', 'rowid', 'fk_soc', 'fk_project', null),
            'project'=>array('projet', 'rowid', 'fk_soc', 'rowid', null),
            'customer_invoice'=>array('facture', 'rowid', 'fk_soc', 'fk_projet', null),
        );
        $def = $map[$type];
        $fields = array($def[1].' rowid', $def[2].' fk_soc');
        if ($def[3]) $fields[] = $def[3].' fk_project';
        if ($def[4]) $fields[] = $def[4].' fk_shipment';
        $row = $this->one("SELECT ".implode(',', $fields)." FROM ".MAIN_DB_PREFIX.$def[0]." WHERE entity=".$this->entity." AND ".$def[1]."=".(int) $id." AND ".$def[2]."=".(int) $socid." LIMIT 1");
        if (!$row) throw new RuntimeException('Allocation target does not belong to the customer and active entity.');
        return get_object_vars($row);
    }

    private function customerExists($socid)
    {
        return (bool) $this->one("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity=".$this->entity." AND rowid=".(int) $socid." LIMIT 1");
    }

    private function event($socid, $objectType, $objectId, $action, $from, $to, $message, $userId)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_fund_event(entity,fk_soc,object_type,object_id,action_code,status_from,status_to,message,fk_user,date_action) VALUES(".$this->entity.",".(int) $socid.",".$this->q($objectType).",".(int) $objectId.",".$this->q($action).",".$this->nullable($from).",".$this->nullable($to).",".$this->nullable($message).",".(int) $userId.",".$this->q($this->now()).")";
        if (!$this->db->query($sql)) $this->fail('Unable to write fund audit event.');
    }

    private function lock($prefix, $key)
    {
        $name = $prefix.$this->entity.substr($key, 0, 40);
        $row = $this->one('SELECT GET_LOCK('.$this->q($name).',5) acquired');
        if (!$row || (int) $row->acquired !== 1) throw new RuntimeException('Unable to reserve request identity.');
        return $name;
    }

    private function unlock($name)
    {
        if ($name !== '') $this->db->query('SELECT RELEASE_LOCK('.$this->q($name).')');
    }

    private function one($sql)
    {
        $res = $this->db->query($sql);
        if (!$res) $this->fail('Database query failed.');
        return $this->db->fetch_object($res);
    }

    private function lockedAmountTotal($sql)
    {
        $res = $this->db->query($sql);
        if (!$res) $this->fail('Unable to lock allocation rows.');
        $total = 0.0;
        while ($row = $this->db->fetch_object($res)) $total += (float) $row->amount;
        return $total;
    }

    private function assertRequestKey($key)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $key)) throw new RuntimeException('Request identity must be a SHA-256 value.');
    }

    private function sameAmount($a, $b)
    {
        return abs((float) $a - (float) $b) <= 0.00000001;
    }

    private function decimal($amount)
    {
        return number_format((float) $amount, 8, '.', '');
    }

    private function intOrNull($value)
    {
        return (int) $value > 0 ? (string) (int) $value : 'NULL';
    }

    private function q($value)
    {
        return "'".$this->db->escape((string) $value)."'";
    }

    private function nullable($value)
    {
        return trim((string) $value) === '' ? 'NULL' : $this->q($value);
    }

    private function now()
    {
        return $this->db->idate(function_exists('dol_now') ? dol_now() : time());
    }

    private function fail($message)
    {
        $detail = method_exists($this->db, 'lasterror') ? (string) $this->db->lasterror() : '';
        throw new RuntimeException($detail !== '' ? $detail : $message);
    }
}
