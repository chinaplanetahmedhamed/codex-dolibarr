<?php

/**
 * Return the portal route key for an authenticated external user.
 * A null result means the request must continue unchanged.
 */
function cpcc_external_route_decision($user, $script)
{
    if (empty($user->id) || empty($user->socid) || !empty($user->admin)) return null;

    $script = (string) $script;
    if (preg_match('#/user/(logout|passwordforgotten)\.php$#', $script)) return null;
    if (preg_match('#/user/card\.php$#', $script)) return 'profile';
    // Only Dolibarr's root index is the portal-home alias. Module index pages must remain reachable.
    if (preg_match('#^/?index\.php$#', $script)) return 'home';
    if (preg_match('#/core/tools\.php$#', $script) || preg_match('#/admin/#', $script) || preg_match('#/user/#', $script)) return 'home';

    return null;
}

function cpcc_apply_external_route_guard($user, $script)
{
    $decision = cpcc_external_route_decision($user, $script);
    if ($decision === null) return 0;

    $path = $decision === 'profile'
        ? '/chinaplanetcustomercenter/profile.php'
        : '/chinaplanetcustomercenter/board.php';
    header('Location: '.dol_buildpath($path, 1), true, 302);
    exit;
}
