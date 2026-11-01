<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argc!==2||$argv[1][0]!=='/'){
    fwrite(STDERR,"Usage: php generate_receipt_preview.php /absolute/owned/output\n");exit(1);
}
require_once dirname(__DIR__,3).'/deploy/tests/fixtures/m119_receipt_ui.php';
$output=rtrim($argv[1],'/');
if(!is_dir($output)&&!mkdir($output,0700,true))throw new RuntimeException('Cannot create preview output.');
if(is_link($output)||(!is_file($output.'/.m119-static-receipt-preview')&&count(scandir($output))>2))throw new RuntimeException('Use a new or owned preview directory.');
file_put_contents($output.'/.m119-static-receipt-preview','Synthetic static receipt UI only');
list($pdo,$user,$drafts,$receipts,$uploads,$controller)=usetup();list($id,$empty)=udraft($controller);
$controller->handle('POST',array(),uupload($id,'synthetic-preview-one'),ufiles('إيصال_مستلزمات_مكتب_الصين_中国_2026.png'));
$controller->handle('POST',array(),uupload($id,'synthetic-preview-two'),ufiles('SYNTHETIC-office-supplies-receipt.png'));
$meta=$receipts->listOwn($id)[1];$page=new OfficeExpenseEmployeePage();
$module=$root.'/custom/chinaplanetoperations/office_expenses';
$css=file_get_contents($module.'/assets/fontawesome.css')."\n".file_get_contents($module.'/assets/fontawesome-solid.css')."\n".file_get_contents($module.'/employee.css');
$css=preg_replace_callback('/url\(([^)]+)\)/',static function($m)use($module){
    $name=basename(trim($m[1]," '\""));$mime=array('Cairo.ttf'=>'font/ttf','fa-solid-900.woff2'=>'font/woff2');
    if(!isset($mime[$name]))throw new RuntimeException('Unbundled static font asset.');
    return 'url(data:'.$mime[$name].';base64,'.base64_encode(file_get_contents($module.'/assets/'.$name)).')';
},$css);
$js=file_get_contents($module.'/employee.js');$manifest=array('synthetic'=>true,'server_started'=>false,'files'=>array());
foreach(array('ar','en') as $lang)foreach(array('saved','empty','invalid','overview','advances') as $kind){
    if($kind==='saved')$state=$controller->handle('GET',array('draft'=>$id,'lang'=>$lang,'receipt_saved'=>$meta['id']),array());
    elseif($kind==='empty'){$state=$empty;$state['language']=$lang;}
    elseif($kind==='invalid'){$post=uupload($id,'synthetic-preview-invalid');$post['lang']=$lang;$state=$controller->handle('POST',array(),$post,ufiles('receipt.pdf'));}
    else $state=$controller->handle('GET',array('view'=>$kind,'lang'=>$lang),array());
    $state['static_preview']=true;$html=$page->render($state,$lang,'synthetic-preview-token');
    $html=preg_replace('/<link rel="stylesheet"[^>]*>/','',$html);
    $html=preg_replace('/<script src="employee\.js[^>]*><\/script>/','',$html);
    $html=str_replace('</head>','<style>'.$css.'</style><script>document.addEventListener("DOMContentLoaded",()=>{'.$js.'});</script></head>',$html);
    $name=in_array($kind,array('overview','advances'),true)?$lang.'-'.$kind.'.html':($kind==='saved'?'':$kind.'-').$lang.'.html';file_put_contents($output.'/'.$name,$html);
    $manifest['files'][$name]=hash('sha256',$html);
}
file_put_contents($output.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo "10 self-contained synthetic pages generated; all submission controls disabled; no server or credentials\n";
