"""Original-style treasury preview. Synthetic values only; no persistence or posting."""
from decimal import Decimal
import html

from preview_dashboard_design import SAMPLES, figures, icon, money, office_picker, word


def treasury(lang):
    t = lambda ar, en: word(lang, ar, en)
    out = ['<main id="office-workspace" class="finens-dashboard treasury-preview">',
           '<div class="treasury-heading"><div><h1>' + t('الخزنة والحركات النقدية', 'Treasury & cash movements') + '</h1><p>'
           + t('متابعة رصيد الخزنة، تسجيل الإيداعات النقدية، وكشف حساب تفصيلي للحركات', 'Monitor treasury balances, cash deposits and a detailed movement ledger')
           + '</p></div><div class="treasury-heading-actions"><button class="treasury-deposit" type="button" data-open="deposit-preview-dialog">'
           + icon('circle-plus') + t('إيداع نقدية في الخزنة', 'Deposit to treasury')
           + '</button><button class="treasury-print" type="button" data-print-treasury>' + icon('print')
           + t('طباعة كشف الخزنة', 'Print treasury statement') + '</button></div></div>',
           '<div class="treasury-preview-note"><p>' + icon('circle-info')
           + t('معاينة تصميم · أرصدة وحركات تجريبية · الحفظ والترحيل معطّلان', 'Design preview · Sample balances and movements · Saving and posting disabled')
           + '</p>' + office_picker(lang) + '</div>']
    for office, sample in SAMPLES.items():
        deposits, expenses, _, balance = figures(sample)
        currency = sample['currency']
        out.append(f'<div class="office-dashboard treasury-office" data-office="{office}"' + (' hidden' if office != 'egypt' else '') + '>')
        out.append('<section class="treasury-summary" aria-label="' + t('ملخص الخزنة التجريبي', 'Sample treasury summary') + '">'
                   + '<div class="treasury-card"><div class="treasury-card-body"><div class="treasury-card-top"><span>'
                   + icon('vault') + t('الخزنة الرئيسية', 'Main treasury') + '</span>' + icon('coins') + '</div><p>'
                   + t('الرصيد الحالي بالخزنة — تجريبي', 'Current treasury balance — sample') + '</p><h2>' + money(balance, currency)
                   + '</h2></div><div class="treasury-card-footer"><span>' + t('الشركة: China Planet', 'Company: China Planet')
                   + '</span><strong>' + t('معاينة فقط', 'Preview only') + '</strong></div></div>')
        for tone, amount, label, caption, symbol in (
            ('deposit', deposits, t('إجمالي إيداعات الفترة المحددة', 'Total deposits in selected period'),
             t('خلال الفترة من 2026-11-01 إلى 2026-11-02', 'From 2026-11-01 to 2026-11-02'), 'arrow-down'),
            ('expense', expenses, t('إجمالي مسحوبات ومصروفات الفترة', 'Total withdrawals & expenses'),
             t('مصروفات + رواتب مسحوبة من الخزنة', 'Expenses + salaries withdrawn from treasury'), 'arrow-up'),
        ):
            out.append(f'<div class="monthly-card {tone}"><div><span>{label}</span><span class="metric-icon">' + icon(symbol)
                       + '</span></div><div><h3 data-period-' + tone + '>' + money(amount, currency) + '</h3><p'
                       + (' data-period-label' if tone == 'deposit' else '') + '>' + caption + '</p></div></div>')
        out.append('</section><section class="treasury-filters" aria-label="' + t('تصفية الحركات التجريبية', 'Filter sample movements')
                   + '"><form data-treasury-filter><div><label for="' + office + '-type">' + t('نوع الحركة', 'Movement type')
                   + '</label><select id="' + office + '-type" name="type">')
        for value, label in (('', t('-- كل الحركات --', '-- All movements --')), ('deposit', t('إيداعات نقدية فقط', 'Cash deposits only')),
                             ('expense', t('مصروفات فقط', 'Expenses only')), ('salary', t('رواتب فقط', 'Salaries only'))):
            out.append(f'<option value="{value}">{label}</option>')
        out.append('</select></div>')
        for name, date, label in (('from', '2026-11-01', t('من تاريخ', 'From date')), ('to', '2026-11-02', t('إلى تاريخ', 'To date'))):
            out.append(f'<div><label for="{office}-{name}">{label}</label><input id="{office}-{name}" type="date" name="{name}" value="{date}" required></div>')
        out.append('<div class="treasury-filter-actions"><button type="submit">' + icon('filter') + t('تصفية', 'Filter')
                   + '</button><button type="reset" aria-label="' + t('إعادة ضبط', 'Reset filters') + '" title="'
                   + t('إعادة ضبط', 'Reset filters') + '">' + icon('rotate-right') + '</button></div></form><p class="treasury-filter-error" role="alert" hidden>'
                   + t('تاريخ البداية يجب أن يسبق تاريخ النهاية أو يساويه.', 'The start date must be on or before the end date.') + '</p></section>')
        out.append('<section class="treasury-ledger"><div class="treasury-ledger-heading"><h2>'
                   + t('كشف حساب الخزنة التفصيلي (Ledger)', 'Detailed treasury ledger') + '</h2><span data-ledger-count aria-live="polite">'
                   + t('3 حركة مسجلة', '3 recorded movements') + '</span></div><div class="treasury-table-scroll" tabindex="0" role="region" aria-label="'
                   + t('كشف الخزنة التجريبي — مرر لعرض كل الأعمدة', 'Sample treasury ledger — scroll to view all columns') + '"><table><thead><tr>')
        for label in ('#', t('النوع', 'Type'), t('البند / البيان', 'Category / description'), t('المبلغ', 'Amount'),
                      t('الرصيد قبل', 'Balance before'), t('الرصيد بعد', 'Balance after'), t('التاريخ', 'Date'), t('المسؤول', 'Responsible user')):
            out.append('<th scope="col">' + label + '</th>')
        out.append('</tr></thead><tbody>')
        running = Decimal(sample['opening'])
        rows = []
        for number, (date, kind, amount, ar, en) in enumerate(sample['rows'], 1):
            before = running
            running += Decimal(amount) if kind == 'deposit' else -Decimal(amount)
            rows.append((number, date, kind, amount, ar if lang == 'ar' else en, before, running))
        for number, date, kind, amount, label, before, after in reversed(rows):
            deposit = kind == 'deposit'
            out.append(f'<tr data-movement="{kind}" data-date="{date}" data-cents="{int(Decimal(amount) * 100)}"><td class="ledger-number">#{number}</td>'
                       + '<td><span class="movement-label ' + kind + '">' + icon('arrow-down' if deposit else 'arrow-up')
                       + t('إيداع' if deposit else 'مصروف', 'Deposit' if deposit else 'Expense') + '</span></td><td class="ledger-description"><strong>'
                       + html.escape(label) + '</strong><p>' + t('حركة تجريبية', 'Sample movement') + '</p></td><td class="ledger-money ' + kind
                       + '"><bdi>' + ('+' if deposit else '−') + f'{Decimal(amount):,.2f} {currency}' + '</bdi></td><td>' + money(before, currency)
                       + '</td><td class="ledger-after">' + money(after, currency) + '</td><td><bdi>' + date + '</bdi></td><td>'
                       + t('مستخدم تجريبي', 'Sample user') + '</td></tr>')
        out.append('<tr class="ledger-empty" hidden><td colspan="8">' + icon('inbox')
                   + t('لا توجد حركات تجريبية تطابق هذه التصفية.', 'No sample movements match these filters.')
                   + '</td></tr></tbody></table></div></section></div>')
    return ''.join(out) + '</main>'


