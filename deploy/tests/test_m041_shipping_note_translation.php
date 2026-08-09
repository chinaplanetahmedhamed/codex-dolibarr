<?php
/** Pure unit tests for M041 note normalization, provider bounds and import circuit breaker. */

$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php';
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php';

function m041Assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$newlineCases = array(
    " A\nB " => "A\nB",
    " A\r\nB " => "A\nB",
    ' A\\nB ' => "A\nB",
    ' A\\r\\nB ' => "A\nB",
    " A\n\nB " => "A\n\nB",
    " A  \n  B " => "A\nB",
);
foreach ($newlineCases as $input=>$expected) m041Assert(ShippingNoteTranslationService::normalizeSource($input) === $expected, 'newline normalization failed');

$source = '南沙 8.6截延迟到8.7截 KOTA CEMPAKA 0085W(提前到8.31号开仓)\\n目的港免用箱：14天（2026.7.20开始）。40HQ货重26.5T以上，加收选柜费RMB180。';
$normalized = ShippingNoteTranslationService::normalizeSource($source);
foreach (array('8.7','8.31','2026.7.20','26.5T','RMB180','KOTA CEMPAKA 0085W') as $fact) m041Assert(strpos($normalized, $fact) !== false, 'source fact changed: '.$fact);
m041Assert(strpos($normalized, '\\n') === false && strpos($normalized, "\n") !== false, 'literal newline remained visible');

$calls = array();
$transport = function ($endpoint, $payload, $headers, $options) use (&$calls) {
    $calls[] = compact('endpoint','payload','headers','options');
    $text = "Nansha: the 8.6 cut-off is delayed to 8.7. KOTA CEMPAKA 0085W opening changed to 8.31.\nFree container use: 14 days starting 2026.7.20. For 40HQ at 26.5T or more, the fee is RMB180.";
    return array('status'=>200, 'body'=>json_encode(array('choices'=>array(array('message'=>array('content'=>$text))))), 'error'=>'');
};
$env = array(
    'CHINAPLANETSP_TRANSLATION_ENDPOINT'=>'https://translate.example.com/v1/chat/completions',
    'CHINAPLANETSP_TRANSLATION_MODEL'=>'qwen-test',
    'CHINAPLANETSP_TRANSLATION_API_KEY'=>'unit-test-secret',
);
$resolver = function ($host) { return $host === 'translate.example.com' ? array('8.8.8.8') : array(); };
$service = new ShippingNoteTranslationService($transport, $env, $resolver);
$result = $service->translate($normalized);
m041Assert($result['status'] === 'approved' && $result['attempted'] === true, 'successful translation was not accepted automatically: '.$result['error']);
m041Assert(count($calls) === 1, 'provider call count invalid');
m041Assert($calls[0]['endpoint'] === $env['CHINAPLANETSP_TRANSLATION_ENDPOINT'] && strpos($calls[0]['endpoint'], '南沙') === false, 'source note entered URL');
m041Assert(in_array('Authorization: Bearer unit-test-secret', $calls[0]['headers'], true), 'API key not sent only as authorization header');
m041Assert($calls[0]['options']['connect_timeout'] === 2 && $calls[0]['options']['timeout'] === 8 && $calls[0]['options']['connect_timeout_ms'] === 2000 && $calls[0]['options']['timeout_ms'] === 8000 && $calls[0]['options']['max_response_bytes'] === 65536, 'provider bounds changed');
foreach (array('8.7','8.31','2026.7.20','26.5T','RMB180','KOTA CEMPAKA 0085W') as $fact) m041Assert(strpos($result['notes_en'], $fact) !== false, 'translation fixture fact missing: '.$fact);

$deepSeekCalls = array();
$deepSeekTransport = function ($endpoint, $payload, $headers, $options) use (&$deepSeekCalls, $transport) {
    $deepSeekCalls[] = compact('endpoint','payload','headers','options');
    return $transport($endpoint, $payload, $headers, $options);
};
$deepSeekEnv = array(
    'CHINAPLANETSP_TRANSLATION_ENDPOINT'=>'https://api.deepseek.com/chat/completions',
    'CHINAPLANETSP_TRANSLATION_MODEL'=>'deepseek-v4-flash',
    'CHINAPLANETSP_TRANSLATION_API_KEY'=>'unit-test-deepseek-secret',
);
$deepSeekResolver = function ($host) { return $host === 'api.deepseek.com' ? array('8.8.4.4') : array(); };
$deepSeekResult = (new ShippingNoteTranslationService($deepSeekTransport, $deepSeekEnv, $deepSeekResolver))->translate($normalized);
m041Assert($deepSeekResult['status'] === 'approved' && count($deepSeekCalls) === 1, 'DeepSeek V4 provider contract failed');
m041Assert(($deepSeekCalls[0]['payload']['thinking']['type'] ?? '') === 'disabled', 'DeepSeek V4 deterministic non-thinking mode was not requested');

