<?php

$tmp = sys_get_temp_dir().'/m055-contact-'.bin2hex(random_bytes(4));
mkdir($tmp.'/core/class', 0700, true);
mkdir($tmp.'/comm/action/class', 0700, true);
file_put_contents($tmp.'/core/class/CMailFile.class.php', "<?php class CMailFile {}\n");
file_put_contents($tmp.'/comm/action/class/actioncomm.class.php', "<?php class ActionComm {}\n");
define('DOL_DOCUMENT_ROOT', $tmp);
require dirname(__DIR__, 2).'/custom/chinaplanetoperations/class/PublicRequestContactService.class.php';

$cases = array(
    array('Egypt international digits', '201226156863', 'EG', '201226156863'),
    array('Egypt local mobile', '01226156863', 'EG', '201226156863'),
    array('Saudi local mobile', '0551234567', 'SA', '966551234567'),
    array('explicit plus', '+86 138 0013 8000', 'CN', '8613800138000'),
    array('explicit 00', '00 971 50 123 4567', 'AE', '971501234567'),
    array('invalid letters', 'call Ahmed', 'EG', ''),
    array('invalid short', '12345', 'EG', ''),
    array('invalid leading zero without known country', '01234567890', '', ''),
);

$failed = array();
foreach ($cases as $case) {
    $actual = PublicRequestContactService::whatsappNumber($case[1], $case[2]);
    $ok = $actual === $case[3];
    echo ($ok ? 'PASS ' : 'FAIL ').$case[0]."\n";
    if (!$ok) $failed[] = $case[0].' expected='.$case[3].' actual='.$actual;
}

$url = PublicRequestContactService::whatsappUrl(array(
    'phone'=>'201226156863', 'country_code'=>'EG', 'contact_name'=>'Ahmed', 'ref'=>'WEB-TEST-001',
    'request'=>array('locale'=>'ar'),
));
$urlOk = strpos($url, 'https://wa.me/201226156863?text=') === 0 && strpos(rawurldecode($url), 'WEB-TEST-001') !== false;
echo ($urlOk ? 'PASS ' : 'FAIL ')."safe WhatsApp URL and request reference\n";
if (!$urlOk) $failed[] = 'safe WhatsApp URL';

@unlink($tmp.'/core/class/CMailFile.class.php');
@unlink($tmp.'/comm/action/class/actioncomm.class.php');
@rmdir($tmp.'/core/class');
@rmdir($tmp.'/core');
@rmdir($tmp.'/comm/action/class');
@rmdir($tmp.'/comm/action');
@rmdir($tmp.'/comm');
@rmdir($tmp);

if ($failed) {
    fwrite(STDERR, 'M055 contact service verification failed: '.implode(', ', $failed)."\n");
    exit(1);
}

echo "M055_PUBLIC_REQUEST_CONTACT_SERVICE=PASS\n";
