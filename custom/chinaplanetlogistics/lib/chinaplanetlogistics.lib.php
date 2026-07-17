<?php
/**
 * Library for China Planet Logistics
 */

/**
 * Prepare tabs for logistics object
 * @param ChinaPlanetLogistics $object Logistics object
 * @return array Tabs
 */
function chinaplanetlogistics_prepare_head($object)
{
    global $db, $langs, $conf;
    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/chinaplanetlogistics/card.php", 1).'?id='.$object->id;
    $head[$h][1] = "Card";
    $head[$h][2] = 'card';
    $h++;

    return $head;
}

/**
 * Return the canonical shipment statuses in business display order.
 *
 * Stored values are intentionally not sequential: values 8 and 9 were added
 * without renumbering the existing 0..7 records.
 *
 * @return array<int,string>
 */
function chinaplanetlogistics_status_map()
{
    return array(
        0 => 'Inquiry',
        1 => 'Booking Confirmed',
        8 => 'Loaded & Going to Export Customs',
        9 => 'Export Customs Cleared',
        2 => 'On Vessel',
        3 => 'Arrived Port',
        4 => 'Under Customs',
        5 => 'Customs Cleared',
        6 => 'Delivered',
        7 => 'Closed',
    );
}

/**
 * Return the canonical lifecycle groups used by shipment filters and counts.
 *
 * @return array<string,array<int,int>>
 */
function chinaplanetlogistics_status_sets()
{
    return array(
        'active' => array(0, 1, 8, 9, 2, 3, 4, 5),
        'pre_sailing' => array(0, 1, 8, 9),
        'in_transit' => array(2),
        'arrived' => array(3, 4, 5),
        'terminal' => array(6, 7),
    );
}

/**
 * Return one lifecycle group.
 *
 * @param string $set Lifecycle group key
 * @return array<int,int>
 */
function chinaplanetlogistics_status_values($set)
{
    $sets = chinaplanetlogistics_status_sets();
    return isset($sets[$set]) ? $sets[$set] : array();
}

/**
 * Return a safe integer list for an SQL IN clause.
 *
 * @param string $set Lifecycle group key
 * @return string
 */
function chinaplanetlogistics_status_sql_list($set)
{
    return implode(',', array_map('intval', chinaplanetlogistics_status_values($set)));
}

/**
 * Test whether a stored status belongs to a lifecycle group.
 *
 * @param int    $status Stored shipment status
 * @param string $set    Lifecycle group key
 * @return bool
 */
function chinaplanetlogistics_status_in($status, $set)
{
    return in_array((int) $status, chinaplanetlogistics_status_values($set), true);
}

/**
 * Resolve a stored shipment status to its canonical label.
 *
 * @param int    $status   Stored shipment status
 * @param string $fallback Label for an unknown value
 * @return string
 */
function chinaplanetlogistics_status_label($status, $fallback = '-')
{
    $statuses = chinaplanetlogistics_status_map();
    $status = (int) $status;
    return isset($statuses[$status]) ? $statuses[$status] : $fallback;
}

/**
 * Get color for ETA countdown
 * @param int $days Days until ETA
 * @return string Color code
 */
function getETAColor($days)
{
    if ($days === null) return '';
    if ($days > 14) return 'green';
    if ($days >= 10) return 'yellow';
    if ($days >= 7) return 'orange';
    if ($days >= 3) return 'red';
    if ($days >= 0) return '#8B0000'; // Dark Red
    return 'blue'; // Arrived
}

/**
 * Get color for Free Time countdown
 * @param int $days Remaining free days
 * @return string Color code
 */
function getFreeTimeColor($days)
{
    if ($days === null) return '';
    if ($days > 14) return 'green';
    if ($days >= 10) return 'yellow';
    if ($days >= 7) return 'orange';
    if ($days >= 3) return 'red';
    if ($days >= 0) return '#8B0000'; // Dark Red
    return 'black'; // Expired
}
?>
