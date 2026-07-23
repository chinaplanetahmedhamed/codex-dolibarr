<?php
define('MAIN_DB_PREFIX', 'llx_');
require_once __DIR__.'/../../custom/chinaplanetcustomercenter/lib/financial.lib.php';

function m029_assert_close($actual, $expected, $label)
{
    if (abs((float) $actual - (float) $expected) > 0.005) {
        fwrite(STDERR, $label.' expected '.$expected.' got '.$actual."\n");
        exit(1);
    }
}

function m029_row($type, $direction, $amount)
{
    return array(
        'type' => $type,
        'direction' => $direction,
        'amount' => $amount,
        'currency' => 'USD',
    );
}

// A fully allocated invoice has a zero net total in Dolibarr, but its customer-facing
// gross total remains the invoice value before down-payment/credit consumption.
$allocated = cpcc_financial_invoice_amounts(0.00, 0.00, 35000.00);
m029_assert_close($allocated['total'], 35000.00, 'Allocated invoice gross total');
m029_assert_close($allocated['paid'], 35000.00, 'Allocated invoice funded amount');
m029_assert_close($allocated['remaining'], 0.00, 'Allocated invoice remaining');

// Ordinary partial payment behavior remains unchanged.
$partial = cpcc_financial_invoice_amounts(50000.00, 10000.00, 0.00);
m029_assert_close($partial['total'], 50000.00, 'Partially paid invoice total');
m029_assert_close($partial['paid'], 10000.00, 'Partially paid invoice funded amount');
m029_assert_close($partial['remaining'], 40000.00, 'Partially paid invoice remaining');

// The customer wallet is a derived view: native receipts less native allocations.
$wallet = cpcc_financial_summarize_rows(array(
    m029_row('native_deposit_receipt', 'C', 50000.00),
    m029_row('native_deposit_receipt', 'C', 20000.00),
    m029_row('native_credit_allocation', 'D', 25000.00),
    m029_row('native_credit_allocation', 'D', 10000.00),
));
m029_assert_close($wallet['USD']['received'], 70000.00, 'Customer funds received');
m029_assert_close($wallet['USD']['spent'], 35000.00, 'Customer funds allocated');
m029_assert_close($wallet['USD']['available'], 35000.00, 'Customer funds available');
m029_assert_close(
    cpcc_financial_required_funding(0.00, 0.00, 0.00, $wallet['USD']['available']),
    0.00,
    'No funding gap after a fully funded invoice'
);

if (cpcc_financial_type_label('native_deposit_receipt') === 'native_deposit_receipt'
    || cpcc_financial_type_label('native_credit_allocation') === 'native_credit_allocation') {
    fwrite(STDERR, "Native customer-fund labels are missing\n");
    exit(1);
}

echo "M029 core customer funds tests passed\n";
