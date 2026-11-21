<?php
declare(strict_types=1);

require_once __DIR__.'/SupplierAccountService.class.php';

/** Corrects eligible native payments at source. The audit table is not a cash ledger. */
final class SupplierPaymentCorrection
{
    public function __construct(private $db, private int $entity, private string $currency)
    {
    }

    public static function enabled(): bool
    {
        return !empty($GLOBALS['conf']->global->CPO_SUPPLIER_CORRECTIONS_ENABLED);
    }

    public function allowed($user, bool $replace = false): bool
    {
        if (!self::enabled()) return false;
        $policy = new CustomerAccountPolicy($this->db, $user, $this->entity);
        if (!$policy->allowed('post') || !SupplierAccountService::allowed($user, $policy)) return false;
        return !empty($user->admin) || (
            $user->hasRight('banque','modifier') &&
            ($user->hasRight('fournisseur','facture','supprimer') || $user->hasRight('supplier_invoice','delete')) &&
            (!$replace || $user->hasRight('fournisseur','facture','creer') || $user->hasRight('supplier_invoice','write'))
        );
    }

    public function audit(int $order): array
    {
        // The route has already projected this exact order through native finance scope.
        return $this->rows('SELECT fk_payment,fk_replacement,fk_invoice,fk_user,action,reason,date_creation,detail_json
            FROM '.MAIN_DB_PREFIX.'cpo_supplier_payment_correction WHERE entity='.$this->entity.' AND fk_order='.$order.'
            ORDER BY rowid DESC LIMIT 100');
    }

    public function apply($user, array $scope, int $id, string $action, array $input): void
    {
        $replace = $action === 'correct';
        if ((!$replace && $action !== 'cancel') || !$id || empty($scope['order']) || !$this->allowed($user, $replace)) {
            throw new RuntimeException('Supplier payment correction access denied.',403);
        }
        $reason = $this->text($input['reason']??'',500,true);
        $version = $input['version']??'';
        if (!is_string($version) || !preg_match('/^[a-f0-9]{64}$/D',$version)) {
            throw new RuntimeException('Reload the payment before correcting it.',409);
        }
        $reader = new SupplierAccountService($this->db,$this->entity,$this->currency);
        $p = MAIN_DB_PREFIX;
        $orderId = (int)$scope['order'];
        $hash = hash('sha256',json_encode([$action,$scope,$input],JSON_THROW_ON_ERROR));
        if (!empty($this->db->transaction_opened) || !$this->db->begin()) throw new RuntimeException('Cannot start payment correction.');
        try {
            $this->requireRow("SELECT rowid FROM {$p}commande_fournisseur WHERE entity=".$this->entity." AND rowid=$orderId FOR UPDATE");
            $orders = $reader->orders($user,(int)($scope['customer']??0),(int)($scope['project']??0),(int)($scope['task']??0),(int)($scope['supplier']??0),$orderId);
            if (count($orders)!==1) throw new RuntimeException('Supplier order access denied.',403);
            $order = $orders[0];
            $prior = $this->rows("SELECT request_hash,fk_user,fk_order FROM {$p}cpo_supplier_payment_correction
                WHERE entity=".$this->entity." AND fk_payment=$id FOR UPDATE");
            if ($prior) {
                if ((int)$prior[0]['fk_user']!==(int)$user->id || (int)$prior[0]['fk_order']!==$orderId || !hash_equals($prior[0]['request_hash'],$hash)) {
                    throw new RuntimeException('This payment was already corrected or cancelled.',409);
                }
                if (!$this->db->commit()) throw new RuntimeException('Unable to confirm correction.');
                return;
            }
            $displayed = array_values(array_filter($order['correction_payments'],static fn($payment)=>$payment['payment_id']===$id));
            if (count($displayed)!==1) {
                throw new RuntimeException('Payment changed or is outside this order. Reload and review.',409);
            }
            $source = $this->lockedSource($id, $order);
            $lockedVersion = SupplierAccountService::paymentVersion($source['payment']['tms'],$source['payment']['datep'],$source['payment']['num_paiement'],$source['allocation']['rowid'],
                $source['allocation']['amount'],$source['allocation']['multicurrency_amount'],
                $source['bank']['rowid'],$source['bank']['fk_account'],$source['bank']['amount'],
                $source['invoice']['multicurrency_code'],$source['allocation']['multicurrency_code']);
            if (!hash_equals($lockedVersion,$version)) {
                throw new RuntimeException('Payment changed or is outside this order. Reload and review.',409);
            }
            $replacement = $replace ? $this->replacementValues($input,$source,$order) : null;
            $this->checkAccountingDate(substr($source['payment']['datep'],0,10));
            $this->checkAccountingDate(substr($source['bank']['datev'],0,10));
            if ($replacement) $this->checkAccountingDate($replacement['date']);
            $this->nativeDelete($user,$id);
            $oldBank=(int)$source['bank']['rowid'];
            if ($this->rows("SELECT rowid FROM {$p}paiementfourn WHERE rowid=$id")
                || $this->rows("SELECT rowid FROM {$p}bank WHERE rowid=$oldBank")) {
                throw new RuntimeException('Native cancellation did not remove the payment and bank movement.');
            }
            $new = $replacement ? $this->nativeCreate($user,$replacement,$source,$order,$reason) : null;
            $before = json_encode(['before'=>$source,'replacement'=>$replacement,'native_replacement'=>$new],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $this->query("INSERT INTO {$p}cpo_supplier_payment_correction
                (entity,fk_order,fk_invoice,fk_payment,fk_replacement,fk_user,action,reason,detail_json,request_hash,date_creation)
                VALUES (".$this->entity.",$orderId,".(int)$source['invoice']['rowid'].",$id,".($new ? (int)$new['payment'] : 'NULL').",".(int)$user->id.","
                .$this->q($action).",".$this->q($reason).",".$this->q($before).",".$this->q($hash).",NOW())");
            if (!$this->db->commit()) throw new RuntimeException('Unable to confirm correction.');
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    private function lockedSource(int $id, array $order): array
    {
        $p = MAIN_DB_PREFIX;
        $payment = $this->requireRow("SELECT * FROM {$p}paiementfourn WHERE rowid=$id AND entity=".$this->entity.' FOR UPDATE');
        $allocations = $this->rows("SELECT * FROM {$p}paiementfourn_facturefourn WHERE fk_paiementfourn=$id FOR UPDATE");
        if (count($allocations)!==1) throw new RuntimeException('Payments shared across invoices require finance review.',409);
        $allocation = $allocations[0];
        $invoice = $this->lockedInvoice((int)$allocation['fk_facturefourn'], $order);
        $currency = strtoupper(trim((string)$invoice['multicurrency_code'])) ?: $this->currency;
        if ((float)$allocation['amount']<=0
            || ($currency!==$this->currency && ((float)$allocation['multicurrency_amount']<=0
                || strtoupper((string)$allocation['multicurrency_code'])!==$currency))) {
            throw new RuntimeException('The native payment currency or allocation changed. Review the invoice.',409);
        }
        $bankId = (int)$payment['fk_bank'];
        $bank = $this->requireRow("SELECT b.*,ba.currency_code FROM {$p}bank b JOIN {$p}bank_account ba ON ba.rowid=b.fk_account
            WHERE b.rowid=$bankId AND ba.entity=".$this->entity.' FOR UPDATE');
        $links = $this->rows("SELECT * FROM {$p}bank_url WHERE fk_bank=$bankId FOR UPDATE");
        $nativeLink = false;
        foreach ($links as $link) {
            $matchesPayment = $link['type']==='payment_supplier' && (int)$link['url_id']===$id;
            $matchesSupplier = $link['type']==='company' && (int)$link['url_id']===(int)$order['fk_soc'];
            if (!$matchesPayment && !$matchesSupplier) throw new RuntimeException('This bank movement has another source link.',409);
            if ($matchesPayment) $nativeLink = true;
        }
        if (!$nativeLink || !empty($bank['rappro']) || !empty($bank['fk_bordereau']) || (float)$bank['amount']>=0
            || $this->rows("SELECT rowid FROM {$p}accounting_bookkeeping WHERE doc_type='bank' AND fk_doc=$bankId LIMIT 1 FOR UPDATE")) {
            throw new RuntimeException('Reconciled, posted or incomplete bank evidence requires finance review.',409);
        }
        if ($this->rows("SELECT rowid FROM {$p}cpo_supplier_action WHERE entity=".$this->entity." AND fk_supplier_payment=$id LIMIT 1 FOR UPDATE")
            || $this->rows("SELECT rowid FROM {$p}cpcc_fund_allocation WHERE entity=".$this->entity."
                AND disbursement_type='supplier_payment' AND disbursement_id=$id LIMIT 1 FOR UPDATE")) {
            throw new RuntimeException('Payments linked to an Operations workflow or customer funding require correction at their source.',409);
        }
        if ($this->rows("SELECT rowid FROM {$p}paiementfourn WHERE fk_bank=$bankId AND rowid<>$id LIMIT 1 FOR UPDATE")
            || $this->rows("SELECT rowid FROM {$p}paiement WHERE fk_bank=$bankId LIMIT 1 FOR UPDATE")) {
            throw new RuntimeException('This bank movement is shared.',409);
        }
        return compact('payment','allocation','invoice','bank','links');
    }

    private function lockedInvoice(int $id, array $order): array
    {
        $p = MAIN_DB_PREFIX;
        $row = $this->requireRow("SELECT * FROM {$p}facture_fourn WHERE rowid=$id AND entity=".$this->entity.' FOR UPDATE');
        if ((int)$row['fk_soc']!==(int)$order['fk_soc'] || (int)$row['fk_projet']!==(int)$order['fk_projet']
            || (int)$row['fk_statut']!==1 || !empty($row['paye']) || !in_array((int)$row['type'],[0,3],true)) {
            throw new RuntimeException('Only an open supplier invoice or deposit invoice for this order can be corrected here.',409);
        }
        $links = $this->rows("SELECT CASE WHEN sourcetype='order_supplier' THEN fk_source ELSE fk_target END AS order_id
            FROM {$p}element_element WHERE (sourcetype='order_supplier' AND targettype='invoice_supplier' AND fk_target=$id)
            OR (targettype='order_supplier' AND sourcetype='invoice_supplier' AND fk_source=$id) FOR UPDATE");
        $linkedOrders=array_unique(array_map('intval',array_column($links,'order_id')));
        if (count($linkedOrders)!==1 || (int)reset($linkedOrders)!==(int)$order['rowid']) {
            throw new RuntimeException('The invoice must belong to this order alone.',409);
        }
        return $row;
    }

    private function replacementValues(array $input, array $source, array $order): array
    {
        $p = MAIN_DB_PREFIX;
        $id = filter_var($input['invoice']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if (!$id) throw new InvalidArgumentException('Choose a valid native invoice.');
        $invoice = $id===(int)$source['invoice']['rowid'] ? $source['invoice'] : $this->lockedInvoice($id,$order);
        $currency = strtoupper(trim((string)$invoice['multicurrency_code'])) ?: $this->currency;
        $fx = (string)$invoice['multicurrency_tx'];
        if ($currency!==$order['currency'] || !is_numeric($fx) || (float)$fx<=0 ||
            ($currency!==$this->currency && !isModEnabled('multicurrency'))) {
            throw new RuntimeException('Set the correct currency and FX on the native order and invoice before replacing this payment.',409);
        }
        $account = filter_var($input['account']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if (!$account) throw new InvalidArgumentException('Choose a valid bank account.');
        $bank = $this->requireRow("SELECT rowid,currency_code,clos FROM {$p}bank_account WHERE entity=".$this->entity." AND rowid=$account FOR UPDATE");
        if (!empty($bank['clos']) || $bank['currency_code']!==$currency) {
            throw new RuntimeException('Choose an open bank account in the invoice currency.',409);
        }
        $date = $input['date']??'';
        if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$date,$parts) || !checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1])) {
            throw new InvalidArgumentException('Choose a valid payment date.');
        }
        $amount = CustomerAccountAmount::money(CustomerAccountAmount::minor($input['amount']??''));
        $reference = $this->text($input['reference']??'',64,false);
        $column = $currency===$this->currency ? 'amount' : 'multicurrency_amount';
        $total = $currency===$this->currency ? $invoice['total_ttc'] : $invoice['multicurrency_total_ttc'];
        $remaining = bcadd((string)$total,'0',8);
        $existing = $this->rows("SELECT $column AS allocated FROM {$p}paiementfourn_facturefourn WHERE fk_facturefourn=$id
            AND fk_paiementfourn<>".(int)$source['payment']['rowid'].' FOR UPDATE');
        foreach ($existing as $line) $remaining=bcsub($remaining,(string)$line['allocated'],8);
        if (bccomp($amount,$remaining,8)>0) throw new RuntimeException('The corrected amount exceeds the invoice balance.',409);
        return ['invoice'=>$id,'currency'=>$currency,'rate'=>$fx,'account'=>$account,'date'=>$date,'amount'=>$amount,'reference'=>$reference];
    }

    private function checkAccountingDate(string $date): void
    {
        $day=$this->q($date);
        if ($this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'accounting_fiscalyear WHERE entity='.$this->entity
            .' AND statut=1 AND date_start<='.$day.' AND date_end>='.$day.' LIMIT 1 FOR UPDATE')) {
            throw new RuntimeException('The payment date is in a closed accounting period.',409);
        }
    }

    private function nativeDelete($user, int $id): void
    {
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
        $payment = new PaiementFourn($this->db);
        if ($payment->fetch($id)<=0 || $payment->delete($user)<=0) {
            throw new RuntimeException('Dolibarr could not cancel this payment. Review the native payment and bank evidence.',409);
        }
    }

    private function nativeCreate($user,array $values,array $source,array $order,string $reason): array
    {
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
        require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
        $supplier = new Societe($this->db);
        if ($supplier->fetch((int)$order['fk_soc'])<=0) throw new RuntimeException('Supplier unavailable.');
        $payment = new PaiementFourn($this->db);
        $invoice = $values['invoice'];
        $payment->datepaye = strtotime($values['date'].' 12:00:00');
        $payment->paiementid = (int)$source['payment']['fk_paiement'];
        $payment->num_payment = $values['reference'];
        $payment->note_private = 'Correction of supplier payment #'.(int)$source['payment']['rowid'].': '.$reason;
        $payment->fk_account = $values['account'];
        $payment->multicurrency_code = [$invoice=>$values['currency']];
        $payment->multicurrency_tx = [$invoice=>(float)$values['rate']];
        if ($values['currency']===$this->currency) $payment->amounts=[$invoice=>$values['amount']];
        else $payment->multicurrency_amounts=[$invoice=>$values['amount']];
        $id = $payment->create($user,0,$supplier);
        if ($id<=0) throw new RuntimeException('Dolibarr could not record the corrected payment.',409);
        $payment->id = $id;
        $bank = $payment->addPaymentToBank($user,'payment_supplier','(SupplierInvoicePayment)',$values['account'],'','');
        if ($bank<=0) throw new RuntimeException('Dolibarr could not link the corrected bank movement.',409);
        $p=MAIN_DB_PREFIX;
        $proof=$this->requireRow("SELECT pay.fk_bank,a.fk_facturefourn,b.amount FROM {$p}paiementfourn pay
            JOIN {$p}paiementfourn_facturefourn a ON a.fk_paiementfourn=pay.rowid
            JOIN {$p}bank b ON b.rowid=pay.fk_bank WHERE pay.entity=".$this->entity." AND pay.rowid=$id");
        if ((int)$proof['fk_bank']!==$bank || (int)$proof['fk_facturefourn']!==$invoice
            || bccomp((string)$proof['amount'],bcsub('0',$values['amount'],2),2)!==0) {
            throw new RuntimeException('Corrected payment and bank evidence did not match.');
        }
        return ['payment'=>$id,'bank'=>$bank];
    }

    private function requireRow(string $sql): array
    {
        $rows=$this->rows($sql);
        if (count($rows)!==1) throw new RuntimeException('The native record is unavailable or has changed.',409);
        return $rows[0];
    }

    private function query(string $sql)
    {
        $result = $this->db->query($sql);
        if (!$result) throw new RuntimeException('Supplier correction could not be saved.');
        return $result;
    }

    private function rows(string $sql): array
    {
        $result=$this->query($sql);
        $rows=[];
        while ($row=$this->db->fetch_object($result)) $rows[]=(array)$row;
        return $rows;
    }

    private function q(string $value): string { return "'".$this->db->escape($value)."'"; }

    private function text($value,int $max,bool $required): string
    {
        if (!is_string($value) || !mb_check_encoding($value,'UTF-8')) throw new InvalidArgumentException('Enter valid text.');
        $value=trim($value);
        if (($required && $value==='') || mb_strlen($value)>$max || preg_match('/[\x00-\x1f]/u',$value)) {
            throw new InvalidArgumentException('Enter a valid reason or reference.');
        }
        return $value;
    }
}
