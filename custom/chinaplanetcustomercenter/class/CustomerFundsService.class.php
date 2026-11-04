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
    private $connectionRetired = false;

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

    /**
     * Current, read-only position of managed native deposits. This is neither a
     * replacement ledger nor a historical/customer-wide bank balance. Project
     * results contain assignments only; the shared customer pool is not copied.
     */
    public function currentPosition($socid, $projectId = 0)
    {
        $snapshot=array(); $result=null; $failed=false; $nativeLost=false;
        try {
            $this->positionStartSnapshot($snapshot);
            $result=$this->readCurrentPosition($socid,$projectId);
            if (!$this->db->commit()) throw new RuntimeException('Snapshot completion was not confirmed.');
            if ($snapshot['kind']==='sqlite') {
                if ($snapshot['pdo']->inTransaction()) throw new RuntimeException('Snapshot remains open.');
            } else {
                $state=$this->positionNativeSessionState();
                if ((int)$state->autocommit!==1 || !in_array((string)$state->completion,array('0','NO_CHAIN'),true)
                    || (int)$this->db->transaction_opened!==0) throw new RuntimeException('Snapshot completion uncertain.');
                $snapshot['completed']=true;
            }
        } catch (Throwable $error) {
            $failed=true;
            // A failed ownership probe can mean unsupported SQL or a lost wire.
            // Only the latter retires the handle; never reconnect or query it to
            // guess. Client codes are CR_SERVER_GONE_ERROR / CR_SERVER_LOST.
            if (($this->db->type ?? '')==='mysqli' && ($this->db->db ?? null) instanceof mysqli) {
                try { $nativeLost=in_array((int)$this->db->db->errno,array(2006,2013),true); }
                catch (Throwable $closed) { $nativeLost=true; }
            }
        }
        if (!$this->positionFinishSnapshot($snapshot)) $failed=true;
        if ($nativeLost && !$this->connectionRetired) {
            try { $this->db->close(); } catch (Throwable $ignored) {}
            $this->db->db=null; $this->db->connected=false; $this->connectionRetired=true;
        }
        if (!$failed) return $result;
        $bucket=$this->positionBucket();
        foreach (array('received_cash','paid_cash','reserved','held_cash','available_to_allocate','assigned','credit_consumed','other_settled') as $key) $bucket[$key]=null;
        $bucket['coverage']='unavailable'; $bucket['reasons']=array('snapshot_unavailable');
        $currency=cpcc_financial_currency(function_exists('getDolCurrency') ? getDolCurrency() : 'USD','USD');
        return array('scope'=>(int)$projectId ? 'project_allocations_only' : 'managed_receipts_only',
            'customer_id'=>(int)$socid,'project_id'=>(int)$projectId,'coverage'=>'unavailable',
            'connection_retired'=>$this->connectionRetired,
            'reasons'=>array('snapshot_unavailable'),'currencies'=>array($currency=>$bucket));
    }

    private function positionStartSnapshot(array &$snapshot)
    {
        // An ambient transaction may have another owner or weaker isolation.
        // Never nest, commit, roll back, or reconfigure that caller's work.
        if (isset($this->db->transaction_opened) && (int)$this->db->transaction_opened!==0) throw new RuntimeException('Caller transaction.');
        $pdo=isset($this->db->pdo) ? $this->db->pdo : null;
        if ($pdo instanceof PDO) {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='sqlite' || $pdo->inTransaction()
                || (int)$pdo->query('PRAGMA read_uncommitted')->fetchColumn()!==0) throw new RuntimeException('Snapshot unavailable.');
            $snapshot=array('kind'=>'sqlite','pdo'=>$pdo,'attempted'=>false,
                'query_only'=>(int)$pdo->query('PRAGMA query_only')->fetchColumn());
            if ($pdo->exec('PRAGMA query_only=1')===false) throw new RuntimeException('Read-only snapshot unavailable.');
            $snapshot['attempted']=true;
            if (!$this->db->begin() || !$pdo->inTransaction()) throw new RuntimeException('Snapshot did not start.');
            return;
        }
        // Native support is conservative: an unsupported ownership probe or
        // engine is unavailable, never a silently verified autocommit result.
        $host=isset($this->db->database_host) && is_string($this->db->database_host)
            ? preg_replace('~^ssl://~i','',$this->db->database_host) : '';
        if (get_class($this->db)!=='DoliDBMysqli' || ($this->db->type ?? '')!=='mysqli'
            || !($this->db->db instanceof mysqli) || $host==='' || stripos($host,'p:')===0) throw new RuntimeException('Snapshot driver unavailable.');
        $state=$this->positionNativeSessionState();
        if ((int)$state->autocommit!==1 || !in_array((string)$state->completion,array('0','NO_CHAIN'),true)) throw new RuntimeException('Caller session.');
        // The exact native driver/connection is trusted for this call. No other
        // current custom/driver code queues next-transaction characteristics.
        // SET without SESSION/GLOBAL refuses an active transaction (1568), so a
        // raw caller BEGIN is preserved even when driver depth is still zero.
        $snapshot=array('kind'=>'mysqli','prepared'=>true,'started'=>false,'completed'=>false);
        $prepared=$this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        if (!$prepared) {
            $code=(int)$this->db->db->errno; $state=(string)$this->db->db->sqlstate;
            if ($code===1568 && $state==='25001') {
                $snapshot=array(); // Confirmed untouched caller, never close/rollback it.
                throw new RuntimeException('Caller transaction.');
            }
            throw new RuntimeException('Snapshot preparation uncertain.');
        }
        if (!$this->db->begin() || (int)$this->db->transaction_opened!==1) throw new RuntimeException('Snapshot startup uncertain.');
        // DoliDBMysqli at depth0 acknowledges the actual native BEGIN. A lost
        // acknowledgement never reaches this point and retires the connection.
        $snapshot['started']=true;
        $tables=array('societe','projet','cpcc_customer_receipt','cpcc_fund_allocation','cpcc_fund_event',
            'paiement','paiement_facture','facture','societe_remise_except','facturedet','bank','bank_account','bank_url',
            'paiementfourn','paiementfourn_facturefourn','facture_fourn','cpo_operation','cpo_supplier_action',
            'commande_fournisseur','element_element','chinaplanetlogistics');
        $names=array();
        foreach ($tables as $table) {
            $name=MAIN_DB_PREFIX.$table; $names[]=$name;
            // Access first to hold metadata locks through the owned transaction;
            // checking engines before access would leave a DDL conversion race.
            if (!$this->db->query('SELECT 1 FROM `'.str_replace('`','``',$name).'` WHERE 1=0')) throw new RuntimeException('Snapshot schema unavailable.');
        }
        $rows=$this->positionRows('SELECT TABLE_NAME table_name,ENGINE engine,TABLE_TYPE table_type FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_map(array($this,'q'),$names)).')');
        $seen=array();
        foreach ($rows as $row) {
            if (!in_array((string)$row->table_name,$names,true) || isset($seen[$row->table_name])
                || strtoupper((string)$row->engine)!=='INNODB' || (string)$row->table_type!=='BASE TABLE') throw new RuntimeException('Nontransactional snapshot source.');
            $seen[$row->table_name]=true;
        }
        if (count($seen)!==count($names)) throw new RuntimeException('Incomplete snapshot sources.');
    }

    private function positionNativeSessionState()
    {
        $state=$this->one('SELECT @@SESSION.autocommit autocommit,@@SESSION.completion_type completion');
        if (!$state || !in_array((string)$state->autocommit,array('0','1'),true)) throw new RuntimeException('Native session cannot be proven.');
        return $state;
    }

    private function positionFinishSnapshot(array &$snapshot)
    {
        if (!$snapshot) return true; // No effects, including rejected caller ownership.
        $clean=true;
        if ($snapshot['kind']==='sqlite') {
            $pdo=$snapshot['pdo'];
            try {
                if ($snapshot['attempted'] && $pdo->inTransaction()) {
                    $this->db->rollback();
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($pdo->inTransaction()) throw new RuntimeException('Read snapshot remains open.');
                }
                if ($snapshot['attempted'] && !$pdo->inTransaction() && isset($this->db->transaction_opened)) $this->db->transaction_opened=0;
                if ($pdo->exec('PRAGMA query_only='.(int)$snapshot['query_only'])===false
                    || (int)$pdo->query('PRAGMA query_only')->fetchColumn()!==$snapshot['query_only']) throw new RuntimeException('Read-only flag not restored.');
            } catch (Throwable $error) {
                $clean=false; $this->connectionRetired=true;
                // This adapter's future operations must not reuse an uncertain
                // owned connection. No caller transaction was entered above.
                try { if (isset($this->db->pdo) && $this->db->pdo===$pdo) $this->db->pdo=null; } catch (Throwable $ignored) {}
            }
        } else {
            try {
                if (!$snapshot['started'] || !in_array((int)$this->db->transaction_opened,array(0,1),true)) throw new RuntimeException('Uncertain prepared state.');
                if (empty($snapshot['completed'])) {
                    // BEGIN was acknowledged, so this is only our own snapshot.
                    // The native driver resets its depth even on failed rollback:
                    // require the actual acknowledgement, never trust depth alone.
                    if (!$this->db->rollback() || (int)$this->db->transaction_opened!==0) throw new RuntimeException('Snapshot rollback uncertain.');
                }
                $state=$this->positionNativeSessionState();
                if ((int)$state->autocommit!==1 || !in_array((string)$state->completion,array('0','NO_CHAIN'),true)
                    || (int)$this->db->transaction_opened!==0) throw new RuntimeException('Snapshot session changed.');
            } catch (Throwable $error) {
                $clean=false; $this->connectionRetired=true;
                try { $this->db->close(); } catch (Throwable $ignored) {}
                // Quarantine even a failed close. Never reconnect or fall back
                // through this handle after an uncertain owned preparation.
                $this->db->db=null; $this->db->connected=false;
            }
        }
        $snapshot=array();
        return $clean;
    }

    private function readCurrentPosition($socid, $projectId = 0)
    {
        $socid = (int) $socid; $projectId = (int) $projectId;
        $result = array('scope'=>$projectId ? 'project_allocations_only' : 'managed_receipts_only',
            'customer_id'=>$socid, 'project_id'=>$projectId, 'coverage'=>'not_managed', 'reasons'=>array(), 'currencies'=>array());
        try {
            if ($socid <= 0 || $projectId < 0 || !$this->customerExists($socid)
                || ($projectId && !$this->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.$this->entity.' AND fk_soc='.$socid.' AND rowid='.$projectId))) {
                $result['coverage']='unavailable'; $result['reasons'][]='invalid_scope'; return $result;
            }
            if (!cpcc_managed_funds_schema_ready($this->db)) return $result;
            $receipts = $this->positionRows('SELECT * FROM '.MAIN_DB_PREFIX.'cpcc_customer_receipt WHERE entity='.$this->entity.' AND fk_soc='.$socid.' ORDER BY rowid');
            foreach ($receipts as $receipt) {
                if ((string) $receipt->status !== 'confirmed') continue;
                $currency = cpcc_financial_currency($receipt->currency_code, '');
                if ($currency === '') { $result['reasons'][]='receipt_evidence_invalid'; continue; }
                if (!isset($result['currencies'][$currency])) $result['currencies'][$currency]=$this->positionBucket();
                $bucket =& $result['currencies'][$currency];
                try {
                    $duplicate = $this->one('SELECT COUNT(*) total FROM '.MAIN_DB_PREFIX.'cpcc_customer_receipt WHERE entity='.$this->entity." AND status='confirmed' AND native_source_type='customer_payment' AND native_source_id=".(int)$receipt->native_source_id);
                    if (!$duplicate || (int)$duplicate->total !== 1) { $bucket['reasons'][]='duplicate_customer_payment'; continue; }
                    $control = $this->reconcileReceiptObject($receipt, false);
                    if ((string)$receipt->native_source_type !== 'customer_payment' || !$control['evidence_valid'] || (float)$control['blocked'] > 0.00000001) {
                        $bucket['reasons'][]='receipt_evidence_invalid'; continue;
                    }
                    if (!$this->positionBank('paiement', 'payment', (int)$receipt->native_source_id, $currency, (float)$receipt->amount, '/compta/paiement/card.php?id=')) {
                        $bucket['reasons'][]='customer_bank_evidence_invalid'; continue;
                    }
                    $bucket['receipt_ids'][]=(int)$receipt->rowid;
                    $bucket['customer_payment_ids'][]=(int)$receipt->native_source_id;
                    if (!$projectId) {
                        $bucket['received_cash']+=(float)$receipt->amount;
                        $bucket['available_to_allocate']+=(float)$control['available'];
                        $bucket['credit_consumed']+=(float)$control['native_consumed'];
                        if ((float)$control['native_consumed'] > 0.00000001) $bucket['reasons'][]='native_credit_consumption';
                    }
                    $allocations=$this->positionRows('SELECT * FROM '.MAIN_DB_PREFIX.'cpcc_fund_allocation WHERE entity='.$this->entity.' AND fk_receipt='.(int)$receipt->rowid." AND status IN ('reserved','settled') ORDER BY rowid");
                    foreach ($allocations as $allocation) {
                        if (!$this->positionAllocationContext($allocation, $receipt)) { $bucket['reasons'][]='allocation_context_invalid'; continue; }
                        if ($projectId && (int)$allocation->fk_project !== $projectId) continue;
                        $amount=(float)$allocation->amount; $bucket['assigned']+=$amount;
                        if ((string)$allocation->status === 'reserved') { $bucket['reserved']+=$amount; continue; }
                        if ((string)$allocation->disbursement_type !== 'supplier_payment') {
                            $bucket['other_settled']+=$amount; $bucket['reasons'][]='unsupported_settlement'; continue;
                        }
                        $native=$this->positionSupplierEvidence($allocation, $currency);
                        if (!$native) { $bucket['reasons'][]='supplier_evidence_invalid'; continue; }
                        $total=0.0; $validCap=true;
                        foreach ($this->positionRows('SELECT amount,currency_code FROM '.MAIN_DB_PREFIX.'cpcc_fund_allocation WHERE entity='.$this->entity." AND status='settled' AND disbursement_type='supplier_payment' AND disbursement_id=".(int)$allocation->disbursement_id) as $share) {
                            if (!is_numeric($share->amount) || !is_finite((float)$share->amount) || (float)$share->amount<=0 || cpcc_financial_currency($share->currency_code,'')!==$currency) $validCap=false;
                            else $total+=(float)$share->amount;
                        }
                        if (!$validCap || $total > (float)$native['amount'] + 0.00000001) { $bucket['reasons'][]='supplier_attribution_exceeds_payment'; continue; }
                        // Only this customer's explicit attribution is counted,
                        // never the whole payment or an inferred company share.
                        $bucket['paid_cash']+=$amount;
                        $bucket['supplier_payment_ids'][]=(int)$allocation->disbursement_id;
                    }
                } catch (Throwable $error) { $bucket['reasons'][]='query_failed'; }
                unset($bucket);
            }
            // Other native receipts are outside the managed subset, not zero
            // and never implicitly folded into its cash or allocation capacity.
            $unmanaged=$this->one('SELECT COUNT(DISTINCT p.rowid) total FROM '.MAIN_DB_PREFIX.'paiement p INNER JOIN '.MAIN_DB_PREFIX.'paiement_facture pf ON pf.fk_paiement=p.rowid INNER JOIN '.MAIN_DB_PREFIX.'facture f ON f.rowid=pf.fk_facture AND f.entity=p.entity WHERE p.entity='.$this->entity.' AND f.fk_soc='.$socid.' AND '.cpcc_financial_payment_status_sql('p').' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX."cpcc_customer_receipt r WHERE r.entity=p.entity AND r.fk_soc=f.fk_soc AND r.status='confirmed' AND r.native_source_type='customer_payment' AND r.native_source_id=p.rowid)");
            if ($unmanaged && (int)$unmanaged->total > 0) $result['reasons'][]='unmanaged_native_payments';
        } catch (Throwable $error) {
            $result['reasons'][]='query_failed';
            if (!$result['currencies']) $result['currencies'][cpcc_financial_currency(function_exists('getDolCurrency') ? getDolCurrency() : 'USD', 'USD')]=$this->positionBucket();
            foreach ($result['currencies'] as &$failed) $failed['reasons'][]='query_failed';
            unset($failed);
        }
        foreach ($result['currencies'] as &$bucket) {
            $bucket['reasons']=array_values(array_unique($bucket['reasons']));
            $fatal=array_diff($bucket['reasons'], array('native_credit_consumption','unsupported_settlement'));
            $bucket['coverage']=$fatal ? 'unavailable' : ($bucket['reasons'] ? 'partial' : 'verified_managed');
            $bucket['held_cash']=$bucket['reasons'] ? null : $bucket['received_cash']-$bucket['paid_cash'];
            foreach (array('receipt_ids','customer_payment_ids','supplier_payment_ids') as $key) $bucket[$key]=array_values(array_unique($bucket[$key]));
            if ($fatal) foreach (array('received_cash','paid_cash','reserved','held_cash','available_to_allocate','assigned','credit_consumed','other_settled') as $key) $bucket[$key]=null;
            if ($projectId) foreach (array('received_cash','held_cash','available_to_allocate') as $key) $bucket[$key]=null;
            $result['reasons']=array_merge($result['reasons'],$bucket['reasons']);
        }
        unset($bucket);
        $result['reasons']=array_values(array_unique($result['reasons']));
        ksort($result['currencies']);
        if ($result['currencies']) $result['coverage']=$result['reasons'] ? 'partial' : 'verified_managed';
        elseif ($result['reasons'] && $result['reasons'] !== array('unmanaged_native_payments')) $result['coverage']='unavailable';
        return $result;
    }

    private function positionBucket()
    {
        return array('received_cash'=>0.0,'paid_cash'=>0.0,'reserved'=>0.0,'held_cash'=>0.0,'available_to_allocate'=>0.0,'assigned'=>0.0,
            'credit_consumed'=>0.0,'other_settled'=>0.0,'coverage'=>'verified_managed','reasons'=>array(),
            'receipt_ids'=>array(),'customer_payment_ids'=>array(),'supplier_payment_ids'=>array());
    }

    private function positionRows($sql)
    {
        $result=$this->db->query($sql);
        if (!$result) throw new RuntimeException('Current managed funds could not be verified.');
        $rows=array(); while ($row=$this->db->fetch_object($result)) $rows[]=$row;
        return $rows;
    }

    private function positionBank($table, $type, $paymentId, $currency, $amount, $url, $accountId = 0)
    {
        // Table/type/url are private fixed callers, never request inputs. A
        // closed bank account can still prove a retained historical cash row.
        $row=$this->one('SELECT b.amount,b.amount_main_currency,b.fk_account,p.amount payment_amount,p.multicurrency_amount payment_multi,ba.currency_code,(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'bank_url u WHERE u.fk_bank=b.rowid AND u.url_id=p.rowid AND u.type='.$this->q($type).') links,(SELECT MAX(u.url) FROM '.MAIN_DB_PREFIX.'bank_url u WHERE u.fk_bank=b.rowid AND u.url_id=p.rowid AND u.type='.$this->q($type).') link_url,(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX."bank_url u WHERE u.fk_bank=b.rowid AND u.type IN ('payment','payment_supplier')) cash_links,(SELECT COUNT(*) FROM ".MAIN_DB_PREFIX.'paiement x WHERE x.fk_bank=b.rowid)+(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'paiementfourn x WHERE x.fk_bank=b.rowid) cash_owners FROM '.MAIN_DB_PREFIX.$table.' p INNER JOIN '.MAIN_DB_PREFIX.'bank b ON b.rowid=p.fk_bank INNER JOIN '.MAIN_DB_PREFIX.'bank_account ba ON ba.rowid=b.fk_account AND ba.entity=p.entity WHERE p.entity='.$this->entity.' AND p.rowid='.(int)$paymentId);
        $base=cpcc_financial_currency(function_exists('getDolCurrency') ? getDolCurrency() : 'EUR', 'EUR');
        if (!$row || cpcc_financial_currency($row->currency_code,$base)!==$currency || !$this->sameAmount($row->amount,$amount)
            || (int)$row->links!==1 || (int)$row->cash_links!==1 || (int)$row->cash_owners!==1
            || ($accountId && (int)$row->fk_account!==(int)$accountId)
            || (string)$row->link_url!==(defined('DOL_URL_ROOT') ? DOL_URL_ROOT : '').$url) return false;
        $sign=$amount<0 ? -1 : 1;
        $nativeAmount=$currency===$base ? $row->payment_amount : $row->payment_multi;
        if (!$this->sameAmount($nativeAmount,abs($amount))) return false;
        return $currency===$base
            ? ($row->amount_main_currency===null || abs((float)$row->amount_main_currency)<0.00000001)
            : ($row->amount_main_currency!==null && $this->sameAmount($row->amount_main_currency,$sign*(float)$row->payment_amount));
    }

    private function positionAllocationContext($allocation, $receipt)
    {
        if ((int)$allocation->fk_soc !== (int)$receipt->fk_soc || (float)$allocation->amount <= 0
            || cpcc_financial_currency($allocation->currency_code,'') !== cpcc_financial_currency($receipt->currency_code,'')) return false;
        $type=(string)$allocation->target_type;
        if (!in_array($type,self::targetTypes(),true)) return false;
        $map=array('operation'=>array('cpo_operation','fk_project'), 'shipment'=>array('chinaplanetlogistics','fk_project'),
            'project'=>array('projet','rowid'), 'customer_invoice'=>array('facture','fk_projet'));
        $target=$this->one('SELECT '.$map[$type][1].' fk_project'.($type==='customer_invoice' ? ',multicurrency_code' : '').' FROM '.MAIN_DB_PREFIX.$map[$type][0].' WHERE entity='.$this->entity.' AND rowid='.(int)$allocation->target_id.' AND fk_soc='.(int)$receipt->fk_soc);
        if (!$target || (int)$allocation->fk_project !== (int)$target->fk_project) return false;
        if ($type==='customer_invoice' && cpcc_financial_currency($target->multicurrency_code,function_exists('getDolCurrency') ? getDolCurrency() : 'EUR') !== cpcc_financial_currency($receipt->currency_code,'')) return false;
        if ($type==='operation' && (int)$allocation->fk_operation !== (int)$allocation->target_id) return false;
        if ((int)$allocation->fk_project > 0 && !$this->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.$this->entity.' AND fk_soc='.(int)$receipt->fk_soc.' AND rowid='.(int)$allocation->fk_project)) return false;
        return true;
    }

    private function positionSupplierEvidence($allocation, $currency)
    {
        if ((string)$allocation->target_type !== 'operation' || (int)$allocation->fk_operation <= 0) return null;
        $sql='SELECT p.rowid,p.fk_bank,p.fk_paiement,DATE(p.datep) native_date,p.num_paiement,p.note,pf.amount,pf.multicurrency_amount,f.multicurrency_code,a.fk_bank marker_bank,a.fk_bank_account marker_account,a.fk_payment_mode marker_mode,a.payment_date marker_date,a.request_token,a.payment_reference,a.amount marker_amount,a.currency_code marker_currency FROM '.MAIN_DB_PREFIX.'paiementfourn p INNER JOIN '.MAIN_DB_PREFIX.'paiementfourn_facturefourn pf ON pf.fk_paiementfourn=p.rowid INNER JOIN '.MAIN_DB_PREFIX.'facture_fourn f ON f.rowid=pf.fk_facturefourn AND f.entity=p.entity INNER JOIN '.MAIN_DB_PREFIX.'cpo_operation op ON op.entity=p.entity AND op.rowid='.(int)$allocation->fk_operation.' AND op.fk_soc='.(int)$allocation->fk_soc.' AND op.fk_project='.(int)$allocation->fk_project;
        $sql.=' INNER JOIN '.MAIN_DB_PREFIX."cpo_supplier_action a ON a.entity=p.entity AND a.fk_operation=op.rowid AND a.fk_soc_customer=op.fk_soc AND a.fk_project=op.fk_project AND a.fk_supplier=f.fk_soc AND a.fk_supplier_invoice=f.rowid AND a.fk_supplier_payment=p.rowid AND a.action_type='supplier_payment' AND a.status='confirmed'";
        $sql.=' WHERE p.entity='.$this->entity.' AND p.rowid='.(int)$allocation->disbursement_id.' AND f.type=0 AND f.fk_statut>0 AND f.fk_projet=op.fk_project';
        $sql.=' AND (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'paiementfourn_facturefourn x WHERE x.fk_paiementfourn=p.rowid)=1';
        $sql.=' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'cpo_supplier_action i INNER JOIN '.MAIN_DB_PREFIX.'commande_fournisseur so ON so.rowid=i.fk_supplier_order AND so.entity=i.entity INNER JOIN '.MAIN_DB_PREFIX."element_element l ON l.sourcetype='order_supplier' AND l.targettype='invoice_supplier' AND l.fk_source=so.rowid WHERE so.fk_soc=f.fk_soc AND so.fk_projet=f.fk_projet AND l.fk_target=f.rowid AND i.entity=p.entity AND i.fk_operation=op.rowid AND i.fk_soc_customer=op.fk_soc AND i.fk_project=op.fk_project AND i.fk_supplier=f.fk_soc AND i.fk_supplier_invoice=f.rowid AND i.fk_supplier_order=a.fk_supplier_order AND i.action_type='supplier_invoice' AND i.status='confirmed')";
        $sql.=' AND (SELECT COUNT(*) FROM '.MAIN_DB_PREFIX."cpo_supplier_action x WHERE x.entity=p.entity AND x.fk_supplier_payment=p.rowid AND x.action_type='supplier_payment' AND x.status='confirmed')=1";
        $row=$this->one($sql); if (!$row) return null;
        $base=cpcc_financial_currency(function_exists('getDolCurrency') ? getDolCurrency() : 'EUR','EUR');
        $nativeCurrency=cpcc_financial_currency($row->multicurrency_code,$base);
        $amount=$nativeCurrency===$base ? (float)$row->amount : (float)$row->multicurrency_amount;
        if ($nativeCurrency!==$currency || $amount<=0 || !$this->sameAmount($row->marker_amount,$amount)
            || cpcc_financial_currency($row->marker_currency,'')!==$currency || (int)$row->marker_bank!==(int)$row->fk_bank
            || (int)$row->marker_mode!==(int)$row->fk_paiement || (string)$row->marker_date!==(string)$row->native_date
            || (string)$row->request_token!==(string)$row->num_paiement || (string)$row->payment_reference!==(string)$row->note
            || (int)$row->marker_account<=0 || !$this->positionBank('paiementfourn','payment_supplier',(int)$row->rowid,$currency,-$amount,'/fourn/paiement/card.php?id=',(int)$row->marker_account)) return null;
        return array('amount'=>$amount);
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
            if (!$this->db->begin()) throw new RuntimeException('Unable to begin the managed receipt transaction.');
            $started = true;
            $existing = $this->one("SELECT rowid,payload_hash FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND request_key=".$this->q($requestKey)." FOR UPDATE");
            if ($existing) {
                if (!hash_equals((string) $existing->payload_hash, $payloadHash)) throw new RuntimeException('Receipt request payload changed.');
                if (!$this->db->commit()) throw new RuntimeException('Managed receipt commit could not be confirmed. Retry the same request identity to reconcile.');
                $started = false; $this->unlock($lockName);
                return (int) $existing->rowid;
            }
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpcc_customer_receipt(entity,fk_soc,receipt_date,amount,currency_code,reference,status,request_key,payload_hash,note_private,fk_user_creat,date_creation) VALUES(";
            $sql .= $this->entity.",".$socid.",".$this->q($date).",".$this->decimal($amount).",".$this->q($currency).",".$this->nullable($data['reference'] ?? '').",'pending',".$this->q($requestKey).",".$this->q($payloadHash).",".$this->nullable($data['note_private'] ?? '').",".(int) $userId.",".$this->q($this->now()).")";
            if (!$this->db->query($sql)) $this->fail('Unable to create pending receipt.');
            $id = (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'cpcc_customer_receipt');
            $this->event($socid, 'receipt', $id, 'receipt_created', '', 'pending', 'Pending receipt registered; no native cash was created.', $userId);
            if (!$this->db->commit()) throw new RuntimeException('Managed receipt commit could not be confirmed. Retry the same request identity to reconcile.');
            $started = false; $this->unlock($lockName);
            return $id;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            $this->unlock($lockName);
            throw $e;
        }
    }

    public function confirmReceipt($receiptId, $nativePaymentId, $userId)
    {
        $started = false;
        try {
            if (!$this->db->begin()) throw new RuntimeException('Unable to begin the managed funds transaction.');
            $started = true;
            $receipt = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $receiptId." FOR UPDATE");
            if (!$receipt) throw new RuntimeException('Receipt was not found.');
            if ((string) $receipt->status === 'confirmed') {
                if ((string) $receipt->native_source_type !== 'customer_payment' || (int) $receipt->native_source_id !== (int) $nativePaymentId) throw new RuntimeException('Receipt is already confirmed with different evidence.');
                if (!$this->db->commit()) throw new RuntimeException('Managed funds commit could not be confirmed. Retry the same action to reconcile.'); $started = false; return true;
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
            if (!$this->db->commit()) throw new RuntimeException('Managed funds commit could not be confirmed. Retry the same action to reconcile.'); $started = false; return true;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback(); throw $e;
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
            if (!$this->db->begin()) throw new RuntimeException('Unable to begin the managed funds transaction.');
            $started = true;
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
                if (!$this->db->commit()) throw new RuntimeException('Managed funds commit could not be confirmed. Retry the same action to reconcile.'); $started = false; ManagedFundsNativeGuard::releaseLifecycle($this->db); $guardLocked=false; $this->unlock($lockName);
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
            if (!$this->db->commit()) throw new RuntimeException('Managed funds commit could not be confirmed. Retry the same action to reconcile.'); $started = false; ManagedFundsNativeGuard::releaseLifecycle($this->db); $guardLocked=false; $this->unlock($lockName);
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
            if (!$this->db->begin()) throw new RuntimeException('Unable to begin the managed funds transaction.');
            $started = true;
            $allocationHint = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND rowid=".(int) $allocationId." LIMIT 1");
            if (!$allocationHint) throw new RuntimeException('Allocation was not found.');
            $receiptHint = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $allocationHint->fk_receipt." LIMIT 1");
            if (!$receiptHint || (string) $receiptHint->status !== 'confirmed') throw new RuntimeException('Settlement requires a currently valid managed receipt.');
            $this->lockNativeEvidence((int) $receiptHint->native_source_id);
            $allocation = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND rowid=".(int) $allocationId." FOR UPDATE");
            if (!$allocation) throw new RuntimeException('Allocation was not found.');
            if ((string) $allocation->status === 'settled') {
                if ((string) $allocation->disbursement_type !== 'financial_entry' || (int) $allocation->disbursement_id !== (int) $financialEntryId) throw new RuntimeException('Allocation is already settled with different evidence.');
                if (!$this->db->commit()) throw new RuntimeException('Managed funds commit could not be confirmed. Retry the same action to reconcile.'); $started = false; ManagedFundsNativeGuard::releaseLifecycle($this->db); $guardLocked=false; return true;
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
            if (!$this->db->commit()) throw new RuntimeException('Managed funds commit could not be confirmed. Retry the same action to reconcile.'); $started = false; ManagedFundsNativeGuard::releaseLifecycle($this->db); $guardLocked=false; return true;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            if ($guardLocked) ManagedFundsNativeGuard::releaseLifecycle($this->db);
            throw $e;
        }
    }

    /**
     * Settle an explicit managed-funds reservation against an already posted
     * native supplier payment. This records attribution only; it never creates
     * cash, an invoice, a bank movement, or an FX entry.
     */
    public function settleWithSupplierPayment($allocationId, $supplierPaymentId, $userId)
    {
        $guardLocked = ManagedFundsNativeGuard::acquireLifecycle($this->db);
        if (!$guardLocked || !ManagedFundsNativeGuard::verify($this->db)) {
            if ($guardLocked) ManagedFundsNativeGuard::releaseLifecycle($this->db);
            throw new RuntimeException('Managed-funds native-credit guard is unavailable or changed; supplier-payment attribution is blocked.');
        }
        $started = false;
        try {
            if (!$this->db->begin()) throw new RuntimeException('Unable to begin the managed funds transaction.');
            $started = true;
            $allocation = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND rowid=".(int)$allocationId." FOR UPDATE");
            if (!$allocation) throw new RuntimeException('Customer funds allocation was not found.');
            $alreadySettled=(string)$allocation->status === 'settled';
            if ($alreadySettled && ((string)$allocation->disbursement_type !== 'supplier_payment' || (int)$allocation->disbursement_id !== (int)$supplierPaymentId)) throw new RuntimeException('Allocation is already settled with different evidence.');
            if ((!$alreadySettled && (string)$allocation->status !== 'reserved') || (string)$allocation->target_type !== 'operation' || (int)$allocation->fk_operation <= 0 || (int)$allocation->target_id !== (int)$allocation->fk_operation) throw new RuntimeException('Choose a reserved allocation for this exact operation.');
            $receipt = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int)$allocation->fk_receipt." FOR UPDATE");
            if(!$receipt||(string)$receipt->native_source_type!=='customer_payment')throw new RuntimeException('Customer receipt evidence is no longer valid.');
            $this->lockNativeEvidence((int)$receipt->native_source_id);
            $coverage=$this->reconcileReceiptObject($receipt,true);
            if(!$coverage['evidence_valid']||(int)$receipt->fk_soc!==(int)$allocation->fk_soc||cpcc_financial_currency($receipt->currency_code,'')!==cpcc_financial_currency($allocation->currency_code,'')||(float)$coverage['blocked']>0.00000001)throw new RuntimeException('Customer receipt evidence or allocatable coverage is no longer valid.');
            $sql="SELECT p.rowid,p.fk_bank,p.fk_paiement,DATE(p.datep) payment_date,pf.fk_facturefourn,pf.amount,pf.multicurrency_amount,UPPER(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN f.multicurrency_code ELSE ".$this->q(function_exists('getDolCurrency')?getDolCurrency():'EUR')." END) currency,f.fk_soc supplier_id,f.fk_projet project_id,b.fk_account,(SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn x WHERE x.fk_paiementfourn=p.rowid) invoice_count FROM ".MAIN_DB_PREFIX."paiementfourn p JOIN ".MAIN_DB_PREFIX."paiementfourn_facturefourn pf ON pf.fk_paiementfourn=p.rowid JOIN ".MAIN_DB_PREFIX."facture_fourn f ON f.rowid=pf.fk_facturefourn AND f.entity=p.entity LEFT JOIN ".MAIN_DB_PREFIX."bank b ON b.rowid=p.fk_bank WHERE p.entity=".$this->entity." AND p.rowid=".(int)$supplierPaymentId." FOR UPDATE";
            $native=$this->one($sql);
            if(!$native||(int)$native->invoice_count!==1||(int)$native->fk_bank<=0||(int)$native->fk_account<=0)throw new RuntimeException('Native supplier payment and bank evidence are required.');
            $nativeCurrencyKey=cpcc_financial_currency($native->currency,'');
            if((int)$native->project_id!==(int)$allocation->fk_project||$nativeCurrencyKey===''||$nativeCurrencyKey!==cpcc_financial_currency($allocation->currency_code,''))throw new RuntimeException('Supplier payment project or currency does not match the allocation.');
            $nativeAmount=(string)$native->currency===(function_exists('getDolCurrency')?strtoupper((string)getDolCurrency()):'EUR')?(float)$native->amount:(float)$native->multicurrency_amount;
            $already=$this->lockedAmountTotal("SELECT amount FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND disbursement_type='supplier_payment' AND disbursement_id=".(int)$supplierPaymentId." AND status='settled' AND rowid<>".(int)$allocationId." FOR UPDATE");
            if($already+(float)$allocation->amount>$nativeAmount+0.00000001)throw new RuntimeException('Customer-funds attributions exceed the native supplier payment.');
            if($alreadySettled){if (!$this->db->commit()) throw new RuntimeException('Managed funds commit could not be confirmed. Retry the same action to reconcile.'); $started = false;ManagedFundsNativeGuard::releaseLifecycle($this->db);$guardLocked=false;return true;}
            $update="UPDATE ".MAIN_DB_PREFIX."cpcc_fund_allocation SET status='settled',disbursement_type='supplier_payment',disbursement_id=".(int)$supplierPaymentId.",fk_user_settle=".(int)$userId.",date_settlement=".$this->q($this->now())." WHERE entity=".$this->entity." AND rowid=".(int)$allocationId;
            if(!$this->db->query($update))$this->fail('Unable to attribute customer funds to supplier payment.');
            $this->event((int)$allocation->fk_soc,'allocation',(int)$allocationId,'funds_settled','reserved','settled','Linked to verified native supplier payment #'.(int)$supplierPaymentId.'; no cash was recreated.',(int)$userId);
            if (!$this->db->commit()) throw new RuntimeException('Managed funds commit could not be confirmed. Retry the same action to reconcile.'); $started = false;ManagedFundsNativeGuard::releaseLifecycle($this->db);$guardLocked=false;return true;
        } catch(Throwable $e) {
            if($started)$this->db->rollback();
            if($guardLocked)ManagedFundsNativeGuard::releaseLifecycle($this->db);
            throw $e;
        }
    }

    public function reverseAllocation($allocationId, $reason, $userId)
    {
        $reason = trim((string) $reason);
        if ($reason === '') throw new RuntimeException('Reversal reason is required.');
        $started = false;
        try {
            if (!$this->db->begin()) throw new RuntimeException('Unable to begin the reservation reversal transaction.');
            $started = true;
            $allocation = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_fund_allocation WHERE entity=".$this->entity." AND rowid=".(int) $allocationId." FOR UPDATE");
            if (!$allocation) throw new RuntimeException('Allocation was not found.');
            if ((string) $allocation->status === 'reversed') {
                if (!$this->db->commit()) throw new RuntimeException('Reservation reversal commit could not be confirmed. Retry the same reservation to reconcile.');
                $started = false; return true;
            }
            $from = (string) $allocation->status;
            if ($from !== 'reserved') throw new RuntimeException('Only an unsettled reservation can be reversed. Reverse the posted debit in Dolibarr before correcting a settled attribution.');
            $sql = "UPDATE ".MAIN_DB_PREFIX."cpcc_fund_allocation SET status='reversed',fk_user_reverse=".(int) $userId.",date_reversal=".$this->q($this->now()).",note_private=CONCAT(COALESCE(note_private,''),".$this->q("\nREVERSAL: ".$reason).") WHERE entity=".$this->entity." AND rowid=".(int) $allocationId;
            if (!$this->db->query($sql)) $this->fail('Unable to reverse allocation.');
            $this->event((int) $allocation->fk_soc, 'allocation', (int) $allocationId, 'allocation_reversed', $from, 'reversed', $reason, $userId);
            if (!$this->db->commit()) throw new RuntimeException('Reservation reversal commit could not be confirmed. Retry the same reservation to reconcile.');
            $started = false; return true;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            throw $e;
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
        $started = false;
        try {
            if (!$this->db->begin()) throw new RuntimeException('Unable to begin the managed funds transaction.');
            $started = true;
            $receipt = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_customer_receipt WHERE entity=".$this->entity." AND rowid=".(int) $receiptId." LIMIT 1");
            if (!$receipt) throw new RuntimeException('Receipt was not found.');
            $result = $this->reconcileReceiptObject($receipt, false);
            if (!$this->db->commit()) throw new RuntimeException('Managed funds commit could not be confirmed. Retry the same action to reconcile.'); $started = false;
            return $result;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
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
