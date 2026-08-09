<?php
/** Static privacy/workflow contract for M041. */
$root = dirname(__DIR__, 2);
$read = function ($path) use ($root) { $data=file_get_contents($root.'/'.$path); if($data===false) throw new RuntimeException('Unreadable '.$path); return $data; };
$assert = function ($condition, $message) { if(!$condition) throw new RuntimeException($message); };

$importer = $read('custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php');
$translator = $read('custom/chinaplanetshippingprices/class/ShippingNoteTranslationService.class.php');
$review = $read('custom/chinaplanetshippingprices/class/ShippingNoteReviewService.class.php');
$batch = $read('custom/chinaplanetshippingprices/admin/batch.php');
$service = $read('custom/chinaplanetshippingprices/class/ShippingPriceService.class.php');
$customer = $read('custom/chinaplanetshippingprices/customer_booking.php');
$api = $read('custom/chinaplanetshippingprices/api/class/api_chinaplanetshippingprices.class.php');
$schema = $read('custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_rate.sql');

foreach (array('source_notes_raw','source_notes','notes_en','translation_status','translation_source_hash','translation_provider','translation_model','translation_error','fk_user_translation_review','date_translation_review') as $column) $assert(strpos($schema,$column)!==false,'schema column absent '.$column);
$assert(strpos($importer,"translation_status<>'approved'")!==false&&strpos($importer,'Automatic translation is still pending or failed')!==false,'publish failure/automatic-retry gate absent');
$assert(strpos($translator,'automaticTranslationEnabled')!==false&&strpos($translator,'return false;')!==false&&strpos($translator,"'error' => 'translation_disabled'")!==false,'fail-closed adapter absent');
$assert(strpos($translator,'curl_')===false&&strpos($translator,'getenv(')===false&&strpos($translator,'http://')===false&&strpos($translator,'https://')===false,'disabled adapter retains provider or transport code');
$assert(strpos($translator,'validateEnglishTranslation')!==false&&strpos($translator,'protected_token_missing')!==false&&strpos($translator,'protected_token_added')!==false&&strpos($translator,'translation_not_english')!==false,'bounded manual text validation absent');
$assert(strpos($importer,'->translate(')===false&&strpos($review,'->translate(')===false,'disabled importer/review retains adapter call');
$assert(strpos($review,"hasRight('chinaplanetshippingprices', 'import')")!==false&&strpos($review,"hasRight('chinaplanetshippingprices', 'viewcost')")!==false,'combined private-note review right absent');
$assert(strpos($review,'entity=')!==false&&strpos($review,'fk_batch=')!==false&&strpos($review,'COUNT(DISTINCT source_row)')!==false,'entity/batch/source-row read scope absent');
$assert(strpos($review,'autoTranslatePending')!==false&&substr_count($review,'requires a separately approved Mission.')===2,'mutation refusal contract absent');
$assert(strpos($batch,"define('CSRFCHECK_WITH_TOKEN', 1)")!==false&&strpos($batch,"REQUEST_METHOD")!==false,'translation mutation CSRF/POST gate absent');
$assert(strpos($batch,'listGroups($id, $notePage, 25)')!==false&&strpos($review,'COUNT(DISTINCT source_row)')!==false,'source-row pagination/grouping absent');
$assert(strpos($service,'private function addInternalProjection')!==false&&strpos($service,"if (\$includeCost) \$result = \$this->addInternalProjection")!==false,'explicit private projection absent');
$assert(strpos($service,"translation_status === 'approved'")!==false&&strpos($service,'$hasM041Provenance')!==false,'M041 public-note status gate absent');
$assert(strpos($service,'ShippingNoteTranslationService::automaticTranslationEnabled()')!==false&&strpos($service,'$translationEnabled &&')!==false,'centralized disabled translation public-note suppression absent');
$publicSection = substr($service, strpos($service,"'rate_id' =>"), strpos($service,'if ($includeCost)',strpos($service,"'rate_id' =>"))-strpos($service,"'rate_id' =>"));
foreach (array('source_notes','notes_en','translation_provider','translation_model','translation_error','internal_notes','supplier_cost','base_margin') as $forbidden) $assert(strpos($publicSection,$forbidden)===false,'public projection leak '.$forbidden);
$assert(strpos($api,'), false, 100)')!==false,'REST API no longer forces public projection');
$assert(strpos($customer,'lang="en" dir="ltr"')!==false&&strpos($customer,'nl2br($esc($r[\'notes\']))')!==false,'customer English LTR/multiline note absent');
foreach (array('source_notes','notes_en','translation_provider','translation_model','translation_error','internal_notes','supplier_cost','base_margin') as $forbidden) $assert(strpos($customer,$forbidden)===false,'customer page leak token '.$forbidden);

echo "M041_SOURCE=PASS public_projection=ALLOWLIST review_right=IMPORT_PLUS_VIEWCOST provider_code=ABSENT csrf=PASS grouping=PAGED\n";