$noCalls = 0;
$neverTransport = function () use (&$noCalls) { $noCalls++; throw new RuntimeException('must not call'); };
$freeCalls = array();
$freeTransport = function ($endpoint, $payload, $headers, $options) use (&$freeCalls) {
    $freeCalls[] = compact('endpoint','payload','headers','options');
    preg_match_all('/zzcp[a-z]+tokenzz/i', $payload['q'], $placeholders);
    $tokens = $placeholders[0];
    $text = 'Nansha voyage '.($tokens[0] ?? '').' '.($tokens[1] ?? '').' surcharge '.($tokens[2] ?? '');
    return array('status'=>200, 'body'=>json_encode(array('responseStatus'=>200, 'responseData'=>array('translatedText'=>$text))), 'error'=>'');
};
$freeResolver = function ($host) { return $host === 'api.mymemory.translated.net' ? array('8.8.8.8') : array(); };
$free = new ShippingNoteTranslationService($freeTransport, array(), $freeResolver);
$freeResult = $free->translate('南沙 航次 AB12CD34 40HQ 加收 RMB180');
m041Assert($freeResult['status'] === 'approved' && $freeResult['provider'] === 'api.mymemory.translated.net', 'free default provider was not accepted automatically: '.$freeResult['error']);
m041Assert(count($freeCalls) === 1 && $freeCalls[0]['endpoint'] === ShippingNoteTranslationService::FREE_ENDPOINT, 'free provider endpoint changed');
m041Assert(array_keys($freeCalls[0]['payload']) === array('q','langpair','mt') && $freeCalls[0]['payload']['langpair'] === 'zh-CN|en' && $freeCalls[0]['payload']['mt'] === '1', 'free provider sent unexpected fields');
m041Assert(strpos($freeCalls[0]['payload']['q'], 'AB12CD34') === false && strpos($freeCalls[0]['payload']['q'], '40HQ') === false && strpos($freeCalls[0]['payload']['q'], 'RMB180') === false, 'protected tokens were exposed to free translation');
m041Assert(substr_count($freeCalls[0]['payload']['q'], 'tokenzz') === 3 && strpos($freeResult['notes_en'], 'AB12CD34') !== false && strpos($freeResult['notes_en'], '40HQ') !== false && strpos($freeResult['notes_en'], 'RMB180') !== false, 'protected tokens were not restored verbatim');
$dropTransport = function ($endpoint, $payload) {
    preg_match_all('/zzcp[a-z]+tokenzz/i', $payload['q'], $placeholders);
    $tokens = $placeholders[0];
    $text = 'Nansha voyage '.($tokens[0] ?? '').' '.($tokens[1] ?? '');
    return array('status'=>200, 'body'=>json_encode(array('responseStatus'=>200, 'responseData'=>array('translatedText'=>$text))), 'error'=>'');
};
$dropResult = (new ShippingNoteTranslationService($dropTransport, array(), $freeResolver))->translate('南沙 航次 AB12CD34 40HQ 加收 RMB180');
m041Assert($dropResult['status'] === 'approved' && strpos($dropResult['notes_en'], 'Carrier references preserved from source:') !== false && strpos($dropResult['notes_en'], 'RMB180') !== false, 'provider-dropped source token was not preserved explicitly');
m041Assert(($freeCalls[0]['options']['method'] ?? '') === 'GET' && count(array_filter($freeCalls[0]['headers'], function ($header) { return stripos($header, 'authorization:') === 0; })) === 0, 'free provider method or no-key contract changed');
$fallbackCalls = array();
$fallbackTransport = function ($endpoint, $payload, $headers, $options) use (&$fallbackCalls) {
    $fallbackCalls[] = compact('endpoint','payload','headers','options');
    if ($endpoint === ShippingNoteTranslationService::FREE_ENDPOINT) {
        return array('status'=>429, 'body'=>'{}', 'error'=>'');
    }
    preg_match_all('/zzcp[a-z]+tokenzz/i', $payload['q'], $placeholders);
    $tokens = $placeholders[0];
    $text = 'Nansha voyage '.($tokens[0] ?? '').' '.($tokens[1] ?? '').' surcharge '.($tokens[2] ?? '');
    return array('status'=>200, 'body'=>json_encode(array(array(array($text, '')))), 'error'=>'');
};
$fallbackResolver = function ($host) {
    return in_array($host, array('api.mymemory.translated.net', 'translate.googleapis.com'), true) ? array('8.8.8.8') : array();
};
$fallbackResult = (new ShippingNoteTranslationService($fallbackTransport, array(), $fallbackResolver))->translate('南沙 航次 AB12CD34 40HQ 加收 RMB180');
m041Assert($fallbackResult['status'] === 'approved' && $fallbackResult['provider'] === 'translate.googleapis.com', 'Google fallback was not accepted automatically: '.$fallbackResult['error']);
m041Assert(count($fallbackCalls) === 2 && $fallbackCalls[1]['endpoint'] === ShippingNoteTranslationService::GOOGLE_FREE_ENDPOINT, 'Google fallback call order changed');
m041Assert(array_keys($fallbackCalls[1]['payload']) === array('client','sl','tl','dt','q') && $fallbackCalls[1]['payload']['client'] === 'gtx' && $fallbackCalls[1]['payload']['sl'] === 'zh-CN' && $fallbackCalls[1]['payload']['tl'] === 'en', 'Google fallback sent unexpected fields');
m041Assert(strpos($fallbackCalls[1]['payload']['q'], 'AB12CD34') === false && count(array_filter($fallbackCalls[1]['headers'], function ($header) { return stripos($header, 'authorization:') === 0; })) === 0, 'Google fallback exposed a protected token or authorization data');
$emptyFallback = new ShippingNoteTranslationService(function ($endpoint) {
    if ($endpoint === ShippingNoteTranslationService::FREE_ENDPOINT) return array('status'=>429, 'body'=>'{}', 'error'=>'');
    return array('status'=>200, 'body'=>json_encode(array(array(array('', '')))), 'error'=>'');
}, array(), $fallbackResolver);
$emptyFallbackResult = $emptyFallback->translate('南沙 航次 AB12CD34 40HQ 加收 RMB180');
m041Assert($emptyFallbackResult['status'] === 'failed' && $emptyFallbackResult['error'] === 'translation_not_english', 'empty fallback output was accepted through restored source references');
$beforeEnglish = count($freeCalls);
m041Assert($free->translate('Already English')['status'] === 'approved' && count($freeCalls) === $beforeEnglish, 'English-only note called provider');
$freeTooLong = $free->translate(str_repeat('中', 501));
m041Assert($freeTooLong['status'] === 'failed' && $freeTooLong['error'] === 'free_provider_input_too_long' && count($freeCalls) === $beforeEnglish, 'free provider character limit was not fail-closed');
$privateEndpoint = new ShippingNoteTranslationService($neverTransport, array('CHINAPLANETSP_TRANSLATION_ENDPOINT'=>'https://10.0.0.1/v1','CHINAPLANETSP_TRANSLATION_MODEL'=>'model'), $resolver);
m041Assert($privateEndpoint->translate('中文')['error'] === 'endpoint_ip_forbidden' && $noCalls === 0, 'private endpoint was accepted');
$queryEndpoint = new ShippingNoteTranslationService($neverTransport, array('CHINAPLANETSP_TRANSLATION_ENDPOINT'=>'https://example.com/v1?key=secret','CHINAPLANETSP_TRANSLATION_MODEL'=>'model'), $resolver);
m041Assert($queryEndpoint->translate('中文')['error'] === 'endpoint_unsafe', 'query-bearing endpoint was accepted');

