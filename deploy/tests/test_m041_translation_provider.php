<?php
/** Safe live-provider acceptance probe. Emits no key, source, or translation. */

$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php';

function providerFail($reason)
{
    fwrite(STDERR, 'M041_PROVIDER=FAIL REASON='.$reason."\n");
    exit(1);
}

$endpoint = getenv('CHINAPLANETSP_TRANSLATION_ENDPOINT');
$model = getenv('CHINAPLANETSP_TRANSLATION_MODEL');
$key = getenv('CHINAPLANETSP_TRANSLATION_API_KEY');
if ($endpoint !== 'https://api.deepseek.com/chat/completions') providerFail('ENDPOINT_INVALID');
if ($model !== 'deepseek-v4-flash') providerFail('MODEL_INVALID');
if (!is_string($key) || strlen($key) < 16 || strlen($key) > 512) providerFail('KEY_ABSENT_OR_INVALID');

$source = '南沙 航次 AB12CD34 40HQ 加收 RMB180';
$result = (new ShippingNoteTranslationService())->translate($source, 8.0);
if (($result['status'] ?? '') !== 'approved') providerFail('TRANSLATION_NOT_APPROVED_'.strtoupper((string) ($result['error'] ?? 'UNKNOWN')));
if (($result['provider'] ?? '') !== 'api.deepseek.com' || ($result['model'] ?? '') !== 'deepseek-v4-flash') providerFail('PROVIDER_EVIDENCE_INVALID');
if (ShippingNoteTranslationService::containsHan((string) ($result['notes_en'] ?? ''))) providerFail('OUTPUT_NOT_ENGLISH');
foreach (array('AB12CD34', '40HQ', 'RMB180') as $token) {
    if (strpos((string) $result['notes_en'], $token) === false) providerFail('PROTECTED_TOKEN_MISSING');
}

echo "M041_PROVIDER=PASS PROVIDER=api.deepseek.com MODEL=deepseek-v4-flash TOKENS=3 OUTPUT_REDACTED=YES\n";
