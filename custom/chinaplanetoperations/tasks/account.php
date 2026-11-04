<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/account_bootstrap.lib.php';
require_once __DIR__.'/../class/CustomerAccountExport.class.php';
require_once __DIR__.'/../class/CustomerAccountView.class.php';
require_once __DIR__.'/../class/CustomerAccountSummary.class.php';
try {
    $service=cpo_account_service($db,$user,$conf);$customer=GETPOSTINT('customer');$project=GETPOSTINT('project');$task=GETPOSTINT('task');
    $statement=$service->statement($customer,$project,$task);
    if(GETPOSTINT('audit')===1){$events=$service->auditTrail($customer);echo '<!doctype html><meta charset="utf-8"><title>Account audit</title><h1>Account audit history — latest 200 events</h1>';foreach($events as $event){echo '<details><summary>'.htmlspecialchars($event['date_creation'].' · '.$event['action'].' · User #'.$event['fk_user'].' · Entry #'.$event['fk_entry'].' · '.$event['reason'],ENT_QUOTES,'UTF-8').'</summary><pre>'.htmlspecialchars(json_encode(json_decode($event['detail_json'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8').'</pre></details>';}$db->close();exit;}
    $format=GETPOST('format','alpha');
    if($format){if(!$statement['permissions']['export'])throw new RuntimeException('Finance export access denied.',403);}
    if($format==='xlsx'){header('Cache-Control: no-store');header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="customer-account.xlsx"');echo CustomerAccountExport::xlsx($statement);$db->close();exit;}
    if($format&&$format!=='print')throw new InvalidArgumentException('Unknown export format.');
    $options=$format==='print'?['projects'=>[],'tasks'=>[]]:$service->options($customer);
    $config=['token'=>newToken(),'customer'=>$customer,'project'=>$project,'task'=>$task,'user'=>(int)$user->id,'entity'=>(int)$conf->entity,'lang'=>strpos($langs->defaultlang,'ar')===0?'ar':'en','print'=>$format==='print','api'=>'account_api.php','options'=>$options];
    header('Cache-Control: private, no-store');header("Content-Security-Policy: frame-ancestors 'self'");
    $statement['cash_summary']=CustomerAccountSummary::fromStatement($statement);
    $view=GETPOST('view','alpha');$config['view']=in_array($view,['ledger','statement','spending','feed','workspace'],true)?$view:'statement';
    $config['asset_base']=(defined('DOL_URL_ROOT')?DOL_URL_ROOT.'/custom/chinaplanetoperations':'..');
    $langs->load('chinaplanetoperations@chinaplanetoperations');
    $config['scope_labels']=array('title'=>$langs->trans('AccountManualScopeTitle'),'note'=>$langs->trans('AccountManualScopeNote'),'link'=>$langs->trans('AccountCustomerFundsLink'));
    $fundingUrl=CustomerAccountView::fundingUrl($user,(int)$statement['customer']['rowid'],dol_buildpath('/chinaplanetcustomercenter/funding.php',1),$config['lang']==='ar'?'ar_SA':'en_US');
    if ($fundingUrl !== '') $config['funding_url']=$fundingUrl;

    if($format==='print'){echo CustomerAccountView::render($statement,$config);}else{
        $conf->dol_hide_leftmenu=1; // Request-local; native top menu stays permission-aware.
        llxHeader('',($config['lang']==='ar'?'كشف الحساب':'Customer account statement'));
        echo CustomerAccountView::fragment($statement,$config);llxFooter();
    }
} catch(Throwable $e){http_response_code(403);echo '<p>'.htmlspecialchars('Account statement unavailable: '.$e->getMessage(),ENT_QUOTES,'UTF-8').'</p>';}
$db->close();
