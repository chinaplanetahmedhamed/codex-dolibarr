<?php

require_once __DIR__.'/CustomerFundsService.class.php';
require_once __DIR__.'/ManagedFundsNativeGuard.class.php';

/** Guided native Dolibarr deposit and bank-receipt workflow. */
class NativeAdvanceWorkflowService
{
    private $db;
    private $entity;

    public function __construct($db, $entity)
    {
        $this->db = $db;
        $this->entity = (int) $entity;
    }

    public function createDepositFromOrigin($socid, $originType, $originId, $user, $requestKey)
    {
        if (!$user->hasRight('facture', 'creer')) throw new RuntimeException('Native invoice-create permission is required.');
        if (function_exists('getDolGlobalString') && getDolGlobalString('MAIN_USE_ADVANCED_PERMS') && !$user->hasRight('facture', 'invoice_advance', 'validate')) throw new RuntimeException('Native invoice-validation permission is required.');
        if ($socid <= 0 || !in_array($originType, array('commande', 'propal'), true) || $originId <= 0) throw new RuntimeException('Choose an eligible customer order or proposal.');
        $token = $this->token('invoice', $requestKey);
        $guardLocked = ManagedFundsNativeGuard::acquireLifecycle($this->db);
        if (!$guardLocked || !ManagedFundsNativeGuard::verify($this->db)) {
            if ($guardLocked) ManagedFundsNativeGuard::releaseLifecycle($this->db);
            throw new RuntimeException('Guided native-payment database guard is unavailable or changed; deposit creation is blocked.');
        }
        $requestLock = '';
        $started = false;
        try {
            $requestLock = $this->lock($token);
            if ($originType === 'commande') {
                require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
                $origin = new Commande($this->db);
            } else {
                require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
                $origin = new Propal($this->db);
            }
            if ($origin->fetch($originId) <= 0) throw new RuntimeException('Selected native source was not found.');
            if ((int) $origin->entity !== $this->entity || (int) $origin->socid !== $socid || (int) $origin->status <= 0) throw new RuntimeException('Source does not belong to the selected customer and active entity.');
            $customer = $this->one("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity=".$this->entity." AND rowid=".$socid." AND status=1 AND client IN (1,2,3)");
            if (!$customer) throw new RuntimeException('Selected customer is not active.');
            $term = $this->one("SELECT rowid,deposit_percent FROM ".MAIN_DB_PREFIX."c_payment_term WHERE rowid=".(int) $origin->cond_reglement_id." AND active=1");
            if (!$term || (float) $term->deposit_percent <= 0) throw new RuntimeException('The selected source has no configured deposit percentage.');
            $payloadHash = hash('sha256', json_encode(array($socid,$originType,$originId,(int)$origin->fk_project,(int)$term->rowid,(string)$term->deposit_percent),JSON_UNESCAPED_SLASHES));
            $existing = $this->one("SELECT f.rowid,f.fk_soc,f.fk_projet,f.type,ee.fk_source origin_id,ee.sourcetype,g.request_token,g.payload_hash FROM ".MAIN_DB_PREFIX."facture f LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON ee.fk_target=f.rowid AND ee.targettype='facture' LEFT JOIN ".MAIN_DB_PREFIX."cpcc_guided_receipt g ON g.entity=f.entity AND g.fk_invoice=f.rowid WHERE f.entity=".$this->entity." AND f.ref_ext='".$this->db->escape($token)."' LIMIT 1");
            if ($existing) {
                if ((int) $existing->type !== 3 || (int) $existing->fk_soc !== $socid || (int) $existing->origin_id !== $originId || (string) $existing->sourcetype !== $originType || (int) $existing->fk_projet !== (int) $origin->fk_project || (string)$existing->request_token!==$token || !hash_equals((string)$existing->payload_hash,$payloadHash)) throw new RuntimeException('Deposit retry payload changed or its guard marker is missing.');
                return (int) $existing->rowid;
            }
            $origin->deposit_percent = (float) $term->deposit_percent;
            require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
            $this->db->begin();
            $started = true;
            $deposit = Facture::createDepositFromOrigin($origin, dol_now(), (int) $term->rowid, $user, 0, true, array('ref_ext'=>$token, 'note_private'=>'Guided managed-funds deposit from '.$originType.' #'.$originId));
            if (!$deposit || empty($deposit->id)) throw new RuntimeException($origin->error ?: 'Unable to create the native deposit invoice.');
            $sql="INSERT INTO ".MAIN_DB_PREFIX."cpcc_guided_receipt(entity,fk_soc,fk_invoice,origin_type,origin_id,request_token,payload_hash,status,fk_user_creat,date_creation) VALUES(".$this->entity.",".$socid.",".(int)$deposit->id.",'".$this->db->escape($originType)."',".$originId.",'".$this->db->escape($token)."','".$payloadHash."','invoice_ready',".(int)$user->id.",NOW())";
            if(!$this->db->query($sql)) throw new RuntimeException('Unable to create the guided native-payment guard marker: '.$this->db->lasterror());
            $this->db->commit();
            $started = false;
            return (int) $deposit->id;
        } catch (Throwable $e) {
            if($started)$this->db->rollback();
            throw $e;
        } finally {
            if ($requestLock !== '') $this->unlock($requestLock);
            ManagedFundsNativeGuard::releaseLifecycle($this->db);
        }
    }

