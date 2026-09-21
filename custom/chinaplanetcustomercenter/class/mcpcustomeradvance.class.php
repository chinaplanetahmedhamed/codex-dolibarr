<?php
/** Native customer deposit receipts exposed to internal MCP users (Dolibarr 24+). */
require_once DOL_DOCUMENT_ROOT.'/ai/class/mcptool.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php';

class CpccAdvanceInputException extends RuntimeException {}

class CpccMcpCustomerAdvance extends McpTool
{
    const MARKER = 'CPCC_MCP_ADVANCE_V1:';

    public function getCategories(): array { return array('billing'); }

    public function getDefinitions(): array
    {
        $customer = array('type'=>'integer', 'minimum'=>1, 'description'=>'Exact customer ID from ERP lookup; never guess.');
        return array(
            array('name'=>'get_customer_advance_options',
                'description'=>'Internal use: read customer available deposit credits, receiving account IDs/currencies, and payment methods before recording a customer advance. Does not create a payment.',
                'annotations'=>array('readOnlyHint'=>true, 'openWorldHint'=>false),
                'inputSchema'=>array('type'=>'object', 'properties'=>array('customer_id'=>$customer), 'required'=>array('customer_id'), 'additionalProperties'=>false)),
            array('name'=>'record_customer_advance',
                'description'=>'Record an explicitly authorized, already received customer advance using a native deposit invoice, payment, bank entry and reusable deposit credit. No unpaid invoice is needed. Ask for missing receipt details; never guess account, date, currency, VAT or FX. Reuse the SAME receipt_reference for retries; never generate a new reference after an error. Amount is gross including any VAT. Does not charge a card, transfer money or apply the credit to a final invoice.',
                'annotations'=>array('readOnlyHint'=>false, 'destructiveHint'=>true, 'idempotentHint'=>true, 'openWorldHint'=>false),
                'inputSchema'=>array('type'=>'object', 'properties'=>array(
                    'customer_id'=>$customer,
                    'amount'=>array('type'=>'number', 'exclusiveMinimum'=>0, 'description'=>'Exact gross amount received, maximum two decimal places.'),
                    'currency'=>array('type'=>'string', 'pattern'=>'^[A-Z]{3}$'),
                    'receipt_date'=>array('type'=>'string', 'format'=>'date', 'description'=>'Actual receipt date YYYY-MM-DD.'),
                    'bank_account_id'=>array('type'=>'integer', 'minimum'=>1),
                    'payment_mode'=>array('type'=>'string', 'description'=>'Active received-payment code from options, e.g. VIR or LIQ.'),
                    'vat_rate'=>array('type'=>'number', 'minimum'=>0, 'maximum'=>100, 'description'=>'Explicit applicable VAT percentage; do not assume zero.'),
                    'receipt_reference'=>array('type'=>'string', 'minLength'=>8, 'maxLength'=>50, 'description'=>'Stable unique bank receipt/reference or user-approved receipt identifier. Same receipt must always use this same reference.'),
                    'exchange_rate'=>array('type'=>'number', 'exclusiveMinimum'=>0, 'description'=>'Required for foreign currency: foreign units per ONE company base-currency unit.'),
                    'exchange_rate_date'=>array('type'=>'string', 'format'=>'date'),
                    'exchange_rate_source'=>array('type'=>'string', 'maxLength'=>120)
                ), 'required'=>array('customer_id','amount','currency','receipt_date','bank_account_id','payment_mode','vat_rate','receipt_reference'), 'additionalProperties'=>false))
        );
    }

    public function execute(string $toolName, array $args)
    {
        // Some native failures print an HTML/CLI database error; never corrupt MCP JSON.
        $outputLevel = ob_get_level();
        ob_start();
        try {
            if (!in_array($toolName, array('get_customer_advance_options','record_customer_advance'), true)) {
                throw new CpccAdvanceInputException('Unknown customer advance tool.');
            }
            $this->authorize($toolName === 'record_customer_advance');
            $customerId = $this->positiveId(isset($args['customer_id']) ? $args['customer_id'] : null);
            $customer = $this->customer($customerId);
            if ($toolName === 'get_customer_advance_options') {
                if (array_diff(array_keys($args), array('customer_id'))) throw new CpccAdvanceInputException('Unexpected fields.');
                return $this->options($customer);
            }
            return $this->record($customer, $this->normalize($args));
        } catch (CpccAdvanceInputException $e) {
            return array('error'=>$e->getMessage());
        } catch (Throwable $e) {
            dol_syslog('CPCC customer advance failed: '.$e->getMessage(), LOG_ERR);
            return array('error'=>'Customer advance could not be completed. Check native records using the same receipt reference before retrying; do not use a new reference.');
        } finally {
            while (ob_get_level() > $outputLevel) ob_end_clean();
        }
    }

