<?php

$tmp = sys_get_temp_dir().'/m056-email-'.bin2hex(random_bytes(4));
mkdir($tmp.'/core/class', 0700, true);
mkdir($tmp.'/comm/action/class', 0700, true);
file_put_contents($tmp.'/core/class/CMailFile.class.php', <<<'PHP'
<?php
class CMailFile {
    public static $sendResult = true;
    public static $instances = array();
    public $error = 'synthetic SMTP failure';
    public $msgid = 'm056-test-message';
    public $subject;
    public $sendto;
    public $body;
    public function __construct($subject, $sendto, $from, $body) {
        $this->subject = $subject; $this->sendto = $sendto; $this->body = $body;
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
    public static $createResult = 901;
    public static $instances = array();
    public $error = 'synthetic Agenda failure';
    public function __construct($db) { self::$instances[] = $this; }
    public function create($user) { return self::$createResult; }
}
PHP
);
file_put_contents($tmp.'/core/class/translate.class.php', <<<'PHP'
<?php
class Translate {
    public $defaultlang = 'en_US';
    public function __construct($a = '', $b = null) {}
    public function setDefaultLang($language) { $this->defaultlang = $language; }
    private $labels = array();
    public function loadLangs($languages) {
        $root = $GLOBALS['m056_source_root'];
        $file = $root.'/custom/chinaplanetlogistics/langs/'.$this->defaultlang.'/chinaplanetlogistics.lang';
        if (!is_file($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $this->labels[$key] = $value;
        }
    }
    public function trans($key) {
        return isset($this->labels[$key]) ? $this->labels[$key] : $key;
    }
}
PHP
);

define('DOL_DOCUMENT_ROOT', $tmp);
define('DOL_MAIN_URL_ROOT', 'https://erp.example.test');
define('MAIN_DB_PREFIX', 'llx_');
$conf = (object) array('entity'=>1);
$GLOBALS['conf'] = $conf;

function dol_string_nohtmltag($value, $allowlinebreaks = 0) { return trim(strip_tags((string) $value)); }
function dol_print_date($timestamp, $format = '') { return date('Y-m-d', (int) $timestamp); }
function getDolGlobalString($key) { return $key === 'MAIN_MAIL_EMAIL_FROM' ? 'sales@chinaplanet.org' : ''; }
function dol_now() { return 1786665600; }
function dol_syslog($message, $level = 0) {}
if (!defined('LOG_ERR')) define('LOG_ERR', 3);

$root = getenv('M056_DOL_DOCUMENT_ROOT') ?: dirname(__DIR__, 2);
$GLOBALS['m056_source_root'] = $root;
require $root.'/custom/chinaplanetlogistics/class/ShipmentStatusEmailService.class.php';

class M056Db {
    public $customer;
    public $latestEvent;
    public $lastSql = '';
    public $queries = array();
    public function query($sql) {
        $this->lastSql = $sql;
        $this->queries[] = $sql;
        if (strpos($sql, 'cpl_shipment_event') !== false) return $this->latestEvent ? (object) array('type'=>'event') : false;
        return $this->customer ? (object) array('type'=>'customer') : false;
    }
    public function fetch_object($result) { return $result->type === 'event' ? $this->latestEvent : $this->customer; }
}

$db = new M056Db();
$db->customer = (object) array('nom'=>'عميل <b>آمن</b>', 'email'=>' Customer@Example.COM ', 'default_lang'=>'ar_EG');
$service = new ShipmentStatusEmailService($db);
$shipment = (object) array(
    'id'=>77, 'entity'=>1, 'fk_soc'=>42, 'ref'=>"LOG-TEST-77\r\nBcc:bad@example.test", 'date_eta'=>strtotime('2026-09-15'),
    'notes'=>'PRIVATE INTERNAL NOTE', 'customer_note'=>'not approved for this email', 'shipping_line'=>'PRIVATE CARRIER',
);

$failed = array();
$recipient = $service->recipientForShipment($shipment);
$recipientOk = $recipient === array('email'=>'customer@example.com', 'name'=>'عميل <b>آمن</b>', 'language'=>'ar_SA')
    && strpos($db->lastSql, 's.rowid=42') !== false && strpos($db->lastSql, 's.entity=1') !== false;
echo ($recipientOk ? 'PASS ' : 'FAIL ')."entity-scoped normalized customer recipient\n";
if (!$recipientOk) $failed[] = 'recipient';

$arabic = $service->message($shipment, 1, 2, 'غادرت <script>unsafe</script>', $recipient);
$arabicOk = $arabic['language'] === 'ar_SA'
    && strpos($arabic['subject'], 'LOG-TEST-77') !== false
    && strpos($arabic['subject'], "\r") === false && strpos($arabic['subject'], "\n") === false
    && strpos($arabic['body'], 'على السفينة') !== false
    && strpos($arabic['body'], '2026-09-15') !== false
    && strpos($arabic['body'], 'https://erp.example.test/custom/chinaplanetlogistics/card.php?id=77') !== false
    && strpos($arabic['body'], '<script>') === false
    && strpos($arabic['body'], 'PRIVATE') === false
    && strpos($arabic['body'], 'not approved') === false;
echo ($arabicOk ? 'PASS ' : 'FAIL ')."Arabic customer-safe message\n";
if (!$arabicOk) $failed[] = 'Arabic message';

$english = $service->message($shipment, 1, 2, '', array('email'=>'customer@example.com','name'=>'Customer','language'=>'en_US'));
$englishOk = $english['language'] === 'en_US' && strpos($english['body'], 'Previous status: Booking confirmed') !== false && strpos($english['body'], 'New status: On vessel') !== false;
echo ($englishOk ? 'PASS ' : 'FAIL ')."English status transition\n";
if (!$englishOk) $failed[] = 'English message';

$chinese = $service->message($shipment, 1, 2, '', array('email'=>'customer@example.com','name'=>'客户','language'=>'zh_CN'));
$chineseOk = $chinese['language'] === 'zh_CN' && strpos($chinese['body'], '原状态：订舱已确认') !== false && strpos($chinese['body'], '新状态：船运途中') !== false;
echo ($chineseOk ? 'PASS ' : 'FAIL ')."Chinese status transition\n";
if (!$chineseOk) $failed[] = 'Chinese message';

$db->customer = (object) array('nom'=>'No email', 'email'=>'invalid', 'default_lang'=>'en_US');
$invalidOk = $service->recipientForShipment($shipment) === array();
echo ($invalidOk ? 'PASS ' : 'FAIL ')."invalid customer email fails closed\n";
if (!$invalidOk) $failed[] = 'invalid recipient';

$db->customer = (object) array('nom'=>'Customer', 'email'=>'customer@example.com', 'default_lang'=>'en_US');
CMailFile::$sendResult = true;
CMailFile::$instances = array();
ActionComm::$createResult = 901;
ActionComm::$instances = array();
$sendResult = $service->send($shipment, 1, 2, 'Public reason', (object) array('id'=>9));
$sendOk = count(CMailFile::$instances) === 1 && count(ActionComm::$instances) === 1
    && $sendResult['event_id'] === 901 && $sendResult['history_recorded'] === true
    && ActionComm::$instances[0]->socid === 42 && ActionComm::$instances[0]->elementid === 77;
echo ($sendOk ? 'PASS ' : 'FAIL ')."one mail send and linked Agenda history\n";
if (!$sendOk) $failed[] = 'send success';

ActionComm::$createResult = 0;
$historyResult = $service->send($shipment, 1, 2, '', (object) array('id'=>9));
$historyOk = $historyResult['history_recorded'] === false && count(CMailFile::$instances) === 2;
echo ($historyOk ? 'PASS ' : 'FAIL ')."mail success survives Agenda history failure\n";
if (!$historyOk) $failed[] = 'Agenda failure';

CMailFile::$sendResult = false;
$smtpFailedClosed = false;
try { $service->send($shipment, 1, 2, '', (object) array('id'=>9)); }
catch (ShipmentStatusEmailException $e) { $smtpFailedClosed = $e->getMessage() === 'CPLStatusEmailSendFailed'; }
$smtpOk = $smtpFailedClosed && count(ActionComm::$instances) === 2;
echo ($smtpOk ? 'PASS ' : 'FAIL ')."SMTP failure stops before Agenda history\n";
if (!$smtpOk) $failed[] = 'SMTP failure';

CMailFile::$sendResult = true;
ActionComm::$createResult = 902;
$db->latestEvent = (object) array('status_from'=>1, 'status_to'=>2, 'reason_public'=>'Latest public reason');
$retryResult = $service->resendLatest($shipment, (object) array('id'=>9));
$retrySql = implode("\n", $db->queries);
$retryOk = $retryResult['event_id'] === 902 && strpos($retrySql, 'customer_visible=1') !== false && strpos($retrySql, 'fk_shipment=77') !== false && strpos($retrySql, 'fk_soc=42') !== false;
echo ($retryOk ? 'PASS ' : 'FAIL ')."retry uses latest scoped customer-visible status\n";
if (!$retryOk) $failed[] = 'retry';

@unlink($tmp.'/core/class/CMailFile.class.php');
@unlink($tmp.'/core/class/translate.class.php');
@unlink($tmp.'/comm/action/class/actioncomm.class.php');
@rmdir($tmp.'/core/class');
@rmdir($tmp.'/core');
@rmdir($tmp.'/comm/action/class');
@rmdir($tmp.'/comm/action');
@rmdir($tmp.'/comm');
@rmdir($tmp);

if ($failed) {
    fwrite(STDERR, 'M056 shipment status email service verification failed: '.implode(', ', $failed)."\n");
    exit(1);
}
echo "M056_SHIPMENT_STATUS_EMAIL_SERVICE=PASS\n";
