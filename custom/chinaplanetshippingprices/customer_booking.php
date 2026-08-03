<?php

require_once __DIR__.'/class/CustomerShippingBookingService.class.php';

$viewLang = GETPOST('view_lang', 'alpha') === 'en' ? 'en' : 'ar';
$rtl = $viewLang === 'ar';
$t = function ($ar, $en) use ($rtl) { return $rtl ? $ar : $en; };
$esc = function ($value) { return dol_escape_htmltag((string) $value); };
$bookingService = new CustomerShippingBookingService($db);
$bookingResult = null;
$pageError = '';

if ($m035PostedAction === 'directbook') {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') accessforbidden();
    try {
        chinaplanetsp_require_customer_booking($user);
        $bookingResult = $bookingService->submit($user, $_POST);
        setEventMessages($t('تم استلام طلب الحجز بنجاح: ', 'Booking request received: ').$bookingResult['reference'], null, 'mesgs');
    } catch (Throwable $e) {
        $pageError = $e->getMessage();
        setEventMessages($t('تعذر إرسال الحجز. ', 'Booking could not be submitted. ').$pageError, null, 'errors');
    }
}

$searched = GETPOSTINT('search') === 1 || $m035PostedAction === 'directbook';
$searchReady = $filters['pol'] !== '' && $filters['pod'] !== '' && in_array($filters['container_type'], array('20GP','40GP','40HQ'), true);
$payload = array('results'=>array(), 'error'=>'');
if ($searched && $searchReady) $payload = $service->search($filters, false, 60);
elseif ($searched) $pageError = $t('اختر ميناء التحميل وميناء الوصول ونوع الحاوية.', 'Select port of loading, port of destination and container type.');
$options = $service->getFilterOptions(true);
$bookings = array();
try { $bookings = $bookingService->listCustomerBookings($user, 20); } catch (Throwable $e) { /* Read-only search remains available during first schema activation. */ }
$canBook = chinaplanetsp_can_customer_book($user);

$toggleParams = array_filter(array(
    'view_lang'=>$rtl ? 'en' : 'ar', 'search'=>$searched ? 1 : null,
    'pol'=>$filters['pol'], 'country_code'=>$filters['country_code'], 'pod'=>$filters['pod'],
    'shipping_line'=>$filters['shipping_line'], 'container_type'=>$filters['container_type'],
    'service_type'=>$filters['service_type'], 'incoterm'=>$filters['incoterm'],
), function ($value) { return $value !== '' && $value !== null; });

llxHeader('', $t('أسعار الشحن والحجز المباشر', 'Shipping prices and direct booking'), '', '', 0, 0, array(), $morecss);
cpcc_portal_navigation($user, 'shippingprices');
print '<script>document.documentElement.lang="'.($rtl?'ar':'en').'";document.documentElement.dir="'.($rtl?'rtl':'ltr').'";</script>';
print '<main class="cpsb" lang="'.($rtl?'ar':'en').'" dir="'.($rtl?'rtl':'ltr').'">';
print '<section class="cpsb-hero"><div><span class="cpsb-kicker">'.$t('مركز أسعار الشحن', 'SHIPPING RATE CENTER').'</span><h1>'.$t('السعر الفعلي. حجز مباشر.', 'Actual price. Direct booking.').'</h1><p>'.$t('ابحث في الأسعار المعتمدة الحالية، اختر السعر، ثم أرسل بيانات الشحنة لبدء الحجز.', 'Search current approved sell prices, select a rate, then send cargo details to start booking.').'</p></div><a class="cpsb-lang" href="?'.$esc(http_build_query($toggleParams)).'">'.($rtl?'English':'العربية').'</a></section>';

