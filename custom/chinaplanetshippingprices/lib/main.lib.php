<?php

function chinaplanetsp_require_read($user)
{
    if (empty($user->id) || (!$user->hasRight('chinaplanetshippingprices', 'read') && empty($user->admin))) accessforbidden();
    if (!empty($user->socid) && (int) $user->socid <= 0) accessforbidden();
}

function chinaplanetsp_require_internal($user, $right)
{
    if (empty($user->id) || !empty($user->socid) || (!$user->hasRight('chinaplanetshippingprices', $right) && empty($user->admin))) accessforbidden();
}

function chinaplanetsp_can_customer_book($user)
{
    return !empty($user->id) && !empty($user->socid) && (int) $user->socid > 0
        && $user->hasRight('chinaplanetshippingprices', 'read')
        && $user->hasRight('chinaplanetshippingprices', 'booking_submit');
}

function chinaplanetsp_require_customer_booking($user)
{
    if (!chinaplanetsp_can_customer_book($user)) accessforbidden();
}

function chinaplanetsp_can_sop($user, $right = 'sopread')
{
    return !empty($user->id) && empty($user->socid) && (!empty($user->admin) || $user->hasRight('chinaplanetshippingprices', $right));
}

function chinaplanetsp_require_sop($user, $right = 'sopread')
{
    if (!chinaplanetsp_can_sop($user, $right)) accessforbidden();
}

function chinaplanetsp_money($amount, $currency)
{
    return price(number_format((float) $amount, 2, '.', ''), 0, $GLOBALS['langs'], 1, -1, -1, $currency);
}

function chinaplanetsp_render_history_svg(array $points, $showCost = false)
{
    if (count($points) < 2) return '<div class="opacitymedium">Not enough historical points yet.</div>';
    $values = array_map(function ($p) use ($showCost) { return (float) ($showCost ? $p['cost_price'] : $p['sale_price']); }, $points);
    $min = min($values); $max = max($values); if ($max <= $min) $max = $min + 1;
    $w = 860; $h = 260; $padX = 58; $padY = 28; $plotW = $w - ($padX * 2); $plotH = $h - ($padY * 2);
    $coords = array();
    foreach ($values as $i => $value) {
        $x = $padX + ($plotW * ($i / max(1, count($values) - 1)));
        $y = $padY + $plotH - (($value - $min) / ($max - $min) * $plotH);
        $coords[] = round($x, 1).','.round($y, 1);
    }
    $out = '<div class="chinaplanetsp-chart"><svg viewBox="0 0 '.$w.' '.$h.'" role="img" aria-label="Shipping price history">';
    $out .= '<line x1="'.$padX.'" y1="'.$padY.'" x2="'.$padX.'" y2="'.($h-$padY).'" stroke="#bcc5cf"/>';
    $out .= '<line x1="'.$padX.'" y1="'.($h-$padY).'" x2="'.($w-$padX).'" y2="'.($h-$padY).'" stroke="#bcc5cf"/>';
    $out .= '<polyline points="'.implode(' ', $coords).'" fill="none" stroke="#1677c8" stroke-width="4" stroke-linejoin="round"/>';
    foreach ($coords as $i => $coord) {
        list($x, $y) = explode(',', $coord);
        $label = dol_escape_htmltag($points[$i]['date'].' — '.number_format($values[$i], 2).' '.$points[$i]['currency']);
        $out .= '<circle cx="'.$x.'" cy="'.$y.'" r="5" fill="#1677c8"><title>'.$label.'</title></circle>';
    }
    $out .= '<text x="8" y="'.($padY+5).'" font-size="12">'.number_format($max, 0).'</text>';
    $out .= '<text x="8" y="'.($h-$padY).'" font-size="12">'.number_format($min, 0).'</text>';
    $out .= '<text x="'.$padX.'" y="'.($h-5).'" font-size="12">'.dol_escape_htmltag($points[0]['date']).'</text>';
    $out .= '<text x="'.($w-$padX-75).'" y="'.($h-5).'" font-size="12">'.dol_escape_htmltag(end($points)['date']).'</text>';
    $out .= '</svg></div>';
    return $out;
}

