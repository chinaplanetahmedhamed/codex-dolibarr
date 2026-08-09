<?php
/** M046-C01 replacement for the former private-provider probe: no egress is allowed. */
$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php';
$calls = 0;
$result = (new ShippingNoteTranslationService(function () use (&$calls) { $calls++; throw new RuntimeException('transport must not run'); }, array(
    'CHINAPLANETSP_TRANSLATION_ENDPOINT'=>'https://api.deepseek.com/chat/completions',
    'CHINAPLANETSP_TRANSLATION_MODEL'=>'deepseek-v4-flash',
    'CHINAPLANETSP_TRANSLATION_API_KEY'=>'present-but-unused',
)))->translate('南沙 AB12CD34');
if ($calls !== 0 || ($result['error'] ?? '') !== 'translation_disabled' || !empty($result['attempted'])) throw new RuntimeException('M041 provider boundary is not disabled');
echo "M041_PROVIDER=PASS POLICY=DISABLED PROVIDER_CALLS=0 LIVE_PROBE=FORBIDDEN\n";