$badBodies = array(
    array('status'=>200,'body'=>'{broken','error'=>'','expected'=>'provider_schema_invalid'),
    array('status'=>200,'body'=>json_encode(array('choices'=>array(array('message'=>array('content'=>''))))),'error'=>'','expected'=>'translation_invalid'),
    array('status'=>503,'body'=>'{}','error'=>'','expected'=>'provider_http_error'),
    array('status'=>200,'body'=>str_repeat('x',65537),'error'=>'','expected'=>'provider_response_invalid'),
);
foreach ($badBodies as $bad) {
    $badService = new ShippingNoteTranslationService(function () use ($bad) { return $bad; }, $env, $resolver);
    m041Assert($badService->translate('中文')['error'] === $bad['expected'], 'invalid provider response accepted: '.$bad['expected']);
}
$throwing = new ShippingNoteTranslationService(function () { throw new RuntimeException('secret provider detail'); }, $env, $resolver);
m041Assert($throwing->translate('中文')['error'] === 'transport_exception', 'transport exception leaked/changed');
$html = new ShippingNoteTranslationService(function () { return array('status'=>200,'body'=>json_encode(array('choices'=>array(array('message'=>array('content'=>'<script>alert(1)</script>'))))),'error'=>''); }, $env, $resolver);
$htmlResult = $html->translate('中文');
m041Assert($htmlResult['status'] === 'failed' && $htmlResult['error'] === 'translation_markup_invalid' && $htmlResult['notes_en'] === '', 'provider HTML was accepted for storage/API');

