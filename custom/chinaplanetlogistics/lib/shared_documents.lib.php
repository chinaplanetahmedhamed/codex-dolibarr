<?php
require_once __DIR__.'/shipment_loading_media.lib.php';
/** The shipment and staff Customer Center render the canonical registry, never duplicate files. */
function cpl_shared_documents_html($db,$shipment)
{
    global $user;
    $sid=(int)($shipment->id??$shipment->rowid??0);$svc=new ShipmentDocumentService($db,$user);
    try{$files=$svc->documents($sid,true);}catch(Throwable $e){return '<p role="alert">Shipment documents unavailable / المستندات غير متاحة</p>';}
    $write=$svc->can($sid,'write');$archive=$svc->can($sid,'archive');$action=dol_buildpath('/chinaplanetlogistics/documents_api.php',1);
    $e=function($v){return dol_escape_htmltag((string)$v);};
    $base=function($d,$verb)use($sid,$action,$e){return '<form method="post" action="'.$e($action).'"'.($verb==='upload'?' enctype="multipart/form-data"':'').'><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="shipment_id" value="'.$sid.'"><input type="hidden" name="document_id" value="'.(int)($d['rowid']??0).'"><input type="hidden" name="revision" value="'.(int)($d['revision']??0).'"><input type="hidden" name="action" value="'.$verb.'">';};
    $html='<section id="shipment-files-manager" class="cpl-doc-panel"><h2>Shipment documents / مستندات الشحنة</h2>';
    if($write)$html.=$base([],'upload').'<label>Upload / رفع ملف <input required type="file" name="document_file"></label><button class="button button-save">Upload / رفع</button></form>';
    if($write)$html.=cpl_loading_media_form($db,$shipment);
    if(!$files)$html.='<p>No shipment documents / لا توجد مستندات</p>';
    foreach($files as $d){if($d['status']==='archived'&&!$archive)continue;$pubs=$svc->publicationState($d['rowid']);$pub=$pubs?(int)$pubs[0]['version_no']:0;$url=$action.'?action=download&shipment_id='.$sid.'&document_id='.(int)$d['rowid'];
        $html.='<article class="cpl-shared-document" data-document-id="'.(int)$d['rowid'].'" data-document-version="'.(int)$d['version_no'].'"><strong><bdi>'.$e($d['display_name']).'</bdi></strong><small> v'.(int)$d['version_no'].' · '.$e(dol_print_size($d['file_size'])).' · '.($d['status']==='archived'?'Archived / مؤرشف':($pub===(int)$d['version_no']?'Customer visible / ظاهر للعميل':($pub?'Published v'.$pub.' / النسخة المنشورة '.$pub:'Internal / داخلي'))).'</small>';
        if($d['status']==='active')$html.=' <a class="button button-small" href="'.$e($url).'">Download / تنزيل</a>';
        if($write&&$d['status']==='active'){
            $html.='<details><summary>Options / خيارات</summary>'.$base($d,'rename').'<input required name="name" maxlength="255" value="'.$e($d['display_name']).'"><button class="button">Rename / إعادة تسمية</button></form>';
            $html.=$base($d,'upload').'<input required type="file" name="document_file"><button class="button">Replace / استبدال النسخة</button></form>';
            if($pub!==(int)$d['version_no'])$html.=$base($d,'publish').'<button class="button">Publish this version / نشر هذه النسخة</button></form>';
            if($pub)$html.=$base($d,'unpublish').'<button class="button">Unpublish / إلغاء النشر</button></form>';
            $html.='</details>';
        }
        if($archive)$html.=$base($d,$d['status']==='archived'?'restore':'archive').'<button class="button">'.($d['status']==='archived'?'Restore / استعادة':'Archive / أرشفة').'</button></form>';
        $html.='<details><summary>Versions / النسخ السابقة</summary><ul>';
        foreach($svc->versions($d['rowid']) as $v)$html.='<li>v'.(int)$v['version_no'].' · <a href="'.$e($url.'&version_id='.(int)$v['rowid']).'">'.$e($v['original_name']).'</a> · '.$e($v['date_creation']).'</li>';
        $html.='</ul></details></article>';
    }
    return $html.'</section>';
}
