<?php

require_once __DIR__.'/../lib/financial.lib.php';
require_once __DIR__.'/ManagedFundsNativeGuard.class.php';

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
        $guardLocked = ManagedFundsNativeGuard::acquireLifecycle($this->db);
        if (!$guardLocked || !ManagedFundsNativeGuard::verify($this->db)) {
            if ($guardLocked) ManagedFundsNativeGuard::releaseLifecycle($this->db);
            $this->unlock($lockName);
            throw new RuntimeException('Managed-funds native-credit guard is unavailable or changed; allocation is blocked.');
        }
        $started = false;
        try {
            $this->db->begin(); $started = true;
            $receiptHint = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $receiptId." LIMIT 1");
            if (!$receiptHint || (string) $receiptHint->status !== 'confirmed') throw new RuntimeException('Allocation requires a confirmed receipt.');
            $this->lockNativeEvidence((int) $receiptHint->native_source_id);
            $receipt = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $receiptId." FOR UPDATE");
            if (!$receipt || (string) $receipt->status !== 'confirmed') throw new RuntimeException('Allocation requires a confirmed receipt.');
            if ((int) $receipt->native_source_id !== (int) $receiptHint->native_source_id) throw new RuntimeException('Managed receipt evidence changed during allocation.');
            $evidence = $this->validatedCustomerPayment((int) $receipt->native_source_id, true);
            if (!$evidence || (int) $evidence['fk_soc'] !== (int) $receipt->fk_soc || (string) $evidence['currency'] !== (string) $receipt->currency_code || !$this->sameAmount($evidence['amount'], $receipt->amount)) {
                throw new RuntimeException('Managed receipt evidence is no longer valid; allocation is blocked.');
            }
            $target = $this->validateTarget($targetType, $targetId, (int) $receipt->fk_soc, (string) $receipt->currency_code);
            $payloadHash = hash('sha256', json_encode(array((int) $receiptId, $targetType, $targetId, $this->decimal($amount), (string) $receipt->currency_code, (string) ($data['note_private'] ?? '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $existing = $this->one("SELECT rowid,payload_hash FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND request_key=".$this->q($requestKey)." FOR UPDATE");
            if ($existing) {
                if (!hash_equals((string) $existing->payload_hash, $payloadHash)) throw new RuntimeException('Allocation request payload changed.');
                $this->db->commit(); $started = false; ManagedFundsNativeGuard::releaseLifecycle($this->db); $guardLocked=false; $this->unlock($lockName);
                return (int) $existing->rowid;
            }
            $used = $this->lockedAmountTotal("SELECT amount FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND fk_receipt=".(int) $receiptId." AND status IN ('reserved','settled') FOR UPDATE");
            $nativeConsumed = $this->nativeConsumedAmount((int) $receipt->native_source_id, (string) $receipt->currency_code, true);
            if ((float) $used + $nativeConsumed + $amount > (float) $receipt->amount + 0.00000001) throw new RuntimeException('Allocation exceeds funds remaining after native Dolibarr credit use.');
            $projectId = isset($target['fk_project']) ? (int) $target['fk_project'] : 0;
            $operationId = $targetType === 'operation' ? $targetId : (isset($target['fk_operation']) ? (int) $target['fk_operation'] : 0);
            $shipmentId = $targetType === 'shipment' ? $targetId : (isset($target['fk_shipment']) ? (int) $target['fk_shipment'] : 0);
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_fund_allocation(entity,fk_receipt,fk_soc,target_type,target_id,fk_project,fk_operation,fk_shipment,amount,currency_code,status,request_key,payload_hash,note_private,fk_user_creat,date_creation) VALUES(";
            $sql .= $this->entity.",".(int) $receiptId.",".(int) $receipt->fk_soc.",".$this->q($targetType).",".$targetId.",".$this->intOrNull($projectId).",".$this->intOrNull($operationId).",".$this->intOrNull($shipmentId).",".$this->decimal($amount).",".$this->q((string) $receipt->currency_code).",'reserved',".$this->q($requestKey).",".$this->q($payloadHash).",".$this->nullable($data['note_private'] ?? '').",".(int) $userId.",".$this->q($this->now()).")";
            if (!$this->db->query($sql)) $this->fail('Unable to reserve customer funds.');
            $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpcc_fund_allocation');
            $this->event((int) $receipt->fk_soc, 'allocation', $id, 'funds_reserved', '', 'reserved', $targetType.' #'.$targetId, $userId);
            $this->db->commit(); $started = false; ManagedFundsNativeGuard::releaseLifecycle($this->db); $guardLocked=false; $this->unlock($lockName);
            return $id;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            if ($guardLocked) ManagedFundsNativeGuard::releaseLifecycle($this->db);
            $this->unlock($lockName); throw $e;
        }
    }

    public function settle($allocationId, $financialEntryId, $userId)
    {
        $guardLocked = ManagedFundsNativeGuard::acquireLifecycle($this->db);
        if (!$guardLocked || !ManagedFundsNativeGuard::verify($this->db)) {
            if ($guardLocked) ManagedFundsNativeGuard::releaseLifecycle($this->db);
            throw new RuntimeException('Managed-funds native-credit guard is unavailable or changed; settlement is blocked.');
        }
        $started = false;
        try {
            $this->db->begin(); $started = true;
            $allocationHint = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND rowid=".(int) $allocationId." LIMIT 1");
            if (!$allocationHint) throw new RuntimeException('Allocation was not found.');
            $receiptHint = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $allocationHint->fk_receipt." LIMIT 1");
            if (!$receiptHint || (string) $receiptHint->status !== 'confirmed') throw new RuntimeException('Settlement requires a currently valid managed receipt.');
            $this->lockNativeEvidence((int) $receiptHint->native_source_id);
            $allocation = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND rowid=".(int) $allocationId." FOR UPDATE");
            if (!$allocation) throw new RuntimeException('Allocation was not found.');
            if ((string) $allocation->status === 'settled') {
                if ((string) $allocation->disbursement_type !== 'financial_entry' || (int) $allocation->disbursement_id !== (int) $financialEntryId) throw new RuntimeException('Allocation is already settled with different evidence.');
                $this->db->commit(); $started = false; ManagedFundsNativeGuard::releaseLifecycle($this->db); $guardLocked=false; return true;
            }
            if ((string) $allocation->status !== 'reserved') throw new RuntimeException('Only reserved funds can be settled.');
            if ((int) $allocation->fk_receipt !== (int) $receiptHint->rowid) throw new RuntimeException('Allocation receipt changed during settlement.');
            $receipt = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $allocation->fk_receipt." FOR UPDATE");
            if (!$receipt || (string) $receipt->status !== 'confirmed') throw new RuntimeException('Settlement requires a currently valid managed receipt.');
            if ((int) $receipt->native_source_id !== (int) $receiptHint->native_source_id) throw new RuntimeException('Managed receipt evidence changed during settlement.');
            $reconciliation = $this->reconcileReceiptObject($receipt, true);
            $settledFunds = $this->lockedAmountTotal("SELECT amount FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND fk_receipt=".(int) $receipt->rowid." AND status='settled' AND rowid<>".(int) $allocation->rowid." FOR UPDATE");
            if (!$reconciliation['evidence_valid'] || $settledFunds + (float) $allocation->amount > (float) $reconciliation['allocatable'] + 0.00000001) throw new RuntimeException('Allocation is blocked because settled spending plus this settlement exceeds current deposit coverage.');
            $entry = $this->one("SELECT rowid,fk_soc,fk_project,entry_type,source_type,source_id,amount,currency_code,direction,status FROM ".MAIN_DB_PREFIX."cpcc_financial_entry WHERE entity=".$this->entity." AND rowid=".(int) $financialEntryId." FOR UPDATE");
            if (!$entry || (int) $entry->fk_soc !== (int) $allocation->fk_soc || (string) $entry->direction !== 'D' || (int) $entry->status !== 1) throw new RuntimeException('Settlement requires a posted customer debit.');
            if ((string) cpcc_financial_currency($entry->currency_code, '') !== (string) $allocation->currency_code) throw new RuntimeException('Settlement currency does not match the allocation.');
            if ((string) $entry->source_type !== (string) $allocation->target_type || (int) $entry->source_id !== (int) $allocation->target_id) throw new RuntimeException('Posted debit is not bound to this exact allocation target.');
            if ((int) $allocation->fk_project > 0 && (int) $entry->fk_project !== (int) $allocation->fk_project) throw new RuntimeException('Posted debit project does not match the allocation context.');
            if (in_array((string) $entry->entry_type, array('opening_balance','adjustment','fx_in','fx_out','transfer'), true)) throw new RuntimeException('This financial entry type cannot prove an actual target debit.');
            $already = $this->lockedAmountTotal("SELECT amount FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND disbursement_type='financial_entry' AND disbursement_id=".(int) $financialEntryId." AND status='settled' AND rowid<>".(int) $allocationId." FOR UPDATE");
            if ((float) $already + (float) $allocation->amount > (float) $entry->amount + 0.00000001) throw new RuntimeException('Settlements exceed the posted debit.');
            $sql = "UPDATE ".MAIN_DB_PREFIX."cpcc_fund_allocation SET status='settled',disbursement_type='financial_entry',disbursement_id=".(int) $financialEntryId.",fk_user_settle=".(int) $userId.",date_settlement=".$this->q($this->now())." WHERE entity=".$this->entity." AND rowid=".(int) $allocationId;
            if (!$this->db->query($sql)) $this->fail('Unable to settle allocation.');
            $this->event((int) $allocation->fk_soc, 'allocation', (int) $allocationId, 'funds_settled', 'reserved', 'settled', 'Linked to posted debit #'.(int) $financialEntryId, $userId);
            $this->db->commit(); $started = false; ManagedFundsNativeGuard::releaseLifecycle($this->db); $guardLocked=false; return true;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            if ($guardLocked) ManagedFundsNativeGuard::releaseLifecycle($this->db);
            throw $e;
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

    private function validatedCustomerPayment($paymentId, $locked = false)
    {
        if ($paymentId <= 0) return null;
        $base = function_exists('getDolGlobalString') ? cpcc_financial_currency(getDolGlobalString('MAIN_MONNAIE'), 'EUR') : 'EUR';
        $currencyExpr = "CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN UPPER(f.multicurrency_code) ELSE ".$this->q($base)." END";
        $amountExpr = "CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END";
        $sql = "SELECT MIN(f.fk_soc) fk_soc,COUNT(DISTINCT f.fk_soc) customer_count,MIN(".$currencyExpr.") currency,COUNT(DISTINCT ".$currencyExpr.") currency_count,SUM(".$amountExpr.") amount FROM ".MAIN_DB_PREFIX."paiement p INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture AND f.entity=p.entity WHERE p.entity=".$this->entity." AND p.rowid=".$paymentId." AND f.type=3 AND ".cpcc_financial_payment_status_sql('p')." GROUP BY p.rowid".($locked ? " FOR UPDATE" : "");
        $row = $this->one($sql);
        if (!$row || (int) $row->customer_count !== 1 || (int) $row->currency_count !== 1 || (float) $row->amount <= 0) return null;
        return array('fk_soc'=>(int) $row->fk_soc, 'currency'=>cpcc_financial_currency($row->currency, ''), 'amount'=>(float) $row->amount);
    }

    public function reconcileReceipt($receiptId)
    {
        $this->db->begin();
        try {
            $receipt = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $receiptId." LIMIT 1");
            if (!$receipt) throw new RuntimeException('Receipt was not found.');
            $result = $this->reconcileReceiptObject($receipt, false);
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function reconcileReceiptObject($receipt, $locked)
    {
        $valid = (string) $receipt->status === 'confirmed';
        $evidence = $valid ? $this->validatedCustomerPayment((int) $receipt->native_source_id, $locked) : null;
        $valid = $valid && $evidence && (int) $evidence['fk_soc'] === (int) $receipt->fk_soc
            && (string) $evidence['currency'] === (string) $receipt->currency_code
            && $this->sameAmount($evidence['amount'], $receipt->amount);
        $nativeConsumed = $valid ? $this->nativeConsumedAmount((int) $receipt->native_source_id, (string) $receipt->currency_code, $locked) : 0.0;
        $suffix = $locked ? ' FOR UPDATE' : '';
        $managedActive = $this->lockedAmountTotal("SELECT amount FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND fk_receipt=".(int) $receipt->rowid." AND status IN ('reserved','settled')".$suffix);
        $allocatable = $valid ? max(0.0, (float) $receipt->amount - $nativeConsumed) : 0.0;
        return array(
            'evidence_valid'=>(bool) $valid,
            'native_consumed'=>$nativeConsumed,
            'managed_active'=>$managedActive,
            'allocatable'=>$allocatable,
            'available'=>max(0.0, $allocatable - $managedActive),
            'blocked'=>max(0.0, $managedActive - $allocatable),
        );
    }

    private function nativeConsumedAmount($paymentId, $currency, $locked = false)
    {
        $base = function_exists('getDolGlobalString') ? cpcc_financial_currency(getDolGlobalString('MAIN_MONNAIE'), 'EUR') : 'EUR';
        $amountExpr = (string) $currency === $base ? 'r.amount_ttc' : 'r.multicurrency_amount_ttc';
        $sql = "SELECT r.rowid,".$amountExpr." amount FROM ".MAIN_DB_PREFIX."societe_remise_except r LEFT JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.rowid=r.fk_facture_line WHERE r.entity=".$this->entity." AND r.fk_facture_source IN (SELECT pf.fk_facture FROM ".MAIN_DB_PREFIX."paiement_facture pf INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture AND f.entity=".$this->entity." AND f.type=3 WHERE pf.fk_paiement=".(int) $paymentId.") AND COALESCE(r.fk_facture,fd.fk_facture) IS NOT NULL".($locked ? " FOR UPDATE" : "");
        $res = $this->db->query($sql);
        if (!$res) $this->fail('Unable to reconcile native credit consumption.');
        $amount = 0.0;
        while ($row = $this->db->fetch_object($res)) $amount += (float) $row->amount;
        if ($amount < -0.00000001) throw new RuntimeException('Native credit consumption is invalid.');
        return max(0.0, $amount);
    }

    private function lockNativeEvidence($paymentId)
    {
        $payment = $this->one("SELECT rowid FROM ".MAIN_DB_PREFIX."paiement WHERE entity=".$this->entity." AND rowid=".(int) $paymentId." FOR UPDATE");
        if (!$payment) throw new RuntimeException('Managed receipt evidence is no longer valid; allocation is blocked.');
        $res = $this->db->query("SELECT f.rowid FROM ".MAIN_DB_PREFIX."facture f INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_facture=f.rowid WHERE f.entity=".$this->entity." AND f.type=3 AND pf.fk_paiement=".(int) $paymentId." FOR UPDATE");
        if (!$res) $this->fail('Unable to lock native deposit evidence.');
        while ($this->db->fetch_object($res)) {}
        $res = $this->db->query("SELECT r.rowid FROM ".MAIN_DB_PREFIX."societe_remise_except r WHERE r.entity=".$this->entity." AND r.fk_facture_source IN (SELECT pf.fk_facture FROM ".MAIN_DB_PREFIX."paiement_facture pf INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture AND f.entity=".$this->entity." AND f.type=3 WHERE pf.fk_paiement=".(int) $paymentId.") FOR UPDATE");
        if (!$res) $this->fail('Unable to lock native credit rows.');
        while ($this->db->fetch_object($res)) {}
    }

    private function validateTarget($type, $id, $socid, $receiptCurrency)
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
        if ($type === 'customer_invoice') $fields[] = "CASE WHEN TRIM(COALESCE(multicurrency_code,''))<>'' THEN UPPER(multicurrency_code) ELSE ".$this->q(function_exists('getDolGlobalString') ? cpcc_financial_currency(getDolGlobalString('MAIN_MONNAIE'), 'EUR') : 'EUR')." END target_currency";
        $row = $this->one("SELECT ".implode(',', $fields)." FROM ".MAIN_DB_PREFIX.$def[0]." WHERE entity=".$this->entity." AND ".$def[1]."=".(int) $id." AND ".$def[2]."=".(int) $socid." LIMIT 1");
        if (!$row) throw new RuntimeException('Allocation target does not belong to the customer and active entity.');
        if ($type === 'customer_invoice' && (string) $row->target_currency !== (string) $receiptCurrency) throw new RuntimeException('Customer invoice currency does not match the managed receipt.');
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
