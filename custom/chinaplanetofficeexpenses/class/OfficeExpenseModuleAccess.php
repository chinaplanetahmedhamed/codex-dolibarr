<?php
declare(strict_types=1);

/** Native module gate; the existing pilot/service still applies its narrower scope. */
final class OfficeExpenseModuleAccess
{
    public static function allows($user,$conf): bool
    {
        return is_object($user)&&is_object($conf)
            &&(int)($user->id??0)>0&&(int)($user->status??0)===1&&empty($user->socid)
            &&!empty($conf->chinaplanetofficeexpenses->enabled)
            &&(!empty($user->admin)||(method_exists($user,'hasRight')&&($user->hasRight('chinaplanetofficeexpenses','draft')
                ||((string)($conf->global->CPO_OE_STAFF_ACCESS_ENABLED??'0')==='1'&&$user->hasRight('chinaplanetofficeexpenses','read')))));
    }
}
