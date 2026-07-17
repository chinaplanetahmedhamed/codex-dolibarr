<?php
define('MAIN_DB_PREFIX','llx_');
require_once __DIR__.'/../../custom/chinaplanetcustomercenter/lib/financial.lib.php';

function cpcc_test_close($actual,$expected,$label)
{
    if(abs($actual-$expected)>0.005){
        fwrite(STDERR,$label.' expected '.$expected.' got '.$actual."\n");
        exit(1);
    }
}
function cpcc_test_row($type,$direction,$amount,$currency)
{
    return array('type'=>$type,'direction'=>$direction,'amount'=>$amount,'currency'=>$currency);
}

if(cpcc_financial_currency('LE')!=='EGP'||cpcc_financial_currency('rnb')!=='RMB'||cpcc_financial_currency('cny')!=='RMB'){
    fwrite(STDERR,"Currency normalization failed\n");exit(1);
}

// China Gate reference: deposits and paid-out amounts remain in USD.
$chinaGate=cpcc_financial_summarize_rows(array(
    cpcc_test_row('customer_receipt','C',689928.67,'USD'),
    cpcc_test_row('supplier_payment','D',669428.00,'USD'),
));
cpcc_test_close($chinaGate['USD']['available'],20500.67,'China Gate available');
cpcc_test_close(823278.60-669428.00,153850.60,'China Gate obligations');

// Funding gap: supplier balance + approved services + unpaid invoices - wallet.
cpcc_test_close(
    cpcc_financial_required_funding(33000.00,7680.00,0.00,18000.00),
    22680.00,
    'China Gate RMB additional funding'
);
cpcc_test_close(
    cpcc_financial_required_funding(10000.00,2500.00,500.00,20000.00),
    0.00,
    'No additional funding when wallet covers obligations'
);
cpcc_test_close(
    cpcc_financial_required_funding(10000.00,0.00,0.00,-1000.00),
    11000.00,
    'Existing wallet deficit increases required funding'
);
if(!cpcc_financial_is_supplier_category('product')||cpcc_financial_is_supplier_category('commission')){
    fwrite(STDERR,"Commitment category classification failed\n");exit(1);
}

// Vision Trading reference: USD and RMB must never be netted against each other.
$vision=cpcc_financial_summarize_rows(array(
    cpcc_test_row('customer_receipt','C',54100.00,'USD'),
    cpcc_test_row('supplier_payment','D',61186.25,'USD'),
    cpcc_test_row('opening_balance','C',150761.805844,'RMB'),
    cpcc_test_row('supplier_payment','D',133740.00,'RMB'),
));
cpcc_test_close($vision['USD']['available'],-7086.25,'Vision USD balance');
cpcc_test_close($vision['RMB']['available'],17021.805844,'Vision RMB balance');

// Kareem reference: conversion is a paired USD-out/RMB-in movement at 6.93.
$kareem=cpcc_financial_summarize_rows(array(
    cpcc_test_row('customer_receipt','C',19390.00,'USD'),
    cpcc_test_row('fx_out','D',19390.00,'USD'),
    cpcc_test_row('fx_in','C',134372.70,'RMB'),
    cpcc_test_row('supplier_payment','D',109377.00,'RMB'),
));
cpcc_test_close($kareem['USD']['available'],0.00,'Kareem USD after FX');
cpcc_test_close($kareem['RMB']['available'],24995.70,'Kareem RMB balance');
if($kareem['USD']['received']!==19390.00||$kareem['RMB']['received']!==0.0){
    fwrite(STDERR,"FX movements were incorrectly classified as customer receipts\n");exit(1);
}

// Reporting may value separate balances in USD, but must preserve each ledger.
$chinaGateMulti=array(
    'EGP'=>array('available'=>100000.00),
    'RMB'=>array('available'=>18000.00),
    'USD'=>array('available'=>11500.67),
);
$usdTotal=cpcc_financial_total_usd($chinaGateMulti,array('EGP'=>50.00,'RMB'=>7.00));
cpcc_test_close($usdTotal['converted']['EGP'],2000.00,'China Gate EGP valuation');
cpcc_test_close($usdTotal['converted']['RMB'],2571.428571,'China Gate RMB valuation');
cpcc_test_close($usdTotal['total'],16072.098571,'China Gate total USD valuation');
if(!empty($usdTotal['missing'])){
    fwrite(STDERR,"Configured valuation reported missing currencies\n");exit(1);
}

$missingRate=cpcc_financial_total_usd(array('AED'=>array('available'=>367.25)),array());
if($missingRate['missing']!==array('AED')||$missingRate['total']!==0.0){
    fwrite(STDERR,"Missing valuation rate was not handled safely\n");exit(1);
}

echo "Customer finance multi-currency tests passed\n";