print '<section class="cpsb-search-panel" aria-labelledby="cpsb-search-title"><div class="cpsb-section-head"><div><span>01</span><div><h2 id="cpsb-search-title">'.$t('ابحث عن السعر', 'Search a price').'</h2><p>'.$t('الحقول الثلاثة بعلامة النجمة مطلوبة.', 'The three starred fields are required.').'</p></div></div></div>';
print '<form class="cpsb-search" method="get"><input type="hidden" name="view_lang" value="'.$viewLang.'"><input type="hidden" name="search" value="1">';
$select = function ($name, $label, $values, $selected, $placeholder, $required = false, $id = '') use ($esc) {
    print '<label><span>'.$esc($label).($required?' *':'').'</span><select name="'.$esc($name).'"'.($id?' id="'.$esc($id).'"':'').($required?' required':'').'><option value="">'.$esc($placeholder).'</option>';
    foreach ($values as $key=>$text) { $value=is_int($key)?$text:$key; print '<option value="'.$esc($value).'"'.((string)$selected===(string)$value?' selected':'').'>'.$esc($text).'</option>'; }
    print '</select></label>';
};
$visiblePods = $filters['country_code'] !== '' ? ($options['pod_by_country'][$filters['country_code']] ?? array()) : $options['pod'];
$select('pol',$t('ميناء التحميل','Port of loading'),$options['pol'],$filters['pol'],$t('اختر الميناء','Select port'),true);
$select('country_code',$t('دولة الوصول','Destination country'),$options['countries'],$filters['country_code'],$t('كل الدول','All countries'),false,'cpsb-country');
$select('pod',$t('ميناء الوصول','Port of destination'),$visiblePods,$filters['pod'],$t('اختر الميناء','Select port'),true,'cpsb-pod');
$select('container_type',$t('نوع الحاوية','Container type'),array_intersect_key($options['containers'],array_flip(array('20GP','40GP','40HQ'))),$filters['container_type'],$t('اختر الحاوية','Select container'),true);
$select('shipping_line',$t('شركة الشحن','Shipping line'),$options['lines'],$filters['shipping_line'],$t('كل الشركات','All lines'));
print '<label><span>'.$t('نوع الخدمة','Service').'</span><select name="service_type"><option value="">'.$t('مباشر أو ترانزيت','Direct or transit').'</option><option value="direct"'.($filters['service_type']==='direct'?' selected':'').'>'.$t('مباشر','Direct').'</option><option value="transit"'.($filters['service_type']==='transit'?' selected':'').'>'.$t('ترانزيت','Transit').'</option></select></label>';
print '<input type="hidden" name="incoterm" value="FOB"><button class="cpsb-primary" type="submit"><span class="fas fa-search" aria-hidden="true"></span>'.$t('عرض الأسعار','Show prices').'</button></form></section>';

print '<div class="cpsb-live" aria-live="polite" role="status">';
if ($pageError) print '<div class="cpsb-alert cpsb-alert-error" role="alert" tabindex="-1">'.$esc($pageError).'</div>';
if ($bookingResult) print '<div class="cpsb-alert cpsb-alert-success"><strong>'.$t('تم استلام الحجز','Booking received').'</strong><span>'.$t('رقم الطلب: ','Reference: ').$esc($bookingResult['reference']).'</span><small>'.$t('بدأ فريق العمليات مراجعة البيانات والمساحة. لا يلزم تأكيد ثانٍ منك الآن.','Operations has started cargo and space review. No second routine confirmation is needed from you.').'</small></div>';
if (!$canBook) print '<div class="cpsb-alert cpsb-alert-info">'.$t('يمكنك رؤية الأسعار، لكن صلاحية إرسال الحجز غير مفعلة لحسابك. تواصل مع مدير الحساب.','You can view prices, but booking submission is not enabled for this account. Contact your account manager.').'</div>';
print '</div>';

if ($searched) {
    print '<section class="cpsb-results" aria-labelledby="cpsb-results-title"><div class="cpsb-section-head"><div><span>02</span><div><h2 id="cpsb-results-title">'.$t('الأسعار المتاحة','Available prices').'</h2><p>'.count($payload['results']).' '.$t('نتيجة مطابقة','matching result(s)').'</p></div></div></div>';
    if (!$searchReady || empty($payload['results'])) print '<div class="cpsb-empty"><span class="fas fa-route" aria-hidden="true"></span><h3>'.$t('لا يوجد سعر صالح مطابق','No valid matching price').'</h3><p>'.$t('جرّب تغيير شركة الشحن أو الخدمة، أو تواصل معنا إذا كانت الوجهة مطلوبة.','Try another shipping line or service, or contact us if you need this lane.').'</p></div>';
    else {
        print '<div class="cpsb-result-list">';
        foreach ($payload['results'] as $r) {
            $carrier = !empty($conf->global->CHINAPLANETSP_CUSTOMER_SHOW_CARRIER) ? $r['shipping_line'] : '';
            $rateJson = json_encode(array('rate_id'=>$r['rate_id'],'fingerprint'=>$r['fingerprint'],'unit'=>(float)$r['final_price'],'currency'=>$r['currency'],'route'=>$r['pol'].' → '.$r['pod'],'equipment'=>$r['container_type'],'pol'=>$r['pol'],'pod'=>$r['pod'],'country_code'=>$r['country_code'],'shipping_line'=>$r['shipping_line'],'service_type'=>$r['service_type'],'incoterm'=>$r['incoterm']), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP);
            print '<article class="cpsb-rate"><div class="cpsb-rate-main"><div class="cpsb-route"><span class="cpsb-chip">'.$esc($r['container_type']).'</span><span class="cpsb-chip cpsb-chip-soft">'.$esc(ucfirst($r['service_type'])).'</span><h3 dir="ltr">'.$esc($r['pol']).' <span aria-hidden="true">→</span> '.$esc($r['pod']).'</h3>'.($carrier?'<p>'.$esc($carrier).'</p>':'').'</div><div class="cpsb-price"><strong dir="ltr">'.number_format($r['final_price'],2).'</strong><span dir="ltr">'.$esc($r['currency']).' / '.$t('حاوية','container').'</span></div></div>';
            print '<div class="cpsb-rate-meta"><span><b>'.$t('صالح حتى','Valid to').'</b>'.$esc($r['valid_to']).'</span><span><b>'.$t('العملة','Currency').'</b>'.$esc($r['currency']).'</span><span><b>'.$t('النطاق','Scope').'</b>'.$t('شحن بحري FCL','FCL ocean freight').'</span></div>';
            print '<details class="cpsb-details"><summary>'.$t('تفاصيل السعر والمشمول والمستثنى','Price details, inclusions and exclusions').'</summary><div class="cpsb-detail-grid"><div><h4>'.$t('مشمول','Included').'</h4><ul>';foreach($r['included'] as$item)print'<li>'.$esc($item).'</li>';print'</ul></div><div><h4>'.$t('غير مشمول','Excluded').'</h4><ul>';foreach($r['excluded'] as$item)print'<li>'.$esc($item).'</li>';print'</ul></div></div>'.(!empty($r['notes'])?'<div class="cpsb-carrier-note"><strong>'.$t('ملاحظات الناقل بالإنجليزية','Carrier note in English').'</strong><p lang="en" dir="ltr">'.nl2br($esc($r['notes'])).'</p></div>':'').'</details>';
            print '<button type="button" class="cpsb-book" data-rate="'.$esc($rateJson).'"'.($canBook?'':' disabled').'>'.$t('احجز بهذا السعر','Book this price').'<span class="fas fa-arrow-left" aria-hidden="true"></span></button></article>';
        }
        print '</div>';
    }
    print '</section>';
}