def deposit_dialog(lang):
    t = lambda ar, en: word(lang, ar, en)
    return ('<dialog id="deposit-preview-dialog" class="treasury-deposit-dialog" aria-labelledby="deposit-preview-title">'
            + '<div class="treasury-dialog-heading"><div><span>' + icon('circle-plus') + '</span><h2 id="deposit-preview-title">'
            + t('إيداع نقدية في الخزنة', 'Deposit to treasury') + '</h2></div><button type="button" data-close aria-label="'
            + t('إغلاق', 'Close') + '">' + icon('xmark') + '</button></div><p class="treasury-dialog-note">'
            + t('معاينة فقط. يمكنك تجربة الحقول؛ لن يتم حفظ إيداع أو تغيير الرصيد.', 'Preview only. Try the fields; no deposit is saved and no balance changes.')
            + '</p><form data-preview-deposit><label for="deposit-amount">' + t('المبلغ المودع والعملة', 'Deposit amount & currency')
            + ' <span>*</span></label><div class="deposit-money-input"><input id="deposit-amount" type="number" step="0.01" min="0.01" placeholder="0.00" inputmode="decimal">'
            + '<select aria-label="' + t('العملة', 'Currency') + '" data-deposit-currency><option>EGP</option><option>CNY</option></select></div>'
            + '<label for="deposit-source">' + t('مصدر الإيداع / البند', 'Deposit source / category') + ' <span>*</span></label><input id="deposit-source" placeholder="'
            + t('مثال: توريد مبيعات، تمويل، رصيد إضافي', 'Example: sales proceeds, funding, additional balance') + '">'
            + '<label for="deposit-date">' + t('تاريخ الإيداع', 'Deposit date') + '</label><input id="deposit-date" type="date" value="2026-11-02">'
            + '<label for="deposit-notes">' + t('ملاحظات أو تفاصيل إضافية', 'Notes or additional details') + '</label><textarea id="deposit-notes" rows="2" placeholder="'
            + t('أي تفاصيل أو رقم إيصال...', 'Details or receipt number…') + '"></textarea><div class="treasury-dialog-actions"><button type="button" disabled>'
            + t('تأكيد الإيداع — معطّل في المعاينة', 'Confirm deposit — disabled in preview') + '</button><button type="button" data-close>'
            + t('إلغاء', 'Cancel') + '</button></div></form></dialog>')


