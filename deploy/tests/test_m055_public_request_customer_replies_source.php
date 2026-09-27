<?php

$root = getenv('M055_DOL_DOCUMENT_ROOT') ?: dirname(__DIR__, 2);
$page = file_get_contents($root.'/custom/chinaplanetoperations/operations/public_requests.php');
$service = file_get_contents($root.'/custom/chinaplanetoperations/class/PublicRequestContactService.class.php');
$css = file_get_contents($root.'/custom/chinaplanetoperations/css/operations.css');

$checks = array(
    'page uses CSRF token enforcement'=>strpos($page, "define('CSRFCHECK_WITH_TOKEN', 1)") !== false && strpos($page, 'name="token" value="<?php echo newToken(); ?>"') !== false,
    'send requires both request update and email rights'=>strpos($page, "cpo_require(\$user, 'publicrequest_update');\n    cpo_require(\$user, 'email_write');") !== false,
    'recipient is loaded server side from durable request'=>strpos($page, '$requestToContact = $service->get($id);') !== false && strpos($service, "\$request['email']") !== false,
    'email form does not accept a posted recipient'=>strpos($page, 'name="email_to"') === false && strpos($page, 'name="recipient"') === false,
    'sender comes from Dolibarr server configuration'=>strpos($service, "getDolGlobalString('MAIN_MAIL_EMAIL_FROM')") !== false,
    'subject and body are normalized to plain text server side'=>substr_count($service, 'dol_string_nohtmltag(') >= 2,
    'email is sent through standard Dolibarr mail class'=>strpos($service, 'new CMailFile(') !== false && strpos($service, '->sendfile()') !== false,
    'quotation replies use standard proposal tracker'=>strpos($service, "'pro'.((int) \$request['fk_propal'])") !== false,
    'outgoing email is recorded as an Agenda event'=>strpos($service, 'new ActionComm(') !== false && strpos($service, "\$event->code = 'AC_EMAIL';") !== false,
    'WhatsApp uses wa.me and a bounded international number'=>strpos($service, "return 'https://wa.me/'") !== false && strpos($service, "preg_match('/^[1-9][0-9]{7,14}$/', \$digits)") !== false,
    'WhatsApp opens a separate protected browser tab'=>strpos($page, 'target="_blank" rel="noopener noreferrer"') !== false,
    'WhatsApp requires request update permission'=>strpos($page, '$canContactByWhatsApp = $isActive && $whatsappUrl !== \'\' && cpo_can($user, \'publicrequest_update\');') !== false,
    'WhatsApp never auto-sends a message'=>strpos($service, 'api.whatsapp.com/send') === false && strpos($service, 'graph.facebook.com') === false,
    'customer contact UI has responsive mobile rules'=>strpos($css, '.cpo-contact-actions') !== false && strpos($css, '@media(max-width:700px)') !== false && strpos($css, '.cpo-customer-email-actions{align-items:stretch;flex-direction:column}') !== false,
    'no credential material is present'=>stripos($page.$service, 'titan.email') === false && stripos($page.$service, 'password') === false,
);

$failed = array();
foreach ($checks as $label=>$ok) {
    echo ($ok ? 'PASS ' : 'FAIL ').$label."\n";
    if (!$ok) $failed[] = $label;
}

if ($failed) {
    fwrite(STDERR, 'M055 source verification failed: '.implode(', ', $failed)."\n");
    exit(1);
}

echo "M055_PUBLIC_REQUEST_CUSTOMER_REPLIES_SOURCE=PASS\n";
