<?php
/**
 * Library for China Planet Kanban
 */

/**
 * Resolve the external-user context exactly like the logistics module does.
 * Returns array('is_external'=>bool, 'socid'=>int).
 * Internal users / admins => is_external=false, socid=0 (see whole board).
 */
function cpkanban_user_context($user)
{
    $socid = (int) $user->socid;
    $is_external = ($socid > 0);
    // Admins are always internal even if a socid is somehow set
    if (!empty($user->admin)) {
        $is_external = false;
        $socid = 0;
    }
    return array('is_external' => $is_external, 'socid' => $socid);
}

/**
 * Priority label (Arabic) + css class.
 */
function cpkanban_priority($p)
{
    $map = array(
        'high' => array('label' => 'أولوية عالية', 'cls' => 'pri-high'),
        'med'  => array('label' => 'متوسطة',       'cls' => 'pri-med'),
        'low'  => array('label' => 'منخفضة',       'cls' => 'pri-low'),
    );
    return isset($map[$p]) ? $map[$p] : $map['med'];
}

/**
 * Shipment status label (mirror of logistics module track.php).
 */
function cpkanban_shipment_status($s)
{
    $labels = array(
        0 => 'استعلام', 1 => 'تأكيد الحجز', 2 => 'على متن السفينة', 3 => 'وصلت',
        4 => 'تخليص جمركي', 5 => 'تم التخليص', 6 => 'تم التسليم', 7 => 'مغلقة',
    );
    return isset($labels[$s]) ? $labels[$s] : '-';
}

/**
 * Days between today and a date (timestamp or 'Y-m-d'). Null if empty.
 */
function cpkanban_days_until($date)
{
    if (empty($date) || $date === '0000-00-00') return null;
    $ts = is_numeric($date) ? $date : strtotime($date);
    return (int) ceil(($ts - time()) / 86400);
}

/**
 * Color for an ETA / free-time countdown (mirror of logistics palette).
 */
function cpkanban_countdown_color($days)
{
    if ($days === null) return '#95a5a6';
    if ($days < 0)  return '#1a1a1a';
    if ($days < 3)  return '#c0392b';
    if ($days < 7)  return '#e67e22';
    if ($days < 10) return '#f39c12';
    if ($days < 14) return '#27ae60';
    return '#2ecc71';
}

/**
 * Lightweight customer center statistics for the portal landing page.
 * All queries are scoped by fk_soc = $socid and fail closed to zero.
 */
function cpkanban_customer_center_stats($db, $socid)
{
    $socid = (int) $socid;
    $stats = array(
        'projects' => 0,
        'shipments' => 0,
        'invoices_open' => 0,
        'invoices_total' => 0,
        'orders' => 0,
        'dol_projects' => 0,
    );
    if ($socid <= 0) return $stats;

    $queries = array(
        'projects' => "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."cpkanban_card WHERE fk_soc = ".$socid." AND cust_visible = 1 AND status <> 0",
        'shipments' => "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE fk_soc = ".$socid." AND fk_statut NOT IN (6,7)",
        'invoices_open' => "SELECT COUNT(*) as nb, SUM(total_ttc) as total FROM ".MAIN_DB_PREFIX."facture WHERE fk_soc = ".$socid." AND fk_statut > 0 AND paye = 0",
        'orders' => "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."commande WHERE fk_soc = ".$socid." AND fk_statut > 0",
        'dol_projects' => "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."projet WHERE fk_soc = ".$socid." AND fk_statut = 1",
    );

    foreach ($queries as $key => $sql) {
        $resql = $db->query($sql);
        if (!$resql) continue;
        $obj = $db->fetch_object($resql);
        if (!$obj) continue;
        if ($key == 'invoices_open') {
            $stats['invoices_open'] = (int) $obj->nb;
            $stats['invoices_total'] = (float) $obj->total;
        } else {
            $stats[$key] = (int) $obj->nb;
        }
    }
    return $stats;
}

/**
 * Customer card folding: cards in hidden internal columns should not disappear.
 * Rules: supp/factory-follow-up folds into prog for the customer view. hold/cancelled stays hidden.
 * Other hidden columns fall back to the nearest previous visible column, then first visible column.
 */
function cpkanban_fold_customer_board($board, $visibleColumns, $allColumns)
{
    if (empty($board) || empty($visibleColumns)) return $board;

    $visibleIds = array();
    foreach ($visibleColumns as $col) $visibleIds[(int) $col->rowid] = true;

    $codeToId = array();
    foreach ($allColumns as $col) $codeToId[$col->code] = (int) $col->rowid;
    $firstVisible = 0; foreach ($visibleColumns as $vc) { $firstVisible = (int) $vc->rowid; break; }
    $fallbackProg = !empty($codeToId['prog']) && !empty($visibleIds[$codeToId['prog']]) ? $codeToId['prog'] : $firstVisible;

    $folded = array();
    foreach ($visibleColumns as $col) $folded[(int) $col->rowid] = isset($board[(int)$col->rowid]) ? $board[(int)$col->rowid] : array();

    foreach ($board as $colId => $cards) {
        $colId = (int) $colId;
        if (isset($visibleIds[$colId])) continue;
        $code = isset($allColumns[$colId]) ? $allColumns[$colId]->code : '';
        if ($code === 'hold') {
            continue; // deliberately hidden from customers
        }
        $target = ($code === 'supp') ? $fallbackProg : cpkanban_nearest_visible_column($colId, $visibleColumns, $allColumns, $fallbackProg);
        foreach ($cards as $card) {
            $card->_cpk_folded_from = $colId;
            $card->_cpk_folded_from_label = isset($allColumns[$colId]) ? $allColumns[$colId]->label : '';
            $folded[$target][] = $card;
        }
    }
    return $folded;
}

function cpkanban_nearest_visible_column($hiddenColId, $visibleColumns, $allColumns, $fallback)
{
    $hiddenPos = isset($allColumns[$hiddenColId]) ? (int) $allColumns[$hiddenColId]->position : 0;
    $bestId = $fallback;
    $bestPos = -999999;
    foreach ($visibleColumns as $col) {
        $pos = (int) $col->position;
        if ($pos <= $hiddenPos && $pos > $bestPos) {
            $bestPos = $pos;
            $bestId = (int) $col->rowid;
        }
    }
    return $bestId;
}

function cpkanban_money($amount)
{
    return number_format((float) $amount, 2);
}

function cpkanban_customer_url($path)
{
    return dol_buildpath($path, 1);
}
