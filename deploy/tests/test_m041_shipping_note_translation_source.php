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
$assert(strpos($translator,"\$result['status'] = 'approved'")!==false&&strpos($importer,"\$translation['status'] === 'approved' ? \$translation['notes_en']")!==false,'automatic acceptance/public-note mapping absent');
$assert(strpos($importer,'FOR UPDATE')!==false&&strpos($importer,'translationCircuitOpen')!==false&&strpos($importer,'translationCalls >= 10')!==false,'publish lock or import circuit/budget absent');
$assert(strpos($translator,"getenv(")!==false&&strpos($translator,'CURLOPT_FOLLOWLOCATION => false')!==false&&strpos($translator,'CURLOPT_RESOLVE')!==false,'environment-only provider or SSRF pinning absent');
$assert(strpos($translator,'MAX_RESPONSE_BYTES = 65536')!==false&&strpos($translator,'CONNECT_TIMEOUT = 2')!==false&&strpos($translator,'REQUEST_TIMEOUT = 8')!==false,'translation resource bounds absent');
$assert(strpos($translator,'validateEnglishTranslation')!==false&&strpos($translator,'protected_token_missing')!==false&&strpos($translator,'protected_token_added')!==false&&strpos($translator,'translation_not_english')!==false&&strpos($translator,'translation_markup_invalid')!==false,'automatic translation content validation absent');
$assert(strpos($importer,'$acceptedPrices')!==false&&strpos($importer,'remainingSeconds')!==false,'accepted-row translation or remaining-budget enforcement absent');
$assert(strpos($review,"hasRight('chinaplanetshippingprices', 'import')")!==false&&strpos($review,"hasRight('chinaplanetshippingprices', 'viewcost')")!==false,'combined private-note review right absent');
$assert(strpos($review,'entity=')!==false&&strpos($review,'fk_batch=')!==false&&strpos($review,'source_row=')!==false&&strpos($review,'FOR UPDATE')!==false,'entity/batch/source-row locking absent');
$assert(strpos($review,"translation_status='approved'")!==false&&strpos($review,"translation_provider='staff'")!==false&&strpos($review,"array(0, 2)")!==false,'editable automatic translation behavior absent');
$assert(strpos($review,'autoTranslatePending')!==false&&strpos($review,"translation_status<>'approved'")!==false,'automatic pending-note retry absent');
$assert(strpos($batch,"define('CSRFCHECK_WITH_TOKEN', 1)")!==false&&strpos($batch,"REQUEST_METHOD")!==false,'translation mutation CSRF/POST gate absent');
$assert(strpos($batch,'listGroups($id, $notePage, 25)')!==false&&strpos($review,'COUNT(DISTINCT source_row)')!==false,'source-row pagination/grouping absent');
$assert(strpos($service,'private function addInternalProjection')!==false&&strpos($service,"if (\$includeCost) \$result = \$this->addInternalProjection")!==false,'explicit private projection absent');
$assert(strpos($service,"translation_status === 'approved'")!==false&&strpos($service,'$hasM041Provenance')!==false,'M041 public-note status gate absent');
$assert(strpos($service,'CHINAPLANETSP_AUTO_TRANSLATION_ENABLED')!==false&&strpos($service,'$translationEnabled &&')!==false,'disabled translation public-note suppression absent');
$publicSection = substr($service, strpos($service,"'rate_id' =>"), strpos($service,'if ($includeCost)',strpos($service,"'rate_id' =>"))-strpos($service,"'rate_id' =>"));
foreach (array('source_notes','notes_en','translation_provider','translation_model','translation_error','internal_notes','supplier_cost','base_margin') as $forbidden) $assert(strpos($publicSection,$forbidden)===false,'public projection leak '.$forbidden);
$assert(strpos($api,'), false, 100)')!==false,'REST API no longer forces public projection');
$assert(strpos($customer,'lang="en" dir="ltr"')!==false&&strpos($customer,'nl2br($esc($r[\'notes\']))')!==false,'customer English LTR/multiline note absent');
foreach (array('source_notes','notes_en','translation_provider','translation_model','translation_error','internal_notes','supplier_cost','base_margin') as $forbidden) $assert(strpos($customer,$forbidden)===false,'customer page leak token '.$forbidden);

echo "M041_SOURCE=PASS public_projection=ALLOWLIST review_right=IMPORT_PLUS_VIEWCOST csrf=PASS grouping=PAGED\n";
