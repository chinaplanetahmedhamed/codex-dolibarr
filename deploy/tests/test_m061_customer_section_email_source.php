<?php

function m061EmailAssert($condition, $message)
{
    if (!$condition) { fwrite(STDERR, 'FAIL '.$message."\n"); exit(1); }
    print 'PASS '.$message."\n";
}

$service = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/class/CustomerSectionEmailService.class.php');
$page = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/section_email.php');
$descriptor = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/core/modules/modChinaPlanetCustomerCenter.class.php');
$balance = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/balance.php');
$invoice = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/invoice.php');
$project = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/project.php');
$dashboardCss = file_get_contents(__DIR__.'/../../custom/chinaplanetcustomercenter/css/customer_dashboard.css');

m061EmailAssert(strpos($service, "array('financial', 'invoice', 'project', 'shipment')") !== false, 'only approved customer sections are supported');
m061EmailAssert(strpos($service, "FROM ".'".MAIN_DB_PREFIX."'."societe") !== false || strpos($service, 'MAIN_DB_PREFIX."societe') !== false, 'recipient is loaded from the owned customer record');
m061EmailAssert(strpos($service, 'FILTER_VALIDATE_EMAIL') !== false, 'recipient and sender are validated');
m061EmailAssert(strpos($service, 'new CMailFile(') !== false && strpos($service, '->sendfile()') !== false, 'standard Dolibarr mail transport is used');
m061EmailAssert(strpos($service, "['body_html']") !== false && strpos($service, ', 1, $from,') !== false, 'mail transport receives the previewed HTML body in explicit HTML mode');
m061EmailAssert(strpos($service, "['body_text']") !== false && strpos($service, "\$event->note_private=\$message['body_text']") !== false, 'Agenda history stores the plain-text audit representation');
m061EmailAssert(strpos($service, 'array_slice($transactions, max(0, count($transactions) - 12))') !== false, 'financial email is bounded to the latest 12 transactions');
m061EmailAssert(strpos($page, 'echo $preview[\'body_html\']') !== false && strpos($page, 'dol_escape_htmltag($preview[\'body\'])') === false, 'preview renders the exact safe HTML body without newline escaping');
m061EmailAssert(strpos($service, 'new ActionComm(') !== false && strpos($service, "code='AC_EMAIL'") !== false, 'outgoing email is recorded in Agenda');
m061EmailAssert(strpos($service, 'Staff access required.') !== false && strpos($service, '!empty($user->socid)') !== false, 'external customer can never invoke the send boundary');
m061EmailAssert(strpos($page, "hasRight('chinaplanetcustomercenter','email_write')") !== false, 'route requires the dedicated email permission');
m061EmailAssert(strpos($page, "name=\"recipient\"") === false && strpos($page, "name='recipient'") === false, 'preview has no arbitrary recipient input');
m061EmailAssert(strpos($page, "name=\"action\" value=\"send\"") !== false && strpos($page, 'newToken()') !== false, 'send is an explicit CSRF-protected POST action');
m061EmailAssert(strpos($descriptor, "'email_write'") !== false, 'module defines a dedicated non-default send permission');
m061EmailAssert(strpos($descriptor, '$this->rights[5][0] = 500206;') !== false, 'email permission avoids the reserved local smart-document right');
m061EmailAssert(!preg_match('/SELECT[^;]*note_private/is', $service), 'customer email composition never reads private business notes');
m061EmailAssert(substr_count($balance.$invoice.$project, 'class="button cpcc-section-email-action"') === 3, 'all section email actions use the Dolibarr button class');
m061EmailAssert(strpos($dashboardCss, '.cpcc-page-head .cpcc-section-email-action.button') !== false && strpos($dashboardCss, 'background:#ffd766!important') !== false && strpos($dashboardCss, 'color:#102f44!important') !== false, 'section email buttons enforce readable gold and navy contrast');
m061EmailAssert(strpos($dashboardCss, '.cpcc-section-email-action.button:focus-visible') !== false && strpos($dashboardCss, 'outline:3px solid #fff!important') !== false, 'section email buttons expose a visible keyboard focus ring');

print "OK M061 customer section email source contract\n";
