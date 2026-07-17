<?php

class CommonObject {}

require_once __DIR__.'/../../custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php';

function assertSameDate($expected, $actual, $message)
{
    $actualDate = $actual ? date('Y-m-d', $actual) : null;
    if ($expected !== $actualDate) {
        fwrite(STDERR, 'FAIL '.$message.': expected '.$expected.', got '.var_export($actualDate, true).PHP_EOL);
        exit(1);
    }
}

$shipment = new ChinaPlanetLogistics(null);
$shipment->date_ata = strtotime('2026-07-14 00:00:00');
$shipment->freetime_days = 13;

if (!$shipment->calculateFreeTimeDates()) {
    fwrite(STDERR, 'FAIL valid ATA and free-time days were not calculated'.PHP_EOL);
    exit(1);
}

assertSameDate('2026-07-14', $shipment->freetime_start, 'free-time start');
assertSameDate('2026-07-27', $shipment->freetime_end, 'free-time end');

$withoutArrival = new ChinaPlanetLogistics(null);
$withoutArrival->freetime_days = 13;
if ($withoutArrival->calculateFreeTimeDates()) {
    fwrite(STDERR, 'FAIL free time must not be calculated without ATA'.PHP_EOL);
    exit(1);
}

print 'PASS ATA=2026-07-14 days=13 start=2026-07-14 end=2026-07-27'.PHP_EOL;
