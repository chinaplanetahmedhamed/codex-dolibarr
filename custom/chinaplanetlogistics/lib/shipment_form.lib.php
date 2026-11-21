<?php

/**
 * Apply the same customer scope as the native third-party selector.
 */
function cpl_shipment_form_customer_allowed($db, $user, int $entity, int $customer): bool
{
    if ($customer <= 0) {
        return false;
    }

    $sql = 'SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s'
        .' WHERE s.rowid='.$customer.' AND s.entity='.$entity.' AND s.status=1 AND s.client IN (1,3)';
    if (!$user->hasRight('societe', 'client', 'voir')) {
        $sql .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_commerciaux sc'
            .' WHERE sc.fk_soc=s.rowid AND sc.fk_user='.(int) $user->id.')';
    }

    $result = $db->query($sql);
    return $result && $db->num_rows($result) === 1;
}

/**
 * Reuse Dolibarr project visibility with a mandatory customer and entity filter.
 * The native company filter alone can include shared projects or other companies.
 */
function cpl_shipment_form_projects($db, $user, int $entity, int $customer): array
{
    if ($customer <= 0) {
        return array();
    }

    $sql = 'SELECT p.rowid,p.ref,p.title,p.fk_statut FROM '.MAIN_DB_PREFIX.'projet p'
        .' WHERE p.entity='.$entity.' AND p.fk_soc='.$customer;
    if (!$user->hasRight('projet', 'all', 'lire')) {
        require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
        $project = new Project($db);
        $visible = $project->getProjectsAuthorizedForUser($user, 0, 0, $customer);
        if (!$visible) {
            return array();
        }
        $sql .= ' AND p.rowid IN ('.implode(',', array_map('intval', array_keys($visible))).')';
    }
    if (getDolGlobalInt('PROJECT_ALWAYS_DISCARD_CLOSED_PROJECTS_IN_SELECT')) {
        $sql .= ' AND p.fk_statut <> 2';
    }

    $result = $db->query($sql.' ORDER BY p.ref,p.rowid');
    if (!$result) {
        throw new RuntimeException('Shipment project lookup failed.');
    }

    $options = array();
    while ($row = $db->fetch_object($result)) {
        $draft = (int) $row->fk_statut === 0;
        if ($draft && getDolGlobalString('PROJECT_HIDE_UNSELECTABLES')) {
            continue;
        }
        $options[] = array(
            'id' => (int) $row->rowid,
            'label' => $row->ref.' · '.$row->title,
            'disabled' => $draft,
        );
    }
    $db->free($result);
    return $options;
}

function cpl_shipment_container_types(): array
{
    return array('20GP', '40GP', '40HQ', '45HQ', 'LCL', 'Other');
}

/**
 * Keep the historic type for homogeneous shipments. Mixed shipments use a
 * readable breakdown in the existing field, which is also used by the portal.
 */
function cpl_shipment_container_mix($types, $quantities, string $legacyType = ''): ?array
{
    $allowed = cpl_shipment_container_types();
    if (!is_array($types) || !is_array($quantities) || !$types || count($types) > count($allowed)
        || array_keys($types) !== array_keys($quantities)) {
        return null;
    }

    $counts = array();
    foreach ($types as $key => $type) {
        if (!is_string($type)
            || (!in_array($type, $allowed, true) && !(count($types) === 1 && ($type === '' || $type === $legacyType)))) {
            return null;
        }
        $quantity = $quantities[$key];
        if (!is_scalar($quantity) || !preg_match('/^[1-9][0-9]{0,3}$/D', (string) $quantity)) {
            return null;
        }
        $counts[$type] = ($counts[$type] ?? 0) + (int) $quantity;
    }

    if (max($counts) > 9999) {
        return null;
    }
    if (count($counts) === 1) {
        return array('type' => (string) array_key_first($counts), 'total' => array_sum($counts));
    }

    $parts = array();
    foreach ($counts as $type => $quantity) {
        $parts[] = $quantity.'x'.$type;
    }
    return array('type' => implode(' + ', $parts), 'total' => array_sum($counts));
}

function cpl_shipment_container_rows(string $storedType, int $storedQuantity): array
{
    if (strpos($storedType, ' + ') !== false) {
        $rows = array();
        foreach (explode(' + ', $storedType) as $part) {
            if (!preg_match('/^([1-9][0-9]{0,3})x(20GP|40GP|40HQ|45HQ|LCL|Other)$/D', $part, $matches)) {
                break;
            }
            $rows[] = array($matches[2], (int) $matches[1]);
        }
        $mix = cpl_shipment_container_mix(array_column($rows, 0), array_column($rows, 1));
        if ($mix && $mix['type'] === $storedType && $mix['total'] === $storedQuantity) {
            return $rows;
        }
    }

    return array(array($storedType, max(1, $storedQuantity)));
}

function cpl_shipment_container_mix_fits($db, string $type): bool
{
    $result = $db->query("SELECT CHARACTER_MAXIMUM_LENGTH AS max_length FROM information_schema.COLUMNS"
        ." WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".MAIN_DB_PREFIX."chinaplanetlogistics' AND COLUMN_NAME='container_type'");
    $column = $result ? $db->fetch_object($result) : null;
    return $column && (int) $column->max_length >= strlen($type);
}
