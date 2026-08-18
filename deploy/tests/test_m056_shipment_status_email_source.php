<?php

$root = getenv('M056_DOL_DOCUMENT_ROOT') ?: dirname(__DIR__, 2);
$page = file_get_contents($root.'/custom/chinaplanetlogistics/card.php');
$object = file_get_contents($root.'/custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php');
$service = file_get_contents($root.'/custom/chinaplanetlogistics/class/ShipmentStatusEmailService.class.php');

$checks = array(
    'shipment update remains CSRF protected'=>strpos($page, "define('CSRFCHECK_WITH_TOKEN', 1)") !== false,
    'send option is explicit and only shown while editing'=>strpos($page, "if (\$action === 'edit')") !== false && strpos($page, 'name="send_status_email"') !== false,
    'standard shipment-send permission gates email'=>strpos($page, "!getDolGlobalString('MAIN_USE_ADVANCED_PERMS')") !== false && strpos($page, "hasRight('expedition', 'shipping_advance', 'send')") !== false,
    'external customers cannot send'=>strpos($page, '$cansendshipmentemail = false;') !== false,
    'actual committed status change is captured server side'=>strpos($object, "\$this->last_status_change = array('from'=>") !== false && strpos($page, '$resu = $object->update($user);') < strpos($page, '$emailService->send('),
    'no status change prevents send'=>strpos($page, 'empty($object->last_status_change)') !== false,
    'non-public update prevents send'=>strpos($page, 'empty($object->audit_customer_visible)') !== false,
    'recipient is resolved from entity-scoped third party'=>strpos($service, 'FROM ".MAIN_DB_PREFIX."societe') !== false && strpos($service, 's.entity=') !== false && strpos($service, 's.status=1') !== false,
    'browser cannot post recipient or sender'=>strpos($page, 'name="email_to"') === false && strpos($page, 'name="email_from"') === false,
    'sender comes from Dolibarr configuration'=>strpos($service, "getDolGlobalString('MAIN_MAIL_EMAIL_FROM')") !== false,
    'standard Dolibarr mail class sends after save'=>strpos($service, 'new CMailFile(') !== false && strpos($service, '->sendfile()') !== false,
    'mail subject removes CRLF before send'=>strpos($service, "preg_replace('/[\\r\\n]+/'") !== false && strpos($service, "'subject'=>\$this->headerText(\$subject, 255)") !== false,
    'customer language supports Arabic Chinese and English'=>strpos($service, "return 'ar_SA'") !== false && strpos($service, "return 'zh_CN'") !== false && strpos($service, "return 'en_US'") !== false,
    'email content uses customer-safe allowlist'=>strpos($service, 'customer_note') === false && strpos($service, '->notes') === false && strpos($service, 'shipping_line') === false && strpos($service, 'cost') === false && strpos($service, 'margin') === false,
    'email contains authenticated shipment link'=>strpos($service, "DOL_MAIN_URL_ROOT.'/custom/chinaplanetlogistics/card.php?id='") !== false,
    'outgoing email is recorded against shipment and customer'=>strpos($service, "\$event->elementtype = 'chinaplanetlogistics';") !== false && strpos($service, '$event->socid = (int) $shipment->fk_soc;') !== false,
    'email failure does not roll back shipment'=>strpos($page, 'catch (ShipmentStatusEmailException $e)') !== false && strpos($page, 'setEventMessages($langs->trans($e->getMessage()), null, \'warnings\')') !== false,
    'unexpected mail errors are contained after shipment save'=>strpos($page, 'catch (Throwable $e)') !== false && strpos($page, "Unexpected shipment status email failure") !== false,
    'commit failure prevents send'=>strpos($object, 'if (!$this->db->commit())') !== false && strpos($object, '$this->last_status_change = null;') !== false,
    'bounded retry reuses latest scoped public status event'=>strpos($page, "\$action === 'resend_status_email'") !== false && strpos($service, 'public function resendLatest(') !== false && strpos($service, "event_type='status_changed' AND customer_visible=1") !== false,
    'no credential material is present'=>stripos($page.$service, 'titan.email') === false && stripos($page.$service, 'password') === false,
);

$failed = array();
foreach ($checks as $label=>$ok) {
    echo ($ok ? 'PASS ' : 'FAIL ').$label."\n";
    if (!$ok) $failed[] = $label;
}
if ($failed) {
    fwrite(STDERR, 'M056 shipment status email source verification failed: '.implode(', ', $failed)."\n");
    exit(1);
}
echo "M056_SHIPMENT_STATUS_EMAIL_SOURCE=PASS\n";
