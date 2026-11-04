<?php
/** Actual unmodified native GETPOST/sanitizeVal; synthetic request/session/defaults only. No DB/bootstrap/server. */
error_reporting(E_ALL);set_error_handler(function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});
$core=$argv[1]??'/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-24.0.1-m118/htdocs';
if(!is_file($core.'/core/lib/functions.lib.php'))throw new RuntimeException('Explicit local native source required');
define('DOL_DOCUMENT_ROOT',$core);define('DOL_URL_ROOT','');require $core.'/core/lib/functions.lib.php';
require __DIR__.'/../../custom/chinaplanetcustomercenter/lib/financial.lib.php';
$conf=(object)['global'=>new stdClass()];$user=(object)['id'=>9];$langs=new stdClass();$mysoc=new stdClass();
$_SERVER['PHP_SELF']='/custom/chinaplanetcustomercenter/admin/financial_portfolio.php';$_SERVER['QUERY_STRING']='';
$source=file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/admin/financial_portfolio.php');
if(!preg_match('/cpcc_financial_report_period\((GETPOST\([^\n]+?\)), \$reportingDay\)/',$source,$match))throw new RuntimeException('Actual page retrieval expression missing');
$read=static function()use($match){return eval('return '.$match[1].';');};
$checks=0;$cases=[];function ng_assert($v,$why){$GLOBALS['checks']++;if(!$v)throw new RuntimeException($why);}
$today='2026-11-04';$sessionKey='lastsearch_values_chinaplanetcustomercenter/admin/financial_portfolio.php';
foreach([
 ['malformed GET not restored into today',['asof'=>'not-a-date','restore_lastsearch_values'=>1],[],[$sessionKey=>json_encode(['asof'=>$today])],'invalid_date'],
 ['past GET not overridden by saved today',['asof'=>'2026-11-03','restore_lastsearch_values'=>1],[],[$sessionKey=>json_encode(['asof'=>$today])],'historical_unavailable'],
 ['placeholder remains literal invalid date',['asof'=>'__YEAR__-11-04'],[],[],'invalid_date'],
 ['GET malformed cannot use POST today',['asof'=>'bad'],['asof'=>$today],[],'invalid_date'],
 ['calendar rollover remains invalid',['asof'=>'2026-02-31'],[],[],'invalid_date'],
 ['HTML remains invalid rather than sanitized empty',['asof'=>'<script>'],[],[],'invalid_date'],
 ['array remains invalid',['asof'=>[$today]],[],[],'invalid_date'],
 ['valid current date remains current',['asof'=>$today],[],[],'current'],
 ['missing date is explicit current default',[],[],[],'current'],
 ['empty GET remains default current',['asof'=>''],[],[],'current'],
 ['object default cannot replace missing GET',['action'=>'create'],[],[],'current'],
]as[$name,$get,$post,$session,$mode]){
 $_GET=$get;$_POST=$post;$_SESSION=$session;$object=(object)['fields'=>['asof'=>['default'=>'2000-01-01']]];
 $value=$read();$r=cpcc_financial_report_period($value,$today);ng_assert($r['mode']===$mode,$name.'; observed '.$r['mode']);
 if(isset($get['asof']))ng_assert($value===$get['asof'],'Native retrieval changed explicit input');
 $cases[]=['name'=>$name,'status'=>'passed'];
}
echo json_encode(['checks'=>$checks,'cases'=>$cases,'scope'=>'Actual Dolibarr24 GETPOST/sanitizeVal and actual page retrieval expression; synthetic request/session/defaults; no DB/authenticated ERP','native_functions_sha256'=>hash_file('sha256',$core.'/core/lib/functions.lib.php')],JSON_PRETTY_PRINT)."\n";