print '<section class="cpsb-bookings" aria-labelledby="cpsb-bookings-title"><div class="cpsb-section-head"><div><span>03</span><div><h2 id="cpsb-bookings-title">'.$t('طلبات الحجز الخاصة بي','My booking requests').'</h2><p>'.$t('حالة آمنة ومختصرة بدون بيانات داخلية.','A safe status view without internal operational data.').'</p></div></div></div>';
if (!$bookings) print '<div class="cpsb-empty cpsb-empty-small">'.$t('لا توجد طلبات حجز مباشر حتى الآن.','No direct booking requests yet.').'</div>';
else { print '<div class="cpsb-booking-list">'; foreach ($bookings as $b) { $route=$b['route']; print '<article><div><strong>'.$esc($b['reference']).'</strong><span>'.$esc(($route['pol']??'').' → '.($route['pod']??'')).' · '.$esc($b['equipment']).' × '.(int)$b['quantity'].'</span></div><div><span class="cpsb-status cpsb-status-'.$esc($b['status']).'">'.$esc(str_replace('_',' ',$b['status'])).'</span><b>'.number_format($b['total'],2).' '.$esc($b['currency']).'</b></div></article>'; } print '</div>'; }
print '</section>';

print '<dialog class="cpsb-dialog" id="cpsb-dialog" aria-labelledby="cpsb-dialog-title"><form method="post" id="cpsb-booking-form"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="directbook"><input type="hidden" name="view_lang" value="'.$viewLang.'"><input type="hidden" name="rate_id"><input type="hidden" name="rate_fingerprint"><input type="hidden" name="idempotency_key"><input type="hidden" name="pol"><input type="hidden" name="pod"><input type="hidden" name="country_code"><input type="hidden" name="shipping_line"><input type="hidden" name="container_type"><input type="hidden" name="service_type"><input type="hidden" name="incoterm"><header><div><span>'.$t('طلب حجز مباشر','DIRECT BOOKING REQUEST').'</span><h2 id="cpsb-dialog-title">'.$t('بيانات الشحنة','Cargo and party details').'</h2><p id="cpsb-selected-rate"></p></div><button type="button" class="cpsb-close" aria-label="'.$t('إغلاق','Close').'">×</button></header><div class="cpsb-dialog-body">';
print '<div class="cpsb-form-grid"><label><span>'.$t('عدد الحاويات','Container quantity').' *</span><input type="number" name="container_quantity" min="1" max="20" value="1" required inputmode="numeric"></label><label><span>'.$t('تاريخ جاهزية البضاعة','Cargo-ready date').' *</span><input type="date" name="cargo_ready_date" min="'.date('Y-m-d').'" required></label><label class="wide"><span>'.$t('وصف البضاعة','Cargo description').' *</span><textarea name="cargo_description" maxlength="2000" required></textarea></label><label><span>HS Code</span><input name="hs_code" maxlength="64"></label><label class="wide"><span>Shipper *</span><textarea name="shipper" maxlength="500" required></textarea></label><label class="wide"><span>Consignee *</span><textarea name="consignee" maxlength="500" required></textarea></label><label class="wide"><span>'.$t('جهة الإخطار (اختياري)','Notify Party (optional)').'</span><textarea name="notify_party" maxlength="500"></textarea></label><label class="wide"><span>'.$t('تعليمات إضافية','Additional instruction').'</span><textarea name="customer_instruction" maxlength="2000"></textarea></label></div>';
print '<fieldset class="cpsb-flags"><legend>'.$t('هل تحتوي البضاعة على أي مما يلي؟','Does the cargo contain any of these?').'</legend>';foreach(array('battery'=>$t('بطاريات','Battery'),'liquid'=>$t('سوائل أو زيوت','Liquid or oil'),'powder'=>$t('مساحيق','Powder'),'chemical'=>$t('مواد كيميائية','Chemical'),'dangerous_goods'=>'DG','copy_brand'=>$t('علامة مقلدة','Copy brand')) as$key=>$label)print'<label><input type="checkbox" name="'.$key.'" value="1"><span>'.$label.'</span></label>';print'<p>'.$t('اختيار أي بند يوقف الحجز العادي ويحوّل الطلب للمراجعة اليدوية.','Selecting any item stops normal booking and requires manual review.').'</p></fieldset>';
print '<div class="cpsb-total"><span>'.$t('الحساب المعتمد','Accepted calculation').'</span><strong id="cpsb-total" dir="ltr"></strong><small>'.$t('الإجمالي = سعر الحاوية × العدد. لا يشمل البنود المستثناة أعلاه.','Total = unit price × quantity. Excluded items above are not included.').'</small></div><label class="cpsb-accept"><input type="checkbox" name="accept_terms" value="1" required><span>'.$t('أوافق على السعر والعملة والصلاحية والبنود المشمولة والمستثناة المعروضة.','I accept the displayed price, currency, validity, inclusions and exclusions.').'</span></label></div><footer><button type="button" class="cpsb-secondary cpsb-close">'.$t('إلغاء','Cancel').'</button><button class="cpsb-primary" type="submit">'.$t('إرسال وبدء الحجز','Submit and start booking').'</button></footer></form></dialog>';