    private function authorize($write)
    {
        if (empty($this->user->id) || empty($this->user->statut) || !empty($this->user->socid)
            || !in_array((int) $this->user->entity, array(0, (int) $this->conf->entity), true)) {
            throw new CpccAdvanceInputException('An active internal user in this entity is required.');
        }
        foreach (array('ai','societe','facture','bank','chinaplanetcustomercenter') as $module) {
            if (!isModEnabled($module)) throw new CpccAdvanceInputException('Required module is disabled: '.$module);
        }
        $rights = array(array('societe','lire'),array('facture','lire'),array('banque','lire'));
        if ($write) {
            $rights = array_merge($rights, array(array('facture','creer'),array('facture','paiement'),array('banque','modifier')));
            if (getDolGlobalString('MAIN_USE_ADVANCED_PERMS')) $rights[] = array('facture','invoice_advance','validate');
        }
        foreach ($rights as $right) {
            if (!call_user_func_array(array($this->user, 'hasRight'), $right)) throw new CpccAdvanceInputException('Missing native permission: '.implode('.', $right));
        }
        if ($write && getDolGlobalString('INVOICE_DISABLE_DEPOSIT')) throw new CpccAdvanceInputException('Deposit invoices are disabled.');
    }

    private function positiveId($value)
    {
        if (!is_int($value) || $value <= 0) throw new CpccAdvanceInputException('A positive integer object ID is required.');
        return $value;
    }

    private function rows($sql)
    {
        $result = $this->db->query($sql);
        if (!$result) throw new RuntimeException('Database operation failed.');
        $rows = array();
        while ($row = $this->db->fetch_object($result)) $rows[] = $row;
        return $rows;
    }

    private function customer($id)
    {
        $rows = $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE rowid='.$id.' AND entity='.(int) $this->conf->entity.' AND client IN (1,3) AND status=1');
        if (!$rows) throw new CpccAdvanceInputException('Active customer not found or not accessible in this entity.');
        if (!$this->user->hasRight('societe','client','voir')) {
            $assigned = $this->rows('SELECT fk_soc FROM '.MAIN_DB_PREFIX.'societe_commerciaux WHERE fk_soc='.$id.' AND fk_user='.(int) $this->user->id);
            if (!$assigned) throw new CpccAdvanceInputException('Customer is not assigned to this user.');
        }
        $customer = new Societe($this->db);
        if ($customer->fetch($id) <= 0) throw new RuntimeException('Customer fetch failed.');
        return $customer;
    }

    private function options($customer)
    {
        $accounts = $this->rows('SELECT rowid AS id, ref, label, currency_code AS currency, courant AS account_type FROM '.MAIN_DB_PREFIX.'bank_account WHERE entity='.(int) $this->conf->entity.' AND clos=0 ORDER BY rowid');
        $methods = $this->rows('SELECT code, libelle AS label FROM '.MAIN_DB_PREFIX.'c_paiement WHERE active=1 AND type IN (0,2) AND entity IN ('.getEntity('c_paiement').') ORDER BY code');
        $credits = $this->rows('SELECT r.rowid AS id, f.ref AS source_invoice, f.multicurrency_code AS currency, r.multicurrency_amount_ttc AS amount, r.amount_ttc AS base_amount FROM '.MAIN_DB_PREFIX.'societe_remise_except r JOIN '.MAIN_DB_PREFIX.'facture f ON f.rowid=r.fk_facture_source WHERE r.fk_soc='.(int) $customer->id.' AND r.entity='.(int) $this->conf->entity.' AND f.entity='.(int) $this->conf->entity.' AND f.type=3 AND r.fk_facture IS NULL AND r.fk_facture_line IS NULL ORDER BY r.rowid');
        return array('customer_id'=>(int) $customer->id, 'customer'=>$customer->name, 'base_currency'=>$this->conf->currency,
            'receiving_accounts'=>$accounts, 'payment_modes'=>$methods, 'available_deposit_credits'=>$credits,
            'deposit_product_id'=>getDolGlobalInt('INVOICE_PRODUCTID_DEPOSIT'),
            'requirements'=>'Use an explicit applicable VAT rate. Foreign receipts need a configured currency and explicit exchange rate/date/source. Receiving account currency must match the receipt.');
    }

