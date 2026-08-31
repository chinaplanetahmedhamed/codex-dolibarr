<?php

$tmp = sys_get_temp_dir().'/m061-email-'.bin2hex(random_bytes(4));
mkdir($tmp.'/core/class', 0700, true);
mkdir($tmp.'/comm/action/class', 0700, true);
file_put_contents($tmp.'/core/class/CMailFile.class.php', <<<'PHP'
<?php
class CMailFile {
    public static $instances = array();
    public static $sendResult = true;
    public $error = 'synthetic SMTP failure';
    public $msgid = 'm061-message';
    public $subject;
    public $sendto;
    public $body;
    public $msgishtml;
    public function __construct($subject, $sendto, $from, $body, ...$unused) {
        $this->subject = $subject; $this->sendto = $sendto; $this->body = $body;
        $this->msgishtml = isset($unused[6]) ? $unused[6] : null;
        self::$instances[] = $this;
    }
    public function sendfile() { return self::$sendResult; }
}
PHP
);
file_put_contents($tmp.'/comm/action/class/actioncomm.class.php', <<<'PHP'
<?php
#[AllowDynamicProperties]
class ActionComm {
    public static $instances = array();
    public $error = 'synthetic Agenda failure';
    public function __construct($db) { self::$instances[] = $this; }
    public function create($user) { return 861; }
}
PHP
);

define('DOL_DOCUMENT_ROOT', $tmp);
define('DOL_MAIN_URL_ROOT', 'https://erp.example.test');
define('MAIN_DB_PREFIX', 'llx_');
if (!defined('LOG_ERR')) define('LOG_ERR', 3);
$conf = (object) array('entity'=>1, 'currency'=>'USD');
$GLOBALS['conf'] = $conf;

function getDolGlobalString($key) { return $key === 'MAIN_MAIL_EMAIL_FROM' ? 'portal@example.test' : ''; }
function dol_string_nohtmltag($value, $allowLineBreaks = 0) { return trim(strip_tags((string)$value)); }
function dol_print_date($value, $format = '') { return substr((string)$value, 0, 10); }
function dol_now() { return 1787529600; }
function dol_syslog($message, $level = 0) {}

class M061EmailDb {
    public $rows = array();
    public function query($sql) {
        if (strpos($sql, 'FROM llx_societe') !== false && strpos($sql, 'rowid=10') !== false && strpos($sql, 'entity=1') !== false) {
            return array((object) array('nom'=>'Safe Customer', 'email'=>'CUSTOMER@EXAMPLE.TEST', 'default_lang'=>'en_US'));
        }
        if (strpos($sql, 'FROM llx_chinaplanetlogistics') !== false && strpos($sql, 'rowid=20') !== false && strpos($sql, 'fk_soc=10') !== false && strpos($sql, 'entity=1') !== false) {
            return array((object) array('rowid'=>20, 'ref'=>'SHP-SAFE', 'container_no'=>'CONT-1', 'bl_no'=>'BL-1', 'port_loading'=>'Shanghai', 'port_destination'=>'Alexandria', 'date_etd'=>'2026-08-24', 'date_eta'=>'2026-09-20', 'date_ata'=>null, 'fk_statut'=>1, 'customer_note'=>'Customer-safe update'));
        }
        return array();
    }
    public function fetch_object(&$result) { return array_shift($result); }
}

function m061EmailAssert($condition, $message) {
    if (!$condition) { fwrite(STDERR, 'FAIL '.$message."\n"); exit(1); }
    print 'PASS '.$message."\n";
}

$root = dirname(__DIR__, 2);
require $root.'/custom/chinaplanetcustomercenter/class/CustomerSectionEmailService.class.php';
$service = new CustomerSectionEmailService(new M061EmailDb());
$preview = $service->preview('shipment', 10, 20);
m061EmailAssert(count(CMailFile::$instances) === 0 && count(ActionComm::$instances) === 0, 'preview has no mail or Agenda side effect');
m061EmailAssert($preview['recipient']['email'] === 'customer@example.test', 'recipient is normalized from the owned customer record');
m061EmailAssert(strpos($preview['body'], 'SHP-SAFE') !== false && strpos($preview['body'], 'Customer-safe update') !== false, 'shipment preview contains allowlisted customer content');
m061EmailAssert(strpos($preview['body_html'], '<table') !== false && strpos($preview['body_html'], 'SHP-SAFE') !== false, 'preview contains the formatted customer-safe HTML body');
m061EmailAssert(strpos($preview['body_html'], '\\n') === false && strpos($preview['body_html'], 'https://erp.example.test/custom/chinaplanetlogistics/card.php?id=20') !== false, 'HTML preview has no escaped newline tokens and uses an absolute portal link');

$user = (object) array('id'=>7, 'socid'=>0);
$result = $service->send('shipment', 10, 20, $user);
m061EmailAssert(count(CMailFile::$instances) === 1 && CMailFile::$instances[0]->sendto === 'customer@example.test', 'one confirmed send creates one server-addressed email');
m061EmailAssert(CMailFile::$instances[0]->msgishtml === 1 && CMailFile::$instances[0]->body === $preview['body_html'], 'confirmed send uses the exact previewed HTML body in HTML mode');
m061EmailAssert($result['history_recorded'] === true && count(ActionComm::$instances) === 1, 'successful send records one Agenda event');
$event = ActionComm::$instances[0];
m061EmailAssert($event->socid === 10 && $event->elementtype === 'chinaplanetlogistics' && $event->elementid === 20, 'Agenda event is linked to the owned customer and shipment');
m061EmailAssert($event->note_private === $preview['body_text'] && strpos($event->note_private, '<table') === false, 'Agenda stores the plain-text audit representation instead of raw HTML');

$foreignRejected = false;
try { $service->preview('shipment', 11, 20); } catch (CustomerSectionEmailException $exception) { $foreignRejected = true; }
m061EmailAssert($foreignRejected, 'foreign customer and shipment pairing fails closed');

CMailFile::$sendResult = false;
$agendaBefore = count(ActionComm::$instances);
$smtpRejected = false;
try { $service->send('shipment', 10, 20, $user); } catch (CustomerSectionEmailException $exception) { $smtpRejected = true; }
m061EmailAssert($smtpRejected && count(ActionComm::$instances) === $agendaBefore, 'SMTP failure stops before Agenda history');

echo "OK M061 customer section email service\n";
