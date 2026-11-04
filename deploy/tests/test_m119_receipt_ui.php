<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/m119_receipt_ui.php';

utest('save draft then select its receipt panel without altering original money',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();list($id,$state)=udraft($c);
    ucheck($state['selected_draft']['id'],$id);ucheck($state['selected_draft']['amount'],'700.10');ucheck($state['receipt_enabled'],true);ucheck($state['receipts'],array());
    $html=(new OfficeExpenseEmployeePage())->render($state,'en','synthetic-csrf');ucheck(strpos($html,'data-receipt-form')!==false,true);ucheck(strpos($html,'multipart/form-data')!==false,true);ucheck(strpos($html,'name="receipt"')!==false,true);
});
utest('attach synthetic receipt redirect list and POST download are one scoped audited flow',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();list($id,$state)=udraft($c);$files=ufiles('إيصال.png');
    $added=$c->handle('POST',array(),uupload($id),$files);ucheck($added['status'],303);parse_str(parse_url($added['redirect'],PHP_URL_QUERY),$query);
    $state=$c->handle('GET',$query,array());ucheck($state['receipt_saved'],true);ucheck(count($state['receipts']),1);$meta=$state['receipts'][0];
    $read=$c->handle('POST',array(),array('action'=>'download_receipt','token'=>'synthetic-csrf','draft_id'=>$id,'receipt_id'=>$meta['id'],'variant'=>'original'));
    ucheck($read['status'],200);ucheck($read['download']['bytes'],$files['receipt']['synthetic_bytes']);ucheck(isset($read['download']['path']),false);
    $headers=OfficeExpenseReceiptHttpResponse::headers($read['download'],$meta['id']);ucheck($headers['Content-Type'],'application/octet-stream');ucheck(strpos($headers['Content-Disposition'],"filename*=UTF-8''")!==false,true);
    ucheck(array_column($receipts->auditOwn($id),'event'),array('receipt_added','receipt_access_started'));ucheck($drafts->getOwn($id)['native_record'],null);
});
utest('same upload key retries one receipt while changed bytes conflict',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();list($id)=udraft($c);$post=uupload($id);$files=ufiles();$first=$c->handle('POST',array(),$post,$files);
    ucheck($c->handle('POST',array(),$post,$files)['redirect'],$first['redirect']);$files['receipt']['synthetic_bytes'].='changed';
    $conflict=$c->handle('POST',array(),$post,$files);ucheck($conflict['status'],409);ucheck($conflict['receipt_error'],'receipt_conflict');ucheck($conflict['receipt_values']['receipt_key'],$post['receipt_key']);ucheck(count($receipts->listOwn($id)),1);
});
utest('CSRF and native-sanitized stale POST reject before reading any upload bytes',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();list($id)=udraft($c);$post=uupload($id);$post['token']='bad';
    ucheck($c->handle('POST',array(),$post,ufiles())['status'],403);ucheck($c->handle('POST',array('errorcode'=>'InvalidToken'),array(),ufiles())['status'],403);ucheck($uploads->reads,0);ucheck($receipts->listOwn($id),array());
});
utest('other owner entity office and disabled user never read uploaded files or show private receipt data',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();list($id)=udraft($c);$c->handle('POST',array(),uupload($id),ufiles('private.png'));
    foreach(array(array(1,8,array('egypt'=>array('EGP'))),array(2,7,array('egypt'=>array('EGP'))),array(1,7,array('china'=>array('EGP'))))as$scope){
        $u=new M119ReceiptUIUser();$u->id=$scope[1];$other=new OfficeExpenseDraftService(new LocalDraftStore($pdo),new OfficeExpenseMoney(),$u,$scope[0],$scope[2],'EGP');
        $r=new OfficeExpenseReceiptService($other,new LocalReceiptStore($pdo,dirname($pdo->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file']),dirname(dirname($pdo->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file'])).'/web'));
        $source=new M119SyntheticUploadSource();$foreign=new OfficeExpenseEmployeeController($other,'synthetic-csrf','synthetic-csrf','en',$r,$source);
        $state=$foreign->handle('POST',array(),uupload($id),ufiles());ucheck($state['status'],404);ucheck($source->reads,0);ucheck($state['receipts'],array());ucheck($state['selected_draft'],null);
    }
    $user->status=0;$denied=$c->handle('GET',array('draft'=>$id),array());ucheck($denied['status'],403);ucheck(strpos((new OfficeExpenseEmployeePage())->render($denied,'en','synthetic-csrf'),'private.png'),false);
});
utest('forged context unknown actions and files attached to save cannot create or delete records',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();list($id)=udraft($c);
    foreach(array('owner_id'=>'8','entity'=>'2','path'=>'/etc/passwd')as$key=>$value)ucheck($c->handle('POST',array(),array_merge(uupload($id),array($key=>$value)),ufiles())['status'],422);
    ucheck($c->handle('POST',array(),array_merge(uupload($id),array('action'=>'delete_receipt')),ufiles())['status'],403);
    ucheck($c->handle('POST',array(),array('action'=>'save','token'=>'synthetic-csrf'),ufiles())['status'],415);ucheck($uploads->reads,0);ucheck(count($drafts->listOwn()),1);
});
utest('quota invalid image and effective upload cap have distinct safe retained-form errors',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup(1);list($id)=udraft($c);ucheck($c->handle('POST',array(),uupload($id),ufiles())['status'],303);
    $quota=$c->handle('POST',array(),uupload($id,'synthetic-ui-quota'),ufiles());ucheck($quota['status'],409);ucheck($quota['receipt_error'],'receipt_quota');
    $invalid=$c->handle('POST',array(),uupload($id,'synthetic-ui-invalid'),ufiles('receipt.pdf'));ucheck($invalid['status'],422);ucheck($invalid['receipt_values']['receipt_key'],'synthetic-ui-invalid');
    $uploads->cap=1;$large=$c->handle('POST',array(),uupload($id,'synthetic-ui-size'),ufiles());ucheck($large['status'],413);ucheck($large['receipt_error'],'receipt_too_large');ucheck(count($receipts->listOwn($id)),1);
});
utest('advance receipt validation keeps the advances register and source context',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();udraft($c);
    $advance=$drafts->submit(array('kind'=>'advance','office'=>'egypt','amount'=>'100.00','currency'=>'EGP','occurred_on'=>'2026-11-01','source_ref'=>'SYNTHETIC advance','purpose'=>'SYNTHETIC received advance'),'synthetic-ui-advance');
    $state=$c->handle('POST',array(),uupload($advance['id'],'synthetic-advance-receipt'),ufiles('invalid.pdf'));
    ucheck($state['status'],422);ucheck($state['view'],'advances');ucheck($state['selected_draft']['id'],$advance['id']);ucheck(array_column($state['filtered_drafts'],'kind'),array('advance'));
});
utest('receipt feature is hidden and fails closed without a bound receipt service',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();list($id)=udraft($c);$plain=new OfficeExpenseEmployeeController($drafts,'synthetic-csrf','synthetic-csrf');
    $state=$plain->handle('GET',array('draft'=>$id),array());ucheck($state['receipt_enabled'],false);ucheck(strpos((new OfficeExpenseEmployeePage())->render($state,'en','synthetic-csrf'),'data-receipt-form'),false);
    ucheck($plain->handle('POST',array(),uupload($id),ufiles())['status'],503);
    $other=new OfficeExpenseDraftService(new LocalDraftStore($pdo),new OfficeExpenseMoney(),$user,1,array('egypt'=>array('EGP')),'EGP');
    ureject(function()use($other,$receipts){new OfficeExpenseEmployeeController($other,'token','token','en',$receipts);});
});
utest('POST download requires token and source binding and never accepts receipt paths',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();list($id)=udraft($c);$c->handle('POST',array(),uupload($id),ufiles());$meta=$receipts->listOwn($id)[0];
    $post=array('action'=>'download_receipt','token'=>'bad','draft_id'=>$id,'receipt_id'=>$meta['id']);ucheck($c->handle('POST',array(),$post)['status'],403);
    $post['token']='synthetic-csrf';$post['receipt_id']='../state.sqlite';ucheck($c->handle('POST',array(),$post)['status'],404);
    $post['receipt_id']=$meta['id'];$post['variant']='../../original';ucheck($c->handle('POST',array(),$post)['status'],422);
    ucheck(count($receipts->auditOwn($id)),1);ucheck($c->handle('GET',array('draft'=>$id,'download'=>$meta['id']),array())['download'],null);
});
utest('Arabic English receipt rendering escapes names has native form fields and no private paths',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c,$base)=usetup();list($id)=udraft($c);$c->handle('POST',array(),uupload($id),ufiles('<receipt>.png'));
    foreach(array('ar','en')as$lang){$state=$c->handle('GET',array('draft'=>$id,'lang'=>$lang),array());$html=(new OfficeExpenseEmployeePage())->render($state,$lang,'synthetic-csrf');
        ucheck(strpos($html,'&lt;receipt&gt;.png')!==false,true);ucheck(strpos($html,'<receipt>.png'),false);ucheck(strpos($html,$base),false);
        ucheck(strpos($html,'name="action" value="download_receipt"')!==false,true);ucheck(strpos($html,'name="token" value="synthetic-csrf"')!==false,true);
        ucheck(strpos($html,$lang==='ar'?'إرفاق إيصال':'Attach receipt')!==false,true);ucheck(strpos($html,'delete_receipt'),false);
        preg_match('/class="language-link" href="([^"]+)"/',$html,$match);parse_str(parse_url(html_entity_decode($match[1]),PHP_URL_QUERY),$link);ucheck($link['draft'],$id);
    }
});
utest('zero effective upload limit disables new uploads while existing receipts remain downloadable',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c)=usetup();list($id)=udraft($c);$c->handle('POST',array(),uupload($id),ufiles());$meta=$receipts->listOwn($id)[0];$uploads->cap=0;
    $state=$c->handle('GET',array('draft'=>$id),array());ucheck($state['receipt_upload_limit'],0);ucheck($c->handle('POST',array(),uupload($id,'synthetic-disabled-cap'),ufiles())['status'],503);
    $download=$c->handle('POST',array(),array('action'=>'download_receipt','token'=>'synthetic-csrf','draft_id'=>$id,'receipt_id'=>$meta['id']));ucheck($download['status'],200);ucheck($download['download']['bytes'],uimage());
});
utest('trusted image-format subset controls the picker and server while preserving older receipts',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c,$base)=usetup();list($id)=udraft($c);
    $i=imagecreatetruecolor(2,2);ob_start();imagejpeg($i);$jpeg=(string)ob_get_clean();imagedestroy($i);
    $existing=$receipts->attach($id,'synthetic-older-jpeg','older.jpg',$jpeg);
    $store=new LocalReceiptStore($pdo,$base.'/private',$base.'/web');$png=new OfficeExpenseReceiptService($drafts,$store,array('formats'=>array('image/png')));
    $strict=new OfficeExpenseEmployeeController($drafts,'synthetic-csrf','synthetic-csrf','en',$png,$uploads);
    $state=$strict->handle('GET',array('draft'=>$id),array());$html=(new OfficeExpenseEmployeePage())->render($state,'en','synthetic-csrf');ucheck(strpos($html,'accept="image/png,.png"')!==false,true);
    $files=array('receipt'=>array('name'=>'new.jpg','synthetic_bytes'=>$jpeg));ucheck($strict->handle('POST',array(),uupload($id,'synthetic-restricted-jpeg'),$files)['status'],422);
    $read=$strict->handle('POST',array(),array('action'=>'download_receipt','token'=>'synthetic-csrf','draft_id'=>$id,'receipt_id'=>$existing['id']));ucheck($read['download']['bytes'],$jpeg);
    ureject(function()use($drafts,$store){new OfficeExpenseReceiptService($drafts,$store,array('formats'=>array('application/pdf')));});
});
utest('production HTTP upload reader rejects ordinary local files and array-shaped metadata',function(){
    list($pdo,$user,$drafts,$receipts,$uploads,$c,$base)=usetup();$path=$base.'/private/source.png';file_put_contents($path,uimage());$reader=new OfficeExpenseHttpUploadSource();
    $file=array('name'=>'receipt.png','tmp_name'=>$path,'size'=>filesize($path),'error'=>UPLOAD_ERR_OK,'type'=>'image/png');
    ureject(function()use($reader,$file){$reader->read($file,5242880);});$file['name']=array('nested.png');ureject(function()use($reader,$file){$reader->read($file,5242880);});
    ucheck($reader->effectiveLimit(5242880)<=5242880,true);
});
utest('download headers encode UTF8 safely and reject header injection',function(){
    $id=str_repeat('a',32);$d=array('filename'=>'إيصال.png','bytes'=>uimage(),'mime'=>'application/octet-stream','disposition'=>'attachment');
    $h=OfficeExpenseReceiptHttpResponse::headers($d,$id);ucheck(strpos($h['Content-Disposition'],'filename="receipt-')!==false,true);ucheck($h['Content-Length'],(string)strlen($d['bytes']));
    $d['filename']="receipt.png\r\nInjected: yes";ureject(function()use($d,$id){OfficeExpenseReceiptHttpResponse::headers($d,$id);});
});
echo "$passed passed, $failed failed; synthetic controller/upload-source/rendering flow only; no HTTP server or credentials\n";exit($failed?1:0);
