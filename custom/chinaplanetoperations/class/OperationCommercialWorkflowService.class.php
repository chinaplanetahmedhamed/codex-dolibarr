<?php

require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';

/**
 * Staff-only guided bridge from a China Planet operation to Dolibarr's native
 * proposal and customer-order records.
 */
class OperationCommercialWorkflowService
{
    private $db;
    private $entity;

    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
    }

    public static function eligibleServiceTypes()
    {
        return array('order', 'sourcing', 'purchasing');
    }

    public function products($limit = 500)
    {
        $rows = array();
        $sql = "SELECT rowid,ref,label FROM ".MAIN_DB_PREFIX."product WHERE entity IN (".getEntity('product').") AND tosell=1 ORDER BY ref ASC LIMIT ".max(1, min(1000, (int) $limit));
        $res = $this->db->query($sql);
        while ($res && ($obj = $this->db->fetch_object($res))) {
            $rows[] = array('id'=>(int) $obj->rowid, 'ref'=>(string) $obj->ref, 'label'=>(string) $obj->label);
        }
        return $rows;
    }

    public function currencies()
    {
        $base = strtoupper((string) getDolCurrency());
        $out = array($base=>$base);
        $res = $this->db->query("SELECT DISTINCT code FROM ".MAIN_DB_PREFIX."multicurrency WHERE entity IN (".getEntity('multicurrency').") AND code IS NOT NULL AND code<>'' ORDER BY code");
        while ($res && ($obj = $this->db->fetch_object($res))) {
            $code = strtoupper(trim((string) $obj->code));
            if (preg_match('/^[A-Z]{3}$/', $code)) $out[$code] = $code;
        }
        return $out;
    }

    public function state($operationId)
    {
        $operation = $this->operation($operationId);
        if (!$operation) return null;
        $link = $this->workflowLink($operationId);
        $proposal = !empty($link['proposal_id']) ? $this->proposal((int) $link['proposal_id'], $operation) : null;
        $order = !empty($operation['fk_commande']) ? $this->order((int) $operation['fk_commande'], $operation) : null;
        return array('operation'=>$operation, 'link'=>$link, 'proposal'=>$proposal, 'order'=>$order);
    }

    public function saveDraft($operationId, array $input, $user)
    {
        $this->assertPermission($user, 'quote');
        $lock = $this->lockName($operationId);
        $this->acquireLock($lock);
        try {
            $operation = $this->requiredOperation($operationId);
            if (!empty($operation['fk_commande'])) throw new RuntimeException('This operation already has a linked customer order.');
            $currency = $this->currencyInfo($input['currency'] ?? '');
            $validity = max(1, min(90, (int) ($input['validity_days'] ?? 15)));
            $lines = $this->validatedLines($input['lines'] ?? array(), $currency);
            if (!$lines) throw new RuntimeException('Add at least one quotation line.');
            $link = $this->workflowLink($operationId);
            $proposal = !empty($link['proposal_id']) ? $this->proposal((int) $link['proposal_id'], $operation) : null;
            if ($proposal && (int) $proposal->status !== Propal::STATUS_DRAFT) throw new RuntimeException('Only a draft quotation can be edited.');

            if (!$proposal) {
                $proposal = new Propal($this->db);
                $proposal->socid = (int) $operation['fk_soc'];
                $proposal->date = dol_now();
                $proposal->duree_validite = $validity;
                $proposal->ref_client = (string) $operation['ref'];
                $proposal->fk_project = !empty($operation['fk_project']) ? (int) $operation['fk_project'] : 0;
                $proposal->note_private = 'Created from China Planet OS operation '.$operation['ref'];
                $proposal->multicurrency_code = $currency['code'];
                $proposal->multicurrency_tx = $currency['tx'];
                $proposalId = $proposal->create($user);
                if ($proposalId <= 0) throw new RuntimeException($proposal->error ?: 'Quotation creation failed.');
                $this->saveLink($operationId, array('proposal_id'=>(int) $proposalId), (int) $user->id);
                if ($proposal->fetch($proposalId) <= 0) throw new RuntimeException('Quotation reload failed.');
            } else {
                if (strtoupper((string) $proposal->multicurrency_code) !== $currency['code']) throw new RuntimeException('Quotation currency is locked after creation.');
                $proposal->duree_validite = $validity;
                $proposal->fin_validite = $proposal->date + ($validity * 86400);
                if ($proposal->update($user) <= 0) throw new RuntimeException($proposal->error ?: 'Quotation update failed.');
            }

            $this->replaceLines($proposal, $lines, $currency);
            $this->log($operationId, 'commercial_quote_saved', 'Native quotation '.$proposal->ref.' saved as draft.', (int) $user->id);
            $proposal->fetch($proposal->id);
            return $proposal;
        } finally {
            $this->releaseLock($lock);
        }
    }

    public function validateQuotation($operationId, $user)
    {
        $this->assertPermission($user, 'quote');
        return $this->withWorkflowLock($operationId, function ($operation, $link) use ($operationId, $user) {
            $proposal = !empty($link['proposal_id']) ? $this->proposal((int) $link['proposal_id'], $operation) : null;
            if (!$proposal) throw new RuntimeException('Create the quotation draft first.');
            if ((int) $proposal->status === Propal::STATUS_VALIDATED) return $proposal;
            if ((int) $proposal->status !== Propal::STATUS_DRAFT) throw new RuntimeException('Quotation is not in draft state.');
            if (empty($proposal->lines)) throw new RuntimeException('Quotation must contain at least one line.');
            if ($proposal->valid($user) < 0) throw new RuntimeException($proposal->error ?: 'Quotation validation failed.');
            $proposal->fetch($proposal->id);
            $this->log($operationId, 'commercial_quote_validated', 'Native quotation '.$proposal->ref.' validated.', (int) $user->id);
            return $proposal;
        });
    }

    public function acceptQuotation($operationId, $evidenceReference, $user)
    {
        $this->assertPermission($user, 'quote');
        $evidenceReference = $this->clean($evidenceReference, 255);
        if ($evidenceReference === '') throw new RuntimeException('Add the customer acceptance reference.');
        return $this->withWorkflowLock($operationId, function ($operation, $link) use ($operationId, $evidenceReference, $user) {
            $proposal = !empty($link['proposal_id']) ? $this->proposal((int) $link['proposal_id'], $operation) : null;
            if (!$proposal) throw new RuntimeException('Create the quotation first.');
            if ((int) $proposal->status === Propal::STATUS_SIGNED) return $proposal;
            if ((int) $proposal->status !== Propal::STATUS_VALIDATED) throw new RuntimeException('Validate the quotation before recording customer acceptance.');
            if ($proposal->closeProposal($user, Propal::STATUS_SIGNED, 'Customer acceptance recorded through China Planet OS. Evidence: '.$evidenceReference) < 0) throw new RuntimeException($proposal->error ?: 'Quotation acceptance failed.');
            $proposal->fetch($proposal->id);
            $this->log($operationId, 'commercial_quote_accepted', 'Customer acceptance recorded for '.$proposal->ref.'.', (int) $user->id);
            return $proposal;
        });
    }

    public function createOrder($operationId, $user)
    {
        $this->assertPermission($user, 'order');
        if (getDolGlobalString('ORDER_VALID_AFTER_CLOSE_PROPAL')) throw new RuntimeException('Disable automatic order validation so the staff approval step remains explicit.');
        return $this->withWorkflowLock($operationId, function ($operation, $link) use ($operationId, $user) {
            if (!empty($operation['fk_commande'])) return $this->order((int) $operation['fk_commande'], $operation);
            $proposal = !empty($link['proposal_id']) ? $this->proposal((int) $link['proposal_id'], $operation) : null;
            if (!$proposal || (int) $proposal->status !== Propal::STATUS_SIGNED) throw new RuntimeException('Record customer acceptance before creating the order.');
            $order = new Commande($this->db);
            $orderId = $order->createFromProposal($proposal, $user);
            if ($orderId <= 0) throw new RuntimeException($order->error ?: 'Order creation failed.');
            $res = $this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_operation SET fk_commande=".(int) $orderId.",fk_user_modif=".(int) $user->id." WHERE entity=".$this->entity." AND rowid=".(int) $operationId." AND fk_soc=".(int) $operation['fk_soc']." AND fk_commande IS NULL");
            if (!$res || $this->db->affected_rows($res) !== 1) throw new RuntimeException('Order link conflict.');
            if ($order->fetch($orderId) <= 0) throw new RuntimeException('Order reload failed.');
            $this->log($operationId, 'commercial_order_created', 'Native customer order '.$order->ref.' created from '.$proposal->ref.'.', (int) $user->id);
            return $order;
        });
    }

    public function validateOrder($operationId, $user)
    {
        $this->assertPermission($user, 'order');
        return $this->withWorkflowLock($operationId, function ($operation) use ($operationId, $user) {
            if (empty($operation['fk_commande'])) throw new RuntimeException('Create the customer order first.');
            $order = $this->order((int) $operation['fk_commande'], $operation);
            $status = isset($order->status) ? (int) $order->status : (int) $order->statut;
            if ($status === Commande::STATUS_VALIDATED) return $order;
            if ($status !== Commande::STATUS_DRAFT) throw new RuntimeException('Order is not in draft state.');
            if ($order->valid($user) < 0) throw new RuntimeException($order->error ?: 'Order validation failed.');
            $order->fetch($order->id);
            $this->log($operationId, 'commercial_order_validated', 'Native customer order '.$order->ref.' validated.', (int) $user->id);
            return $order;
        });
    }

    private function validatedLines(array $rawLines, array $currency)
    {
        $out = array();
        foreach (array_slice($rawLines, 0, 10) as $raw) {
            if (!is_array($raw)) continue;
            $productId = (int) ($raw['fk_product'] ?? 0);
            $qty = is_numeric($raw['qty'] ?? null) ? (float) $raw['qty'] : 0;
            $foreignPrice = is_numeric($raw['unit_price'] ?? null) ? (float) $raw['unit_price'] : -1;
            if ($productId <= 0 && $qty <= 0 && $foreignPrice < 0) continue;
            if ($productId <= 0 || $qty <= 0 || $qty > 1000000 || $foreignPrice < 0 || $foreignPrice > 1000000000) throw new RuntimeException('Each quotation line needs a valid product, quantity, and unit price.');
            $product = $this->saleProduct($productId);
            if (!$product) throw new RuntimeException('Selected product is not available for sale.');
            $description = $this->clean($raw['description'] ?? '', 1000);
            if ($description === '') $description = (string) $product->label;
            $out[] = array('product'=>$product, 'qty'=>$qty, 'foreign_price'=>$foreignPrice, 'base_price'=>$foreignPrice / $currency['tx'], 'description'=>$description);
        }
        return $out;
    }

    private function replaceLines($proposal, array $lines, array $currency)
    {
        if (empty($proposal->thirdparty)) $proposal->fetch_thirdparty();
        $existing = (array) $proposal->lines;
        foreach ($lines as $index=>$item) {
            $product = $item['product'];
            $vat = function_exists('get_default_tva') ? get_default_tva($GLOBALS['mysoc'], $proposal->thirdparty, $product->id) : '';
            if ($vat === '' || $vat === null) $vat = !empty($product->tva_tx) ? (string) $product->tva_tx : 0;
            $foreign = $currency['code'] === $currency['base'] ? 0 : $item['foreign_price'];
            if (isset($existing[$index])) {
                if ((int) $existing[$index]->fk_product !== (int) $product->id) throw new RuntimeException('An existing quotation line product cannot be replaced; add a new line instead.');
                $result = $proposal->updateline($existing[$index]->id, $item['base_price'], $item['qty'], 0, $vat, 0, 0, $item['description'], 'HT', 0, 0, 0, 0, 0, '', '', $product->type, '', '', array(), $product->fk_unit, $foreign, 0, $index + 1);
            } else {
                $result = $proposal->addline($item['description'], $item['base_price'], $item['qty'], $vat, 0, 0, $product->id, 0, 'HT', 0, 0, $product->type, $index + 1, 0, 0, 0, 0, '', '', '', array(), $product->fk_unit, '', 0, $foreign);
            }
            if ($result <= 0) throw new RuntimeException($proposal->error ?: 'Quotation line creation failed.');
        }
        for ($index = count($existing) - 1; $index >= count($lines); $index--) {
            if ($proposal->deleteLine((int) $existing[$index]->id, (int) $proposal->id) <= 0) throw new RuntimeException($proposal->error ?: 'Unable to remove quotation line.');
        }
    }

    private function requiredOperation($id)
    {
        $operation = $this->operation($id);
        if (!$operation) throw new RuntimeException('Operation not found.');
        if (!in_array($operation['service_type'], self::eligibleServiceTypes(), true)) throw new RuntimeException('This operation type does not use the customer quotation workflow.');
        if ((int) $operation['fk_soc'] <= 0) throw new RuntimeException('Link a customer before starting the commercial workflow.');
        return $operation;
    }

    private function operation($id)
    {
        $res = $this->db->query("SELECT rowid,ref,service_type,fk_soc,fk_project,fk_commande FROM ".MAIN_DB_PREFIX."cpo_operation WHERE entity=".$this->entity." AND rowid=".(int) $id." AND status=1");
        $obj = $res ? $this->db->fetch_object($res) : null;
        return $obj ? (array) $obj : null;
    }

    private function workflowLink($operationId)
    {
        $res = $this->db->query("SELECT data_json FROM ".MAIN_DB_PREFIX."cpo_operation_data WHERE entity=".$this->entity." AND fk_operation=".(int) $operationId." AND section_code='commercial_workflow'");
        $obj = $res ? $this->db->fetch_object($res) : null;
        $data = $obj ? json_decode((string) $obj->data_json, true) : array();
        return is_array($data) ? $data : array();
    }

    private function saveLink($operationId, array $data, $userId)
    {
        $current = $this->workflowLink($operationId);
        $json = json_encode(array_merge($current, $data), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_operation_data(entity,fk_operation,section_code,data_json,fk_user_modif) VALUES(".$this->entity.",".(int) $operationId.",'commercial_workflow','".$this->db->escape($json)."',".(int) $userId.") ON DUPLICATE KEY UPDATE data_json=VALUES(data_json),fk_user_modif=VALUES(fk_user_modif)";
        if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
    }

    private function proposal($id, array $operation)
    {
        $proposal = new Propal($this->db);
        if ($proposal->fetch($id) <= 0 || (int) $proposal->entity !== $this->entity || (int) $proposal->socid !== (int) $operation['fk_soc'] || (int) $proposal->fk_project !== (int) $operation['fk_project']) throw new RuntimeException('Quotation context mismatch.');
        return $proposal;
    }

    private function order($id, array $operation)
    {
        $order = new Commande($this->db);
        if ($order->fetch($id) <= 0 || (int) $order->entity !== $this->entity || (int) $order->socid !== (int) $operation['fk_soc'] || (int) $order->fk_project !== (int) $operation['fk_project']) throw new RuntimeException('Order context mismatch.');
        return $order;
    }

    private function saleProduct($id)
    {
        $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE entity IN (".getEntity('product').") AND rowid=".(int) $id." AND tosell=1");
        if (!$res || !$this->db->fetch_object($res)) return null;
        $product = new Product($this->db);
        return $product->fetch($id) > 0 ? $product : null;
    }

    private function currencyInfo($currency)
    {
        $code = strtoupper(trim((string) $currency));
        $base = strtoupper((string) getDolCurrency());
        if (!preg_match('/^[A-Z]{3}$/', $code)) throw new RuntimeException('Choose a valid quotation currency.');
        if ($code === $base) return array('code'=>$code, 'base'=>$base, 'tx'=>1.0);
        list($id, $tx) = MultiCurrency::getIdAndTxFromCode($this->db, $code, dol_now());
        if ((int) $id <= 0 || !is_numeric($tx) || (float) $tx <= 0) throw new RuntimeException('No active exchange rate exists for the selected currency.');
        return array('code'=>$code, 'base'=>$base, 'tx'=>(float) $tx);
    }

    private function assertPermission($user, $mode)
    {
        if (empty($user->id) || !empty($user->socid)) throw new RuntimeException('Staff access required.');
        if (!empty($user->admin)) return;
        $canQuote = $user->hasRight('propal', 'creer') && $user->hasRight('propal', 'lire') && $user->hasRight('product', 'read');
        if (!$canQuote || ($mode === 'order' && (!$user->hasRight('commande', 'creer') || !$user->hasRight('commande', 'lire')))) throw new RuntimeException('Native commercial permission required.');
    }

    private function withWorkflowLock($operationId, $callback)
    {
        $lock = $this->lockName($operationId);
        $this->acquireLock($lock);
        try {
            $operation = $this->requiredOperation($operationId);
            return $callback($operation, $this->workflowLink($operationId));
        } finally {
            $this->releaseLock($lock);
        }
    }

    private function log($operationId, $action, $message, $userId)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."cpo_activity(entity,fk_operation,fk_task,action_code,message,visibility,fk_user,date_action) VALUES(".$this->entity.",".(int) $operationId.",NULL,'".$this->db->escape($action)."','".$this->db->escape($message)."','internal',".(int) $userId.",'".$this->db->idate(dol_now())."')";
        if (!$this->db->query($sql)) throw new RuntimeException($this->db->lasterror());
    }

    private function lockName($operationId)
    {
        return 'cpo_commercial_'.$this->entity.'_'.(int) $operationId;
    }

    private function acquireLock($name)
    {
        $res = $this->db->query("SELECT GET_LOCK('".$this->db->escape($name)."',5) acquired");
        $obj = $res ? $this->db->fetch_object($res) : null;
        if (!$obj || (int) $obj->acquired !== 1) throw new RuntimeException('Commercial workflow is busy. Try again.');
    }

    private function releaseLock($name)
    {
        $this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($name)."')");
    }

    private function clean($value, $limit)
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', trim((string) $value));
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }
}