$adversarial = array(
    'chinese_echo'=>array($normalized,'translation_matches_source'),
    'mixed_han'=>array("English words plus 中文 8.6 8.7 KOTA CEMPAKA 0085W 8.31 14 2026.7.20 40HQ 26.5T RMB180",'translation_not_english'),
    'changed_fact'=>array("Nansha: the 8.6 cut-off is delayed to 9.9. KOTA CEMPAKA 0085W opening changed to 8.31. Free use: 14 days starting 2026.7.20. For 40HQ at 26.5T or more, fee RMB180.",'protected_token_missing'),
    'added_fact'=>array("Nansha: the 8.6 cut-off is delayed to 8.7. KOTA CEMPAKA 0085W opening changed to 8.31. Free use: 14 days starting 2026.7.20. For 40HQ at 26.5T or more, fee RMB180; extra 9.9.",'protected_token_added'),
    'token_only'=>array("8.6 8.7 KOTA CEMPAKA 0085W 8.31 14 2026.7.20 40HQ 26.5T RMB180",'translation_not_english'),
);
foreach ($adversarial as $name=>$case) {
    $badService = new ShippingNoteTranslationService(function () use ($case) { return array('status'=>200,'body'=>json_encode(array('choices'=>array(array('message'=>array('content'=>$case[0]))))),'error'=>''); }, $env, $resolver);
    $badResult = $badService->translate($normalized);
    m041Assert($badResult['status'] === 'failed' && $badResult['error'] === $case[1] && $badResult['notes_en'] === '', 'adversarial output accepted: '.$name);
}
m041Assert(ShippingNoteTranslationService::validateEnglishTranslation('航次 AB12CD34', 'Voyage AB12CD35') === 'protected_token_missing', 'alternating voyage code change was accepted');
m041Assert(ShippingNoteTranslationService::validateEnglishTranslation('航次 A1B2', 'Voyage A1B3') === 'protected_token_missing', 'short alternating voyage code change was accepted');
m041Assert(ShippingNoteTranslationService::validateEnglishTranslation('航次 AB12CD34', 'Voyage AB12CD34') === '', 'unchanged alternating voyage code was rejected');
m041Assert(ShippingNoteTranslationService::validateEnglishTranslation('价格待定', 'Price TBD') === '', 'safe derived TBD marker was rejected');

$boundedService = new ShippingNoteTranslationService($transport, $env, $resolver);
$boundedService->translate($normalized, 0.35);
$lastBoundedCall = end($calls);
m041Assert($lastBoundedCall['options']['timeout_ms'] <= 350 && $lastBoundedCall['options']['connect_timeout_ms'] <= 350, 'remaining import budget was not enforced per request');

class M041FakeTranslator
{
    public $calls = 0;
    public $failFirst = false;
    public function translate($source, $remainingSeconds = null)
    {
        $this->calls++;
        if ($this->failFirst && $this->calls === 1) return array('status'=>'failed','notes_en'=>'','source_hash'=>hash('sha256',$source),'provider'=>'fake','model'=>'fake','error'=>'timeout','attempted'=>true);
        return array('status'=>'approved','notes_en'=>'EN '.$source,'source_hash'=>hash('sha256',$source),'provider'=>'fake','model'=>'fake','error'=>'','attempted'=>true);
    }
}
if (!isset($GLOBALS['conf'])) $GLOBALS['conf'] = new stdClass();
if (!isset($GLOBALS['conf']->global)) $GLOBALS['conf']->global = new stdClass();
$GLOBALS['conf']->global->CHINAPLANETSP_AUTO_TRANSLATION_ENABLED = 1;
$reflector = new ReflectionClass('ShippingPriceImporter');
$importer = $reflector->newInstanceWithoutConstructor();
$set = function ($name, $value) use ($reflector, $importer) { $property=$reflector->getProperty($name); $property->setAccessible(true); $property->setValue($importer,$value); };
$call = $reflector->getMethod('translationForNote'); $call->setAccessible(true);
$fake = new M041FakeTranslator();
$set('translator',$fake); $set('translationCache',array()); $set('translationStartedAt',microtime(true)); $set('translationCalls',0); $set('translationCircuitOpen',false);
$call->invoke($importer,'中文一'); $call->invoke($importer,'中文一');
m041Assert($fake->calls === 1, 'duplicate note called provider more than once');
for ($i=2; $i<=10; $i++) $call->invoke($importer,'中文'.$i);
$eleventh = $call->invoke($importer,'中文十一');
m041Assert($fake->calls === 10 && $eleventh['status'] === 'pending' && $eleventh['error'] === 'translation_budget_exhausted', 'whole-import call budget failed');

