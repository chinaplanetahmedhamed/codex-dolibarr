<?php
require_once __DIR__.'/../class/CustomerAccountService.class.php';
function cpo_account_service($db,$user,$conf)
{
    if(empty($conf->global->CPO_ACCOUNT_STATEMENTS_ENABLED))throw new RuntimeException('Account statements are disabled.',403);
    $policy=new CustomerAccountPolicy($db,$user,(int)$conf->entity);$policy->requireRight('read');
    $codes=array_values(array_filter(array_map('trim',explode(',', $conf->global->CPO_ACCOUNT_CURRENCIES??'USD,CNY,EGP')),function($c){return preg_match('/^[A-Z]{3}$/D',$c);}));
    if(!in_array('USD',$codes,true))$codes[]='USD';
    return new CustomerAccountService($db,$policy,(int)$conf->entity,$codes);
}
