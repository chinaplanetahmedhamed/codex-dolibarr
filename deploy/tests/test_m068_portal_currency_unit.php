<?php

if (!function_exists('getDolCurrency')) { function getDolCurrency() { return 'EUR'; } }
require_once dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

$assert = static function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$proposal = (object) array('multicurrency_code'=>'USD', 'multicurrency_tx'=>1.1);
$foreign = cpcc_portal_proposal_money($proposal, 90.0, 99.0, 'EUR');
$assert($foreign['currency'] === 'USD' && abs($foreign['amount'] - 99.0) < 0.000001, 'foreign proposal amount is not customer currency');
$proposal->multicurrency_code = 'EUR';
$proposal->multicurrency_tx = 1.0;
$base = cpcc_portal_proposal_money($proposal, 90.0, 99.0, 'EUR');
$assert($base['currency'] === 'EUR' && abs($base['amount'] - 90.0) < 0.000001, 'base proposal amount changed');

echo "M068_PORTAL_CURRENCY=PASS FOREIGN=multicurrency_total BASE=total_ht ISO=explicit\n";