    public function postBankReceipt($socid, $invoiceId, $accountId, $paymentCode, $date, $reference, $user, $requestKey)
    {
        global $conf;
        if (!$user->hasRight('facture', 'paiement')) throw new RuntimeException('Native customer-payment permission is required.');
        $reference = trim((string) $reference);
        if ($socid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $reference === '') throw new RuntimeException('Customer, receipt date and bank reference are required.');
        $token = $this->token('payment', $requestKey);
        $guardLocked=ManagedFundsNativeGuard::acquireLifecycle($this->db);
        if(!$guardLocked||!ManagedFundsNativeGuard::verify($this->db)){
            if($guardLocked)ManagedFundsNativeGuard::releaseLifecycle($this->db);
            throw new RuntimeException('Guided native-payment database guard is unavailable or changed; receipt posting is blocked.');
        }
        $requestLock = '';
        $invoiceLock = '';
        $started = false;
        try {
            $requestLock = $this->lock($token);
            $global = $this->one("SELECT p.rowid payment_id,p.fk_bank,b.fk_account,p.fk_paiement,DATE(p.datep) datep,p.note note_private,pf.fk_facture,CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END amount,f.fk_soc FROM ".MAIN_DB_PREFIX."paiement p INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture LEFT JOIN ".MAIN_DB_PREFIX."bank b ON b.rowid=p.fk_bank WHERE p.entity=".$this->entity." AND p.num_paiement='".$this->db->escape($token)."' LIMIT 1");
            if ($global && ((int) $global->fk_facture !== $invoiceId || (int) $global->fk_soc !== $socid)) throw new RuntimeException('Payment retry payload changed.');
            $invoiceLock = $this->lock('CPMF-INV-'.$this->entity.'-'.$invoiceId);
            $this->db->begin();
            $started = true;
            $invoice = $this->one("SELECT f.rowid,f.fk_soc,f.fk_statut,f.type,f.datef,f.total_ttc,f.multicurrency_total_ttc,UPPER(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN f.multicurrency_code ELSE '".$this->db->escape($conf->currency)."' END) currency,COALESCE(SUM(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END),0) paid FROM ".MAIN_DB_PREFIX."facture f LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_facture=f.rowid WHERE f.entity=".$this->entity." AND f.rowid=".$invoiceId." GROUP BY f.rowid FOR UPDATE");
            if (!$invoice || (int) $invoice->fk_soc !== $socid || (int) $invoice->type !== 3 || !in_array((int) $invoice->fk_statut, array(1, 2), true)) throw new RuntimeException('Choose a validated guided deposit invoice for the selected customer.');
            if (!$global && (int) $invoice->fk_statut !== 1) throw new RuntimeException('Choose an unpaid guided deposit invoice for the selected customer.');
            if ($date < substr((string) $invoice->datef, 0, 10)) throw new RuntimeException('Receipt date cannot precede the deposit invoice.');
            $account = $this->one("SELECT rowid,UPPER(CASE WHEN TRIM(COALESCE(currency_code,''))<>'' THEN currency_code ELSE '".$this->db->escape($conf->currency)."' END) currency FROM ".MAIN_DB_PREFIX."bank_account WHERE entity=".$this->entity." AND rowid=".$accountId." AND clos=0");
            if (!$account || (string) $account->currency !== (string) $invoice->currency) throw new RuntimeException('Bank account currency must exactly match the deposit invoice.');
            $mode = $this->one("SELECT id,code FROM ".MAIN_DB_PREFIX."c_paiement WHERE active=1 AND code='".$this->db->escape($paymentCode)."' AND code<>'INTR'");
            if (!$mode) throw new RuntimeException('Choose an active customer receipt method.');
            $amount = (float) (((string) $invoice->currency === (string) $conf->currency) ? $invoice->total_ttc : $invoice->multicurrency_total_ttc);
            $credit = $this->one("SELECT COALESCE(SUM(CASE WHEN '".$this->db->escape($invoice->currency)."'='".$this->db->escape($conf->currency)."' THEN r.amount_ttc ELSE r.multicurrency_amount_ttc END),0) used FROM ".MAIN_DB_PREFIX."societe_remise_except r LEFT JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.rowid=r.fk_facture_line WHERE r.entity=".$this->entity." AND COALESCE(r.fk_facture,fd.fk_facture)=".$invoiceId." FOR UPDATE");
            $paymentPayloadHash=hash('sha256',json_encode(array($socid,$invoiceId,$accountId,(string)$mode->code,$date,$reference,number_format($amount,8,'.',''),(string)$invoice->currency),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $marker=$this->one("SELECT * FROM ".MAIN_DB_PREFIX."cpcc_guided_receipt WHERE entity=".$this->entity." AND (fk_invoice=".$invoiceId." OR payment_token='".$this->db->escape($token)."') FOR UPDATE");
            if(!$marker||(int)$marker->fk_invoice!==$invoiceId||(int)$marker->fk_soc!==$socid)throw new RuntimeException('Guided invoice guard marker is missing or belongs to another customer.');
            if($marker->payment_token!==null&&((string)$marker->payment_token!==$token||!hash_equals((string)$marker->payment_payload_hash,$paymentPayloadHash)))throw new RuntimeException('Payment retry payload changed.');
            if($marker->payment_token===null){$sql="UPDATE ".MAIN_DB_PREFIX."cpcc_guided_receipt SET payment_token='".$this->db->escape($token)."',payment_payload_hash='".$paymentPayloadHash."',status='payment_preparing' WHERE entity=".$this->entity." AND rowid=".(int)$marker->rowid;if(!$this->db->query($sql))throw new RuntimeException('Unable to reserve the guided payment: '.$this->db->lasterror());}
            if(!$this->db->query("SET @cpcc_guided_receipt_token='".$this->db->escape((string)$marker->request_token)."'"))throw new RuntimeException('Unable to authorize the guarded native payment.');
            if ($global) {
                if ((int) $global->fk_bank <= 0 || (int) $global->fk_account !== $accountId || (int) $global->fk_paiement !== (int) $mode->id || (string) $global->datep !== $date || (string) $global->note_private !== $reference || abs((float) $global->amount - $amount) > 0.00001 || ((int)$marker->fk_payment>0&&(int)$marker->fk_payment!==(int)$global->payment_id)) throw new RuntimeException('Payment retry payload changed.');
                $paymentId = (int) $global->payment_id;
            } else {
                if ((float) $invoice->paid > 0.00001 || (float) $credit->used > 0.00001) throw new RuntimeException('Deposit invoice already has a payment or applied credit.');
                require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
                require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
                $thirdparty = new Societe($this->db);
                if ($thirdparty->fetch($socid) <= 0) throw new RuntimeException('Customer could not be loaded.');
                $payment = new Paiement($this->db);
                $payment->datepaye = strtotime($date.' 12:00:00');
                $payment->paiementcode = (string) $mode->code;
                $payment->paiementid = (int) $mode->id;
                $payment->num_payment = $token;
                $payment->note_private = $reference;
                $payment->fk_account = $accountId;
                if ((string) $invoice->currency === (string) $conf->currency) $payment->amounts = array($invoiceId=>$amount);
                else {
                    $payment->multicurrency_amounts = array($invoiceId=>$amount);
                    $payment->multicurrency_code = array($invoiceId=>(string) $invoice->currency);
                }
                $paymentId = $payment->create($user, 1, $thirdparty);
                if ($paymentId <= 0) throw new RuntimeException($payment->error ?: 'Native payment creation failed.');
                if ($payment->addPaymentToBank($user, 'payment', 'Managed customer deposit', $accountId, '', '') <= 0) throw new RuntimeException($payment->error ?: 'Native bank posting failed.');
            }
            $this->db->query("SET @cpcc_guided_receipt_token=NULL");
            if(!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_guided_receipt SET fk_payment=".$paymentId.",status='bank_posted' WHERE entity=".$this->entity." AND rowid=".(int)$marker->rowid))throw new RuntimeException('Unable to finalize the guided bank marker: '.$this->db->lasterror());
            $this->db->commit();
            $started = false;
            $funds = new CustomerFundsService($this->db, $this->entity);
            $receiptKey = hash('sha256', 'native-bank-receipt:'.$token);
            $receiptId = $funds->createPendingReceipt(array('fk_soc'=>$socid, 'receipt_date'=>$date, 'amount'=>$amount, 'currency_code'=>(string) $invoice->currency, 'reference'=>$reference, 'note_private'=>'Bank-posted native deposit payment #'.$paymentId, 'request_key'=>$receiptKey), (int) $user->id);
            $funds->confirmReceipt($receiptId, $paymentId, (int) $user->id);
            if(!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_guided_receipt SET status='confirmed' WHERE entity=".$this->entity." AND rowid=".(int)$marker->rowid." AND fk_payment=".$paymentId))throw new RuntimeException('Managed receipt confirmed but workflow marker update failed; retry safely.');
            return array('invoice_id'=>$invoiceId, 'payment_id'=>$paymentId, 'receipt_id'=>$receiptId);
        } catch (Throwable $e) {
            $this->db->query("SET @cpcc_guided_receipt_token=NULL");
            if ($started) $this->db->rollback();
            throw $e;
        } finally {
            if ($invoiceLock !== '') $this->unlock($invoiceLock);
            if ($requestLock !== '') $this->unlock($requestLock);
            if($guardLocked)ManagedFundsNativeGuard::releaseLifecycle($this->db);
        }
    }

    private function token($kind, $key)
    {
        $key = strtolower(trim((string) $key));
        if (!preg_match('/^[a-f0-9]{64}$/', $key)) throw new RuntimeException('Request key is invalid.');
        return 'CPMF-'.strtoupper(substr(hash('sha256', $kind.':'.$key), 0, 40));
    }

    private function lock($name)
    {
        $row = $this->one("SELECT GET_LOCK('".$this->db->escape($name)."',10) acquired");
        if (!$row || (int) $row->acquired !== 1) throw new RuntimeException('Workflow is busy; retry safely.');
        return $name;
    }

    private function unlock($name){$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($name)."')");}
    private function one($sql){$res=$this->db->query($sql);if(!$res)throw new RuntimeException($this->db->lasterror());return $this->db->fetch_object($res);}
}