SCRIPT = r'''
'use strict';
(() => {
  const ar = document.documentElement.lang.startsWith('ar');
  const format = (cents, currency) => (cents / 100).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ' + currency;
  document.querySelectorAll('[data-treasury-filter]').forEach(form => {
    const office = form.closest('.treasury-office');
    const currency = office.dataset.office === 'china' ? 'CNY' : 'EGP';
    const update = () => {
      const from = form.elements.from.value, to = form.elements.to.value;
      const invalid = from && to && from > to;
      office.querySelector('.treasury-filter-error').hidden = !invalid;
      form.elements.to.setAttribute('aria-invalid', invalid ? 'true' : 'false');
      if (invalid) return;
      let count = 0, deposits = 0, expenses = 0;
      office.querySelectorAll('[data-movement]').forEach(row => {
        row.hidden = !!((form.elements.type.value && row.dataset.movement !== form.elements.type.value) || (from && row.dataset.date < from) || (to && row.dataset.date > to));
        if (!row.hidden) {
          count++;
          if (row.dataset.movement === 'deposit') deposits += Number(row.dataset.cents);
          else expenses += Number(row.dataset.cents);
        }
      });
      office.querySelector('[data-period-deposit]').textContent = format(deposits, currency);
      office.querySelector('[data-period-expense]').textContent = format(expenses, currency);
      office.querySelector('[data-period-label]').textContent = ar ? `خلال الفترة من ${from} إلى ${to}` : `From ${from} to ${to}`;
      office.querySelector('[data-ledger-count]').textContent = ar ? `${count} حركة مسجلة` : `${count} recorded movements`;
      office.querySelector('.ledger-empty').hidden = count !== 0;
    };
    form.addEventListener('submit', event => { event.preventDefault(); update(); });
    form.addEventListener('reset', () => setTimeout(update, 0));
  });
  const picker = document.querySelector('[data-office-picker]');
  const syncCurrency = () => {
    const currency = document.querySelector('[data-deposit-currency]');
    if (currency) currency.value = (picker?.value || new URLSearchParams(location.search).get('office')) === 'china' ? 'CNY' : 'EGP';
  };
  picker?.addEventListener('change', syncCurrency);
  syncCurrency();
  // The integrated browser can deliver Escape without the native cancel event.
  document.querySelector('#deposit-preview-dialog')?.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); event.currentTarget.close(); }
  });
  document.querySelector('[data-preview-deposit]')?.addEventListener('submit', event => event.preventDefault());
  document.querySelector('[data-print-treasury]')?.addEventListener('click', () => window.print());
})();
'''


