<?php
class CustomerAccountView
{
    public static function render(array $statement,array $config)
    {
        $ar=($config['lang']??'en')==='ar';$title=$ar?'كشف حساب العميل':'Customer account statement';$cfg=json_encode(['statement'=>$statement,'config'=>$config],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
        return '<!doctype html><html lang="'.($ar?'ar':'en').'" dir="'.($ar?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.'</title><link rel="stylesheet" href="../css/customer_account.css"><script defer src="../js/customer_account.js"></script></head><body><main id="cpo-account"><noscript>JavaScript is required for this account statement.</noscript></main><div id="account-message" role="status" aria-live="polite"></div><script type="application/json" id="account-config">'.$cfg.'</script></body></html>';
    }
}
