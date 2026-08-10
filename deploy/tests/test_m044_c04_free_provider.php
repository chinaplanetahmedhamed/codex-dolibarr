<?php
/** M046-C01 replacement for the former live-provider probe: no egress is allowed. */
$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php';
$calls = 0;
$result = (new ShippingNoteTranslationService(function () use (&$calls) { $calls++; throw new RuntimeException('transport must not run'); }))->translate('南沙 AB12CD34');
if ($calls !== 0 || ($result['error'] ?? '') !== 'translation_disabled' || !empty($result['attempted'])) throw new RuntimeException('M044 provider boundary is not disabled');
echo "M044_C04_PROVIDER=PASS POLICY=DISABLED PROVIDER_CALLS=0 LIVE_PROBE=FORBIDDEN\n";
