<?php

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

class OperationBillingEvidenceException extends RuntimeException {}

/** Staff-first bridge from a validated operation order to native billing. */
class OperationBillingWorkflowService
{
    private $db;
    private $entity;

    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
    }

    public function state($operationId, $includePaymentControls = false)
    {
        $operation = $this->requiredOperation($operationId);
        $order = $this->requiredOrder($operation);
        $marker = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpo_billing_action WHERE entity=".$this->entity." AND fk_operation=".(int)$operationId." AND action_type='customer_invoice' ORDER BY rowid DESC LIMIT 1");
        $invoice = $marker && (int)$marker->fk_invoice > 0 ? $this->requiredInvoice((int)$marker->fk_invoice, $operation, $order) : null;
        $summary = $invoice ? $this->invoiceSummary((int)$invoice->id) : null;
        $receipts=array();
        if($invoice&&$includePaymentControls){$res=$this->db->query("SELECT rowid,status,fk_payment,fk_bank,payment_date,payment_reference,amount,currency_code,error_code FROM ".MAIN_DB_PREFIX."cpo_billing_action WHERE entity=".$this->entity." AND fk_operation=".(int)$operationId." AND action_type='customer_receipt' AND fk_invoice=".(int)$invoice->id." ORDER BY rowid DESC");while($res&&($row=$this->db->fetch_object($res)))$receipts[]=(array)$row;}
        return array('operation'=>$operation, 'order'=>$order, 'marker'=>$marker, 'invoice'=>$invoice, 'summary'=>$summary, 'receipts'=>$receipts, 'banks'=>$includePaymentControls?$this->bankAccounts($order):array(), 'payment_modes'=>$includePaymentControls?$this->paymentModes():array());
    }

    public function createInvoice($operationId, $user, $requestKey)
    {
        $this->assertInvoiceCreatePermission($user);
        $operationId = (int) $operationId;
        $token = $this->token('INV', $requestKey);
        $lock = $this->lock('CPO-BILL-OP-'.$this->entity.'-'.$operationId);
        $started = false;
        try {
            $operation = $this->requiredOperation($operationId);
            $order = $this->requiredOrder($operation);
            if ($this->orderStatus($order) !== Commande::STATUS_VALIDATED) throw new RuntimeException('Validate the customer order before creating its invoice.');
            if (empty($order->lines)) throw new RuntimeException('The validated customer order has no invoiceable lines.');
            $currency = $this->currency($order);
            $invoiceKey = hash('sha256', $this->entity.':'.$operationId.':'.(int)$order->id.':standard-full');
            $payloadHash = $this->orderPayloadHash($operation, $order, $currency);

            $this->db->begin();
            $started = true;
            $marker = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpo_billing_action WHERE entity=".$this->entity." AND (invoice_key='".$this->db->escape($invoiceKey)."' OR request_token='".$this->db->escape($token)."') FOR UPDATE");
            if ($marker) {
                if ((string)$marker->invoice_key !== $invoiceKey || !hash_equals((string)$marker->payload_hash, $payloadHash)) throw new RuntimeException('Invoice retry payload changed; creation is blocked.');
                if ((int)$marker->fk_invoice <= 0) throw new RuntimeException('Invoice action has incomplete evidence and requires correction.');
                $invoice = $this->requiredInvoice((int)$marker->fk_invoice, $operation, $order);
                $this->verifyInvoiceMatchesOrder($invoice, $order, $currency);
                $this->db->commit();
                $started = false;
                return (int)$invoice->id;
            }
            $unownedInvoice = $this->one("SELECT f.rowid FROM ".MAIN_DB_PREFIX."element_element ee JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=ee.fk_target AND f.entity=".$this->entity." WHERE ee.sourcetype='commande' AND ee.targettype='facture' AND ee.fk_source=".(int)$order->id." AND f.type=".(int)Facture::TYPE_STANDARD." LIMIT 1");
            if ($unownedInvoice) throw new RuntimeException('This order already has a native standard invoice outside the guided marker; automatic duplication is blocked.');

            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_billing_action(entity,fk_operation,action_type,request_token,invoice_key,payload_hash,status,fk_soc,fk_project,fk_order,currency_code,fk_user_creat,date_creation) VALUES(".$this->entity.",".$operationId.",'customer_invoice','".$this->db->escape($token)."','".$invoiceKey."','".$payloadHash."','preparing',".(int)$operation['fk_soc'].",".$this->nullableInt($operation['fk_project']).",".(int)$order->id.",'".$this->db->escape($currency['code'])."',".(int)$user->id.",NOW())";
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to reserve invoice creation: '.$this->db->lasterror());
            $markerId = (int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_billing_action');

            $invoice = new Facture($this->db);
            $invoice->type = Facture::TYPE_STANDARD;
            $invoice->ref_ext = $token;
            $invoice->multicurrency_code = $currency['code'];
            $invoice->multicurrency_tx = $currency['tx'];
            if ($invoice->createFromOrder($order, $user) <= 0 || empty($invoice->id)) throw new RuntimeException($invoice->error ?: 'Native invoice creation failed.');
            if ($invoice->fetch((int)$invoice->id) <= 0) throw new RuntimeException('Native invoice reload failed.');
            $this->verifyInvoiceMatchesOrder($invoice, $order, $currency);
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_billing_action SET fk_invoice=".(int)$invoice->id.",status='native_created',fk_user_modif=".(int)$user->id." WHERE entity=".$this->entity." AND rowid=".$markerId." AND status='preparing'")) throw new RuntimeException('Unable to finalize the invoice marker.');
            $this->log($operationId, 'billing_invoice_created', 'Native customer invoice draft '.$invoice->ref.' created from '.$order->ref.'.', (int)$user->id);
            if ($this->db->commit() <= 0) throw new RuntimeException('Invoice transaction commit failed.');
            $started = false;
            return (int)$invoice->id;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            throw $e;
        } finally {
            $this->unlock($lock);
        }
    }

    public function validateInvoice($operationId, $user)
    {
        $this->assertInvoiceValidatePermission($user);
        $lock = $this->lock('CPO-BILL-OP-'.$this->entity.'-'.(int)$operationId);
        $started = false;
        try {
            $operation = $this->requiredOperation($operationId);
            $order = $this->requiredOrder($operation);
            $this->db->begin();
            $started = true;
            $marker = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpo_billing_action WHERE entity=".$this->entity." AND fk_operation=".(int)$operationId." AND action_type='customer_invoice' FOR UPDATE");
            if (!$marker || (int)$marker->fk_invoice <= 0 || !in_array((string)$marker->status, array('native_created','confirmed'), true)) throw new RuntimeException('Create the guided invoice draft first.');
            $invoice = $this->requiredInvoice((int)$marker->fk_invoice, $operation, $order);
            $this->verifyInvoiceMatchesOrder($invoice, $order, $this->currency($order));
            if ((int)$invoice->status === Facture::STATUS_DRAFT) {
                if ($invoice->validate($user) <= 0) throw new RuntimeException($invoice->error ?: 'Native invoice validation failed.');
                if ($invoice->fetch((int)$invoice->id) <= 0 || (int)$invoice->status !== Facture::STATUS_VALIDATED) throw new RuntimeException('Validated invoice could not be verified.');
                $this->log((int)$operationId, 'billing_invoice_validated', 'Native customer invoice '.$invoice->ref.' validated. No message was sent.', (int)$user->id);
            } elseif (!in_array((int)$invoice->status, array(Facture::STATUS_VALIDATED, Facture::STATUS_CLOSED), true)) {
                throw new RuntimeException('Invoice is not eligible for validation.');
            }
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_billing_action SET status='confirmed',fk_user_modif=".(int)$user->id." WHERE entity=".$this->entity." AND rowid=".(int)$marker->rowid)) throw new RuntimeException('Unable to confirm invoice validation.');
            if ($this->db->commit() <= 0) throw new RuntimeException('Invoice validation commit failed.');
            $started = false;
            return (int)$invoice->id;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            throw $e;
        } finally {
            $this->unlock($lock);
        }
    }

    public function postReceipt($operationId, $amount, $accountId, $paymentCode, $date, $reference, $user, $requestKey)
    {
        $this->assertPaymentPermission($user);
        $operationId = (int)$operationId;
        $amount = (float)price2num($amount, 'MT');
        $accountId = (int)$accountId;
        $date = trim((string)$date);
        $reference = trim((string)$reference);
        $paymentCode = strtoupper(trim((string)$paymentCode));
        if ($amount <= 0 || $accountId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $reference === '') throw new RuntimeException('Positive amount, bank account, date and receipt reference are required.');
        $token = $this->token('PAY', $requestKey);
        $requestLock = $this->lock($token);
        $invoiceLock = '';
        $started = false;
        try {
            $operation = $this->requiredOperation($operationId);
            $order = $this->requiredOrder($operation);
            $invoiceMarker = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpo_billing_action WHERE entity=".$this->entity." AND fk_operation=".$operationId." AND action_type='customer_invoice'");
            if (!$invoiceMarker || (int)$invoiceMarker->fk_invoice <= 0 || (string)$invoiceMarker->status !== 'confirmed') throw new RuntimeException('Validate the guided customer invoice first.');
            $invoiceId = (int)$invoiceMarker->fk_invoice;
            $invoiceLock = $this->lock('CPO-BILL-INV-'.$this->entity.'-'.$invoiceId);
            $this->db->begin();
            $started = true;
            $summary = $this->lockedInvoiceSummary($invoiceId);
            $invoice = $this->requiredInvoice($invoiceId, $operation, $order);
            if ((int)$invoice->type !== Facture::TYPE_STANDARD || !in_array((int)$invoice->status, array(Facture::STATUS_VALIDATED, Facture::STATUS_CLOSED), true)) throw new RuntimeException('Choose a validated standard invoice.');
            if ($date < substr((string)$invoice->date, 0, 10)) throw new RuntimeException('Receipt date cannot precede the invoice date.');
            $account = $this->bankAccount($accountId, (string)$summary['currency']);
            $mode = $this->paymentMode($paymentCode);
            $payloadHash = hash('sha256', json_encode(array($operationId,$invoiceId,$accountId,(int)$mode->id,$paymentCode,$date,$reference,number_format($amount,8,'.',''),$summary['currency']), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            $marker = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpo_billing_action WHERE entity=".$this->entity." AND request_token='".$this->db->escape($token)."' FOR UPDATE");
            if ($marker) {
                if ((string)$marker->action_type !== 'customer_receipt' || (int)$marker->fk_operation !== $operationId || (int)$marker->fk_invoice !== $invoiceId || !hash_equals((string)$marker->payload_hash, $payloadHash)) throw new RuntimeException('Receipt retry payload changed.');
                $result = $this->resumeLockedReceipt($marker, $operation, $order, $user);
                if ($this->db->commit() <= 0) throw new RuntimeException('Receipt resume commit failed.');
                $started = false;
                return $result;
            }
            if ((int)$invoice->status !== Facture::STATUS_VALIDATED) throw new RuntimeException('Choose an open validated standard invoice.');
            if ($amount - (float)$summary['remaining'] > 0.00001) throw new RuntimeException('Overpayment is blocked; amount exceeds the current remaining balance.');
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_billing_action(entity,fk_operation,action_type,request_token,payload_hash,status,fk_soc,fk_project,fk_order,fk_invoice,fk_bank_account,fk_payment_mode,payment_code,payment_date,payment_reference,amount,currency_code,fk_user_creat,date_creation) VALUES(".$this->entity.",".$operationId.",'customer_receipt','".$this->db->escape($token)."','".$payloadHash."','preparing',".(int)$operation['fk_soc'].",".$this->nullableInt($operation['fk_project']).",".(int)$order->id.",".$invoiceId.",".$accountId.",".(int)$mode->id.",'".$this->db->escape($paymentCode)."','".$this->db->escape($date)."','".$this->db->escape($reference)."',".$this->number($amount).",'".$this->db->escape($summary['currency'])."',".(int)$user->id.",NOW())";
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to reserve receipt posting: '.$this->db->lasterror());
            $markerId = (int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_billing_action');
            $payment = $this->createNativePayment($invoice, $account, $mode, $date, $reference, $amount, (string)$summary['currency'], $token, $user);
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_billing_action SET fk_payment=".(int)$payment->id.",status='native_created',fk_user_modif=".(int)$user->id." WHERE entity=".$this->entity." AND rowid=".$markerId)) throw new RuntimeException('Unable to persist native payment evidence.');
            $bankId = $payment->addPaymentToBank($user, 'payment', 'Customer invoice receipt '.$invoice->ref, $accountId, '', '');
            if ($bankId <= 0) throw new RuntimeException($payment->error ?: 'Native bank posting failed.');
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_billing_action SET fk_bank=".(int)$bankId.",status='bank_posted',fk_user_modif=".(int)$user->id." WHERE entity=".$this->entity." AND rowid=".$markerId)) throw new RuntimeException('Unable to persist bank evidence.');
            $marker = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpo_billing_action WHERE entity=".$this->entity." AND rowid=".$markerId." FOR UPDATE");
            $result = $this->resumeLockedReceipt($marker, $operation, $order, $user);
            $this->log($operationId, 'billing_receipt_posted', 'Native customer receipt #'.(int)$result['payment_id'].' posted to same-currency bank evidence #'.(int)$result['bank_id'].'.', (int)$user->id);
            if ($this->db->commit() <= 0) throw new RuntimeException('Receipt transaction commit failed.');
            $started = false;
            return $result;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            if ($e instanceof OperationBillingEvidenceException) $this->markCorrectionByToken($token, (int)$user->id);
            throw $e;
        } finally {
            if ($invoiceLock !== '') $this->unlock($invoiceLock);
            $this->unlock($requestLock);
        }
    }

    public function resumeReceipt($operationId, $markerId, $user)
    {
        $this->assertPaymentPermission($user);
        $hint = $this->one("SELECT request_token,fk_invoice FROM ".MAIN_DB_PREFIX."cpo_billing_action WHERE entity=".$this->entity." AND rowid=".(int)$markerId." AND fk_operation=".(int)$operationId." AND action_type='customer_receipt'");
        if (!$hint) throw new RuntimeException('Persisted receipt action was not found.');
        $requestLock = $this->lock((string)$hint->request_token);
        $invoiceLock = $this->lock('CPO-BILL-INV-'.$this->entity.'-'.(int)$hint->fk_invoice);
        $started = false;
        try {
            $operation = $this->requiredOperation($operationId);
            $order = $this->requiredOrder($operation);
            $this->db->begin();
            $started = true;
            $marker = $this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpo_billing_action WHERE entity=".$this->entity." AND rowid=".(int)$markerId." FOR UPDATE");
            $result = $this->resumeLockedReceipt($marker, $operation, $order, $user);
            if ($this->db->commit() <= 0) throw new RuntimeException('Receipt resume commit failed.');
            $started = false;
            return $result;
        } catch (Throwable $e) {
            if ($started) $this->db->rollback();
            if ($e instanceof OperationBillingEvidenceException) $this->markCorrectionById((int)$markerId, (int)$user->id);
            throw $e;
        } finally {
            $this->unlock($invoiceLock);
            $this->unlock($requestLock);
        }
    }

    private function resumeLockedReceipt($marker, array $operation, $order, $user)
    {
        if (!$marker || (string)$marker->action_type !== 'customer_receipt' || !in_array((string)$marker->status, array('native_created','bank_posted','confirmed'), true) || (int)$marker->fk_payment <= 0) throw new OperationBillingEvidenceException('Receipt evidence is incomplete; cash recreation is blocked.');
        try {
            $invoice = $this->requiredInvoice((int)$marker->fk_invoice, $operation, $order);
            $sql = "SELECT p.rowid payment_id,p.fk_bank,p.fk_paiement,DATE(p.datep) payment_date,p.num_paiement,p.note,pf.fk_facture,pf.amount,pf.multicurrency_amount,pf.multicurrency_code,b.fk_account,(SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."paiement_facture x WHERE x.fk_paiement=p.rowid) invoice_count FROM ".MAIN_DB_PREFIX."paiement p JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid LEFT JOIN ".MAIN_DB_PREFIX."bank b ON b.rowid=p.fk_bank WHERE p.entity=".$this->entity." AND p.rowid=".(int)$marker->fk_payment." FOR UPDATE";
            $native = $this->one($sql);
            if (!$native || (int)$native->invoice_count !== 1 || (int)$native->fk_facture !== (int)$invoice->id || (int)$native->fk_paiement !== (int)$marker->fk_payment_mode || (string)$native->payment_date !== (string)$marker->payment_date || (string)$native->num_paiement !== (string)$marker->request_token || (string)$native->note !== (string)$marker->payment_reference) throw new OperationBillingEvidenceException('Persisted payment evidence changed.');
            $nativeAmount = (string)$marker->currency_code === strtoupper((string)getDolCurrency()) ? (float)$native->amount : (float)$native->multicurrency_amount;
            if (abs($nativeAmount - (float)$marker->amount) > 0.00001 || strtoupper((string)($native->multicurrency_code ?: getDolCurrency())) !== (string)$marker->currency_code) throw new OperationBillingEvidenceException('Persisted payment amount or currency changed.');
            if ((int)$native->fk_bank <= 0 && (string)$marker->status === 'native_created' && (int)$marker->fk_bank <= 0) {
                $payment = new Paiement($this->db);
                if ($payment->fetch((int)$marker->fk_payment) <= 0) throw new OperationBillingEvidenceException('Persisted native payment cannot be reloaded.');
                $payment->amounts=array((int)$invoice->id=>(float)$native->amount);
                $payment->multicurrency_amounts=array((int)$invoice->id=>(float)$native->multicurrency_amount);
                $bankId=$payment->addPaymentToBank($user,'payment','Customer invoice receipt '.$invoice->ref,(int)$marker->fk_bank_account,'','');
                if($bankId<=0)throw new OperationBillingEvidenceException($payment->error?:'Persisted payment could not be posted to its bank account.');
                if(!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_billing_action SET fk_bank=".(int)$bankId.",status='bank_posted' WHERE entity=".$this->entity." AND rowid=".(int)$marker->rowid))throw new OperationBillingEvidenceException('Recovered bank evidence could not be saved.');
                $marker->fk_bank=(int)$bankId;$marker->status='bank_posted';
                $native->fk_bank=(int)$bankId;$native->fk_account=(int)$marker->fk_bank_account;
            }
            if ((int)$native->fk_bank <= 0 || (int)$native->fk_bank !== (int)$marker->fk_bank || (int)$native->fk_account !== (int)$marker->fk_bank_account) throw new OperationBillingEvidenceException('Persisted bank evidence changed.');
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_billing_action SET status='confirmed',error_code=NULL,fk_user_modif=".(int)$user->id." WHERE entity=".$this->entity." AND rowid=".(int)$marker->rowid)) throw new RuntimeException('Unable to confirm receipt evidence.');
            return array('invoice_id'=>(int)$invoice->id, 'payment_id'=>(int)$native->payment_id, 'bank_id'=>(int)$native->fk_bank);
        } catch (OperationBillingEvidenceException $e) { throw $e; }
    }

    private function createNativePayment($invoice, $account, $mode, $date, $reference, $amount, $currency, $token, $user)
    {
        $thirdparty = new Societe($this->db);
        if ($thirdparty->fetch((int)$invoice->socid) <= 0) throw new RuntimeException('Customer could not be loaded.');
        $payment = new Paiement($this->db);
        $payment->datepaye = strtotime($date.' 12:00:00');
        $payment->paiementcode = (string)$mode->code;
        $payment->paiementid = (int)$mode->id;
        $payment->num_payment = $token;
        $payment->note_private = $reference;
        $payment->fk_account = (int)$account->rowid;
        if ($currency === strtoupper((string)getDolCurrency())) $payment->amounts = array((int)$invoice->id=>$amount);
        else {
            $payment->multicurrency_amounts = array((int)$invoice->id=>$amount);
            $payment->multicurrency_code = array((int)$invoice->id=>$currency);
        }
        $paymentId = $payment->create($user, 1, $thirdparty);
        if ($paymentId <= 0) throw new RuntimeException($payment->error ?: 'Native payment creation failed.');
        $payment->id = $paymentId;
        return $payment;
    }

    private function requiredOperation($id)
    {
        $row = $this->one("SELECT rowid,ref,service_type,fk_soc,fk_project,fk_commande FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".$this->entity." AND rowid=".(int)$id." AND status=1");
        if (!$row || (int)$row->fk_soc <= 0 || (int)$row->fk_commande <= 0) throw new RuntimeException('An active customer operation with a linked sales order is required.');
        return (array)$row;
    }

    private function requiredOrder(array $operation)
    {
        $order = new Commande($this->db);
        if ($order->fetch((int)$operation['fk_commande']) <= 0 || (int)$order->entity !== $this->entity || (int)$order->socid !== (int)$operation['fk_soc'] || (int)$order->fk_project !== (int)$operation['fk_project']) throw new RuntimeException('Sales order context mismatch.');
        return $order;
    }

    private function requiredInvoice($id, array $operation, $order)
    {
        $invoice = new Facture($this->db);
        if ($invoice->fetch((int)$id) <= 0 || (int)$invoice->entity !== $this->entity || (int)$invoice->socid !== (int)$operation['fk_soc'] || (int)$invoice->fk_project !== (int)$operation['fk_project'] || (int)$invoice->type !== Facture::TYPE_STANDARD) throw new RuntimeException('Invoice context mismatch.');
        $link = $this->one("SELECT rowid FROM ".MAIN_DB_PREFIX."element_element WHERE sourcetype='commande' AND targettype='facture' AND fk_source=".(int)$order->id." AND fk_target=".(int)$invoice->id);
        if (!$link) throw new RuntimeException('Invoice is not linked to the operation sales order.');
        return $invoice;
    }

    private function verifyInvoiceMatchesOrder($invoice, $order, array $currency)
    {
        if ((int)$invoice->socid !== (int)$order->socid || (int)$invoice->fk_project !== (int)$order->fk_project || strtoupper((string)($invoice->multicurrency_code ?: getDolCurrency())) !== $currency['code'] || abs((float)$invoice->multicurrency_tx - $currency['tx']) > 0.0000001 || count((array)$invoice->lines) !== count((array)$order->lines)) throw new RuntimeException('Created invoice header or currency differs from the sales order.');
        foreach ((array)$order->lines as $index=>$source) {
            $target = $invoice->lines[$index] ?? null;
            if (!$target || (int)$target->fk_product !== (int)$source->fk_product || abs((float)$target->qty-(float)$source->qty)>0.000001 || abs((float)$target->tva_tx-(float)$source->tva_tx)>0.000001 || abs((float)$target->multicurrency_subprice-(float)$source->multicurrency_subprice)>0.000001) throw new RuntimeException('Created invoice lines, VAT, or original-currency prices differ from the sales order.');
        }
    }

    private function currency($object)
    {
        $code = strtoupper(trim((string)$object->multicurrency_code));
        if ($code === '') $code = strtoupper((string)getDolCurrency());
        $tx = $code === strtoupper((string)getDolCurrency()) ? 1.0 : (float)$object->multicurrency_tx;
        if (!preg_match('/^[A-Z]{3}$/', $code) || $tx <= 0) throw new RuntimeException('Sales order currency or exchange rate is invalid. Automatic FX is disabled.');
        return array('code'=>$code, 'tx'=>$tx);
    }

    private function orderPayloadHash(array $operation, $order, array $currency)
    {
        $lines = array();
        foreach ((array)$order->lines as $line) $lines[] = array((int)$line->fk_product,(string)$line->qty,(string)$line->subprice,(string)$line->tva_tx,(string)$line->multicurrency_subprice,(string)$line->multicurrency_total_ttc);
        return hash('sha256', json_encode(array($this->entity,(int)$operation['rowid'],(int)$operation['fk_soc'],(int)$operation['fk_project'],(int)$order->id,$this->orderStatus($order),$currency,$lines), JSON_UNESCAPED_SLASHES));
    }

    private function invoiceSummary($invoiceId)
    {
        $row = $this->one($this->invoiceSummarySql($invoiceId, false));
        if (!$row) return null;
        $total = (float)$row->total_amount;
        $paid = (float)$row->paid_amount + (float)$row->credit_amount;
        return array('total'=>$total,'paid'=>$paid,'remaining'=>max(0,$total-$paid),'currency'=>(string)$row->currency,'status'=>(int)$row->fk_statut);
    }

    private function lockedInvoiceSummary($invoiceId)
    {
        $row = $this->one($this->invoiceSummarySql($invoiceId, true));
        if (!$row) throw new RuntimeException('Invoice balance could not be loaded.');
        $total = (float)$row->total_amount;
        $paid = (float)$row->paid_amount + (float)$row->credit_amount;
        return array('total'=>$total,'paid'=>$paid,'remaining'=>max(0,$total-$paid),'currency'=>(string)$row->currency,'status'=>(int)$row->fk_statut);
    }

    private function invoiceSummarySql($invoiceId, $lock)
    {
        $base = $this->db->escape(strtoupper((string)getDolCurrency()));
        return "SELECT f.fk_statut,UPPER(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN f.multicurrency_code ELSE '".$base."' END) currency,CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN f.multicurrency_total_ttc ELSE f.total_ttc END total_amount,COALESCE((SELECT SUM(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END) FROM ".MAIN_DB_PREFIX."paiement_facture pf WHERE pf.fk_facture=f.rowid),0) paid_amount,COALESCE((SELECT SUM(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN r.multicurrency_amount_ttc ELSE r.amount_ttc END) FROM ".MAIN_DB_PREFIX."societe_remise_except r LEFT JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.rowid=r.fk_facture_line WHERE r.entity=".$this->entity." AND COALESCE(r.fk_facture,fd.fk_facture)=f.rowid),0) credit_amount FROM ".MAIN_DB_PREFIX."facture f WHERE f.entity=".$this->entity." AND f.rowid=".(int)$invoiceId.($lock?' FOR UPDATE':'');
    }

    private function bankAccounts($order)
    {
        $currency = $this->currency($order)['code'];
        $base = $this->db->escape(strtoupper((string)getDolCurrency()));
        $rows = array();
        $res = $this->db->query("SELECT rowid,label,bank,UPPER(CASE WHEN TRIM(COALESCE(currency_code,''))<>'' THEN currency_code ELSE '".$base."' END) currency FROM ".MAIN_DB_PREFIX."bank_account WHERE entity=".$this->entity." AND clos=0 ORDER BY label");
        while ($res && ($row=$this->db->fetch_object($res))) if ((string)$row->currency === $currency) $rows[]=(array)$row;
        return $rows;
    }

    private function bankAccount($id, $currency)
    {
        $base = $this->db->escape(strtoupper((string)getDolCurrency()));
        $row = $this->one("SELECT rowid,UPPER(CASE WHEN TRIM(COALESCE(currency_code,''))<>'' THEN currency_code ELSE '".$base."' END) currency FROM ".MAIN_DB_PREFIX."bank_account WHERE entity=".$this->entity." AND rowid=".(int)$id." AND clos=0");
        if (!$row || (string)$row->currency !== $currency) throw new RuntimeException('Bank account currency must exactly match the invoice currency.');
        return $row;
    }

    private function paymentModes()
    {
        $rows=array(); $res=$this->db->query("SELECT id,code,libelle FROM ".MAIN_DB_PREFIX."c_paiement WHERE active=1 AND code<>'INTR' ORDER BY sortorder,code");
        while($res&&($row=$this->db->fetch_object($res)))$rows[]=(array)$row;
        return $rows;
    }

    private function paymentMode($code)
    {
        $row=$this->one("SELECT id,code FROM ".MAIN_DB_PREFIX."c_paiement WHERE active=1 AND code='".$this->db->escape($code)."' AND code<>'INTR'");
        if(!$row)throw new RuntimeException('Choose an active customer receipt method.');
        return $row;
    }

    private function assertStaff($user)
    {
        if (empty($user->id) || !empty($user->socid)) throw new RuntimeException('Staff access required.');
        if (empty($user->admin) && !$user->hasRight('chinaplanetoperations','operation_write')) throw new RuntimeException('Operation write permission is required.');
    }

    private function assertInvoiceCreatePermission($user)
    {
        $this->assertStaff($user);
        if (empty($user->admin) && !$user->hasRight('facture','creer')) throw new RuntimeException('Native invoice-create permission is required.');
    }

    private function assertInvoiceValidatePermission($user)
    {
        $this->assertInvoiceCreatePermission($user);
        if (function_exists('getDolGlobalString') && getDolGlobalString('MAIN_USE_ADVANCED_PERMS') && empty($user->admin) && !$user->hasRight('facture','invoice_advance','validate')) throw new RuntimeException('Native invoice-validation permission is required.');
    }

    private function assertPaymentPermission($user)
    {
        $this->assertStaff($user);
        if (!empty($user->admin)) return;
        if (!$user->hasRight('chinaplanetcustomercenter','financial_write') || !$user->hasRight('facture','paiement') || !$user->hasRight('banque','lire')) throw new RuntimeException('Financial, customer-payment and bank-read permissions are required.');
    }

    private function token($kind, $requestKey)
    {
        $key=trim((string)$requestKey);
        if(!preg_match('/^[a-f0-9]{40}$/i',$key))throw new RuntimeException('Invalid guided action token. Reload and try again.');
        return 'CPO-'.$kind.'-'.strtoupper($key);
    }

    private function orderStatus($order) { return isset($order->status)?(int)$order->status:(int)$order->statut; }
    private function nullableInt($value) { return (int)$value>0?(string)(int)$value:'NULL'; }
    private function number($value) { return number_format((float)$value,8,'.',''); }
    private function one($sql) { $res=$this->db->query($sql); if(!$res)throw new RuntimeException($this->db->lasterror()); return $this->db->fetch_object($res)?:null; }

    private function log($operationId,$action,$message,$userId)
    {
        $sql="INSERT INTO ".MAIN_DB_PREFIX."cpo_activity(entity,fk_operation,fk_task,action_code,message,visibility,fk_user,date_action) VALUES(".$this->entity.",".(int)$operationId.",NULL,'".$this->db->escape($action)."','".$this->db->escape($message)."','internal',".(int)$userId.",NOW())";
        if(!$this->db->query($sql))throw new RuntimeException($this->db->lasterror());
    }

    private function lock($name)
    {
        $res=$this->db->query("SELECT GET_LOCK('".$this->db->escape($name)."',5) acquired"); $row=$res?$this->db->fetch_object($res):null;
        if(!$row||(int)$row->acquired!==1)throw new RuntimeException('Billing workflow is busy. Try again.');
        return $name;
    }

    private function unlock($name)
    {
        if($name!=='')$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($name)."')");
    }

    private function markCorrectionByToken($token,$userId)
    {
        $this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_billing_action SET status='correction_required',error_code='EVIDENCE_MISMATCH',fk_user_modif=".(int)$userId." WHERE entity=".$this->entity." AND request_token='".$this->db->escape($token)."' AND action_type='customer_receipt'");
    }

    private function markCorrectionById($markerId,$userId)
    {
        $this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_billing_action SET status='correction_required',error_code='EVIDENCE_MISMATCH',fk_user_modif=".(int)$userId." WHERE entity=".$this->entity." AND rowid=".(int)$markerId." AND action_type='customer_receipt'");
    }
}
