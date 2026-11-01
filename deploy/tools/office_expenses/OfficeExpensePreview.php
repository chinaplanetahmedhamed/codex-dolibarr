<?php
declare(strict_types=1);
require_once __DIR__.'/../../../custom/chinaplanetofficeexpenses/class/OfficeExpenseMoney.php';

/** Static review artifact, with no forms, scripts, network dependencies or native writes. */
final class OfficeExpensePreview
{
    private $money;
    public function __construct(?OfficeExpenseMoney $money = null)
    {
        $this->money=$money ?: new OfficeExpenseMoney();
    }

    public function render(array $drafts, string $language): string
    {
        if (!in_array($language, array('en','ar'), true)) throw new InvalidArgumentException('Preview language must be en or ar.');
        $ar=$language === 'ar';
        $t=$ar ? array(
            'title'=>'مصروفات المكتب','subtitle'=>'مصر والصين · مبالغ واضحة بعملتها الأصلية',
            'notice'=>'معاينة محلية ببيانات افتراضية فقط. جميع الحركات مسودات ولم يُرحّل أي مبلغ إلى الحسابات.',
            'claims'=>'مطالبات المصروفات','help'=>'المطالبات وحدها تظهر هنا، كل عملة على حدة. العهد والتحويلات والتعويضات ليست مصروفات إضافية.',
            'register'=>'سجل المسودات','office'=>'المكتب','type'=>'نوع الحركة','purpose'=>'السبب والمستند','original'=>'المبلغ الأصلي','estimate'=>'تقدير بعملة الأساس','status'=>'الحالة',
            'draft'=>'مسودة','egypt'=>'مصر','china'=>'الصين','expense_claim'=>'مطالبة مصروف','advance'=>'عهدة موظف','advance_return'=>'مرتجع عهدة','transfer'=>'تحويل أموال','reimbursement'=>'تعويض موظف',
            'rate'=>'سعر التحويل','date'=>'التاريخ','source'=>'المصدر','empty'=>'لا توجد مسودات ضمن النطاق الحالي.',
            'example'=>'مثال تسوية عهدة','advanceLabel'=>'عهدة مستلمة','expenseLabel'=>'مصروفات موثقة','returnLabel'=>'مبلغ مرتجع','balanceLabel'=>'عهدة متبقية',
            'foot'=>'المصروف في هذا المثال 700.00 EGP فقط. الربط المحاسبي والموافقات مرحلة تالية؛ هذه المعاينة لا تحفظ مدخلات أو تُنشئ دفعات.',
        ) : array(
            'title'=>'Office expenses','subtitle'=>'Egypt and China · original amounts, clear currencies',
            'notice'=>'Local preview with synthetic data only. Every movement is a draft; nothing has been posted to accounting.',
            'claims'=>'Expense claims','help'=>'Claims only, kept separate by currency. Advances, transfers and reimbursements do not add another expense.',
            'register'=>'Draft register','office'=>'Office','type'=>'Movement','purpose'=>'Purpose and evidence','original'=>'Original amount','estimate'=>'Base-currency estimate','status'=>'Status',
            'draft'=>'Draft','egypt'=>'Egypt','china'=>'China','expense_claim'=>'Expense claim','advance'=>'Employee advance','advance_return'=>'Advance return','transfer'=>'Funds transfer','reimbursement'=>'Employee reimbursement',
            'rate'=>'Rate','date'=>'Date','source'=>'Source','empty'=>'No drafts in the current scope.',
            'example'=>'Advance settlement example','advanceLabel'=>'Advance received','expenseLabel'=>'Documented expense','returnLabel'=>'Money returned','balanceLabel'=>'Advance outstanding',
            'foot'=>'Expense in this example is 700.00 EGP only. Accounting integration and approvals follow later; this preview accepts no submissions or payments.',
        );
        $e=static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
        $groups=array(); $totals=array();
        foreach ($drafts as $draft) {
            if ($draft['kind'] === 'expense_claim') {
                $currency=$draft['currency'];
                $groups[$currency][]=$draft['amount'];
            }
        }
        foreach ($groups as $currency=>$amounts) $totals[$currency]=$this->money->total($amounts, $currency);
        ksort($totals);
        ob_start();
        ?>
<!doctype html>
<html lang="<?= $language ?>" dir="<?= $ar ? 'rtl' : 'ltr' ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($t['title']) ?> · ChinaPlanetOS</title><link rel="stylesheet" href="preview.css"></head>
<body>
<a class="skip" href="#main"><?= $ar ? 'انتقل إلى المحتوى' : 'Skip to content' ?></a>
<header><strong>ChinaPlanetOS</strong><nav aria-label="<?= $ar ? 'اللغة' : 'Language' ?>"><a href="ar.html" lang="ar"<?= $ar ? ' aria-current="page"' : '' ?>>العربية</a><a href="en.html" lang="en"<?= !$ar ? ' aria-current="page"' : '' ?>>English</a></nav></header>
<main id="main">
<h1><?= $e($t['title']) ?></h1><p class="subtitle"><?= $e($t['subtitle']) ?></p>
<p class="notice"><?= $e($t['notice']) ?></p>
<section aria-labelledby="claims"><h2 id="claims"><?= $e($t['claims']) ?></h2><p><?= $e($t['help']) ?></p>
<dl class="totals"><?php foreach ($totals as $currency=>$amount) { ?><div><dt><?= $e($currency) ?></dt><dd data-claim-total="<?= $e($amount.' '.$currency) ?>"><bdi><?= $e($amount) ?></bdi></dd></div><?php } ?></dl></section>
<section aria-labelledby="register"><h2 id="register"><?= $e($t['register']) ?></h2>
<div class="table-wrap" tabindex="0" role="region" aria-label="<?= $e($t['register']) ?>"><table><thead><tr><?php foreach (array('office','type','purpose','original','estimate','status') as $key) { ?><th scope="col"><?= $e($t[$key]) ?></th><?php } ?></tr></thead><tbody>
<?php foreach ($drafts as $draft) { $fx=$draft['conversion']; ?>
<tr><td data-label="<?= $e($t['office']) ?>"><?= $e($t[$draft['office']] ?? $draft['office']) ?><small><bdi><?= $e($draft['occurred_on']) ?></bdi></small></td>
<td data-label="<?= $e($t['type']) ?>"><?= $e($t[$draft['kind']]) ?></td>
<td data-label="<?= $e($t['purpose']) ?>"><strong><?= $e($draft['purpose']) ?></strong><small><?= $e($draft['source_ref']) ?></small><?php if (isset($draft['destination'])) { ?><small><bdi><?= $e($draft['amount'].' '.$draft['currency'].' → '.$draft['destination']['amount'].' '.$draft['destination']['currency']) ?></bdi></small><?php } ?></td>
<td class="money" data-label="<?= $e($t['original']) ?>"><bdi><?= $e($draft['amount'].' '.$draft['currency']) ?></bdi></td>
<td data-label="<?= $e($t['estimate']) ?>"><bdi><?= $e($fx['base_amount'].' '.$fx['base_currency']) ?></bdi><small><?= $e($t['rate']) ?>: <bdi><?= $e($fx['rate']) ?></bdi><?php if ($fx['rate_date']) { ?> · <bdi><?= $e($fx['rate_date']) ?></bdi><?php } ?></small><small><?= $e($t['source']) ?>: <?= $e($fx['rate_source']) ?></small></td>
<td data-label="<?= $e($t['status']) ?>"><span class="status"><?= $e($t['draft']) ?></span></td></tr>
<?php } ?>
<?php if (!$drafts) { ?><tr><td colspan="6"><?= $e($t['empty']) ?></td></tr><?php } ?>
</tbody></table></div></section>
<section class="settlement" aria-labelledby="settlement"><h2 id="settlement"><?= $e($t['example']) ?></h2><dl><?php foreach (array('advanceLabel'=>'1000.00','expenseLabel'=>'700.00','returnLabel'=>'300.00','balanceLabel'=>'0.00') as $label=>$amount) { ?><div><dt><?= $e($t[$label]) ?></dt><dd><bdi><?= $amount ?> EGP</bdi></dd></div><?php } ?></dl><p><?= $e($t['foot']) ?></p></section>
</main></body></html>
        <?php
        return (string) ob_get_clean();
    }
}
