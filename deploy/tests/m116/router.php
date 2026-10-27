<?php
/** LOCAL SYNTHETIC UI harness ONLY. NOT a Dolibarr bootstrap/authentication substitute. */
if(getenv('M116_UI_FIXTURE')!=='1'){http_response_code(403);exit('Fixture disabled');}
$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$module=realpath(__DIR__.'/../../../custom/chinaplanetoperations');
if(preg_match('#^/(css|js)/([a-z_]+\.(css|js))$#D',$uri,$m)){$f=$module.'/'.$m[1].'/'.$m[2];if(!is_file($f)){http_response_code(404);exit;}header('Content-Type: '.($m[1]==='css'?'text/css':'text/javascript'));readfile($f);return;}
$_SERVER['DOCUMENT_ROOT']=__DIR__.'/native';
if($uri==='/tasks/account.php'){require $module.'/tasks/account.php';return;}
if($uri==='/tasks/account_summary.php'){require $module.'/tasks/account_summary.php';return;}
if($uri==='/tasks/account_api.php'){require $module.'/tasks/account_api.php';return;}
http_response_code(404);
