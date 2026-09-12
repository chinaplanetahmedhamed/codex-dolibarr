<?php

require_once __DIR__.'/CustomerFundsService.class.php';

/** Guided native Dolibarr deposit and bank-receipt workflow. */
class NativeAdvanceWorkflowService
{
    private $db; private $entity;
    public function __construct($db,$entity){$this->db=$db;$this->entity=(int)$entity;}

    public function createDepositFromOrigin($originType,$originId,$user,$requestKey)
    {
        if(!$user->hasRight('facture','creer')) throw new RuntimeException('Native invoice-create permission is required.');
        if(function_exists('getDolGlobalString')&&getDolGlobalString('MAIN_USE_ADVANCED_PERMS')&&!$user->hasRight('facture','invoice_advance','validate')) throw new RuntimeException('Native invoice-validation permission is required.');
        if(!in_array($originType,array('commande','propal'),true)||$originId<=0) throw new RuntimeException('Choose an eligible customer order or proposal.');
        $token=$this->token('invoice',$requestKey); $lock=$this->lock($token);
        try{
            $existing=$this->one("SELECT rowid,fk_soc,fk_projet,type,fk_statut FROM ".MAIN_DB_PREFIX."facture WHERE entity=".$this->entity." AND ref_ext='".$this->db->escape($token)."' LIMIT 1");
            if($existing){if((int)$existing->type!==3)throw new RuntimeException('Deposit retry token conflicts with another invoice.');return (int)$existing->rowid;}
            if($originType==='commande'){require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';$origin=new Commande($this->db);}
            else{require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';$origin=new Propal($this->db);}
            if($origin->fetch($originId)<=0) throw new RuntimeException('Selected native source was not found.');
            if((int)$origin->entity!==$this->entity||(int)$origin->socid<=0||(int)$origin->status<=0) throw new RuntimeException('Source must be a validated customer-owned record in the active entity.');
            $customer=$this->one("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity=".$this->entity." AND rowid=".(int)$origin->socid." AND status=1 AND client IN (1,2,3)");
            if(!$customer) throw new RuntimeException('Source customer is not active in this entity.');
            $term=$this->one("SELECT rowid,deposit_percent FROM ".MAIN_DB_PREFIX."c_payment_term WHERE rowid=".(int)$origin->cond_reglement_id." AND active=1");
            if(!$term||(float)$term->deposit_percent<=0) throw new RuntimeException('The selected source has no configured deposit percentage.');
            $origin->deposit_percent=(float)$term->deposit_percent;
            require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
            $deposit=Facture::createDepositFromOrigin($origin,dol_now(),(int)$term->rowid,$user,0,true,array('ref_ext'=>$token,'note_private'=>'Created by guided managed-funds workflow from '.$originType.' #'.$originId));
            if(!$deposit||empty($deposit->id)) throw new RuntimeException($origin->error?:'Unable to create the native deposit invoice.');
            return (int)$deposit->id;
        }finally{$this->unlock($lock);}
    }

    public function postBankReceipt($invoiceId,$accountId,$paymentCode,$date,$reference,$user,$requestKey)
    {
        global $conf;
        if(!$user->hasRight('facture','paiement')) throw new RuntimeException('Native customer-payment permission is required.');
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new RuntimeException('Receipt date is invalid.');
        $token=$this->token('payment',$requestKey);$lock=$this->lock($token);
        try{
            $invoice=$this->one("SELECT f.rowid,f.fk_soc,f.fk_statut,f.type,f.datef,f.total_ttc,f.multicurrency_total_ttc,UPPER(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN f.multicurrency_code ELSE '".$this->db->escape($conf->currency)."' END) currency,COALESCE(SUM(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END),0) paid FROM ".MAIN_DB_PREFIX."facture f LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_facture=f.rowid WHERE f.entity=".$this->entity." AND f.rowid=".(int)$invoiceId." GROUP BY f.rowid FOR UPDATE");
            if(!$invoice||(int)$invoice->type!==3||(int)$invoice->fk_statut<=0) throw new RuntimeException('Choose a validated native deposit invoice.');
            if($date<(string)$invoice->datef) throw new RuntimeException('Receipt date cannot precede the deposit invoice.');
            $account=$this->one("SELECT rowid,label,UPPER(CASE WHEN TRIM(COALESCE(currency_code,''))<>'' THEN currency_code ELSE '".$this->db->escape($conf->currency)."' END) currency FROM ".MAIN_DB_PREFIX."bank_account WHERE entity=".$this->entity." AND rowid=".(int)$accountId." AND clos=0");
            if(!$account||(string)$account->currency!==(string)$invoice->currency) throw new RuntimeException('Bank account currency must exactly match the deposit invoice.');
            $mode=$this->one("SELECT id,code FROM ".MAIN_DB_PREFIX."c_paiement WHERE active=1 AND code='".$this->db->escape($paymentCode)."' AND code<>'INTR'");
            if(!$mode) throw new RuntimeException('Choose an active customer receipt method.');
            $existing=$this->one("SELECT p.rowid,p.fk_bank,p.fk_account,p.fk_paiement,p.datep,CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END amount FROM ".MAIN_DB_PREFIX."paiement p INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture WHERE p.entity=".$this->entity." AND p.num_paiement='".$this->db->escape($token)."' AND pf.fk_facture=".(int)$invoiceId." LIMIT 1");
            $expectedAmount=(float)(((string)$invoice->currency===(string)$conf->currency)?$invoice->total_ttc:$invoice->multicurrency_total_ttc);
            if($existing){if((int)$existing->fk_bank<=0)throw new RuntimeException('The native payment exists but is not bank-posted; complete or correct it in Dolibarr.');if((int)$existing->fk_account!==(int)$account->rowid||(int)$existing->fk_paiement!==(int)$mode->id||(string)$existing->datep!==$date||abs((float)$existing->amount-$expectedAmount)>0.00001)throw new RuntimeException('Payment retry payload changed.');$paymentId=(int)$existing->rowid;}
            else{
                if((float)$invoice->paid>0.00001) throw new RuntimeException('Deposit invoice already has a different payment.');
                require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
                $thirdparty=new Societe($this->db);if($thirdparty->fetch((int)$invoice->fk_soc)<=0)throw new RuntimeException('Customer could not be loaded.');
                $amount=$expectedAmount;
                $payment=new Paiement($this->db);$payment->datepaye=strtotime($date.' 12:00:00');$payment->paiementcode=(string)$mode->code;$payment->paiementid=(int)$mode->id;$payment->num_payment=$token;$payment->note_private=trim($reference);$payment->fk_account=(int)$account->rowid;
                if((string)$invoice->currency===(string)$conf->currency)$payment->amounts=array((int)$invoiceId=>$amount);else{$payment->multicurrency_amounts=array((int)$invoiceId=>$amount);$payment->multicurrency_code=array((int)$invoiceId=>(string)$invoice->currency);}
                $this->db->begin();$paymentId=$payment->create($user,1,$thirdparty);if($paymentId<=0){$this->db->rollback();throw new RuntimeException($payment->error?:'Native payment creation failed.');}
                if($payment->addPaymentToBank($user,'payment','Managed customer deposit',(int)$account->rowid,'','')<=0){$this->db->rollback();throw new RuntimeException($payment->error?:'Native bank posting failed.');}$this->db->commit();
            }
            $funds=new CustomerFundsService($this->db,$this->entity);$receiptKey=hash('sha256','native-bank-receipt:'.$token);$amount=$expectedAmount;
            $receiptId=$funds->createPendingReceipt(array('fk_soc'=>(int)$invoice->fk_soc,'receipt_date'=>$date,'amount'=>$amount,'currency_code'=>(string)$invoice->currency,'reference'=>$reference?:$token,'note_private'=>'Bank-posted native deposit payment #'.$paymentId,'request_key'=>$receiptKey),(int)$user->id);
            $funds->confirmReceipt($receiptId,$paymentId,(int)$user->id);return array('invoice_id'=>(int)$invoiceId,'payment_id'=>$paymentId,'receipt_id'=>$receiptId);
        }finally{$this->unlock($lock);}
    }

    private function token($kind,$key){$key=strtolower(trim((string)$key));if(!preg_match('/^[a-f0-9]{64}$/',$key))throw new RuntimeException('Request key is invalid.');return 'CPMF-'.strtoupper(substr(hash('sha256',$kind.':'.$key),0,48));}
    private function lock($name){$row=$this->one("SELECT GET_LOCK('".$this->db->escape($name)."',10) acquired");if(!$row||(int)$row->acquired!==1)throw new RuntimeException('Workflow is busy; retry safely.');return $name;}
    private function unlock($name){$this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($name)."')");}
    private function one($sql){$res=$this->db->query($sql);if(!$res)throw new RuntimeException($this->db->lasterror());return $this->db->fetch_object($res);}
}