CSS = '''
.preview-with-erp .treasury-preview [hidden]{display:none!important}
.preview-with-erp .treasury-heading{display:flex;flex-direction:column;gap:16px}
.preview-with-erp .treasury-heading h1{font-size:24px;line-height:32px;font-weight:900;color:#0f172a}
.preview-with-erp .treasury-heading p{font-size:12px;line-height:16px;color:#64748b;margin-top:4px}
.preview-with-erp .treasury-heading-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.preview-with-erp .treasury-heading button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 16px;border-radius:12px;font-size:12px;line-height:16px;font-weight:700;cursor:pointer}
.preview-with-erp .treasury-heading .treasury-deposit{background:#059669;color:white;border:0;box-shadow:0 4px 6px -1px #0000001a,0 2px 4px -2px #0000001a}
.preview-with-erp .treasury-heading .treasury-deposit:hover{background:#047857}
.preview-with-erp .treasury-heading .treasury-print{background:white;border:1px solid #cbd5e1;color:#334155;padding:8px 12px;gap:6px}
.preview-with-erp .treasury-heading .treasury-print:hover{background:#f1f5f9}
.preview-with-erp .treasury-preview-note{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-top:-12px;margin-bottom:-8px}
.preview-with-erp .treasury-preview-note p{font-size:11px;color:#64748b;display:flex;align-items:center;gap:6px}
.preview-with-erp .treasury-summary{display:grid;grid-template-columns:minmax(0,1fr);gap:20px}
.preview-with-erp .treasury-summary .treasury-card{position:static;overflow:visible}
.preview-with-erp .treasury-summary .treasury-card-top{margin-bottom:8px}
.preview-with-erp .treasury-summary .treasury-card-top>span{backdrop-filter:none}
.preview-with-erp .treasury-summary .treasury-card-top>i{font-size:24px;line-height:32px}
.preview-with-erp .treasury-summary .treasury-card-body>p{margin-top:8px}
.preview-with-erp .treasury-summary .treasury-card-footer{padding-top:16px;margin-top:12px;font-size:11px;line-height:16.5px}
.preview-with-erp .treasury-summary .monthly-card>div+div{margin-top:16px}
.preview-with-erp .treasury-summary .monthly-card.deposit h3{color:#059669}
.preview-with-erp .treasury-summary .monthly-card p{font-weight:400;color:#94a3b8}
.preview-with-erp .treasury-filters{padding:16px;background:white;border:1px solid #e2e8f0cc;border-radius:16px}
.preview-with-erp .treasury-filters form{display:grid;grid-template-columns:minmax(0,1fr);gap:12px;align-items:end;font-size:12px;line-height:16px}
.preview-with-erp .treasury-filters form>div{min-width:0}
.preview-with-erp .treasury-filters label{display:block;font-weight:700;color:#334155;margin-bottom:4px}
.preview-with-erp .treasury-filters :is(input,select){width:100%;min-width:0;min-height:0;padding:8px 12px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;font:inherit;color:#1e293b}
.preview-with-erp .treasury-filters :is(input,select):focus{background:white;outline:2px solid #6366f1;outline-offset:2px}
.preview-with-erp .treasury-filter-actions{display:flex;align-items:center;gap:8px}
.preview-with-erp .treasury-filter-actions button{background:#4f46e5;color:white;font:inherit;font-weight:700;border:0;border-radius:12px;padding:8px 12px;cursor:pointer}
.preview-with-erp .treasury-filter-actions button[type=submit]{flex:1}
.preview-with-erp .treasury-filter-actions button[type=submit]:hover{background:#4338ca}
.preview-with-erp .treasury-filter-actions button[type=reset]{background:#f1f5f9;color:#475569}
.preview-with-erp .treasury-filter-error{font-size:12px;color:#be123c;margin-top:8px}
.preview-with-erp .treasury-ledger{min-width:0;background:white;border:1px solid #e2e8f0cc;border-radius:16px;overflow:hidden}
.preview-with-erp .treasury-ledger-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px;border-bottom:1px solid #f1f5f9}
.preview-with-erp .treasury-ledger-heading h2{font-size:16px;line-height:24px;font-weight:900;color:#1e293b}
.preview-with-erp .treasury-ledger-heading>span{font-size:12px;line-height:16px;font-weight:500;color:#94a3b8}
.preview-with-erp .treasury-table-scroll{overflow-x:auto;overscroll-behavior-x:contain}
.preview-with-erp .treasury-table-scroll table{width:100%;border-collapse:collapse;font-size:12px;line-height:16px;text-align:start}
.preview-with-erp .treasury-table-scroll :is(th,td){padding:14px;text-align:start}
.preview-with-erp .treasury-table-scroll th{background:#f8fafc;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:700}
.preview-with-erp .treasury-table-scroll th:first-child{display:table-cell}
.preview-with-erp .treasury-table-scroll td{white-space:nowrap;color:#64748b;border-bottom:1px solid #f1f5f9}
.preview-with-erp .treasury-table-scroll tr:last-child td{border-bottom:0}
.preview-with-erp .treasury-table-scroll tbody tr:hover{background:#f8fafcb3}
.preview-with-erp .treasury-table-scroll .ledger-description{white-space:normal;min-width:150px}
.preview-with-erp .ledger-description strong{font-weight:700;color:#1e293b}
.preview-with-erp .ledger-description p{font-size:11px;line-height:16.5px;color:#94a3b8;margin-top:2px}
.preview-with-erp .treasury-table-scroll .ledger-number{font-size:10px;font-weight:700;color:#94a3b8}
.preview-with-erp .treasury-table-scroll .ledger-money{font-size:14px;line-height:20px;font-weight:900;color:#059669}
.preview-with-erp .treasury-table-scroll .ledger-money.expense{color:#e11d48}
.preview-with-erp .treasury-table-scroll .ledger-after{font-weight:700;background:#f8fafc80;color:#334155}
.preview-with-erp .ledger-empty td{text-align:center;padding:40px 16px;color:#64748b;white-space:normal}
.preview-with-erp .ledger-empty i{margin-inline-end:8px}
.preview-with-erp .treasury-deposit-dialog{width:calc(100% - 32px);max-width:448px;max-height:90dvh;overflow-y:auto;padding:24px;border:1px solid #f1f5f9;border-radius:16px;box-shadow:0 25px 50px -12px #0004;color:#1e293b;font:16px/1.5 ui-sans-serif,system-ui,sans-serif}
.preview-with-erp .treasury-deposit-dialog::backdrop{background:#0f172a99;backdrop-filter:blur(4px)}
.preview-with-erp .treasury-dialog-heading{display:flex;align-items:center;justify-content:space-between;padding-bottom:12px;border-bottom:1px solid #f1f5f9;margin-bottom:16px;gap:8px}
.preview-with-erp .treasury-dialog-heading>div{display:flex;align-items:center;gap:8px}
.preview-with-erp .treasury-dialog-heading span{display:flex;align-items:center;justify-content:center;width:32px;height:32px;flex-shrink:0;border-radius:8px;background:#d1fae5;color:#059669}
.preview-with-erp .treasury-dialog-heading h2{font-size:18px;line-height:28px;font-weight:700;margin:0}
.preview-with-erp .treasury-dialog-heading button{background:transparent;border:0;color:#94a3b8;font-size:18px;cursor:pointer;padding:4px}
.preview-with-erp .treasury-dialog-note{font-size:11px;color:#64748b;margin:0 0 16px}
.preview-with-erp .treasury-deposit-dialog label{display:block;font-size:12px;line-height:16px;font-weight:700;color:#334155;margin-bottom:4px;margin-top:16px}
.preview-with-erp .treasury-deposit-dialog label span{color:#ef4444}
.preview-with-erp .treasury-deposit-dialog :is(input,select,textarea){width:100%;min-width:0;background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:8px 12px;font:14px/20px ui-sans-serif,system-ui,sans-serif;color:#1e293b}
.preview-with-erp .treasury-deposit-dialog :is(input,select,textarea):focus{outline:2px solid #10b981;outline-offset:2px;background:white}
.preview-with-erp .treasury-deposit-dialog .deposit-money-input{display:flex;gap:8px}
.preview-with-erp .deposit-money-input input{flex:1;font-size:20px;line-height:28px;font-weight:700;color:#059669;padding:10px 16px}
.preview-with-erp .deposit-money-input select{width:144px;font-size:12px;font-weight:700;flex-shrink:0}
.preview-with-erp .treasury-dialog-actions{display:flex;align-items:center;gap:8px;padding-top:8px;margin-top:16px}
.preview-with-erp .treasury-dialog-actions button{border:0;border-radius:12px;padding:10px 16px;font-size:14px;line-height:20px;font-weight:700;cursor:pointer;background:#f1f5f9;color:#334155}
.preview-with-erp .treasury-dialog-actions button:disabled{flex:1;background:#059669;color:white;opacity:.55;cursor:not-allowed}
@media(min-width:640px){
 .preview-with-erp .treasury-heading{flex-direction:row;align-items:center;justify-content:space-between}
 .preview-with-erp .treasury-heading .treasury-deposit{font-size:14px;line-height:20px}
 .preview-with-erp .treasury-filters form{grid-template-columns:repeat(4,minmax(0,1fr))}
 .preview-with-erp .treasury-ledger-heading{padding:20px}
}
@media(min-width:768px){.preview-with-erp .treasury-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media print{
 .preview-with-erp :is(.erp-topbar,#app-sidebar,.app-navbar,.bottom-nav,dialog,.treasury-heading-actions,.treasury-filters,.office-picker){display:none!important}
 .preview-with-erp :is(.app-layout,.app-content){display:block!important}
 .preview-with-erp .treasury-preview{padding:0;max-width:none}
 .preview-with-erp .treasury-preview .treasury-summary{grid-template-columns:repeat(3,minmax(0,1fr));break-inside:avoid}
 .preview-with-erp .treasury-table-scroll{overflow:visible}
 .preview-with-erp .treasury-table-scroll table{font-size:10px}
 .preview-with-erp .treasury-table-scroll :is(th,td){padding:6px;white-space:normal}
 .preview-with-erp .treasury-table-scroll .ledger-description{min-width:0}
 .preview-with-erp .treasury-preview-note{margin:0}
 .preview-with-erp .treasury-preview *{print-color-adjust:exact;-webkit-print-color-adjust:exact}
}
'''
