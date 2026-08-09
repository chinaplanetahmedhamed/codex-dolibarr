<?php

/** Safe live MyMemory acceptance probe. Emits no source or translation. */

$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php';

function m044c04ProviderFail($reason)
{
    fwrite(STDERR, 'M044_C04_PROVIDER=FAIL REASON='.$reason."\n");
    exit(1);
}

if (getenv('CHINAPLANETSP_TRANSLATION_ENDPOINT') || getenv('CHINAPLANETSP_TRANSLATION_MODEL') || getenv('CHINAPLANETSP_TRANSLATION_API_KEY')) {
    m044c04ProviderFail('PRIVATE_PROVIDER_ENV_MUST_BE_EMPTY');
}

$source = '南沙 航次 AB12CD34 40HQ 加收 RMB180';
$result = (new ShippingNoteTranslationService())->translate($source, 8.0);
if (($result['status'] ?? '') !== 'approved') m044c04ProviderFail('TRANSLATION_NOT_APPROVED_'.strtoupper((string) ($result['error'] ?? 'UNKNOWN')));
if (($result['provider'] ?? '') !== 'api.mymemory.translated.net' || ($result['model'] ?? '') !== 'zh-CN|en') m044c04ProviderFail('PROVIDER_EVIDENCE_INVALID');
if (ShippingNoteTranslationService::containsHan((string) ($result['notes_en'] ?? ''))) m044c04ProviderFail('OUTPUT_NOT_ENGLISH');
foreach (array('AB12CD34', '40HQ', 'RMB180') as $token) {
    if (strpos((string) $result['notes_en'], $token) === false) m044c04ProviderFail('PROTECTED_TOKEN_MISSING');
}

echo "M044_C04_PROVIDER=PASS PROVIDER=api.mymemory.translated.net MODEL=zh-CN|en KEY=NONE FIELDS=q,langpair,mt TOKENS=3 OUTPUT_REDACTED=YES\n";