print '<script>(function(){var dialog=document.getElementById("cpsb-dialog"),form=document.getElementById("cpsb-booking-form"),summary=document.getElementById("cpsb-selected-rate"),total=document.getElementById("cpsb-total"),last=null,rate=null;function key(){var a=new Uint8Array(18);crypto.getRandomValues(a);return Array.from(a,function(x){return x.toString(16).padStart(2,"0")}).join("")}function refresh(){if(!rate)return;var q=Math.max(1,parseInt(form.container_quantity.value||"1",10)),format={minimumFractionDigits:2,maximumFractionDigits:2},sum=Number(rate.unit*q).toLocaleString(undefined,format),unit=Number(rate.unit).toLocaleString(undefined,format);total.textContent=sum+" "+rate.currency+" = "+unit+" "+rate.currency+" × "+q}document.querySelectorAll(".cpsb-book").forEach(function(button){button.addEventListener("click",function(){last=button;rate=JSON.parse(button.dataset.rate);Object.keys(rate).forEach(function(k){var input=form.elements[k];if(input)input.value=rate[k]});form.idempotency_key.value=key();summary.textContent=rate.route+" · "+rate.equipment+" · "+Number(rate.unit).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+" "+rate.currency+" / container";refresh();dialog.showModal()})});document.querySelectorAll(".cpsb-close").forEach(function(x){x.addEventListener("click",function(){dialog.close()})});dialog.addEventListener("close",function(){if(last)last.focus()});form.container_quantity.addEventListener("input",refresh);var country=document.getElementById("cpsb-country"),pod=document.getElementById("cpsb-pod"),allPorts='.json_encode(array_values($options['pod']),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).',byCountry='.json_encode(array_map('array_values',$options['pod_by_country']),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).';if(country&&pod)country.addEventListener("change",function(){var list=country.value?(byCountry[country.value]||[]):allPorts;pod.replaceChildren(new Option("'.$t('اختر الميناء','Select port').'",""));list.forEach(function(name){pod.add(new Option(name,name))})})})();</script>';
print '<script>(function(){var form=document.getElementById("cpsb-booking-form");document.querySelectorAll(".cpsb-book").forEach(function(button){button.addEventListener("click",function(){var selected=JSON.parse(button.dataset.rate);form.elements.rate_fingerprint.value=selected.fingerprint;form.elements.rate_id.value=selected.rate_id;});});})();</script>';
print '</main>';
llxFooter();
$db->close();
