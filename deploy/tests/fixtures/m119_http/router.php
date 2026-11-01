<?php
declare(strict_types=1);
// Disposable, loopback-only HTTP fixture. Never deploy or use as an authentication service.
if (PHP_SAPI!=='cli-server' || !in_array($_SERVER['REMOTE_ADDR']??'',array('127.0.0.1','::1'),true)) {http_response_code(404);exit;}
$state=realpath((string)getenv('M119_UI_STATE'));
if (!$state || !is_file($state.'/.m119-ui-synthetic')) {http_response_code(503);exit;}
$module=dirname(__DIR__,4).'/custom/chinaplanetoperations/office_expenses';
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path==='/employee.css') {header('Content-Type: text/css');readfile($module.'/employee.css');exit;}
$assets=array('/employee.js'=>'text/javascript','/assets/fontawesome.css'=>'text/css','/assets/fontawesome-solid.css'=>'text/css','/assets/fa-solid-900.woff2'=>'font/woff2','/assets/Cairo.ttf'=>'font/ttf');
if(isset($assets[$path])){header('Content-Type: '.$assets[$path]);readfile($module.$path);exit;}
if ($path==='/__fixture/session') {
    $ticket=$_GET['ticket']??'';
    if (!is_string($ticket)||!preg_match('/^[a-f0-9]{48}$/D',$ticket)||!is_file($state.'/tickets/'.$ticket)) {http_response_code(401);exit;}
    $actor=file_get_contents($state.'/tickets/'.$ticket);unlink($state.'/tickets/'.$ticket);
    session_save_path($state.'/sessions');session_name('M119SESSID');session_start(array('cookie_httponly'=>true,'cookie_samesite'=>'Strict'));
    session_regenerate_id(true);$_SESSION=array('actor'=>$actor,'csrf'=>bin2hex(random_bytes(24)));
    header('Cache-Control: no-store');header('Location: /index.php',true,303);exit;
}
if ($path!=='/index.php'&&$path!=='/') {http_response_code(404);exit;}
require $module.'/index.php';
