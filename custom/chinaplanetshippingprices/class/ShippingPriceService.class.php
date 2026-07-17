<?php

class ShippingPriceService
{
    private $db;
    private $entity;

    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int) $GLOBALS['conf']->entity : (int) $entity;
    }

    public function getFilterOptions($publicOnly = true)
    {
        global $langs;
        $out = array('pol' => array(), 'countries' => array(), 'pod' => array(), 'pod_by_country' => array(), 'lines' => array(), 'containers' => array());
        $sql = "SELECT DISTINCT r.pol,r.destination_country,r.pod,r.shipping_line,r.container_type,c.label country_label";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate r";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_country c ON c.code=r.destination_country";
        $sql .= " WHERE r.entity=".$this->entity." AND r.status=1 AND r.is_current=1 AND r.cost_price>=".price2num($this->ignoreBelow());
        if ($publicOnly) $sql .= " AND r.public_visible=1";
        $sql .= " ORDER BY r.destination_country,r.pod,r.shipping_line,r.container_type";
        $res = $this->db->query($sql);
        if (!$res) return $out;
        while ($obj = $this->db->fetch_object($res)) {
            if ($obj->pol !== '') $out['pol'][$obj->pol] = $obj->pol;
            if (!empty($obj->destination_country)) {
                $translated = $langs->transnoentitiesnoconv('Country'.$obj->destination_country);
                $countryName = $translated !== 'Country'.$obj->destination_country ? $translated : ($obj->country_label ?: $obj->destination_country);
                $out['countries'][$obj->destination_country] = dol_ucwords($countryName);
                if (!isset($out['pod_by_country'][$obj->destination_country])) $out['pod_by_country'][$obj->destination_country] = array();
                if ($obj->pod !== '') $out['pod_by_country'][$obj->destination_country][$obj->pod] = $obj->pod;
            }
            if ($obj->pod !== '') $out['pod'][$obj->pod] = $obj->pod;
            if ($obj->shipping_line !== '') $out['lines'][$obj->shipping_line] = $obj->shipping_line;
            if ($obj->container_type !== '') $out['containers'][$obj->container_type] = $obj->container_type;
        }
        foreach (array('pol','countries','pod','lines','containers') as $key) natcasesort($out[$key]);
        foreach ($out['pod_by_country'] as &$ports) natcasesort($ports);
        return $out;
    }

    public function search(array $filters, $includeCost = false, $limit = 100)
    {
        $filters = $this->normalizeFilters($filters);
        $sql = "SELECT r.*,b.ref batch_ref,b.source_name,b.effective_date";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate r";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."chinaplanetsp_batch b ON b.rowid=r.fk_batch AND b.entity=r.entity AND b.status=2";
        $sql .= " WHERE r.entity=".$this->entity." AND r.status=1 AND r.is_current=1 AND r.cost_price>=".price2num($this->ignoreBelow());
        $sql .= $this->enabledRateSql('r');
        if (!$includeCost) $sql .= " AND r.public_visible=1";
        foreach (array('pol', 'pod', 'shipping_line', 'container_type', 'service_type') as $field) {
            if ($filters[$field] !== '') $sql .= " AND r.".$field."='".$this->db->escape($filters[$field])."'";
        }
        if ($filters['country_code'] !== '') $sql .= " AND r.destination_country='".$this->db->escape($filters['country_code'])."'";
        $sql .= " ORDER BY (r.pol='UNKNOWN') ASC,(r.shipping_line='UNKNOWN') ASC,r.sale_price ASC,r.shipping_line ASC LIMIT ".max(1, min(250, (int) $limit));
        $res = $this->db->query($sql);
        if (!$res) return array('filters' => $filters, 'results' => array(), 'error' => $this->db->lasterror());

        $rules = $this->loadActiveRules();
        $results = array();
        while ($row = $this->db->fetch_object($res)) {
            $result = $this->priceRate($row, $filters, $rules, $includeCost);
            $results[] = $result;
        }
        return array('filters' => $filters, 'results' => $results, 'error' => '');
    }

    public function getRate($rateId)
    {
        $sql = "SELECT r.*,b.ref batch_ref,b.source_name,b.effective_date FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate r";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."chinaplanetsp_batch b ON b.rowid=r.fk_batch AND b.entity=r.entity";
        $sql .= " WHERE r.entity=".$this->entity." AND r.rowid=".(int) $rateId;
        $res = $this->db->query($sql);
        return $res ? $this->db->fetch_object($res) : null;
    }

    public function history(array $route, $months = 12, $publicOnly = true)
    {
        $months = max(1, min(60, (int) $months));
        $sql = "SELECT r.rowid,r.valid_from,r.cost_price,r.sale_price,r.currency_code,r.shipping_line,r.service_type,b.ref batch_ref";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate r";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."chinaplanetsp_batch b ON b.rowid=r.fk_batch AND b.status=2";
        $sql .= " WHERE r.entity=".$this->entity." AND r.status=1 AND r.cost_price>=".price2num($this->ignoreBelow());
        $sql .= " AND r.valid_from >= DATE_SUB(CURDATE(), INTERVAL ".$months." MONTH)";
        foreach (array('pol', 'pod', 'shipping_line', 'container_type', 'service_type') as $field) {
            if (!empty($route[$field])) $sql .= " AND r.".$field."='".$this->db->escape($route[$field])."'";
        }
        if (!empty($route['country_code'])) $sql .= " AND r.destination_country='".$this->db->escape($route['country_code'])."'";
        if ($publicOnly) $sql .= " AND r.public_visible=1";
        $sql .= " ORDER BY r.valid_from ASC,r.rowid ASC";
        $res = $this->db->query($sql);
        $points = array();
        if ($res) while ($obj = $this->db->fetch_object($res)) {
            $points[] = array(
                'date' => $obj->valid_from,
                'sale_price' => (float) $obj->sale_price,
                'cost_price' => (float) $obj->cost_price,
                'currency' => $obj->currency_code,
                'line' => $obj->shipping_line,
                'service' => $obj->service_type,
                'batch' => $obj->batch_ref,
            );
        }
        return $points;
    }

    public function marketHistory(array $route, $months = 12, $publicOnly = true)
    {
        $months = max(1, min(60, (int) $months));
        $sql = "SELECT r.valid_from,AVG(r.cost_price) cost_price,AVG(r.sale_price) sale_price,MIN(r.sale_price) low_price,MAX(r.sale_price) high_price,COUNT(*) offers,MAX(r.currency_code) currency_code";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate r INNER JOIN ".MAIN_DB_PREFIX."chinaplanetsp_batch b ON b.rowid=r.fk_batch AND b.status=2";
        $sql .= " WHERE r.entity=".$this->entity." AND r.status=1 AND r.cost_price>=".price2num($this->ignoreBelow())." AND r.valid_from>=DATE_SUB(CURDATE(),INTERVAL ".$months." MONTH)";
        foreach (array('pod','container_type','service_type') as $field) if (!empty($route[$field])) $sql .= " AND r.".$field."='".$this->db->escape($route[$field])."'";
        if (!empty($route['country_code'])) $sql .= " AND r.destination_country='".$this->db->escape($route['country_code'])."'";
        if ($publicOnly) $sql .= " AND r.public_visible=1";
        $sql .= " GROUP BY r.valid_from ORDER BY r.valid_from";
        $res=$this->db->query($sql);$points=array();
        if($res)while($obj=$this->db->fetch_object($res))$points[]=array('date'=>$obj->valid_from,'sale_price'=>(float)$obj->sale_price,'cost_price'=>(float)$obj->cost_price,'low_price'=>(float)$obj->low_price,'high_price'=>(float)$obj->high_price,'offers'=>(int)$obj->offers,'currency'=>$obj->currency_code,'line'=>'Market average','service'=>$route['service_type']??'','batch'=>'');
        return $points;
    }

    /** Historical market averages grouped for the interactive comparison chart. */
    public function comparisonHistory(array $filters, $months = 24, $publicOnly = true)
    {
        $filters = $this->normalizeFilters($filters);
        $months = max(1, min(60, (int) $months));
        $sql = "SELECT r.valid_from,r.destination_country,c.label country_name,r.pod,r.container_type,r.shipping_line,r.region_label,AVG(r.sale_price) sale_price,COUNT(*) offers,r.currency_code";
        $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate r";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."chinaplanetsp_batch b ON b.rowid=r.fk_batch AND b.entity=r.entity AND b.status=2";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_country c ON c.code=r.destination_country";
        $sql .= " WHERE r.entity=".$this->entity." AND r.status=1 AND r.cost_price>=".price2num($this->ignoreBelow());
        $sql .= " AND r.valid_from>=DATE_SUB(CURDATE(),INTERVAL ".$months." MONTH)";
        foreach (array('pol','pod','shipping_line','container_type','service_type') as $field) {
            if ($filters[$field] !== '') $sql .= " AND r.".$field."='".$this->db->escape($filters[$field])."'";
        }
        if ($filters['country_code'] !== '') $sql .= " AND r.destination_country='".$this->db->escape($filters['country_code'])."'";
        if ($publicOnly) $sql .= " AND r.public_visible=1";
        $sql .= " GROUP BY r.valid_from,r.destination_country,c.label,r.pod,r.container_type,r.shipping_line,r.region_label,r.currency_code";
        $sql .= " ORDER BY r.valid_from,r.pod,r.container_type,r.shipping_line";
        $res = $this->db->query($sql);
        $points = array();
        if ($res) while ($obj = $this->db->fetch_object($res)) {
            $points[] = array('date'=>$obj->valid_from,'country_code'=>$obj->destination_country,'country'=>$obj->country_name?:$obj->destination_country,'pod'=>$obj->pod,'container'=>$obj->container_type,'line'=>$obj->shipping_line,'lane'=>$obj->region_label,'sale_price'=>round((float)$obj->sale_price,2),'offers'=>(int)$obj->offers,'currency'=>$obj->currency_code);
        }
        return $points;
    }

    public function saveInquiry($user, $socid, array $request, array $pricedResult, $channel = 'dolibarr')
    {
        if (empty($pricedResult['rate_id'])) return -1;
        $ref = 'SPI-'.dol_print_date(dol_now(), '%Y%m%d%H%M%S').'-'.substr(bin2hex(random_bytes(3)), 0, 6);
        $requestJson = json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resultJson = json_encode($pricedResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."chinaplanetsp_inquiry(entity,ref,fk_soc,channel,request_json,result_json,currency_code,subtotal,additions,total_amount,status,fk_user_creat,date_creation) VALUES(";
        $sql .= $this->entity.",'".$this->db->escape($ref)."',".($socid > 0 ? (int) $socid : 'NULL').",'".$this->db->escape($channel)."','".$this->db->escape($requestJson)."','".$this->db->escape($resultJson)."','".$this->db->escape($pricedResult['currency'])."',";
        $sql .= price2num($pricedResult['base_sale_price']).",".price2num($pricedResult['additions_total']).",".price2num($pricedResult['final_price']).",1,".(int) $user->id.",'".$this->db->idate(dol_now())."')";
        if (!$this->db->query($sql)) return -1;
        return array('id' => $this->db->last_insert_id(MAIN_DB_PREFIX.'chinaplanetsp_inquiry'), 'ref' => $ref);
    }

    private function normalizeFilters(array $filters)
    {
        $out = array(
            'pol' => trim((string) ($filters['pol'] ?? '')),
            'country_code' => strtoupper(trim((string) ($filters['country_code'] ?? ''))),
            'pod' => trim((string) ($filters['pod'] ?? '')),
            'shipping_line' => trim((string) ($filters['shipping_line'] ?? '')),
            'container_type' => strtoupper(trim((string) ($filters['container_type'] ?? ''))),
            'service_type' => strtolower(trim((string) ($filters['service_type'] ?? ''))),
            'incoterm' => strtoupper(trim((string) ($filters['incoterm'] ?? 'FOB'))),
            'cargo_type' => strtolower(trim((string) ($filters['cargo_type'] ?? 'normal'))),
            'documents' => array(),
        );
        if (!in_array($out['service_type'], array('', 'direct', 'transit', 'unknown'), true)) $out['service_type'] = '';
        if (!in_array($out['incoterm'], array('FOB', 'EXW'), true)) $out['incoterm'] = 'FOB';
        $documents = $filters['documents'] ?? array();
        if (!is_array($documents)) $documents = array($documents);
        foreach ($documents as $doc) {
            $doc = strtolower(trim((string) $doc));
            if (preg_match('/^[a-z0-9_-]{1,32}$/', $doc)) $out['documents'][] = $doc;
        }
        return $out;
    }

    private function ignoreBelow()
    {
        return max(0, (float) ($GLOBALS['conf']->global->CHINAPLANETSP_IGNORE_BELOW ?? 1000));
    }

    /** Exclude temporarily blocked commercial scopes from live inquiry only. History deliberately does not use this clause. */
    private function enabledRateSql($rateAlias)
    {
        $b = 'cpblock';
        return " AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."chinaplanetsp_block ".$b.
            " WHERE ".$b.".entity=".$rateAlias.".entity AND ".$b.".status=1".
            " AND (".$b.".date_end IS NULL OR ".$b.".date_end>=CURDATE())".
            " AND (".$b.".country_code IS NULL OR ".$b.".country_code='' OR ".$b.".country_code=".$rateAlias.".destination_country)".
            " AND (".$b.".pod IS NULL OR ".$b.".pod='' OR ".$b.".pod=".$rateAlias.".pod)".
            " AND (".$b.".pol IS NULL OR ".$b.".pol='' OR ".$b.".pol=".$rateAlias.".pol)".
            " AND (".$b.".shipping_line IS NULL OR ".$b.".shipping_line='' OR ".$b.".shipping_line=".$rateAlias.".shipping_line)".
            " AND (".$b.".container_type IS NULL OR ".$b.".container_type='' OR ".$b.".container_type=".$rateAlias.".container_type)".
            " AND (".$b.".service_type IS NULL OR ".$b.".service_type='' OR ".$b.".service_type=".$rateAlias.".service_type))";
    }

    private function loadActiveRules()
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX."chinaplanetsp_rule WHERE entity=".$this->entity." AND status=1";
        $sql .= " AND (date_start IS NULL OR date_start<=CURDATE()) AND (date_end IS NULL OR date_end>=CURDATE())";
        // Exact-price overrides run after ordinary additions so the requested final price stays exact.
        $sql .= " ORDER BY (calc_type='override') ASC,priority DESC,rowid ASC";
        $res = $this->db->query($sql);
        $rules = array();
        if ($res) while ($obj = $this->db->fetch_object($res)) $rules[] = $obj;
        return $rules;
    }

    private function priceRate($row, array $filters, array $rules, $includeCost)
    {
        $base = (float) $row->sale_price;
        $running = $base;
        $components = array();
        $exclusive = array();
        $matchedTypes = array();
        foreach ($rules as $rule) {
            if (!$this->ruleMatches($rule, $row, $filters)) continue;
            $componentKey = $rule->component_type;
            if (empty($rule->stackable) && isset($exclusive[$componentKey])) continue;
            if ($rule->calc_type === 'override') {
                $amount = (float) $rule->amount - $running;
                $running = (float) $rule->amount;
            } else {
                $amount = $rule->calc_type === 'percent' ? $running * ((float) $rule->amount / 100) : (float) $rule->amount;
                $running += $amount;
            }
            $components[] = array(
                'rule_id' => (int) $rule->rowid,
                'type' => $rule->component_type,
                'label' => $rule->public_label !== '' ? $rule->public_label : $rule->label,
                'amount' => round($amount, 2),
                'currency' => $row->currency_code,
            );
            $matchedTypes[$rule->component_type] = true;
            if (empty($rule->stackable)) $exclusive[$componentKey] = true;
        }

        $warnings = array();
        $needsReview = false;
        if ($filters['incoterm'] === 'EXW' && empty($matchedTypes['origin'])) {
            $warnings[] = 'EXW origin/inland cost is not configured for this route.';
            $needsReview = true;
        }
        if ($filters['cargo_type'] !== 'normal' && empty($matchedTypes['cargo'])) {
            $warnings[] = 'This cargo category requires manual carrier review.';
            $needsReview = true;
        }
        if ($row->service_type === 'unknown') $warnings[] = 'Direct/transit service was not identified in the source file.';

        $result = array(
            'rate_id' => (int) $row->rowid,
            'batch_ref' => $row->batch_ref,
            'effective_date' => $row->effective_date,
            'pol' => $row->pol,
            'country_code' => $row->destination_country,
            'pod' => $row->pod,
            'shipping_line' => $row->shipping_line,
            'trade_lane' => in_array($row->region_label, array('Red Sea','Middle East','North Africa','Mediterranean'), true) ? $row->region_label : '',
            'service_type' => $row->service_type,
            'route_detail' => $row->route_detail,
            'container_type' => $row->container_type,
            'incoterm' => $filters['incoterm'],
            'cargo_type' => $filters['cargo_type'],
            'currency' => $row->currency_code,
            'base_sale_price' => round($base, 2),
            'components' => $components,
            'additions_total' => round($running - $base, 2),
            'final_price' => round($running, 2),
            'notes' => $row->public_notes,
            'warnings' => $warnings,
            'needs_manual_review' => $needsReview,
        );
        if ($includeCost) {
            $result['supplier_cost'] = round((float) $row->cost_price, 2);
            $result['base_margin'] = round($base - (float) $row->cost_price, 2);
            $result['internal_notes'] = $row->internal_notes;
            $result['source'] = $row->source_name;
            $result['source_sheet'] = $row->source_sheet;
            $result['source_row'] = (int) $row->source_row;
        }
        return $result;
    }

    private function ruleMatches($rule, $row, array $filters)
    {
        $map = array(
            'container_type' => $row->container_type,
            'country_code' => $row->destination_country,
            'pod' => $row->pod,
            'pol' => $row->pol,
            'shipping_line' => $row->shipping_line,
            'service_type' => $row->service_type,
            'incoterm' => $filters['incoterm'],
            'cargo_type' => $filters['cargo_type'],
        );
        foreach ($map as $field => $actual) {
            $expected = trim((string) $rule->$field);
            if ($expected !== '' && strcasecmp($expected, (string) $actual) !== 0) return false;
        }
        $doc = strtolower(trim((string) $rule->document_code));
        if ($doc !== '' && !in_array($doc, $filters['documents'], true)) return false;
        return true;
    }
}