function chinaplanetsp_render_comparison_chart(array $points)
{
    if (count($points) < 2) return '<div class="opacitymedium">Not enough historical points yet.</div>';
    $countries = array(); $pods = array(); $containers = array(); $lines = array(); $lanes = array();
    foreach ($points as $point) {
        if (!empty($point['country_code'])) $countries[$point['country_code']] = $point['country'].' ('.$point['country_code'].')';
        $pods[$point['pod']] = $point['pod'];
        $containers[$point['container']] = $point['container'];
        $lines[$point['line']] = $point['line'];
        if (!empty($point['lane'])) $lanes[$point['lane']] = $point['lane'];
    }
    natcasesort($countries); natcasesort($pods); natcasesort($containers); natcasesort($lines); natcasesort($lanes);
    $select = function ($id, $label, $values) {
        $out = '<label><span>'.dol_escape_htmltag($label).'</span><select id="'.$id.'"><option value="">All / الكل</option>';
        foreach ($values as $key=>$value) $out .= '<option value="'.dol_escape_htmltag($key).'">'.dol_escape_htmltag($value).'</option>';
        return $out.'</select></label>';
    };
    $out = '<section class="chinaplanetsp-history" id="chinaplanetsp-history">';
    $out .= '<div class="chinaplanetsp-history-head"><div><span class="fas fa-chart-line"></span><div><h2>Price history / تاريخ الأسعار</h2><p>Compare market averages by destination port and container size.</p></div></div><span class="chinaplanetsp-series-count" id="chinaplanetsp-series-count"></span></div>';
    $out .= '<div class="chinaplanetsp-chart-filters">'.$select('chinaplanetsp-chart-country','Country / الدولة',$countries).$select('chinaplanetsp-chart-pod','POD / ميناء الوصول',$pods).$select('chinaplanetsp-chart-container','Container / الحاوية',$containers).$select('chinaplanetsp-chart-line','Shipping line / شركة الشحن',$lines).$select('chinaplanetsp-chart-lane','Trade lane / خط التشغيل',$lanes);
    $out .= '<label><span>Time / الفترة</span><select id="chinaplanetsp-chart-time"><option value="3">3 months</option><option value="6">6 months</option><option value="12" selected>12 months</option><option value="24">24 months</option><option value="0">All available</option></select></label></div>';
    $out .= '<div class="chinaplanetsp-comparison-chart"><svg id="chinaplanetsp-history-svg" viewBox="0 0 1000 390" role="img" aria-label="Shipping price comparison chart"></svg><div id="chinaplanetsp-history-empty" class="chinaplanetsp-chart-empty" hidden>No data for these filters.</div></div>';
    $out .= '<div class="chinaplanetsp-chart-legend" id="chinaplanetsp-chart-legend"></div>';
    $out .= '<details class="chinaplanetsp-history-data"><summary>Show chart data / عرض بيانات الرسم</summary><div class="div-table-responsive"><table class="noborder centpercent"><thead><tr class="liste_titre"><th>Date</th><th>Country</th><th>POD</th><th>Container</th><th>Shipping line</th><th>Trade lane</th><th class="right">Average price</th><th>Offers</th><th>Currency</th></tr></thead><tbody id="chinaplanetsp-history-rows"></tbody></table></div></details></section>';
    $json = json_encode(array_values($points), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    $out .= '<script>(function(){var data='.$json.',country=document.getElementById("chinaplanetsp-chart-country"),pod=document.getElementById("chinaplanetsp-chart-pod"),container=document.getElementById("chinaplanetsp-chart-container"),line=document.getElementById("chinaplanetsp-chart-line"),lane=document.getElementById("chinaplanetsp-chart-lane"),time=document.getElementById("chinaplanetsp-chart-time"),svg=document.getElementById("chinaplanetsp-history-svg"),legend=document.getElementById("chinaplanetsp-chart-legend"),rows=document.getElementById("chinaplanetsp-history-rows"),empty=document.getElementById("chinaplanetsp-history-empty"),count=document.getElementById("chinaplanetsp-series-count"),colors=["#087fc3","#e29424","#169c75","#8b5fc7","#d85868","#476cdb","#9a6b3b","#00a6a6"];
function esc(v){return String(v).replace(/[&<>\"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c];});}
function refreshPods(){var previous=pod.value,names={};data.forEach(function(p){if(!country.value||p.country_code===country.value)names[p.pod]=1;});pod.replaceChildren();var all=document.createElement("option");all.value="";all.textContent="All / الكل";pod.appendChild(all);Object.keys(names).sort().forEach(function(name){var option=document.createElement("option");option.value=name;option.textContent=name;pod.appendChild(option);});if(names[previous])pod.value=previous;}
function draw(){var months=parseInt(time.value,10),cutoff=null;if(months){cutoff=new Date();cutoff.setMonth(cutoff.getMonth()-months);}var filtered=data.filter(function(p){return (!country.value||p.country_code===country.value)&&(!pod.value||p.pod===pod.value)&&(!container.value||p.container===container.value)&&(!line.value||p.line===line.value)&&(!lane.value||p.lane===lane.value)&&(!cutoff||new Date(p.date+"T00:00:00Z")>=cutoff);});
var groups={};
filtered.forEach(function(p){var displayLine=p.line+(p.lane?" · "+p.lane:"");var key=p.country+" · "+p.pod+" · "+p.container+" · "+displayLine+(p.currency?" · "+p.currency:"");(groups[key]||(groups[key]=[])).push(p);});
var keys=Object.keys(groups);
count.textContent=keys.length+" series · "+filtered.length+" points";
svg.replaceChildren();
legend.replaceChildren();
rows.replaceChildren();
empty.hidden=filtered.length>0;
svg.hidden=filtered.length===0;
if(!filtered.length)return;
var ns="http://www.w3.org/2000/svg",W=1000,H=390,L=72,R=24,T=24,B=55,dates=filtered.map(function(p){return new Date(p.date+"T00:00:00Z").getTime();}),values=filtered.map(function(p){return Number(p.sale_price);}),minDate=Math.min.apply(null,dates),maxDate=Math.max.apply(null,dates),dateStart=filtered.reduce(function(a,p){return !a||p.date<a?p.date:a;},""),dateEnd=filtered.reduce(function(a,p){return !a||p.date>a?p.date:a;},""),minVal=Math.min.apply(null,values),maxVal=Math.max.apply(null,values);if(maxDate===minDate)maxDate=minDate+86400000;if(maxVal===minVal)maxVal=minVal+1;var margin=(maxVal-minVal)*.1;minVal=Math.max(0,minVal-margin);maxVal+=margin;function x(d){return L+(new Date(d+"T00:00:00Z").getTime()-minDate)/(maxDate-minDate)*(W-L-R);}function y(v){return T+(maxVal-v)/(maxVal-minVal)*(H-T-B);}function el(name,attrs,text){var n=document.createElementNS(ns,name);Object.keys(attrs||{}).forEach(function(k){n.setAttribute(k,attrs[k]);});if(text!==undefined)n.textContent=text;svg.appendChild(n);return n;}for(var g=0;g<5;g++){var val=maxVal-(maxVal-minVal)*g/4,yy=y(val);el("line",{x1:L,y1:yy,x2:W-R,y2:yy,stroke:"#dbe7ed","stroke-width":1});el("text",{x:8,y:yy+4,fill:"#536d7d","font-size":12},Math.round(val).toLocaleString());}el("line",{x1:L,y1:H-B,x2:W-R,y2:H-B,stroke:"#9fb3bf"});el("text",{x:L,y:H-18,fill:"#536d7d","font-size":12},dateStart);el("text",{x:W-R-74,y:H-18,fill:"#536d7d","font-size":12},dateEnd);
keys.forEach(function(key,i){var series=groups[key].sort(function(a,b){return a.date.localeCompare(b.date);}),color=colors[i%colors.length],pts=series.map(function(p){return x(p.date).toFixed(1)+","+y(Number(p.sale_price)).toFixed(1);}).join(" ");el("polyline",{points:pts,fill:"none",stroke:color,"stroke-width":4,"stroke-linejoin":"round","stroke-linecap":"round"});series.forEach(function(p){var circle=el("circle",{cx:x(p.date),cy:y(Number(p.sale_price)),r:5,fill:color,stroke:"#fff","stroke-width":2});var title=document.createElementNS(ns,"title");title.textContent=key+" | "+p.date+" | "+Number(p.sale_price).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+" "+p.currency+" | "+p.offers+" offers";circle.appendChild(title);});var item=document.createElement("span");item.innerHTML="<i style=\"background:"+color+"\"></i>"+esc(key);legend.appendChild(item);});
filtered.sort(function(a,b){return b.date.localeCompare(a.date)||a.country.localeCompare(b.country)||a.pod.localeCompare(b.pod)||a.container.localeCompare(b.container)||a.line.localeCompare(b.line)||(a.lane||"").localeCompare(b.lane||"");}).forEach(function(p){var tr=document.createElement("tr");tr.innerHTML="<td>"+esc(p.date)+"</td><td>"+esc(p.country)+"</td><td>"+esc(p.pod)+"</td><td>"+esc(p.container)+"</td><td>"+esc(p.line)+"</td><td>"+esc(p.lane||"")+"</td><td class=\"right\">"+Number(p.sale_price).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+"</td><td>"+Number(p.offers)+"</td><td>"+esc(p.currency)+"</td>";rows.appendChild(tr);});}
country.addEventListener("change",function(){refreshPods();draw();});
pod.addEventListener("change",draw);
container.addEventListener("change",draw);
line.addEventListener("change",draw);
lane.addEventListener("change",draw);
time.addEventListener("change",draw);
refreshPods();draw();})();</script>';
    return $out;
}
