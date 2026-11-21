<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') define('CSRFCHECK_WITH_TOKEN',1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../class/SupplierWorkspaceService.class.php';
require_once __DIR__.'/../class/SupplierPaymentCorrection.class.php';

header('Cache-Control: private, no-store');
header("Content-Security-Policy: frame-ancestors 'self'");
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && ($_SERVER['REQUEST_METHOD'] !== 'POST' || !SupplierPaymentCorrection::enabled())) {
    http_response_code(405);
    header('Allow: '.(SupplierPaymentCorrection::enabled() ? 'GET, POST' : 'GET'));
    exit;
}
if (empty($conf->global->CPO_ACCOUNT_STATEMENTS_ENABLED)) {
    http_response_code(403);
    exit;
}

$customer = GETPOSTINT('customer');
$project = GETPOSTINT('project');
$task = GETPOSTINT('task');
$supplier = GETPOSTINT('supplier');
$ar = strpos($langs->getDefaultLang(), 'ar') === 0;
$t = static fn(string $en, string $arabic): string => $ar ? $arabic : $en;
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$url = static fn(array $args): string => 'supplier_account.php'.($args ? '?'.http_build_query($args) : '');
$order = GETPOSTINT('order');
$scope = array_filter(compact('customer', 'project', 'task', 'supplier', 'order'));
$title = $order ? $t('Supplier order statement', 'كشف حساب طلبية المورد')
    : ($supplier ? $t('Supplier statement', 'كشف حساب المورد') : $t('Supplier accounts', 'حسابات الموردين'));
$corrections = new SupplierPaymentCorrection($db,(int)$conf->entity,(string)$conf->currency);
$commandError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (GETPOST('errorcode','aZ09')==='InvalidToken') throw new RuntimeException('Session expired. Reload and try again.',403);
        $input = [];
        foreach (['reason','version','invoice','account','date','amount','reference'] as $field) $input[$field]=GETPOST($field,'alphanohtml');
        $corrections->apply($user,$scope,GETPOSTINT('payment'),GETPOST('payment_action','aZ09'),$input);
        header('Location: '.$url($scope+['audit'=>1]),true,303);
        exit;
    } catch (Throwable $exception) {
        $code=$exception instanceof InvalidArgumentException ? 422 : (int)$exception->getCode();
        http_response_code(in_array($code,[403,409,422],true)?$code:500);
        $commandError=in_array($code,[403,409,422],true)?$exception->getMessage():'The supplier payment could not be corrected. Review the source and retry.';
    }
}
try {
    $reader = new SupplierAccountService($db, (int)$conf->entity, (string)$conf->currency);
    $orders = $reader->orders($user, $customer, $project, $task, $supplier, $order);
    $audit = $order && count($orders)===1 && SupplierPaymentCorrection::enabled() && GETPOSTINT('audit')===1
        ? $corrections->audit($order) : null;
    $error = '';
} catch (Throwable $exception) {
    $code = $exception->getCode() === 403 || $exception instanceof InternalTaskAccessException
        ? 403 : ($exception->getCode() === 422 ? 422 : 503);
    http_response_code($code);
    $error = $code === 422
        ? $t('Narrow the account to a specific supplier or project.', 'افتح حساب مورد أو مشروع محدد.')
        : $t('Supplier account unavailable.', 'حساب المورد غير متاح.');
    $orders = [];
    $audit = null;
}
$workspace = null;
if (!$error && $supplier && SupplierWorkspaceService::enabled()) {
    $workspace = (new SupplierWorkspaceService($db, $user, (int)$conf->entity))->findWorkspace($supplier);
}
$money = static fn(?string $minor, string $currency): string => $minor === null
    ? '—' : number_format((float)CustomerAccountAmount::money($minor), 2).' '.$currency;
