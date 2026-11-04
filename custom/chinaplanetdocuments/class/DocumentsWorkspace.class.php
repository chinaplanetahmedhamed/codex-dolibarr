<?php
/** Read-only facade; Docs retains draft/template ownership. */
class DocumentsWorkspace
{
    public static function canRead($user)
    {
        return !empty($user->id) && empty($user->socid)
            && (!isset($user->statut) || (int) $user->statut === 1)
            && (!empty($user->admin) || $user->hasRight('chinaplanetdocuments', 'workspace_read'));
    }

    /** Configuration only, never supplied by request. No tokens/query/fragment. */
    public static function editorUrl($url)
    {
        if (!is_string($url) || $url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return '';
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) return '';
        $scheme = strtolower($parts['scheme'] ?? '');
        $local = in_array(strtolower($parts['host']), array('127.0.0.1', 'localhost', '[::1]'), true);
        if ($scheme !== 'https' && !($scheme === 'http' && $local)) return '';
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    /** Call native object visibility checks before passing fetched objects. */
    public static function context($entity, $customer, $project, $shipment = null)
    {
        if ((int) $entity <= 0) throw new InvalidArgumentException('Invalid entity');
        foreach (array($customer, $project, $shipment) as $record) {
            if ($record !== null && (empty($record->id) || (int) $record->entity !== (int) $entity)) {
                throw new InvalidArgumentException('Context unavailable');
            }
        }
        if ($project !== null && ($customer === null || (int) $project->socid !== (int) $customer->id)) {
            throw new InvalidArgumentException('Context unavailable');
        }
        if ($shipment !== null && ($customer === null || (int) $shipment->fk_soc !== (int) $customer->id
            || (int) $shipment->fk_project !== (int) ($project->id ?? 0))) {
            throw new InvalidArgumentException('Context unavailable');
        }
        return array('customer' => $customer, 'project' => $project, 'shipment' => $shipment);
    }

    /** Generate only canonical links from validated records; never accept a URL. */
    public static function returnTarget(array $context, $root, $target = 'workspace')
    {
        $paths = array('customer'=>'/societe/card.php', 'project'=>'/projet/card.php', 'shipment'=>'/custom/chinaplanetlogistics/card.php');
        if ($target !== 'workspace' && !isset($paths[$target])) throw new InvalidArgumentException('Invalid return target');
        if (isset($paths[$target]) && !empty($context[$target]->id)) return $root.$paths[$target].'?id='.(int) $context[$target]->id;
        $params = array();
        foreach (array('customer'=>'socid', 'project'=>'projectid', 'shipment'=>'shipmentid') as $kind=>$key) {
            if (!empty($context[$kind]->id)) $params[$key] = (int) $context[$kind]->id;
        }
        return $root.'/custom/chinaplanetdocuments/index.php'.($params ? '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
    }

    /** Read projections only; caller supplies native authorized project IDs. */
    public static function projectChoices($db, $entity, $customer, array $authorizedIds)
    {
        self::context($entity, $customer, null);
        if (!$customer) return array();
        $ids = array_filter(array_map('intval', $authorizedIds), function ($id) { return $id > 0; });
        if (!$ids) return array();
        $sql = 'SELECT rowid AS id, title, ref FROM '.MAIN_DB_PREFIX.'projet WHERE entity='.(int) $entity;
        $sql .= ' AND fk_soc='.(int) $customer->id.' AND rowid IN ('.implode(',', $ids).') ORDER BY title, rowid LIMIT 100';
        return self::rows($db, $sql);
    }

    public static function shipmentChoices($db, $entity, $customer, $project, array $authorizedIds = array())
    {
        self::context($entity, $customer, $project);
        if (!$customer) return array();
        $sql = 'SELECT rowid AS id, ref, container_no, vessel_name FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.(int) $entity.' AND fk_soc='.(int) $customer->id;
        if ($project) $sql .= ' AND fk_project='.(int) $project->id;
        else {
            $ids = array_filter(array_map('intval', $authorizedIds), function ($id) { return $id > 0; });
            $sql .= ' AND (fk_project IS NULL OR fk_project=0'.($ids ? ' OR fk_project IN ('.implode(',', $ids).')' : '').')';
        }
        return self::rows($db, $sql.' ORDER BY rowid DESC LIMIT 100');
    }

    private static function rows($db, $sql)
    {
        $result = $db->query($sql);
        if (!$result) throw new RuntimeException('Document choices unavailable');
        $rows = array();
        while ($row = $db->fetch_object($result)) $rows[] = $row;
        return $rows;
    }

    public static function templates()
    {
        return array('CommercialInvoice', 'ProformaInvoice', 'PackingList', 'BillOfLading',
            'CertificateOfOrigin', 'ShippingMarks', 'Quotation', 'BookingForm');
    }

    /** Minimal selection hints for an explicitly enabled same-origin adapter. */
    public static function handoffPayload($entity, array $context, $root, $returnto)
    {
        self::context($entity, $context['customer'], $context['project'], $context['shipment'] ?? null);
        self::returnTarget($context, $root, $returnto);
        if (!preg_match('~^(?:/[A-Za-z0-9_-]+)*$~', $root)) throw new InvalidArgumentException('Invalid adapter root');
        if (empty($context['customer'])) return null;
        return array('version'=>1, 'entity'=>(int) $entity, 'customerId'=>(int) $context['customer']->id,
            'projectId'=>(int) ($context['project']->id ?? 0), 'shipmentId'=>(int) ($context['shipment']->id ?? 0),
            'shipmentRef'=>(string) ($context['shipment']->ref ?? ''), 'root'=>$root, 'returnto'=>$returnto);
    }
}
