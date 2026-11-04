<?php
declare(strict_types=1);

// Synthetic fixtures only. No Dolibarr bootstrap or real database configuration.
$root = dirname(__DIR__, 2);
$moneyFile = $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseMoney.php';
if (!is_file($moneyFile)) {
    fwrite(STDERR, "FAIL: exact office-expense money implementation is missing\n");
    exit(1);
}
require_once $moneyFile;
$passed = 0;
$failed = 0;
function check($actual, $expected, string $message = ''): void {
    if ($actual !== $expected) throw new RuntimeException($message.' expected '.var_export($expected, true).' got '.var_export($actual, true));
}
function rejects(callable $fn, string $class = InvalidArgumentException::class): void {
    try { $fn(); } catch (Throwable $e) {
        if ($e instanceof $class) return;
        throw $e;
    }
    throw new RuntimeException('Expected rejection: '.$class);
}
function scenario(string $name, callable $fn): void {
    global $passed, $failed;
    try { $fn(); $passed++; echo "PASS $name\n"; }
    catch (Throwable $e) { $failed++; fwrite(STDERR, "FAIL $name: ".$e->getMessage()."\n"); }
}
$money = new OfficeExpenseMoney();
scenario('decimal money has no float drift and preserves currency precision', function () use ($money) {
    check($money->amount('0.10', 'EGP'), '0.10');
    check($money->amount('180', 'CNY'), '180.00');
    check($money->amount('999999999999.99', 'USD'), '999999999999.99');
    check((new OfficeExpenseMoney(array('JPY'=>0)))->amount('100', 'JPY'), '100');
});
scenario('reject ambiguous and out-of-range money rather than silently round', function () use ($money) {
    foreach (array(0.1, 100, '-1', '1e3', ' 1', '1,000', '01', '.50', '1.001', '1000000000000', 'NaN') as $value) {
        rejects(function () use ($money, $value) { $money->amount($value, 'EGP'); });
    }
    rejects(function () use ($money) { $money->amount('1', 'RMB'); });
    rejects(function () { new OfficeExpenseMoney(array('BAD'=>-1)); });
});
scenario('FX quote means base units per original unit and preserves provenance', function () use ($money) {
    $r = $money->convert('180.00', 'CNY', 'USD', array('rate'=>'0.14', 'date'=>'2026-10-31', 'source'=>'SYNTHETIC manual quote'));
    check($r['original_amount'], '180.00'); check($r['original_currency'], 'CNY');
    check($r['base_currency'], 'USD'); check($r['base_amount'], '25.20');
    check($r['rate'], '0.14000000'); check($r['rate_date'], '2026-10-31');
    check($r['rate_source'], 'SYNTHETIC manual quote');
    check($money->convert('0.05', 'EGP', 'USD', array('rate'=>'0.1','date'=>'2026-10-31','source'=>'SYNTHETIC'))['base_amount'], '0.01');
    check($money->convert('700', 'EGP', 'EGP', array())['base_amount'], '700.00');
});
scenario('missing or malformed FX inputs fail closed', function () use ($money) {
    foreach (array(array(), array('rate'=>'0','date'=>'2026-10-31','source'=>'test'), array('rate'=>0.14,'date'=>'2026-10-31','source'=>'test'), array('rate'=>'1','date'=>'2026-02-30','source'=>'test'), array('rate'=>'1','date'=>'2026-10-31','source'=>''), array('rate'=>'1.123456789','date'=>'2026-10-31','source'=>'test')) as $quote) {
        rejects(function () use ($money, $quote) { $money->convert('10', 'EGP', 'USD', $quote); });
    }
    rejects(function () use ($money) { $money->convert('10', 'EGP', 'EGP', array('rate'=>'2','date'=>'2026-10-31','source'=>'test')); });
});
scenario('1000 advance minus 700 expense minus 300 return is settled with 700 expense', function () use ($money) {
    $r = $money->settlement(array('amount'=>'1000','currency'=>'EGP'), array(array('amount'=>'700','currency'=>'EGP')), array(array('amount'=>'300','currency'=>'EGP')));
    check($r, array('currency'=>'EGP','advance'=>'1000.00','expense'=>'700.00','returned'=>'300.00','outstanding'=>'0.00','reimbursement_due'=>'0.00'));
    $over = $money->settlement(array('amount'=>'1000','currency'=>'CNY'), array(array('amount'=>'1100','currency'=>'CNY')), array());
    check($over['outstanding'], '0.00'); check($over['reimbursement_due'], '100.00');
    rejects(function () use ($money) { $money->settlement(array('amount'=>'1000','currency'=>'EGP'), array(array('amount'=>'1','currency'=>'CNY')), array()); });
    rejects(function () use ($money) { $money->settlement(array('amount'=>'100','currency'=>'EGP'), array(), array(array('amount'=>'101','currency'=>'EGP'))); });
});
$serviceFile = $root.'/custom/chinaplanetofficeexpenses/class/OfficeExpenseDraftService.php';
$storeFile = $root.'/deploy/tools/office_expenses/LocalDraftStore.php';
if (!is_file($serviceFile) || !is_file($storeFile)) {
    scenario('scoped persistent draft implementation exists', function () { throw new RuntimeException('Draft service/store implementation is missing'); });
    exit(1);
}
require_once $serviceFile;
require_once $storeFile;
final class M119SyntheticUser {
    public $id = 7; public $socid = 0; public $status = 1; public $admin = 0; public $allowed = true;
    public function hasRight($module, $right) { return $this->allowed && $module === 'expensereport' && $right === 'creer'; }
}
function draftPayload(string $kind = 'expense_claim'): array {
    $p = array('kind'=>$kind,'office'=>'egypt','amount'=>'700','currency'=>'EGP','occurred_on'=>'2026-10-31','source_ref'=>'SYNTHETIC receipt 001','purpose'=>'SYNTHETIC office supplies');
    if (in_array($kind, array('reimbursement','advance_return'), true)) $p['settles_ref'] = 'SYNTHETIC prior record';
    if ($kind === 'transfer') {
        $p['from_account_ref'] = 'SYNTHETIC source';
        $p['destination'] = array('account_ref'=>'SYNTHETIC destination','amount'=>'100','currency'=>'CNY');
    }
    return $p;
}
function draftService($store = null, $user = null, int $entity = 1, array $offices = array('egypt'=>array('EGP','USD'), 'china'=>array('CNY','USD')), string $base = 'EGP'): OfficeExpenseDraftService {
    return new OfficeExpenseDraftService($store ?: new LocalDraftStore(new PDO('sqlite::memory:')), new OfficeExpenseMoney(), $user ?: new M119SyntheticUser(), $entity, $offices, $base);
}
scenario('claims preserve original money and derive immutable entity/creator context', function () {
    $service = draftService(); $r = $service->submit(draftPayload(), 'synthetic-key-001');
    check($r['entity'], 1); check($r['owner_id'], 7); check($r['status'], 'draft');
    check($r['amount'], '700.00'); check($r['currency'], 'EGP'); check($r['native_record'], null);
    check($r['accounting_route'], 'expense_report_review');
    check(count($service->listOwn()), 1);
});
scenario('all movement types remain drafts and cannot post or recognize cash as expense', function () {
    $s = draftService();
    $routes = array('expense_claim'=>'expense_report_review','advance'=>'employee_advance_review','advance_return'=>'employee_advance_settlement_review','transfer'=>'bank_transfer_review','reimbursement'=>'expense_payment_review');
    foreach ($routes as $kind=>$route) {
        $r = $s->submit(draftPayload($kind), 'synthetic-'.$kind);
        check($r['accounting_route'], $route); check($r['status'], 'draft');
        check($r['native_record'], null);
        rejects(function () use ($s, $r) { $s->post($r); }, LogicException::class);
        if ($kind === 'transfer') { check($r['destination']['amount'], '100.00'); check($r['destination']['currency'], 'CNY'); }
    }
    check(count($s->listOwn()), 5);
});
scenario('external admins guests missing native rights and office scope are denied', function () {
    foreach (array('external','guest','rights') as $case) {
        $u = new M119SyntheticUser();
        if ($case === 'external') { $u->socid = 99; $u->admin = 1; }
        if ($case === 'guest') $u->id = 0;
        if ($case === 'rights') $u->allowed = false;
        rejects(function () use ($u) { draftService(null, $u)->submit(draftPayload(), 'synthetic-denied'); }, DomainException::class);
    }
    $p = draftPayload(); $p['office'] = 'china'; $p['currency'] = 'CNY';
    rejects(function () use ($p) { draftService(null, null, 1, array('egypt'=>array('EGP')))->submit($p, 'synthetic-denied'); }, DomainException::class);
});
scenario('reject forged context missing evidence invalid dates and incomplete settlements/transfers', function () {
    foreach (array('entity','owner_id','employee_id','status','native_record') as $key) {
        $p = draftPayload(); $p[$key] = 999;
        rejects(function () use ($p) { draftService()->submit($p, 'synthetic-forged'); });
    }
    foreach (array(array('amount','0'),array('occurred_on','2026-02-30'),array('source_ref',''),array('kind','salary'),array('currency','CNY')) as $invalid) {
        $p = draftPayload(); $p[$invalid[0]] = $invalid[1];
        rejects(function () use ($p) { draftService()->submit($p, 'synthetic-invalid'); });
    }
    foreach (array('advance_return','reimbursement','transfer') as $kind) {
        $p = draftPayload($kind); unset($p['settles_ref'], $p['destination']);
        rejects(function () use ($p) { draftService()->submit($p, 'synthetic-invalid'); });
    }
});
scenario('entity base currency is independent of office currency and quote is mandatory', function () {
    $s = draftService(null, null, 1, array('china'=>array('CNY')), 'USD');
    $p = draftPayload(); $p['office'] = 'china'; $p['currency'] = 'CNY'; $p['amount'] = '180';
    rejects(function () use ($s, $p) { $s->submit($p, 'synthetic-no-quote'); });
    $p['quote'] = array('rate'=>'0.14','date'=>'2026-10-31','source'=>'SYNTHETIC');
    $r = $s->submit($p, 'synthetic-fx-quote');
    check($r['conversion']['base_currency'], 'USD'); check($r['conversion']['base_amount'], '25.20');
    check($r['amount'], '180.00'); check($r['currency'], 'CNY');
});
scenario('idempotent retry returns existing draft and changed intent conflicts', function () {
    $s = draftService(); $p = draftPayload(); $first = $s->submit($p, 'synthetic-retry');
    check($s->submit($p, 'synthetic-retry'), $first);
    $p['amount'] = '701'; rejects(function () use ($s, $p) { $s->submit($p, 'synthetic-retry'); }, DomainException::class);
    check(count($s->listOwn()), 1);
});
scenario('synthetic persistence survives reopen with entity and owner isolation', function () {
    $file = tempnam(sys_get_temp_dir(), 'm119-synthetic-');
    try {
        $store = new LocalDraftStore(new PDO('sqlite:'.$file)); $s = draftService($store);
        $r = $s->submit(draftPayload(), 'synthetic-reopen'); unset($s, $store);
        $store = new LocalDraftStore(new PDO('sqlite:'.$file));
        check(draftService($store)->listOwn(), array($r));
        check(draftService($store, null, 2)->listOwn(), array());
        $other = new M119SyntheticUser(); $other->id = 8;
        check(draftService($store, $other)->listOwn(), array());
        check(draftService($store, null, 2)->submit(draftPayload(), 'synthetic-reopen')['entity'], 2);
    } finally { unlink($file); }
});
scenario('audit insert failure rolls back draft and nested transactions are rejected', function () {
    $pdo = new PDO('sqlite::memory:'); $store = new LocalDraftStore($pdo); $s = draftService($store);
    $pdo->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON oe_audit BEGIN SELECT RAISE(ABORT, 'synthetic audit failure'); END");
    rejects(function () use ($s) { $s->submit(draftPayload(), 'synthetic-rollback'); }, PDOException::class);
    check((int) $pdo->query('SELECT COUNT(*) FROM oe_draft')->fetchColumn(), 0);
    check((int) $pdo->query('SELECT COUNT(*) FROM oe_audit')->fetchColumn(), 0);
    $pdo->exec('DROP TRIGGER fail_audit');
    $pdo->beginTransaction();
    rejects(function () use ($s) { $s->submit(draftPayload(), 'synthetic-nested'); }, LogicException::class);
    check($pdo->inTransaction(), true); $pdo->rollBack();
    $s->submit(draftPayload(), 'synthetic-recovered');
    check((int) $pdo->query('SELECT COUNT(*) FROM oe_audit')->fetchColumn(), 1);
});
$previewFile = $root.'/deploy/tools/office_expenses/OfficeExpensePreview.php';
scenario('Arabic and English preview escapes content and totals claims separately by currency', function () use ($previewFile) {
    if (!is_file($previewFile)) throw new RuntimeException('Bilingual preview renderer is missing');
    require_once $previewFile;
    $s = draftService(); $p = draftPayload(); $p['purpose'] = '<script>alert(1)</script>';
    $claim = $s->submit($p, 'synthetic-preview');
    $advance = $s->submit(draftPayload('advance'), 'synthetic-preview-advance');
    $preview = new OfficeExpensePreview();
    foreach (array('en'=>'ltr','ar'=>'rtl') as $lang=>$dir) {
        $html = $preview->render(array($claim,$advance), $lang);
        check(strpos($html, 'dir="'.$dir.'"') !== false, true);
        check(strpos($html, '<script>'), false);
        check(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, true);
        check(strpos($html, 'data-claim-total="700.00 EGP"') !== false, true);
        check(strpos($html, 'data-claim-total="1400.00 EGP"'), false);
    }
    rejects(function () use ($preview) { $preview->render(array(), 'xx'); });
});
scenario('preview aggregation retains configured three and four decimal currencies', function () {
    $m = new OfficeExpenseMoney(array('EGP'=>2,'KWD'=>3,'XTS'=>4));
    $s = new OfficeExpenseDraftService(new LocalDraftStore(new PDO('sqlite::memory:')), $m, new M119SyntheticUser(), 1, array('testoffice'=>array('KWD','XTS')), 'KWD');
    foreach (array('kwd-one','kwd-two') as $key) {
        $p = draftPayload(); $p['office']='testoffice'; $p['currency']='KWD'; $p['amount']='1.009';
        $s->submit($p, 'synthetic-'.$key);
    }
    $preview = new OfficeExpensePreview($m);
    check(strpos($preview->render($s->listOwn(), 'en'), 'data-claim-total="2.018 KWD"') !== false, true);
    check($m->total(array('1.0001','2.0001'), 'XTS'), '3.0002');
    check($m->total(array(), 'KWD'), '0.000');
});
scenario('normalized identical transfer accounts are rejected', function () {
    foreach (array(array('SYNTHETIC account',' SYNTHETIC account '),array(' SYNTHETIC account ','SYNTHETIC account')) as $accounts) {
        $p = draftPayload('transfer'); $p['from_account_ref']=$accounts[0]; $p['destination']['account_ref']=$accounts[1];
        rejects(function () use ($p) { draftService()->submit($p, 'synthetic-same-account'); });
    }
});
echo "$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
