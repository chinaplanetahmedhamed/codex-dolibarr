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
