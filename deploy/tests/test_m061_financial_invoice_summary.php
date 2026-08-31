<?php

require_once __DIR__.'/../../custom/chinaplanetcustomercenter/lib/financial.lib.php';

function m061FinancialAssert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL '.$message."\n");
        exit(1);
    }
    print 'PASS '.$message."\n";
}

$invoiceAmounts = cpcc_financial_invoice_amounts(63201.70, 2238.00, 0.00);
m061FinancialAssert(abs($invoiceAmounts['total'] - 63201.70) < 0.001, 'gross invoice amount remains explicit');
m061FinancialAssert(abs($invoiceAmounts['paid'] - 2238.00) < 0.001, 'direct payment is funded amount');
m061FinancialAssert(abs($invoiceAmounts['remaining'] - 60963.70) < 0.001, 'remaining is gross less funded');

$withCredit = cpcc_financial_invoice_amounts(900.00, 100.00, 100.00);
m061FinancialAssert(abs($withCredit['total'] - 1000.00) < 0.001, 'consumed customer credit restores gross invoice total');
m061FinancialAssert(abs($withCredit['paid'] - 200.00) < 0.001, 'direct payment and applied credit are both funded');

$totals = cpcc_financial_invoice_totals(array(
    array('currency'=>'USD', 'total'=>1000, 'paid'=>200, 'remaining'=>800, 'direct_paid'=>100, 'applied_credit'=>100),
    array('currency'=>'USD', 'total'=>500, 'paid'=>500, 'remaining'=>0, 'direct_paid'=>500, 'applied_credit'=>0),
    array('currency'=>'RMB', 'total'=>7000, 'paid'=>1000, 'remaining'=>6000, 'direct_paid'=>1000, 'applied_credit'=>0),
));
m061FinancialAssert(count($totals) === 2, 'currencies remain separate');
m061FinancialAssert(abs($totals['USD']['total'] - 1500.00) < 0.001, 'same-currency invoice totals aggregate');
m061FinancialAssert(abs($totals['USD']['paid'] - 700.00) < 0.001, 'same-currency funded totals aggregate');
m061FinancialAssert(abs($totals['RMB']['remaining'] - 6000.00) < 0.001, 'foreign-currency outstanding stays in original currency');

$source = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/lib/financial.lib.php');
$descriptor = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/core/modules/modChinaPlanetCustomerCenter.class.php');
m061FinancialAssert(strpos($source, 'cpcc_public_note') !== false, 'payment public-note projection is explicit');
m061FinancialAssert(strpos($descriptor, "'paiement'") !== false, 'payment extra field targets the Dolibarr paiement table element');
m061FinancialAssert(strpos($source, 'note_private') === false, 'private notes are never used as payment-note fallback');

print "OK M061 financial summary and public payment-note contract\n";