$failing = new M041FakeTranslator(); $failing->failFirst = true;
$set('translator',$failing); $set('translationCache',array()); $set('translationStartedAt',microtime(true)); $set('translationCalls',0); $set('translationCircuitOpen',false);
$first = $call->invoke($importer,'故障一'); $second = $call->invoke($importer,'故障二');
m041Assert($first['status'] === 'failed' && $second['status'] === 'pending' && $failing->calls === 1, 'provider circuit breaker failed');

class M041FakeCell
{
    private $value; private $coordinate;
    public function __construct($value, $coordinate) { $this->value=$value; $this->coordinate=$coordinate; }
    public function getValue() { return $this->value; }
    public function getCoordinate() { return $this->coordinate; }
}
class M041FakeSheet
{
    private $cells;
    public function __construct(array $cells) { $this->cells=$cells; }
    public function getHighestDataRow() { return 4; }
    public function getTitle() { return 'M041'; }
    public function getCellByColumnAndRow($column, $row) { return new M041FakeCell($this->cells[$row][$column] ?? null, chr(64+$column).$row); }
}
$acceptedOnlyTranslator = new M041FakeTranslator();
$set('translator',$acceptedOnlyTranslator); $set('translationCache',array()); $set('translationStartedAt',microtime(true)); $set('translationCalls',0); $set('translationCircuitOpen',false); $set('countryMap',array('SOKHNA'=>'EG'));
if (!isset($GLOBALS['conf'])) $GLOBALS['conf'] = new stdClass();
if (!isset($GLOBALS['conf']->global)) $GLOBALS['conf']->global = new stdClass();
$sheet = new M041FakeSheet(array(
    3=>array(1=>'NANSHA PIL',2=>'SOKHNA',3=>'DIRECT',4=>900,5=>'低价中文 8.7'),
    4=>array(1=>'NANSHA PIL',2=>'SOKHNA',3=>'DIRECT',4=>3000,5=>'有效中文 8.7'),
));
$parse = $reflector->getMethod('parseSheet'); $parse->setAccessible(true);
$parsed = $parse->invoke($importer,$sheet,array('container_row'=>2,'origin_col'=>1,'pod_col'=>2,'route_col'=>3,'price_cols'=>array(4=>'20GP'),'notes_col'=>5,'effective_date'=>'2026-08-03','price_group'=>'tariff'),1000,'USD');
m041Assert($acceptedOnlyTranslator->calls === 1 && count($parsed['rows']) === 1 && $parsed['rows'][0]['source_row'] === 4, 'rejected price rows consumed translation budget');

$GLOBALS['conf']->global->CHINAPLANETSP_AUTO_TRANSLATION_ENABLED = 0;
$disabledImporter = $reflector->newInstanceWithoutConstructor();
$disabledTranslator = new M041FakeTranslator();
foreach (array('translator'=>$disabledTranslator,'translationCache'=>array(),'translationStartedAt'=>microtime(true),'translationCalls'=>0,'translationCircuitOpen'=>false) as $name=>$value) {
    $property = $reflector->getProperty($name); $property->setAccessible(true); $property->setValue($disabledImporter, $value);
}
$disabledResult = $call->invoke($disabledImporter, '停用翻译 AB12CD34');
m041Assert($disabledResult['status'] === 'not_needed' && $disabledResult['error'] === 'translation_disabled' && $disabledTranslator->calls === 0, 'disabled translation still called an external provider');
$GLOBALS['conf']->global->CHINAPLANETSP_AUTO_TRANSLATION_ENABLED = 1;

echo "M041_TRANSLATION=PASS normalization=6 provider_bounds=PASS free_provider=PASS disabled_no_call=PASS ssrf=PASS dedup=PASS circuit=PASS\n";
