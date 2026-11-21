<?php
/**
 * Authenticated compatibility endpoint for legacy shipment-tracking links.
 *
 * Anonymous customer-code/container/BL lookup was intentionally removed by
 * M003. The authenticated Logistics list owns entity and customer scoping.
 */
require_once __DIR__.'/../lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

cpcc_portal_require_access($user, array(
    array('chinaplanetlogistics', 'read'),
    array('chinaplanetcustomercenter', 'read'),
));

$scope = cpcc_portal_user_scope($user);
if (!empty($scope['is_external']) && (int) $scope['socid'] <= 0) accessforbidden();

$search = trim((string) GETPOST('search', 'alphanohtml'));
$url = dol_buildpath('/chinaplanetlogistics/list.php', 1);
if ($search !== '') $url .= '?search_any='.urlencode($search);

header('Location: '.$url, true, 302);
exit;