$correctionToken = SupplierPaymentCorrection::enabled() && $corrections->allowed($user) ? newToken() : '';
$asset = DOL_URL_ROOT.'/custom/chinaplanetoperations/css/customer_account.css?v=m127';
$conf->dol_hide_leftmenu = 1;
llxHeader('', $title, '', '', 0, 0, [], [$asset]);
?>
<main id="cpo-account" class="account-ui supplier-account" data-theme="dark" lang="<?= $ar ? 'ar' : 'en' ?>" dir="<?= $ar ? 'rtl' : 'ltr' ?>">
    <aside class="account-sidebar" aria-label="<?= $h($title) ?>">
        <div class="account-sidebar-heading"><?= $h($title) ?></div>
        <nav class="account-sidebar-links">
            <a href="boards.php"><?= $h($t('Boards', 'اللوحات')) ?></a>
            <?php if (!$error && !empty($conf->global->CPO_SUPPLIER_WORKSPACES_ENABLED)): ?>
                <a href="boards.php?view=suppliers<?= $workspace ? '&amp;supplier='.$supplier : '' ?>"><?= $h($t('Supplier workspaces', 'مساحات الموردين')) ?></a>
                <?php if ($supplier && $order): ?>
                    <a href="<?= $h($url(['supplier'=>$supplier])) ?>"><?= $h($t('Supplier statement', 'كشف حساب المورد')) ?></a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="supplier_account.php" <?= !$scope ? 'aria-current="page"' : '' ?>><?= $h($t('All linked supplier orders', 'كل طلبيات الموردين المرتبطة')) ?></a>
            <?php if (!$error && $customer): ?>
                <a href="<?= $h($url(['customer'=>$customer])) ?>"><?= $h($t('Customer context', 'سياق العميل')) ?></a>
            <?php endif; ?>
            <?php if (!$error && $project): ?>
                <a href="<?= $h($url(['customer'=>$customer,'project'=>$project])) ?>"><?= $h($t('Project context', 'سياق المشروع')) ?></a>
            <?php endif; ?>
            <?php if (!$error && $task): ?>
                <a href="card.php?id=<?= $task ?>"><?= $h($t('Back to card', 'العودة للبطاقة')) ?></a>
            <?php endif; ?>
        </nav>
    </aside>
    <div class="account-main">
        <header class="account-heading">
            <h1><?= $h($title) ?></h1>
            <p><?= $h($t('Purchase commitments and recorded supplier payments in their original currency.', 'التزامات الشراء ومدفوعات المورد المسجّلة بالعملة الأصلية.')) ?></p>
        </header>
        <?php if ($commandError): ?><p role="alert"><?= $h($commandError) ?></p><?php endif; ?>
        <?php if ($audit !== null): ?>
            <section class="account-entry">
                <h2><?= $h($t('Internal payment audit', 'سجل تدقيق المدفوعات الداخلي')) ?></h2>
                <?php if (!$audit): ?><p><?= $h($t('No corrections recorded.', 'لا توجد تصحيحات مسجلة.')) ?></p><?php endif; ?>
                <?php foreach ($audit as $event): ?>
                    <details>
                        <summary><?= $h($event['date_creation'].' · #'.$event['fk_payment'].' · '.$event['action'].' · User #'.$event['fk_user'].' · '.$event['reason']) ?></summary>
                        <pre><?= $h(json_encode(json_decode($event['detail_json'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
                    </details>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        <?php if ($error): ?>
            <p role="alert"><?= $h($error) ?></p>
        <?php elseif (!$orders): ?>
            <p class="account-note"><?= $h($task
                ? $t('No supplier order is linked to this card.', 'لا توجد طلبية مورد مرتبطة بهذه البطاقة.')
                : $t('No accessible supplier orders are linked to this context.', 'لا توجد طلبيات مورد متاحة ومرتبطة بهذا السياق.')) ?></p>
        <?php endif; ?>
        <?php if ($supplier && !$order && !$error): ?>
            <?php foreach (SupplierAccountService::totalsByCurrency($orders) as $currency => $total): ?>
                <section class="account-entry">
                    <h2><?= $h($t('Active orders summary', 'ملخص الطلبيات السارية')) ?> · <bdi><?= $h($currency) ?></bdi></h2>
                    <div class="account-totals">
                        <div><span><?= $h($t('Order value', 'قيمة الطلبيات')) ?></span><strong><bdi><?= $h($money($total['ordered'], $currency)) ?></bdi></strong></div>
                        <div><span><?= $h($t('Linked payments', 'المدفوعات المرتبطة')) ?></span><strong><bdi><?= $h($money($total['paid'], $currency)) ?></bdi></strong></div>
                        <div><span><?= $h($t('Remaining on orders', 'المتبقي من الطلبيات')) ?></span><strong><bdi><?= $h($money($total['remaining'], $currency)) ?></bdi></strong></div>
                    </div>
                    <?php if ($total['remaining'] === null): ?>
                        <p><?= $h($t('Review incomplete order or payment links before using this total.', 'راجع روابط الطلبيات والمدفوعات الناقصة قبل استخدام الإجمالي.')) ?></p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php foreach ($orders as $order): ?>
            <article class="account-entry">
                <header>
                    <h2><a href="<?= $h($url(['supplier'=>(int)$order['fk_soc']])) ?>"><?= $h($order['supplier']) ?></a></h2>
                    <a href="<?= $h(DOL_URL_ROOT.'/fourn/commande/card.php?id='.(int)$order['rowid']) ?>"><bdi><?= $h($order['ref']) ?></bdi></a>
                </header>
                <p class="account-path">
                    <a href="<?= $h($url(['customer'=>(int)$order['customer_id']])) ?>"><?= $h($order['customer']) ?></a>
                    /
                    <a href="<?= $h($url(['customer'=>(int)$order['customer_id'],'project'=>(int)$order['fk_projet']])) ?>"><bdi><?= $h($order['project']) ?></bdi></a>
                </p>
                <div class="account-totals">
                    <div><span><?= $h($t('Supplier order', 'قيمة طلبية المورد')) ?></span><strong><bdi><?= $h($money($order['ordered'], $order['currency'])) ?></bdi></strong></div>
                    <div><span><?= $h($t('Linked payments', 'المدفوعات المرتبطة')) ?></span><strong><bdi><?= $h($money($order['paid'], $order['currency'])) ?></bdi></strong></div>
                    <div><span><?= $h(($order['remaining'] !== null && str_starts_with($order['remaining'], '-') ? $t('Paid above order value', 'مدفوع زائد عن قيمة الطلبية') : $t('Remaining on order', 'المتبقي من الطلبية'))) ?></span><strong><bdi><?= $h($money($order['remaining'] === null ? null : ltrim($order['remaining'], '-'), $order['currency'])) ?></bdi></strong></div>
                </div>
                <?php if ($order['review']): ?>
                    <p role="status"><?= $h($t('Review the native invoice, currency and bank links before using the remaining amount.', 'راجع الفواتير والعملة والربط البنكي قبل اعتماد المبلغ المتبقي.')) ?></p>
                <?php elseif (!$order['committed']): ?>
                    <p><?= $h($t('This order has no active purchase commitment.', 'هذه الطلبية ليست التزام شراء ساريًا.')) ?></p>
                <?php endif; ?>
                <?php if (SupplierPaymentCorrection::enabled()): ?>
                    <?php $orderScope = $scope; $orderScope['order'] = (int)$order['rowid']; ?>
                    <p><a href="<?= $h($url($orderScope+['audit'=>1])) ?>"><?= $h($t('Internal payment audit','سجل تدقيق المدفوعات الداخلي')) ?></a></p>
                    <?php if ($corrections->allowed($user) && $order['correction_payments']): ?>
                        <section aria-label="<?= $h($t('Correct supplier payment','تصحيح دفعة المورد')) ?>">
                            <?php foreach ($order['correction_payments'] as $payment): ?>
                                <details>
                                    <summary><?= $h($t('Edit or cancel payment','تعديل أو إلغاء الدفعة').' · '.$payment['payment_ref'].' · '.$money($payment['amount'],$payment['currency'])) ?></summary>
                                    <form class="account-fields" method="post" action="<?= $h($url($orderScope)) ?>">
                                        <input type="hidden" name="token" value="<?= $h($correctionToken) ?>">
                                        <input type="hidden" name="payment" value="<?= $payment['payment_id'] ?>">
                                        <input type="hidden" name="version" value="<?= $h($payment['version']) ?>">
                                        <label><?= $h($t('Reason','السبب')) ?><input name="reason" maxlength="500" required></label>
                                        <?php if ($corrections->allowed($user,true)): ?>
                                            <label><?= $h($t('Original amount','المبلغ الأصلي')) ?><input name="amount" inputmode="decimal" value="<?= $h(CustomerAccountAmount::money($payment['amount'])) ?>"></label>
                                            <label><?= $h($t('Date','التاريخ')) ?><input type="date" name="date" value="<?= $h(substr($payment['date'],0,10)) ?>"></label>
                                            <label><?= $h($t('Reference','المرجع')) ?><input name="reference" maxlength="64" value="<?= $h($payment['reference']) ?>"></label>
                                            <label><?= $h($t('Native invoice ID','معرّف فاتورة Dolibarr')) ?><input type="number" name="invoice" min="1" value="<?= $payment['invoice_id'] ?>"></label>
                                            <label><?= $h($t('Bank account ID','معرّف الحساب البنكي')) ?><input type="number" name="account" min="1" value="<?= $payment['account'] ?>"></label>
                                            <p class="account-note"><?= $h($t('Currency and exchange rate come from the selected native invoice.','العملة وسعر الصرف من فاتورة Dolibarr المختارة.')) ?></p>
                                            <button name="payment_action" value="correct"><?= $h($t('Save correction','حفظ التصحيح')) ?></button>
                                        <?php endif; ?>
                                        <button name="payment_action" value="cancel"><?= $h($t('Cancel recording mistake','إلغاء خطأ التسجيل')) ?></button>
                                    </form>
                                </details>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($order['payments']): ?>
                    <details open>
                        <summary><?= $h($t('Payment details', 'تفاصيل المدفوعات')) ?></summary>
                        <div class="account-table-scroll" tabindex="0">
                            <table class="account-table">
                                <thead><tr><th><?= $h($t('Date', 'التاريخ')) ?></th><th><?= $h($t('Supplier invoice', 'فاتورة المورد')) ?></th><th><?= $h($t('Payment', 'الدفعة')) ?></th><th><?= $h($t('Amount', 'المبلغ')) ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($order['payments'] as $payment): ?>
                                    <tr>
                                        <td><?= $h(substr((string)$payment['date'], 0, 10)) ?></td>
                                        <td><a href="<?= $h(DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$payment['invoice_id']) ?>"><?= $h($payment['invoice_ref']) ?></a></td>
                                        <td><?= $h($payment['payment_ref']) ?> <?= $payment['advance'] ? $h($t('Advance', 'عربون')) : '' ?></td>
                                        <td><bdi><?= $h($money($payment['amount'], $order['currency'])) ?></bdi></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</main>
<?php
llxFooter();
$db->close();
