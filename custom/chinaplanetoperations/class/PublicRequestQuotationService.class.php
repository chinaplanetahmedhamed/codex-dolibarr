<?php

require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';

/**
 * Staff-only bridge from immutable website intake evidence to one standard
 * Dolibarr draft commercial proposal.
 */
class PublicRequestQuotationService
{
    private $db;
    private $entity;
    private $columnCache = array();

    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
    }

    public function catalogLines(array $requestRow)
    {
        $request = isset($requestRow['request']) && is_array($requestRow['request']) ? $requestRow['request'] : array();
        $details = isset($request['details']) && is_array($request['details']) ? $request['details'] : array();
        $rawLines = isset($details['lines']) && is_array($details['lines']) ? $details['lines'] : array();
        $packing = isset($details['packing_plan']) && is_array($details['packing_plan']) ? $details['packing_plan'] : array();
        $trustedLines = isset($packing['lines']) && is_array($packing['lines']) ? $packing['lines'] : array();

        $trustedByKey = array();
        foreach ($trustedLines as $trusted) {
            if (!is_array($trusted)) continue;
            $trustedByKey[$this->websiteLineKey($trusted)] = $trusted;
        }

        $out = array();
        foreach (array_slice($rawLines, 0, 100) as $index=>$raw) {
            if (!is_array($raw)) continue;
            $key = $this->websiteLineKey($raw);
            $source = isset($trustedByKey[$key]) ? array_merge($raw, $trustedByKey[$key]) : $raw;
            $out[] = $this->normalizeCatalogLine($source, $index);
        }
        if (empty($out)) {
            foreach (array_slice($trustedLines, 0, 100) as $index=>$raw) {
                if (is_array($raw)) $out[] = $this->normalizeCatalogLine($raw, $index);
            }
        }
        if (!empty($requestRow['rowid']) && (int)($requestRow['schema_version'] ?? 1) === 2) {
            $working = array();
            $res = $this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."cpo_public_request_line WHERE entity=".$this->entity." AND fk_request=".(int)$requestRow['rowid']." ORDER BY position,rowid");
            if ($res) while ($obj = $this->db->fetch_object($res)) $working[(string)$obj->line_key] = (array)$obj;
            foreach ($out as &$line) {
                $review = $working[$line['line_key']] ?? null;
                if (!$review) continue;
                $line['working_line_id'] = (int)$review['rowid'];
                $line['review_status'] = (string)$review['review_status'];
                $line['packing_verified'] = !empty($review['packing_verified']);
                $line['verified_packing_source'] = (string)$review['verified_packing_source'];
                $line['staff_note'] = (string)$review['staff_note'];
                $line['mapped_product_id'] = (int)$review['fk_product'];
                if ($line['packing_verified']) {
                    $line['units_per_carton'] = (int)$review['verified_units_per_carton'];
                    $line['cbm_per_carton'] = (float)$review['verified_cbm_per_carton'];
                    $line['gross_weight'] = (float)$review['verified_gross_weight_kg'];
                    $line['total_units'] = $line['cartons'] * $line['units_per_carton'];
                    $line['total_cbm'] = $line['cartons'] * $line['cbm_per_carton'];
                    $line['total_gross_weight'] = $line['cartons'] * $line['gross_weight'];
                    $line['packing_source'] = 'staff_verified';
                }
            }
            unset($line);
        }
        return $out;
    }

    public function products($limit = 1000)
    {
        $rows = array();
        $sql = "SELECT rowid,ref,label FROM ".MAIN_DB_PREFIX."product WHERE entity IN (".getEntity('product').") AND tosell=1 ORDER BY ref ASC LIMIT ".max(1, min(2000, (int) $limit));
        $res = $this->db->query($sql);
        if ($res) while ($obj = $this->db->fetch_object($res)) $rows[] = array('id'=>(int) $obj->rowid, 'ref'=>(string) $obj->ref, 'label'=>(string) $obj->label);
        return $rows;
    }

    public function contacts($limit = 2000)
    {
        $rows = array();
        $sql = "SELECT sp.rowid,sp.fk_soc,sp.firstname,sp.lastname,sp.email FROM ".MAIN_DB_PREFIX."socpeople sp INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=sp.fk_soc WHERE s.entity IN (0,".$this->entity.") AND sp.statut=1 ORDER BY sp.lastname,sp.firstname LIMIT ".max(1, min(3000, (int) $limit));
        $res = $this->db->query($sql);
        if ($res) while ($obj = $this->db->fetch_object($res)) $rows[] = array('id'=>(int) $obj->rowid, 'fk_soc'=>(int) $obj->fk_soc, 'name'=>trim($obj->firstname.' '.$obj->lastname), 'email'=>(string) $obj->email);
        return $rows;
    }

    public function currencies()
    {
        $base = strtoupper((string) getDolCurrency());
        $currencies = array($base=>$base);
        $sql = "SELECT DISTINCT code FROM ".MAIN_DB_PREFIX."multicurrency WHERE entity IN (".getEntity('multicurrency').") AND code IS NOT NULL AND code<>'' ORDER BY code";
        $res = $this->db->query($sql);
        if ($res) while ($obj = $this->db->fetch_object($res)) {
            $code = strtoupper(trim((string) $obj->code));
            if (preg_match('/^[A-Z]{3}$/', $code)) $currencies[$code] = $code;
        }
        return $currencies;
    }

    public function prepareLines(array $requestRow, $socid = 0, array $selectedProducts = array(), $currency = '')
    {
        $customer = $socid > 0 ? $this->fetchCustomer($socid) : null;
        $currencyInfo = $this->currencyInfo($currency ?: getDolCurrency());
        $proposal = !empty($requestRow['fk_propal']) ? $this->fetchProposal((int) $requestRow['fk_propal'], false) : null;
        $proposalLines = $proposal ? $proposal->lines : array();
        $prepared = array();
        foreach ($this->catalogLines($requestRow) as $index=>$line) {
            $selectedId = isset($selectedProducts[$index]) ? (int) $selectedProducts[$index] : (int)($line['mapped_product_id'] ?? 0);
            $product = $this->resolveProduct($line, $selectedId);
            $line = $this->withProductPacking($line, $product);
            $defaultBase = $product ? $this->sellingPrice($product, $customer) : null;
            $saved = isset($proposalLines[$index]) ? $proposalLines[$index] : null;
            $line['product'] = $product;
            $line['default_price'] = $defaultBase === null ? null : $defaultBase * $currencyInfo['tx'];
            $line['saved_price'] = $saved ? ($currencyInfo['code'] === $currencyInfo['base'] ? (float) $saved->subprice : (float) $saved->multicurrency_subprice) : null;
            $line['saved_public_description'] = $saved ? $this->extractPublicLineDescription((string) $saved->desc) : '';
            $prepared[] = $line;
        }
        return $prepared;
    }

    public function saveDraft($requestId, $socid, $contactId, $currency, $publicNote, $privateNote, array $lineInput, $user)
    {
        $lockName = 'cpo_quote_'.$this->entity.'_'.(int) $requestId;
        $this->acquireLock($lockName);
        $createdProposal = null;
        try {
            $this->db->begin();
            $row = $this->fetchRequest((int) $requestId);
            if (!$row || $row['request_type'] !== 'catalog_order') throw new RuntimeException('quotation_request_not_found');
            if ((int)($row['schema_version'] ?? 1) === 2 && ((string)($row['qualification_status'] ?? '') !== 'qualified' || (int)($row['fk_socpeople'] ?? 0) !== (int)$contactId)) throw new RuntimeException('quotation_qualification_required');
            if (!empty($row['fk_soc']) && (int) $row['fk_soc'] !== (int) $socid) throw new RuntimeException('quotation_request_customer_mismatch');
            $customer = $this->fetchCustomer((int) $socid);
            if (!$customer) throw new RuntimeException('invalid_customer');
            $contact = $this->fetchContact((int) $contactId, (int) $socid);
            $currencyInfo = $this->currencyInfo($currency);
            $requestLines = $this->catalogLines($row);
            if (empty($requestLines) || count($requestLines) !== count($lineInput)) throw new RuntimeException('quotation_lines_invalid');

            $validated = array();
            foreach ($requestLines as $index=>$line) {
                $input = isset($lineInput[$index]) && is_array($lineInput[$index]) ? $lineInput[$index] : array();
                $product = $this->resolveProduct($line, (int) ($input['fk_product'] ?? 0));
                if (!$product) throw new RuntimeException('quotation_product_mapping_required');
                $line = $this->withProductPacking($line, $product);
                if ((int)($row['schema_version'] ?? 1) === 2 && (empty($line['packing_verified']) || (string)($line['review_status'] ?? '') !== 'ready' || (int)($line['mapped_product_id'] ?? 0) !== (int)$product->id)) throw new RuntimeException('quotation_line_review_required');
                if ($line['units_per_carton'] < 1) throw new RuntimeException('quotation_pieces_per_carton_required');
                $source = (string) ($input['price_source'] ?? 'dolibarr');
                if (!in_array($source, array('dolibarr','manual'), true)) throw new RuntimeException('quotation_price_source_invalid');
                $defaultBase = $this->sellingPrice($product, $customer);
                if ($source === 'dolibarr') {
                    if ($defaultBase === null) throw new RuntimeException('quotation_product_price_missing');
                    $priceBase = $defaultBase;
                    $priceCurrency = $priceBase * $currencyInfo['tx'];
                } else {
                    $priceCurrency = $this->positivePrice($input['manual_price'] ?? null);
                    $priceBase = $priceCurrency / $currencyInfo['tx'];
                }
                $qty = $line['cartons'] * $line['units_per_carton'];
                $validated[] = array(
                    'line'=>$line,
                    'product'=>$product,
                    'qty'=>$qty,
                    'price_base'=>$priceBase,
                    'price_currency'=>$priceCurrency,
                    'public_description'=>$this->clean($input['public_description'] ?? '', 1000),
                );
            }

            $proposal = !empty($row['fk_propal']) ? $this->fetchProposal((int) $row['fk_propal'], true) : null;
            if ($proposal) {
                if ((int) $proposal->socid !== (int) $socid) throw new RuntimeException('quotation_customer_locked');
                if (strtoupper((string) $proposal->multicurrency_code) !== $currencyInfo['code']) throw new RuntimeException('quotation_currency_locked');
            } else {
                $proposal = new Propal($this->db);
                $proposal->socid = (int) $socid;
                $proposal->date = dol_now();
                $proposal->duree_validite = 15;
                $proposal->ref_client = (string) $row['ref'];
                $proposal->note_public = $this->clean($publicNote, 10000);
                $proposal->note_private = $this->privateNote($row['ref'], $privateNote);
                $proposal->multicurrency_code = $currencyInfo['code'];
                $proposal->multicurrency_tx = $currencyInfo['tx'];
                $proposalId = $proposal->create($user);
                if ($proposalId <= 0) throw new RuntimeException($proposal->error ?: 'quotation_create_failed');
                $createdProposal = $proposal;
                $sql = "UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET fk_soc=".(int) $socid.",fk_propal=".(int) $proposalId.",fk_user_modif=".(int) $user->id.",date_modification='".$this->db->idate(dol_now())."' WHERE entity=".$this->entity." AND rowid=".(int) $requestId." AND fk_propal IS NULL";
                $res = $this->db->query($sql);
                if (!$res) { dol_syslog('M068 proposal link update failed: '.$this->db->lasterror(), LOG_ERR); throw new RuntimeException('quotation_link_update_failed'); }
                if ($this->db->affected_rows($res) !== 1) throw new RuntimeException('quotation_link_conflict');
                $proposal->fetch($proposalId);
            }

            if ((int) $proposal->status !== Propal::STATUS_DRAFT) throw new RuntimeException('quotation_not_draft');
            $proposal->note_public = $this->clean($publicNote, 10000);
            $proposal->note_private = $this->privateNote($row['ref'], $privateNote);
            if ($proposal->update($user) <= 0) throw new RuntimeException($proposal->error ?: 'quotation_note_update_failed');
            $this->syncProposalContact($proposal, $contact);
            $this->replaceProposalLines($proposal, $validated, $currencyInfo);
            $proposal->fetch($proposal->id);
            $this->db->commit();
            return $proposal;
        } catch (Throwable $e) {
            $this->db->rollback();
            if ($createdProposal && !empty($createdProposal->id)) {
                $orphan = new Propal($this->db);
                if ($orphan->fetch((int)$createdProposal->id) > 0 && !$this->requestHasProposal((int)$requestId, (int)$createdProposal->id)) $orphan->delete($user, 1);
            }
            throw $e;
        } finally {
            $this->releaseLock($lockName);
        }
    }

    public function safeSnapshotImage($value)
    {
        $url = trim((string) $value);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return '';
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || $host === '' || filter_var($host, FILTER_VALIDATE_IP)) return '';
        $allowed = array('chinaplanet.org', 'www.chinaplanet.org', 'cms.chinaplanet.org');
        $configured = function_exists('getDolGlobalString') ? (string) getDolGlobalString('CHINAPLANET_PUBLIC_PRODUCT_IMAGE_HOSTS') : '';
        foreach (explode(',', $configured) as $item) if (trim($item) !== '') $allowed[] = strtolower(trim($item));
        foreach (array_unique($allowed) as $domain) {
            if ($host === $domain || substr($host, -strlen('.'.$domain)) === '.'.$domain) return $url;
        }
        return '';
    }

    private function normalizeCatalogLine(array $line, $index)
    {
        $cartons = max(1, min(2000, (int) ($line['cartons'] ?? $line['quantity'] ?? 1)));
        $units = max(0, min(1000000, (int) ($line['unitsPerCarton'] ?? $line['units_per_carton'] ?? 0)));
        $cbm = max(0, (float) ($line['cbmPerCarton'] ?? $line['cbm_per_carton'] ?? 0));
        $weight = max(0, (float) ($line['grossWeightKg'] ?? $line['gross_weight'] ?? 0));
        return array(
            'index'=>(int) $index,
            'line_key'=>$this->clean($line['lineId'] ?? ('legacy-'.$index), 80),
            'product_id'=>max(0, (int) ($line['productId'] ?? $line['product_id'] ?? 0)),
            'variation_id'=>max(0, (int) ($line['variationId'] ?? $line['variation_id'] ?? 0)),
            'cartons'=>$cartons,
            'sku'=>$this->clean($line['sku'] ?? '', 128),
            'name'=>$this->clean($line['name'] ?? $line['title'] ?? '', 255),
            'option'=>$this->clean($line['optionLabel'] ?? $line['option_label'] ?? $line['variantLabel'] ?? '', 255),
            'image'=>$this->safeSnapshotImage($line['image'] ?? ''),
            'units_per_carton'=>$units,
            'total_units'=>$units > 0 ? $cartons * $units : 0,
            'cbm_per_carton'=>$cbm,
            'total_cbm'=>$cbm > 0 ? $cartons * $cbm : 0,
            'gross_weight'=>$weight,
            'total_gross_weight'=>$weight > 0 ? $cartons * $weight : 0,
            'comment'=>$this->clean($line['comment'] ?? '', 1000),
            'packing_verified'=>false,
            'review_status'=>'pending',
            'mapped_product_id'=>0,
        );
    }

    private function websiteLineKey(array $line)
    {
        return (int) ($line['productId'] ?? $line['product_id'] ?? 0).':'.(int) ($line['variationId'] ?? $line['variation_id'] ?? 0).':'.(int) ($line['cartons'] ?? $line['quantity'] ?? 0);
    }

    private function resolveProduct(array $line, $selectedId = 0)
    {
        if ($selectedId > 0) return $this->fetchSaleProduct($selectedId);
        $where = array();
        $join = '';
        if ($this->hasColumn(MAIN_DB_PREFIX.'product_extrafields', 'cp_woo_variation_id') && $line['variation_id'] > 0) {
            $join = " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields pe ON pe.fk_object=p.rowid";
            $where[] = "pe.cp_woo_variation_id=".(int) $line['variation_id'];
        }
        if ($this->hasColumn(MAIN_DB_PREFIX.'product_extrafields', 'cp_woo_product_id') && $line['product_id'] > 0) {
            if ($join === '') $join = " LEFT JOIN ".MAIN_DB_PREFIX."product_extrafields pe ON pe.fk_object=p.rowid";
            $where[] = "pe.cp_woo_product_id=".(int) $line['product_id']." AND (pe.cp_woo_variation_id IS NULL OR pe.cp_woo_variation_id=0)";
        }
        if ($line['sku'] !== '') $where[] = "p.ref='".$this->db->escape($line['sku'])."'";
        if (empty($where)) return null;
        $sql = "SELECT DISTINCT p.rowid FROM ".MAIN_DB_PREFIX."product p".$join." WHERE p.entity IN (".getEntity('product').") AND p.tosell=1 AND (".implode(' OR ', $where).") ORDER BY p.rowid LIMIT 2";
        $res = $this->db->query($sql);
        if (!$res || $this->db->num_rows($res) !== 1) return null;
        $obj = $this->db->fetch_object($res);
        return $this->fetchSaleProduct((int) $obj->rowid);
    }

    private function fetchSaleProduct($id)
    {
        $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE entity IN (".getEntity('product').") AND rowid=".(int) $id." AND tosell=1");
        if (!$res || !$this->db->fetch_object($res)) return null;
        $product = new Product($this->db);
        return $product->fetch((int) $id) > 0 ? $product : null;
    }

    private function withProductPacking(array $line, $product)
    {
        if ($line['units_per_carton'] > 0 || !$product || !$this->hasColumn(MAIN_DB_PREFIX.'product_extrafields', 'b2b_units_per_carton')) return $line;
        $res = $this->db->query("SELECT b2b_units_per_carton FROM ".MAIN_DB_PREFIX."product_extrafields WHERE fk_object=".(int) $product->id);
        $obj = $res ? $this->db->fetch_object($res) : null;
        $units = $obj ? max(0, min(1000000, (int) $obj->b2b_units_per_carton)) : 0;
        if ($units < 1) return $line;
        $line['units_per_carton'] = $units;
        $line['total_units'] = $line['cartons'] * $units;
        $line['packing_source'] = 'dolibarr_product';
        return $line;
    }

    private function sellingPrice($product, $customer)
    {
        $price = is_numeric($product->price) ? (float) $product->price : null;
        if (!$customer) return $price;
        $level = !empty($customer->price_level) ? (int) $customer->price_level : 0;
        if ((getDolGlobalString('PRODUIT_MULTIPRICES') || getDolGlobalString('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES')) && $level > 0 && isset($product->multiprices[$level]) && is_numeric($product->multiprices[$level])) {
            $price = (float) $product->multiprices[$level];
        }
        if (getDolGlobalString('PRODUIT_CUSTOMER_PRICES') || getDolGlobalString('PRODUIT_CUSTOMER_PRICES_AND_MULTIPRICES')) {
            require_once DOL_DOCUMENT_ROOT.'/product/class/productcustomerprice.class.php';
            $customerPrice = new ProductCustomerPrice($this->db);
            if ($customerPrice->fetchAll('', '', 0, 0, array('t.fk_product'=>(string) $product->id, 't.fk_soc'=>(string) $customer->id)) >= 0) {
                $today = (int) floor(dol_now() / 86400) * 86400;
                foreach ($customerPrice->lines as $line) {
                    if ($line->date_begin <= $today && (empty($line->date_end) || $today <= $line->date_end) && is_numeric($line->price)) {
                        $price = (float) $line->price;
                        break;
                    }
                }
            }
        }
        return $price !== null && $price >= 0 ? $price : null;
    }

    private function replaceProposalLines($proposal, array $validated, array $currencyInfo)
    {
        if (empty($proposal->thirdparty)) $proposal->fetch_thirdparty();
        $existing = (array) $proposal->lines;
        foreach ($validated as $index=>$item) {
            $line = $item['line'];
            $description = $this->lineDescription($line, $item['public_description']);
            $product = $item['product'];
            $vat = function_exists('get_default_tva') ? get_default_tva($GLOBALS['mysoc'], $proposal->thirdparty, $product->id) : '';
            if ($vat === '' || $vat === null) $vat = !empty($product->tva_tx) ? (string) $product->tva_tx : 0.0;
            $foreign = $currencyInfo['code'] === $currencyInfo['base'] ? 0 : (float) $item['price_currency'];
            if (isset($existing[$index])) {
                $current = $existing[$index];
                $result = $proposal->updateline($current->id, $item['price_base'], $item['qty'], 0, $vat, 0, 0, $description, 'HT', 0, 0, 0, 0, 0, '', '', $product->type, '', '', array(), $product->fk_unit, $foreign, 0, $index + 1);
            } else {
                $result = $proposal->addline($description, $item['price_base'], $item['qty'], $vat, 0, 0, $product->id, 0, 'HT', 0, 0, $product->type, $index + 1, 0, 0, 0, 0, '', '', '', array(), $product->fk_unit, '', 0, $foreign);
            }
            if ($result <= 0) throw new RuntimeException($proposal->error ?: 'quotation_line_save_failed');
        }
        for ($index = count($existing) - 1; $index >= count($validated); $index--) {
            if ($proposal->deleteLine($existing[$index]->id, $proposal->id) <= 0) throw new RuntimeException($proposal->error ?: 'quotation_line_delete_failed');
        }
    }

    private function syncProposalContact($proposal, $contact)
    {
        $selectedId = $contact ? (int) $contact->id : 0;
        $links = $proposal->liste_contact(-1, 'external', 0, 'CUSTOMER');
        if (!is_array($links)) throw new RuntimeException('quotation_contact_failed');
        $alreadyLinked = false;
        foreach ($links as $link) {
            if ((int) $link['id'] === $selectedId && $selectedId > 0) {
                $alreadyLinked = true;
                continue;
            }
            if ($proposal->delete_contact((int) $link['rowid']) <= 0) throw new RuntimeException('quotation_contact_failed');
        }
        if ($selectedId > 0 && !$alreadyLinked && $proposal->add_contact($selectedId, 'CUSTOMER', 'external') < 0) throw new RuntimeException('quotation_contact_failed');
    }

    private function lineDescription(array $line, $publicDescription)
    {
        $parts = array();
        if ($line['option'] !== '') $parts[] = 'Option: '.$line['option'];
        $parts[] = 'Cartons: '.$line['cartons'];
        if ($line['units_per_carton'] > 0) {
            $parts[] = 'Pieces/carton: '.$line['units_per_carton'];
            $parts[] = 'Total pieces: '.$line['total_units'];
        }
        if ($line['total_cbm'] > 0) $parts[] = 'Total CBM: '.round($line['total_cbm'], 4);
        if ($line['total_gross_weight'] > 0) $parts[] = 'Gross weight: '.round($line['total_gross_weight'], 3).' kg';
        $description = implode(' | ', $parts);
        if ($publicDescription !== '') $description .= "\n".$publicDescription;
        return $description;
    }

    private function extractPublicLineDescription($description)
    {
        $parts = explode("\n", (string) $description, 2);
        return isset($parts[1]) ? $parts[1] : '';
    }

    private function positivePrice($value)
    {
        if ($value === '' || $value === null || !is_numeric($value)) throw new RuntimeException('quotation_manual_price_required');
        $price = (float) $value;
        if ($price < 0 || $price > 1000000000) throw new RuntimeException('quotation_manual_price_invalid');
        return $price;
    }

    private function currencyInfo($currency)
    {
        $code = strtoupper(trim((string) $currency));
        $base = strtoupper((string) getDolCurrency());
        if (!preg_match('/^[A-Z]{3}$/', $code)) throw new RuntimeException('quotation_currency_invalid');
        if ($code === $base) return array('code'=>$code, 'base'=>$base, 'id'=>0, 'tx'=>1.0);
        list($id, $tx) = MultiCurrency::getIdAndTxFromCode($this->db, $code, dol_now());
        if ((int) $id <= 0 || !is_numeric($tx) || (float) $tx <= 0) throw new RuntimeException('quotation_currency_rate_missing');
        return array('code'=>$code, 'base'=>$base, 'id'=>(int) $id, 'tx'=>(float) $tx);
    }

    private function fetchRequest($id)
    {
        $res = $this->db->query("SELECT * FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND rowid=".(int) $id);
        $obj = $res ? $this->db->fetch_object($res) : null;
        if (!$obj) return null;
        $row = (array) $obj;
        $row['request'] = $this->decodeJson($row['request_json'] ?? null);
        $row['snapshot'] = $this->decodeJson($row['snapshot_json'] ?? null);
        return $row;
    }

    private function fetchCustomer($id)
    {
        if ($id <= 0) return null;
        $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (0,".$this->entity.") AND rowid=".(int) $id." AND status=1");
        if (!$res || !$this->db->fetch_object($res)) return null;
        $customer = new Societe($this->db);
        return $customer->fetch((int) $id) > 0 ? $customer : null;
    }

    private function fetchContact($id, $socid)
    {
        if ($id <= 0) return null;
        $contact = new Contact($this->db);
        if ($contact->fetch($id) <= 0 || (int) $contact->socid !== (int) $socid || empty($contact->statut)) throw new RuntimeException('invalid_customer_contact');
        return $contact;
    }

    private function fetchProposal($id, $requireDraft)
    {
        $proposal = new Propal($this->db);
        if ($proposal->fetch((int) $id) <= 0 || (int) $proposal->entity !== $this->entity) throw new RuntimeException('quotation_not_found');
        if ($requireDraft && (int) $proposal->status !== Propal::STATUS_DRAFT) throw new RuntimeException('quotation_not_draft');
        return $proposal;
    }

    private function privateNote($ref, $note)
    {
        $value = 'Website request: '.$this->clean($ref, 64);
        $note = $this->clean($note, 10000);
        return $note === '' ? $value : $value."\n".$note;
    }

    private function hasColumn($table, $column)
    {
        $key = $table.'.'.$column;
        if (!array_key_exists($key, $this->columnCache)) {
            $res = $this->db->query("SHOW COLUMNS FROM `".$table."` LIKE '".$this->db->escape($column)."'");
            $this->columnCache[$key] = $res && $this->db->num_rows($res) === 1;
        }
        return $this->columnCache[$key];
    }

    private function decodeJson($value)
    {
        $decoded = json_decode((string) $value, true);
        for ($depth = 0; $depth < 2 && is_string($decoded); $depth++) $decoded = json_decode($decoded, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function acquireLock($name)
    {
        $res = $this->db->query("SELECT GET_LOCK('".$this->db->escape($name)."',5) acquired");
        $obj = $res ? $this->db->fetch_object($res) : null;
        if (!$obj || (int) $obj->acquired !== 1) throw new RuntimeException('quotation_busy');
    }

    private function releaseLock($name)
    {
        $this->db->query("SELECT RELEASE_LOCK('".$this->db->escape($name)."')");
    }

    private function requestHasProposal($requestId, $proposalId)
    {
        $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND rowid=".(int) $requestId." AND fk_propal=".(int) $proposalId);
        return $res && $this->db->fetch_object($res);
    }

    private function clean($value, $limit)
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', trim((string) $value));
        return function_exists('mb_substr') ? mb_substr($value, 0, $limit) : substr($value, 0, $limit);
    }
}