    private function dateValue($value)
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new CpccAdvanceInputException('A valid YYYY-MM-DD date is required.');
        }
        return dol_mktime(12, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function number($value, $min, $max)
    {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < $min || $value > $max) {
            throw new CpccAdvanceInputException('Invalid numeric amount, VAT or exchange rate.');
        }
        return (float) $value;
    }

    private function normalize($args)
    {
        $definition = $this->getDefinitions()[1]['inputSchema'];
        if (array_diff($definition['required'], array_keys($args)) || array_diff(array_keys($args), array_keys($definition['properties']))) throw new CpccAdvanceInputException('Missing required receipt details or unexpected fields.');
        $data = array('customer_id'=>$this->positiveId($args['customer_id']), 'bank_account_id'=>$this->positiveId($args['bank_account_id']), 'base_currency'=>$this->conf->currency);
        $data['amount'] = $this->number($args['amount'], 0.01, 999999999.99);
        if (abs($data['amount'] - round($data['amount'], 2)) > 0.000001) throw new CpccAdvanceInputException('Receipt amount must have at most two decimal places.');
        $data['vat_rate'] = $this->number($args['vat_rate'], 0, 100);
        foreach (array('currency'=>'/^[A-Z]{3}$/D','payment_mode'=>'/^[A-Z0-9_]{1,6}$/D','receipt_reference'=>'/^[A-Za-z0-9][A-Za-z0-9._:\/-]{7,49}$/D') as $key=>$pattern) {
            if (!is_string($args[$key]) || !preg_match($pattern, $args[$key])) throw new CpccAdvanceInputException('Invalid '.$key.'.');
            $data[$key] = $args[$key];
        }
        $this->dateValue($args['receipt_date']);
        if ($args['receipt_date'] > dol_print_date(dol_now(), '%Y-%m-%d')) throw new CpccAdvanceInputException('Receipt date cannot be in the future.');
        $data['receipt_date'] = $args['receipt_date'];
        $data['exchange_rate'] = 1.0;
        if ($data['currency'] !== $this->conf->currency) {
            if (!isModEnabled('multicurrency') || empty($args['exchange_rate']) || empty($args['exchange_rate_date']) || empty($args['exchange_rate_source'])) throw new CpccAdvanceInputException('Foreign currency requires multicurrency and explicit exchange_rate, exchange_rate_date and exchange_rate_source.');
            $data['exchange_rate'] = $this->number($args['exchange_rate'], 0.00000001, 100000000);
            $this->dateValue($args['exchange_rate_date']);
            if ($args['exchange_rate_date'] > $data['receipt_date']) throw new CpccAdvanceInputException('Exchange rate date cannot be after the receipt date.');
            if (!is_string($args['exchange_rate_source']) || !preg_match('/^[^<>\x00-\x1f]{1,120}$/uD', $args['exchange_rate_source'])) throw new CpccAdvanceInputException('Invalid exchange rate source.');
            $data['exchange_rate_date'] = $args['exchange_rate_date'];
            $data['exchange_rate_source'] = $args['exchange_rate_source'];
            $currency = $this->rows("SELECT rowid FROM ".MAIN_DB_PREFIX."multicurrency WHERE entity=".(int) $this->conf->entity." AND code='".$this->db->escape($data['currency'])."'");
            if (!$currency) throw new CpccAdvanceInputException('Currency is not configured in this entity.');
        } elseif (isset($args['exchange_rate']) || isset($args['exchange_rate_date']) || isset($args['exchange_rate_source'])) {
            throw new CpccAdvanceInputException('Do not supply FX fields for a base-currency receipt.');
        }
        return $data;
    }

    private function record($customer, $data)
    {
        if ($this->db->type !== 'mysqli' || $this->db->transaction_opened) throw new CpccAdvanceInputException('This tool requires a standalone MariaDB/MySQL transaction.');
        $key = hash('sha256', $this->conf->entity.':'.$data['receipt_reference']);
        $reference = 'CP-MCP-ADV:'.$key;
        $lock = 'cpcc-advance-'.substr($key, 0, 48);
        $got = $this->rows("SELECT GET_LOCK('".$lock."', 5) AS acquired");
        if (!$got || (int) $got[0]->acquired !== 1) throw new CpccAdvanceInputException('Receipt is being processed. Retry with the same receipt_reference.');
        try {
            $prior = $this->rows("SELECT rowid, note_private FROM ".MAIN_DB_PREFIX."facture WHERE entity=".(int) $this->conf->entity." AND ref_ext='".$reference."'");
            if ($prior) {
                if (count($prior) !== 1 || $prior[0]->note_private !== self::MARKER.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)) throw new CpccAdvanceInputException('Receipt reference already exists with different details or needs reconciliation. Nothing was reposted.');
                return $this->receipt((int) $prior[0]->rowid, $data, true);
            }
            if (!$this->db->begin()) throw new RuntimeException('Unable to start receipt transaction.');
            $bankRows = $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'bank_account WHERE rowid='.$data['bank_account_id'].' AND entity='.(int) $this->conf->entity.' AND clos=0 FOR UPDATE');
            $bank = new Account($this->db);
            if (!$bankRows || $bank->fetch($data['bank_account_id']) <= 0) throw new CpccAdvanceInputException('Open receiving account not found in this entity.');
            if ($bank->currency_code !== $data['currency']) throw new CpccAdvanceInputException('Receiving account currency must match the receipt currency.');
            if (($data['payment_mode'] === 'LIQ') !== ((int) $bank->type === 2)) throw new CpccAdvanceInputException('Cash payments require a cash account; other methods require a bank account.');
            $modes = $this->rows("SELECT id FROM ".MAIN_DB_PREFIX."c_paiement WHERE active=1 AND type IN (0,2) AND entity IN (".getEntity('c_paiement').") AND code='".$this->db->escape($data['payment_mode'])."'");
            if (count($modes) !== 1) throw new CpccAdvanceInputException('Invalid or disabled received-payment method.');
            global $mysoc;
            $taxes = getTaxesFromId($data['vat_rate'], $customer, $mysoc, 0);
            if (!empty($taxes['localtax1']) || !empty($taxes['localtax2'])) throw new CpccAdvanceInputException('This local-tax configuration requires the native invoice workflow.');
            $productId = getDolGlobalInt('INVOICE_PRODUCTID_DEPOSIT');
            if ($productId) {
                $products = $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'product WHERE rowid='.$productId.' AND entity='.(int) $this->conf->entity.' AND fk_product_type=1');
                if (!$products) throw new CpccAdvanceInputException('Configured deposit product must be a service in this entity.');
            }
            $invoice = new Facture($this->db);
            $invoice->socid = (int) $customer->id;
            $invoice->type = Facture::TYPE_DEPOSIT;
            $invoice->date = $this->dateValue($data['receipt_date']);
            $invoice->ref_ext = $reference;
            $invoice->note_private = self::MARKER.json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            $invoice->multicurrency_code = $data['currency'];
            $invoice->multicurrency_tx = $data['exchange_rate'];
            $invoice->mode_reglement_id = (int) $modes[0]->id;
            $invoice->fk_account = $data['bank_account_id'];
            if ($invoice->create($this->user) <= 0) throw new RuntimeException('Native deposit creation failed.');
            if ($invoice->multicurrency_code !== $data['currency']) throw new RuntimeException('Native invoice currency does not match receipt.');
            $foreignHt = $data['amount'] / (1 + $data['vat_rate'] / 100);
            if ($invoice->addline('(DEPOSIT)', $foreignHt / $data['exchange_rate'], 1, $data['vat_rate'], 0, 0, $productId, 0, '', '', 0, 0, 0, 'HT', 0, 1, -1, 0, '', 0, 0, null, 0, '', array(), 100, 0, null, $foreignHt) <= 0) throw new RuntimeException('Native deposit line creation failed.');
            if ($invoice->fetch($invoice->id) <= 0 || abs((float) $invoice->multicurrency_total_ttc - $data['amount']) > 0.000001) throw new CpccAdvanceInputException('Native tax/FX rounding does not match the exact receipt amount. Nothing was posted; use the native invoice workflow.');
            if ($invoice->validate($this->user) <= 0) throw new RuntimeException('Native deposit validation failed.');
            $payment = new Paiement($this->db);
            $payment->datepaye = $invoice->date;
            $payment->paiementid = (int) $modes[0]->id;
            $payment->paiementcode = $data['payment_mode'];
            $payment->fk_account = $data['bank_account_id'];
            $payment->num_payment = $data['receipt_reference'];
            $payment->amounts = array($invoice->id => (float) $invoice->total_ttc);
            $payment->multicurrency_code = array($invoice->id => $data['currency']);
            $payment->multicurrency_tx = array($invoice->id => $data['exchange_rate']);
            if ($data['currency'] !== $this->conf->currency) $payment->multicurrency_amounts = array($invoice->id => $data['amount']);
            // Native create(..., 1) converts a fully paid deposit to linked available credit.
            if ($payment->create($this->user, 1, $customer) <= 0) throw new RuntimeException('Native receipt or deposit credit creation failed.');
            if ($payment->addPaymentToBank($this->user, 'payment', '(Customer advance via MCP)', $data['bank_account_id'], '', '') <= 0) throw new RuntimeException('Native receiving bank entry failed.');
            $result = $this->receipt((int) $invoice->id, $data, false);
            if ($this->db->transaction_opened !== 1 || !$this->db->commit()) throw new RuntimeException('Receipt commit failed.');
            return $result;
        } catch (Throwable $e) {
            // Dolibarr nested rollback only reduces depth. Unwind to the real outer rollback.
            while ($this->db->transaction_opened > 0) $this->db->rollback();
            throw $e;
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('".$lock."')");
        }
    }

    private function receipt($invoiceId, $data, $replayed)
    {
        $entity = (int) $this->conf->entity;
        $rows = $this->rows('SELECT f.rowid, f.ref, f.fk_soc, f.type, f.paye, f.datef, f.multicurrency_tx, f.total_ttc, f.multicurrency_total_ttc, f.multicurrency_code, p.rowid AS payment_id, p.num_paiement, p.datep, p.fk_paiement, p.fk_bank, pf.amount AS paid_base, pf.multicurrency_amount AS paid_foreign, b.amount AS bank_amount, b.fk_account FROM '.MAIN_DB_PREFIX.'facture f JOIN '.MAIN_DB_PREFIX.'paiement_facture pf ON pf.fk_facture=f.rowid JOIN '.MAIN_DB_PREFIX.'paiement p ON p.rowid=pf.fk_paiement JOIN '.MAIN_DB_PREFIX.'bank b ON b.rowid=p.fk_bank JOIN '.MAIN_DB_PREFIX.'bank_account ba ON ba.rowid=b.fk_account WHERE f.rowid='.$invoiceId.' AND f.entity='.$entity.' AND p.entity='.$entity.' AND ba.entity='.$entity);
        if (count($rows) !== 1) throw new CpccAdvanceInputException('Receipt needs reconciliation: native invoice/payment/bank links are incomplete. Nothing was reposted.');
        $r = $rows[0];
        $credits = $this->rows('SELECT rowid, amount_ttc, multicurrency_amount_ttc, fk_facture, fk_facture_line FROM '.MAIN_DB_PREFIX.'societe_remise_except WHERE entity='.$entity.' AND fk_facture_source='.$invoiceId.' AND fk_soc='.(int) $data['customer_id']);
        $creditTotal = 0.0;
        foreach ($credits as $credit) $creditTotal += (float) $credit->multicurrency_amount_ttc;
        if ((int) $r->fk_soc !== $data['customer_id'] || (int) $r->type !== Facture::TYPE_DEPOSIT || !(int) $r->paye || $r->multicurrency_code !== $data['currency'] || (int) $r->fk_account !== $data['bank_account_id'] || $r->num_paiement !== $data['receipt_reference'] || abs((float) $r->bank_amount - $data['amount']) > 0.000001 || abs((float) $r->multicurrency_total_ttc - $data['amount']) > 0.000001 || abs((float) $r->paid_base - (float) $r->total_ttc) > 0.000001 || abs((float) $r->paid_foreign - $data['amount']) > 0.000001 || abs($creditTotal - $data['amount']) > 0.000001) {
            throw new CpccAdvanceInputException('Receipt needs reconciliation: native amounts or links differ from the original request. Nothing was reposted.');
        }
        $mode = $this->rows('SELECT code FROM '.MAIN_DB_PREFIX.'c_paiement WHERE id='.(int) $r->fk_paiement);
        if (!$mode || $mode[0]->code !== $data['payment_mode'] || substr($r->datef, 0, 10) !== $data['receipt_date'] || substr($r->datep, 0, 10) !== $data['receipt_date'] || abs((float) $r->multicurrency_tx - $data['exchange_rate']) > 0.000000001) {
            throw new CpccAdvanceInputException('Receipt needs reconciliation: payment date, method or FX rate was changed. Nothing was reposted.');
        }
        $available = 0.0;
        foreach ($credits as $credit) if (empty($credit->fk_facture) && empty($credit->fk_facture_line)) $available += (float) $credit->multicurrency_amount_ttc;
        return array('success'=>true, 'replayed'=>$replayed, 'customer_id'=>$data['customer_id'], 'receipt_reference'=>$data['receipt_reference'], 'received_amount'=>$data['amount'], 'currency'=>$data['currency'], 'receipt_date'=>$data['receipt_date'], 'invoice_id'=>$invoiceId, 'invoice_reference'=>$r->ref, 'payment_id'=>(int) $r->payment_id, 'bank_transaction_id'=>(int) $r->fk_bank, 'credit_ids'=>array_map(static function ($c) { return (int) $c->rowid; }, $credits), 'available_from_this_receipt'=>$available, 'invoice_url'=>DOL_URL_ROOT.'/compta/facture/card.php?facid='.$invoiceId);
    }
}
