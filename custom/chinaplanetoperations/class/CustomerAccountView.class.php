<?php
class CustomerAccountView
{
    /** Presentation link only; the destination keeps its own access checks. */
    public static function fundingUrl($actor, $customer, $base, $language = 'en_US')
    {
        if (empty($actor->id) || !empty($actor->socid) || !empty($actor->fk_soc) || (isset($actor->statut) && (int)$actor->statut !== 1) || (int)$customer <= 0) return '';
        if (empty($actor->admin) && !$actor->hasRight('chinaplanetcustomercenter','read') && !$actor->hasRight('chinaplanetcustomercenter','write')) return '';
        return $base.'?'.http_build_query(array('socid'=>(int)$customer,'lang'=>$language==='ar_SA'?'ar_SA':'en_US'));
    }
    public static function fragment(array $statement,array $config)
    {
        $cfg=json_encode(['statement'=>$statement,'config'=>$config],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
        $ar=($config['lang']??'en')==='ar';
        $base=htmlspecialchars($config['asset_base']??'..',ENT_QUOTES,'UTF-8');
        $scope='';
        if (!empty($config['scope_labels'])) {
            $labels=$config['scope_labels']; $escape=function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};
            $scope='<section id="cpo-account-scope" class="account-note" lang="'.($ar?'ar':'en').'" dir="'.($ar?'rtl':'ltr').'"><h2>'.$escape($labels['title']??'').'</h2><p>'.$escape($labels['note']??'').'</p>';
            $url=$config['funding_url']??'';
            if (empty($config['print']) && is_string($url) && preg_match('~^/(?!/)[^\x00-\x20]*$~D',$url)) $scope.='<p><a class="button" href="'.$escape($url).'">'.$escape($labels['link']??'').'</a></p>';
            $scope.='</section>';
        }

        return '<link rel="stylesheet" href="'.$base.'/css/customer_account.css?v=2"><script defer src="'.$base.'/js/customer_account_presentations.js?v=2"></script><script defer src="'.$base.'/js/customer_account.js?v=2"></script>'.$scope.'<main id="cpo-account" lang="'.($ar?'ar':'en').'" dir="'.($ar?'rtl':'ltr').'"><noscript>JavaScript is required for this account statement.</noscript></main><div id="account-message" role="status" aria-live="polite"></div><script type="application/json" id="account-config">'.$cfg.'</script>';
    }
    public static function render(array $statement,array $config)
    {
        $ar=($config['lang']??'en')==='ar';
        return '<!doctype html><html lang="'.($ar?'ar':'en').'" dir="'.($ar?'rtl':'ltr').'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.($ar?'كشف الحساب':'Customer account statement').'</title></head><body>'.self::fragment($statement,$config).'</body></html>';
    }
}
