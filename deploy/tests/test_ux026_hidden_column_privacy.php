<?php

require dirname(__DIR__, 2).'/custom/cpkanban/lib/cpkanban.lib.php';

function ux026Column($id, $code, $label, $position, $visible)
{
    $column = new stdClass();
    $column->rowid = $id;
    $column->code = $code;
    $column->label = $label;
    $column->label_en = $code === 'supp' ? 'Supplier Follow-up' : 'In Progress';
    $column->position = $position;
    $column->cust_visible = $visible;
    return $column;
}

$prog = ux026Column(20, 'prog', 'قيد التنفيذ', 40, 1);
$supp = ux026Column(30, 'supp', 'متابعة المصنع/المورّد', 50, 0);
$visible = array(20 => $prog);
$all = array(20 => $prog, 30 => $supp);
$card = new stdClass();
$card->rowid = 4;
$board = array(20 => array(), 30 => array($card));

$external = cpkanban_fold_customer_board($board, $visible, $all);
if (count($external[20]) !== 1 || $external[20][0] !== $card) {
    fwrite(STDERR, "FAIL folded card missing from public progress column\n");
    exit(1);
}
foreach (array('_cpk_folded_from', '_cpk_folded_from_label') as $property) {
    if (property_exists($card, $property)) {
        fwrite(STDERR, "FAIL hidden metadata attached to external card\n");
        exit(1);
    }
}
if (cpkanban_customer_visible_column_id(30, $visible, $all) !== 20) {
    fwrite(STDERR, "FAIL card detail did not resolve to public progress column\n");
    exit(1);
}

$root = dirname(__DIR__, 2).'/custom/cpkanban/';
$externalSources = file_get_contents($root.'board.php').file_get_contents($root.'card.php');
foreach (array('داخلياً:', '_cpk_folded_from', '_cpk_folded_from_label') as $forbidden) {
    if (strpos($externalSources, $forbidden) !== false) {
        fwrite(STDERR, "FAIL external renderer contains forbidden hidden metadata\n");
        exit(1);
    }
}
if (strpos(file_get_contents($root.'admin/columns.php'), 'dol_escape_htmltag($c->label)') === false) {
    fwrite(STDERR, "FAIL internal column label rendering changed\n");
    exit(1);
}

print "PASS external_fold=prog hidden_metadata=absent internal_labels=preserved\n";

