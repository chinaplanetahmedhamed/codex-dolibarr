<?php
/** M041 normalization and M046-C01 fail-closed regression. */

$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php';
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php';

function m041Assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$newlineCases = array(
    " A\nB " => "A\nB",
    " A\r\nB " => "A\nB",
    ' A\\nB ' => "A\nB",
    ' A\\r\\nB ' => "A\nB",
    " A  \n  B " => "A\nB",
);
foreach ($newlineCases as $input=>$expected) m041Assert(ShippingNoteTranslationService::normalizeSource($input) === $expected, 'newline normalization failed');

$source = '南沙 航次 AB12CD34 40HQ 加收 RMB180';
m041Assert(ShippingNoteTranslationService::containsHan($source), 'Han detection failed');
m041Assert(ShippingNoteTranslationService::validateEnglishTranslation($source, 'Nansha voyage AB12CD34 40HQ surcharge RMB180') === '', 'valid bounded English rejected');
m041Assert(ShippingNoteTranslationService::validateEnglishTranslation($source, 'Nansha voyage AB12CD35 40HQ surcharge RMB180') === 'protected_token_missing', 'changed operational token accepted');
m041Assert(ShippingNoteTranslationService::automaticTranslationEnabled() === false, 'effective translation policy is not disabled');

$transportCalls = 0;
$environmentReads = 0;
$resolverCalls = 0;
$transport = function () use (&$transportCalls) { $transportCalls++; throw new RuntimeException('transport must not run'); };
$resolver = function () use (&$resolverCalls) { $resolverCalls++; throw new RuntimeException('resolver must not run'); };
$environment = array(
    'CHINAPLANETSP_TRANSLATION_ENDPOINT'=>'https://translate.example.com/v1/chat/completions',
    'CHINAPLANETSP_TRANSLATION_MODEL'=>'configured-model',
    'CHINAPLANETSP_TRANSLATION_API_KEY'=>'configured-secret',
);
$result = (new ShippingNoteTranslationService($transport, $environment, $resolver))->translate($source);
m041Assert($result === array(
    'status'=>'not_needed', 'notes_en'=>'', 'source_hash'=>'', 'provider'=>'', 'model'=>'',
    'error'=>'translation_disabled', 'attempted'=>false,
), 'direct service did not fail closed before processing');
m041Assert($transportCalls === 0 && $resolverCalls === 0, 'disabled direct service reached transport or resolver');

if (!isset($GLOBALS['conf'])) $GLOBALS['conf'] = new stdClass();
if (!isset($GLOBALS['conf']->global)) $GLOBALS['conf']->global = new stdClass();
$reflector = new ReflectionClass('ShippingPriceImporter');
$method = $reflector->getMethod('translationForNote');
$method->setAccessible(true);
foreach (array(0, 1) as $stored) {
    $GLOBALS['conf']->global->CHINAPLANETSP_AUTO_TRANSLATION_ENABLED = $stored;
    $importer = $reflector->newInstanceWithoutConstructor();
    $disabled = $method->invoke($importer, $source);
    m041Assert($disabled['status'] === 'not_needed' && $disabled['error'] === 'translation_disabled', 'importer did not honor disabled policy for stored='.$stored);
}

echo "M041_TRANSLATION=PASS policy=DISABLED stored_0_1=INEFFECTIVE provider_calls=0 normalization=PASS validation=PASS\n";
