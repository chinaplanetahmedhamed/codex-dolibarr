<?php
class CustomerAccountView
{
    public static function fragment(array $statement,array $config)
    {
        $cfg=json_encode(['statement'=>$statement,'config'=>$config],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
        $ar=($config['lang']??'en')==='ar';
        $base=htmlspecialchars($config['asset_base']??'..',ENT_QUOTES,'UTF-8');
        return '<link rel="stylesheet" href="'.$base.'/css/customer_account.css?v=2"><script defer src="'.$base.'/js/customer_account_presentations.js?v=2"></script><script defer src="'.$base.'/js/customer_account.js?v=2"></script><main id="cpo-account" lang="'.($ar?'ar':'en').'" dir="'.($ar?'rtl':'ltr').'"><noscript>JavaScript is required for this account statement.</noscript></main><div id="account-message" role="status" aria-live="polite"></div><script type="application/json" id="account-config">'.$cfg.'</script>';
    }
    public static function render(array $statement,array $config)
    {
        $ar=($config['lang']??'en')==='ar';
        return '<!doctype html><html lang="'.($ar?'ar':'en').'" dir="'.($ar?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.($ar?'كشف الحساب':'Customer account statement').'</title></head><body>'.self::fragment($statement,$config).'</body></html>';
    }
}
