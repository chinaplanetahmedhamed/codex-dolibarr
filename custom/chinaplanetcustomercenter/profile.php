<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/portal.lib.php';

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
if (empty($scope['is_external']) || empty($scope['socid'])) accessforbidden();

$customerName = cpcc_portal_customer_name($db, (int) $scope['socid']);
llxHeader('', 'الملف الشخصي / Profile', '', '', 0, 0, array(), array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'));
cpcc_portal_navigation($user, 'profile');

print '<main class="cpcc-dashboard" dir="rtl"><header class="cpcc-page-head"><div><span class="cpcc-eyebrow">CUSTOMER PROFILE</span><h1>الملف الشخصي / Profile</h1><p>بيانات حساب بوابة العميل / Customer portal account information</p></div></header>';
print '<div class="cpcc-table-wrap"><table class="cpcc-table">';
print '<tr><td class="titlefield">الاسم / Name</td><td>'.dol_escape_htmltag($user->getFullName($langs)).'</td></tr>';
print '<tr><td>الشركة / Company</td><td>'.dol_escape_htmltag($customerName).'</td></tr>';
print '<tr><td>البريد الإلكتروني / Email</td><td>'.dol_escape_htmltag((string) $user->email).'</td></tr>';
print '<tr><td>لغة الواجهة / Language</td><td>'.dol_escape_htmltag((string) $user->lang).'</td></tr>';
print '</table></div>';
print '<p class="opacitymedium">لتغيير كلمة المرور، استخدم مسار استعادة كلمة المرور الآمن من صفحة تسجيل الدخول.</p>';
print '</main>';
llxFooter();
$db->close();
