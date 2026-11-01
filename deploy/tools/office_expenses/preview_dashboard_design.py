"""Synthetic Finens dashboard design only; never reads a ledger or posts money."""
from decimal import Decimal
import html


SAMPLES = {
    'egypt': {'currency': 'EGP', 'ar': 'مكتب مصر', 'en': 'Egypt office', 'opening': '2000.00',
              'rows': [('2026-11-01', 'deposit', '10000.00', 'تمويل المكتب التجريبي', 'Sample office funding'),
                       ('2026-11-02', 'expense', '1900.00', 'إيجار المقر', 'Office rent'),
                       ('2026-11-02', 'expense', '750.00', 'أدوات مكتبية وصيانة', 'Office supplies')]},
    'china': {'currency': 'CNY', 'ar': 'مكتب الصين', 'en': 'China office', 'opening': '500.00',
              'rows': [('2026-11-01', 'deposit', '3000.00', 'تمويل المكتب التجريبي', 'Sample office funding'),
                       ('2026-11-02', 'expense', '420.00', 'فواتير ومرافق', 'Office utilities'),
                       ('2026-11-02', 'expense', '180.29', 'انتقالات المكتب', 'Office transport')]},
}


def icon(name):
    return f'<i class="fa-solid fa-{name}" aria-hidden="true"></i>'


def url(lang, view):
    return lang + ('' if view == 'expenses' else '-' + view) + '.html'


def word(lang, ar, en):
    return ar if lang == 'ar' else en


def figures(sample):
    deposits = sum((Decimal(row[2]) for row in sample['rows'] if row[1] == 'deposit'), Decimal('0'))
    expenses = sum((Decimal(row[2]) for row in sample['rows'] if row[1] == 'expense'), Decimal('0'))
    flow = deposits - expenses
    return deposits, expenses, flow, Decimal(sample['opening']) + flow


def money(amount, currency):
    return f'<bdi>{Decimal(amount):,.2f} {currency}</bdi>'


def office_picker(lang):
    return '<label class="office-picker"><span>' + word(lang, 'المكتب', 'Office') + '</span><select data-office-picker aria-label="' + word(lang, 'المكتب والعملة', 'Office and currency') + '">' + ''.join(
        f'<option value="{office}">{sample[lang]} · {sample["currency"]}</option>' for office, sample in SAMPLES.items()) + '</select></label>'


def actions(lang, compact=False):
    receipt = word(lang, 'وصل استلام' if compact else 'وصل استلام نقدية', 'Cash receipt')
    deposit = word(lang, 'إيداع سريع' if compact else 'إيداع في الخزنة', 'Quick deposit' if compact else 'Deposit to treasury')
    expense = word(lang, 'مصروف جديد' if compact else 'تسجيل مصروف', 'New expense' if compact else 'Record expense')
    return ('<div class="finens-actions' + (' compact-actions' if compact else '') + '">'
            + f'<a class="button action-receipt" href="{url(lang,"receipts")}">' + icon('receipt') + receipt + '</a>'
            + '<button class="button action-deposit" type="button" data-open="deposit-preview-dialog">' + icon('circle-plus') + deposit + '</button>'
            + '<button class="button action-expense" type="button" data-open="expense-dialog">' + icon('circle-minus') + expense + '</button></div>')


def toolbar(lang, view):
    label = word(lang, 'رصيد الخزنة', 'Treasury balance')
    pill = '<div class="treasury-pill" title="' + word(lang, 'رصيد توضيحي فقط، ليس رصيداً فعلياً', 'Illustrative balance only, not an actual balance') + '">' + icon('vault') + '<span>' + label + ': </span>'
    for office, sample in SAMPLES.items():
        pill += f'<strong data-office="{office}"' + (' hidden' if office != 'egypt' else '') + '>' + money(figures(sample)[3], sample['currency']) + '</strong>'
    pill += '<small>' + word(lang, 'تجريبي', 'Sample') + '</small></div>'
    alternate = url('en' if lang == 'ar' else 'ar', view)
    return ('<header class="app-navbar finens-navbar" role="region" aria-label="Office expenses toolbar"><div class="company-context">'
            + '<button type="button" class="icon-button menu-toggle" data-open="navigation-drawer" aria-label="' + word(lang, 'فتح القائمة', 'Open menu') + '">' + icon('bars') + '</button><span class="company-icon">' + icon('building') + '</span>'
            + '<div><strong>China Planet</strong><small>' + word(lang, 'مدير المكتب · مراجعة التصميم', 'Office manager · Design review') + '</small></div></div>'
            + '<div class="finens-toolbar-actions">' + pill + actions(lang, True)
            + f'<a class="language-link" href="{alternate}" lang="' + ('en' if lang == 'ar' else 'ar') + '" aria-label="' + word(lang, 'Switch to English', 'التبديل إلى العربية') + '">' + icon('globe') + '</a></div></header>')


def deposit_dialog(lang):
    heading = word(lang, 'إيداع نقدية في الخزنة', 'Deposit to treasury')
    note = word(lang, 'معاينة تصميم فقط. لن يتم حفظ إيداع أو تعديل أي رصيد.', 'Design preview only. No deposit is saved and no balance changes.')
    return ('<dialog id="deposit-preview-dialog" class="expense-dialog" aria-labelledby="deposit-preview-title"><div class="dialog-header"><h2 id="deposit-preview-title">' + heading + '</h2><button type="button" class="icon-button" data-close aria-label="' + word(lang, 'إغلاق', 'Close') + '">' + icon('xmark') + '</button></div><p class="dialog-note">' + note + '</p>'
            + '<fieldset class="form-grid" disabled><div><label for="preview-deposit-amount">' + word(lang, 'المبلغ', 'Amount') + '</label><input id="preview-deposit-amount" placeholder="0.00" inputmode="decimal"></div><div><label for="preview-deposit-currency">' + word(lang, 'العملة', 'Currency') + '</label><select id="preview-deposit-currency"><option>EGP</option><option>CNY</option></select></div><div class="full"><label for="preview-deposit-purpose">' + word(lang, 'البيان / السبب', 'Description / purpose') + '</label><textarea id="preview-deposit-purpose" rows="2"></textarea></div></fieldset>'
            + '<div class="dialog-actions"><button class="button action-deposit" type="button" disabled>' + word(lang, 'حفظ الإيداع — غير متاح في المعاينة', 'Save deposit — unavailable in preview') + '</button><button class="button button-muted" type="button" data-close>' + word(lang, 'إغلاق', 'Close') + '</button></div></dialog>')


def dashboard(lang):
    ar = lang == 'ar'
    output = ['<main id="office-workspace" class="finens-dashboard">',
              '<section class="dashboard-welcome"><div><div class="welcome-meta"><span class="company-tag">' + icon('building') + 'China Planet</span><span>' + word(lang, 'شهر 11 / 2026', 'November 2026') + '</span>' + office_picker(lang) + '</div><h1>' + word(lang, 'مرحباً بك، مدير المكتب', 'Welcome, Office Manager') + '</h1><p>' + word(lang, 'متابعة دقيقة لحركة الخزنة والمصروفات اليومية', 'Follow treasury movements and daily office expenses') + '</p></div>' + actions(lang) + '</section>',
              '<div class="dashboard-context"><p>' + icon('circle-info') + word(lang, 'معاينة تصميم · أرصدة وحركات مصطنعة · لا يتم حفظ أو ترحيل أي مبالغ', 'Design preview · Synthetic balances and transactions · No amounts are saved or posted') + '</p></div>']
    for office, sample in SAMPLES.items():
        deposits, expenses, flow, balance = figures(sample)
        currency = sample['currency']
        output.append(f'<div class="office-dashboard" data-office="{office}"' + (' hidden' if office != 'egypt' else '') + '>')
        output.append('<section class="dashboard-metrics" aria-label="' + word(lang, 'ملخص الخزنة التجريبي', 'Sample treasury summary') + '"><div class="treasury-card"><div class="treasury-card-body"><div class="treasury-card-top"><span>' + icon('vault') + word(lang, 'الخزنة الرئيسية', 'Main treasury') + '</span>' + icon('shield-halved') + '</div><p>' + word(lang, 'رصيد توضيحي متوفر حالياً', 'Illustrative available balance') + '</p><h2>' + money(balance, currency) + '</h2></div><div class="treasury-card-footer"><span>' + word(lang, 'آخر تحديث: 2026-11-02', 'Updated: 2026-11-02') + '</span><a href="' + url(lang, 'treasury') + '">' + word(lang, 'كشف الحركة ←', 'View ledger →') + '</a></div></div><div class="monthly-metrics">')
        for name, amount, symbol, tone, caption in (
            (word(lang, 'إيداعات الشهر', 'Monthly deposits'), deposits, 'arrow-down-long', 'deposit', word(lang, 'داخل الخزنة', 'Into treasury')),
            (word(lang, 'مصروفات الشهر', 'Monthly expenses'), expenses, 'arrow-up-long', 'expense', word(lang, 'تم سحبها وصرفها', 'Withdrawn and spent')),
            (word(lang, 'صافي السيولة', 'Net cash flow'), flow, 'scale-balanced', 'flow', word(lang, 'فائض سيولة', 'Cash surplus')),
        ):
            output.append(f'<div class="monthly-card {tone}"><div><span>{name}</span><span class="metric-icon">' + icon(symbol) + '</span></div><div><h3>' + money(amount, currency) + '</h3><p>' + (icon('check') if tone == 'deposit' else icon('minus') if tone == 'expense' else '') + caption + '</p></div></div>')
        output.append('</div></section><section class="modules-strip" aria-label="' + word(lang, 'أقسام معاينة الشركة', 'Company preview sections') + '"><div>' + icon('circle-info') + '<strong>' + word(lang, 'الموديولات في المعاينة:', 'Preview modules:') + '</strong>')
        for view, name in (('treasury', word(lang, 'الخزنة', 'Treasury')), ('expenses', word(lang, 'المصروفات', 'Expenses')), ('reports', word(lang, 'التقارير', 'Reports')), ('payroll', word(lang, 'شؤون الموظفين والمرتبات', 'HR & payroll'))):
            output.append('<a href="' + url(lang, view) + '">' + name + '</a>')
        output.append('</div><small>' + html.escape(sample[lang]) + ' · ' + currency + '</small></section><div class="dashboard-detail-grid"><section class="recent-transactions"><div class="dashboard-panel-heading"><div><h2>' + word(lang, 'آخر حركات الخزنة والمصروفات', 'Recent treasury and expense movements') + '</h2><p>' + word(lang, 'سجل الإيداعات والمسحوبات الأخيرة — بيانات تجريبية', 'Recent deposits and withdrawals — sample data') + '</p></div><a href="' + url(lang, 'treasury') + '">' + word(lang, 'عرض كامل السجل ←', 'View full ledger →') + '</a></div><div class="dashboard-table-scroll" tabindex="0" role="region" aria-label="' + word(lang, 'حركات تجريبية', 'Sample movements') + '"><table><thead><tr>')
        for name in (word(lang, 'النوع', 'Type'), word(lang, 'البند / السبب', 'Category / purpose'), word(lang, 'المبلغ', 'Amount'), word(lang, 'الرصيد بعد العملية', 'Balance after'), word(lang, 'التاريخ', 'Date'), word(lang, 'بواسطة', 'By')):
            output.append('<th scope="col">' + name + '</th>')
        output.append('</tr></thead><tbody>')
        running = Decimal(sample['opening'])
        rows = []
        for date, kind, amount, arabic, english in sample['rows']:
            running += Decimal(amount) if kind == 'deposit' else -Decimal(amount)
            rows.append((date, kind, amount, arabic if ar else english, running))
        for date, kind, amount, purpose, after in reversed(rows):
            deposit = kind == 'deposit'
            output.append('<tr><td><span class="movement-label ' + kind + '">' + icon('arrow-down' if deposit else 'arrow-up') + word(lang, 'إيداع' if deposit else 'مصروف', 'Deposit' if deposit else 'Expense') + '</span></td><td><strong>' + html.escape(purpose) + '</strong><small>' + word(lang, 'حركة تجريبية', 'Sample movement') + '</small></td><td class="movement-money ' + kind + '"><span dir="ltr">' + ('+' if deposit else '−') + money(amount, currency) + '</span></td><td>' + money(after, currency) + '</td><td><bdi>' + date + '</bdi></td><td>' + word(lang, 'مستخدم تجريبي', 'Sample user') + '</td></tr>')
        output.append('</tbody></table></div><div class="opening-note">' + word(lang, 'الرصيد الافتتاحي التجريبي:', 'Sample opening balance:') + ' ' + money(sample['opening'], currency) + '</div></section><section class="top-spending"><div class="top-spending-body"><h2>' + word(lang, 'أعلى بنود الصرف هذا الشهر', 'Top spending categories this month') + '</h2><p>' + word(lang, 'توزيع النفقات بحسب البنود', 'Expenses by category') + '</p>')
        for date, kind, amount, arabic, english in sorted(sample['rows'], key=lambda row: Decimal(row[2]), reverse=True):
            if kind != 'expense':
                continue
            percent = (Decimal(amount) / expenses * 100).quantize(Decimal('0.1'))
            output.append('<div class="spending-category"><div><strong>' + html.escape(arabic if ar else english) + '</strong>' + money(amount, currency) + '</div><div class="spending-track"><span style="width:' + str(percent) + '%"></span></div><small>' + str(percent) + word(lang, '% من إجمالي مصروفات الشهر', '% of monthly expenses') + '</small></div>')
        output.append('</div><div class="spending-report-footer"><a class="report-preview-link" href="' + url(lang, 'reports') + '">' + icon('chart-pie') + word(lang, 'عرض التقارير والرسوم البيانية الكاملة', 'View financial reports and charts') + '</a></div></section></div></div>')
    return ''.join(output) + '</main>'


SCRIPT = '''
'use strict';
(() => {
  const selected = new URLSearchParams(location.search).get('office');
  const picker = document.querySelector('[data-office-picker]');
  let office = selected === 'china' ? 'china' : 'egypt';
  const update = () => {
    document.querySelectorAll('[data-office]').forEach(element => { element.hidden = element.dataset.office !== office; });
    document.querySelectorAll('a[href]').forEach(anchor => {
      const target = new URL(anchor.getAttribute('href'), location.href);
      if (target.origin === location.origin && target.pathname.endsWith('.html')) {
        target.searchParams.set('office', office);
        anchor.setAttribute('href', target.pathname.split('/').pop() + target.search);
      }
    });
  };
  if (picker) {
    picker.value = office;
    picker.addEventListener('change', () => {
      office = picker.value === 'china' ? 'china' : 'egypt';
      const target = new URL(location.href);
      target.searchParams.set('office', office);
      history.replaceState(null, '', target);
      update();
    });
  }
  update();
})();
'''


CSS = '''
.preview-with-erp [data-office][hidden]{display:none!important}
.preview-with-erp .finens-navbar{min-height:65px;padding:12px 24px;gap:16px}
.preview-with-erp .finens-toolbar-actions{display:flex;align-items:center;gap:10px;min-width:0}
.preview-with-erp .finens-actions{display:flex;align-items:center;gap:8px;flex-shrink:0}
.preview-with-erp .finens-actions .button{font-size:12px;min-height:40px;border-radius:12px;padding:10px 16px;white-space:nowrap}
.preview-with-erp .compact-actions .button{min-height:30px;padding:5px 10px;border-radius:8px;font-size:11px;gap:6px}
.preview-with-erp .action-receipt{background:#4f46e5;color:white;box-shadow:0 4px 6px -1px #0002}
.preview-with-erp .action-receipt:hover{background:#4338ca}
.preview-with-erp .action-deposit{background:#059669;color:white;box-shadow:0 4px 6px -1px #0002}
.preview-with-erp .action-deposit:hover{background:#047857}
.preview-with-erp .action-expense{background:#e11d48;color:white;box-shadow:0 4px 6px -1px #0002}
.preview-with-erp .action-expense:hover{background:#be123c}
.preview-with-erp .treasury-pill{display:flex;align-items:center;justify-content:center;gap:5px;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;padding:6px 10px;border-radius:12px;white-space:nowrap;font-size:11px;font-weight:700}
.preview-with-erp .treasury-pill strong{font-size:13px;font-weight:900}
.preview-with-erp .treasury-pill small{font-size:9px;color:#047857;font-weight:500}
.preview-with-erp .finens-navbar .language-link{font-size:12px;padding:6px;min-width:32px}
/* Match the supplied original dashboard utilities; scoped away from ERP/runtime UI. */
.preview-with-erp .finens-dashboard{display:flex;flex-direction:column;gap:24px;padding:16px;max-width:1280px;font-family:ui-sans-serif,system-ui,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";font-size:16px;line-height:1.5;color:#1e293b}
.preview-with-erp .finens-dashboard :is(h1,h2,h3,p){margin:0}
.preview-with-erp .dashboard-welcome{display:flex;flex-direction:column;gap:16px;background:white;border:1px solid #e2e8f0cc;border-radius:24px;padding:20px}
.preview-with-erp .welcome-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px;color:#94a3b8;font-size:12px;font-weight:500;margin-bottom:4px}
.preview-with-erp .company-tag{display:inline-flex;align-items:center;gap:4px;color:#4338ca;background:#eef2ff;border:1px solid #c7d2fe;border-radius:9999px;padding:2px 10px;font-size:12px;font-weight:700}
.preview-with-erp .dashboard-welcome h1{font-size:20px;line-height:28px;font-weight:900;color:#0f172a}
.preview-with-erp .dashboard-welcome p{font-size:12px;line-height:16px;color:#64748b;margin-top:2px}
.preview-with-erp .dashboard-welcome .finens-actions{flex-shrink:1;min-width:0}
.preview-with-erp .dashboard-welcome .finens-actions .button{flex:1 1 0%;min-width:0;min-height:0;font-size:12px;line-height:16px;white-space:normal;text-align:center;padding:10px 16px;font-weight:700}
.preview-with-erp .dashboard-welcome .action-receipt{box-shadow:0 4px 6px -1px #4f46e533,0 2px 4px -2px #4f46e533}
.preview-with-erp .dashboard-welcome .action-deposit{box-shadow:0 4px 6px -1px #05966933,0 2px 4px -2px #05966933}
.preview-with-erp .dashboard-welcome .action-expense{box-shadow:0 4px 6px -1px #e11d4833,0 2px 4px -2px #e11d4833}
.preview-with-erp .dashboard-context{margin-top:-14px;margin-bottom:-8px}
.preview-with-erp .dashboard-context p{font-size:11px;color:#64748b;display:flex;align-items:center;gap:6px}
.preview-with-erp .office-picker{display:flex;align-items:center;gap:8px;color:#475569;font-size:11px;flex-shrink:0}
.preview-with-erp .office-picker select{width:auto;min-height:36px;padding:6px 10px;border:1px solid #e2e8f0;border-radius:8px;background:white;color:#334155;font-size:11px}
.preview-with-erp .office-dashboard{display:flex;flex-direction:column;gap:24px;min-width:0}
.preview-with-erp .dashboard-metrics{display:grid;grid-template-columns:minmax(0,1fr);gap:20px}
.preview-with-erp .monthly-metrics{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;min-width:0}
.preview-with-erp .treasury-card{display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;border-radius:24px;padding:24px;background:linear-gradient(135deg,#059669 0%,#10b981 50%,#34d399 100%);color:white;box-shadow:0 20px 25px -5px #10b9811a,0 8px 10px -6px #10b9811a}
.preview-with-erp .treasury-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.preview-with-erp .treasury-card-top>span{font-size:12px;line-height:16px;font-weight:900;letter-spacing:.05em;text-transform:uppercase;border-radius:9999px;padding:4px 12px;background:#fff3;backdrop-filter:blur(12px)}
.preview-with-erp .treasury-card-top>i{font-size:20px;line-height:28px;color:#ffffff80}
.preview-with-erp .treasury-card-top>span i{margin-inline-end:4px}
.preview-with-erp .treasury-card-body>p{font-size:12px;line-height:16px;font-weight:700;color:#d1fae5}
.preview-with-erp .treasury-card h2{font-size:30px;font-weight:900;line-height:36px;letter-spacing:-.025em;margin-top:8px;overflow-wrap:normal}
.preview-with-erp .treasury-card-footer{display:flex;align-items:center;justify-content:space-between;border-top:1px solid #fff3;padding-top:20px;margin-top:16px;font-size:12px;line-height:16px;color:#d1fae5}
.preview-with-erp .treasury-card-footer a{font-weight:700;text-decoration:underline;color:white}
.preview-with-erp .monthly-card{display:flex;flex-direction:column;justify-content:space-between;background:white;border:1px solid #e2e8f0cc;border-radius:16px;padding:20px;min-width:0}
.preview-with-erp .monthly-card>div:first-child{display:flex;align-items:center;justify-content:space-between;color:#64748b;font-size:12px;line-height:16px;font-weight:700}
.preview-with-erp .monthly-card>div+div{margin-top:12px}
.preview-with-erp .metric-icon{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:12px;background:#ecfdf5;color:#059669;flex:0 0 36px;font-size:14px;line-height:20px}
.preview-with-erp .monthly-card h3{font-size:24px;line-height:32px;font-weight:900;color:#1e293b;overflow-wrap:normal}
.preview-with-erp .monthly-card p{font-size:11px;line-height:16.5px;color:#059669;margin-top:4px;font-weight:700}
.preview-with-erp .monthly-card p i{margin-inline-end:4px}
.preview-with-erp .monthly-card.expense h3{color:#e11d48}
.preview-with-erp .monthly-card.expense p{color:#f43f5e}
.preview-with-erp .monthly-card.expense .metric-icon{background:#fff1f2;color:#e11d48}
.preview-with-erp .monthly-card.flow h3,.preview-with-erp .monthly-card.flow p{color:#d97706}
.preview-with-erp .monthly-card.flow .metric-icon{background:#fffbeb;color:#d97706}
.preview-with-erp .modules-strip{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;background:#f1f5f9cc;border:1px solid #e2e8f099;border-radius:16px;padding:16px}
.preview-with-erp .modules-strip>div{display:flex;align-items:center;flex-wrap:wrap;gap:8px;font-size:12px;line-height:16px;color:#475569;font-weight:700}
.preview-with-erp .modules-strip i{color:#4f46e5}
.preview-with-erp .modules-strip a{background:white;border:1px solid #e5e7eb;border-radius:4px;padding:2px 8px;font-size:12px;line-height:16px;font-weight:700;color:#334155}
.preview-with-erp .modules-strip a:last-child{background:#f3e8ff;border-color:#e9d5ff;color:#6b21a8}
.preview-with-erp .modules-strip small{font-size:11px;line-height:16.5px;color:#94a3b8}
.preview-with-erp .dashboard-detail-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:24px;align-items:stretch}
.preview-with-erp .recent-transactions,.preview-with-erp .top-spending{min-width:0;background:white;border:1px solid #e2e8f0cc;border-radius:16px;overflow:hidden}
.preview-with-erp .dashboard-panel-heading{display:flex;justify-content:space-between;align-items:center;padding:16px;border-bottom:1px solid #f1f5f9}
.preview-with-erp .dashboard-detail-grid h2{font-size:16px;line-height:24px;font-weight:900;color:#1e293b}
.preview-with-erp .dashboard-detail-grid p{font-size:11px;line-height:16.5px;color:#94a3b8}
.preview-with-erp .dashboard-panel-heading>a{color:#4f46e5;font-size:12px;line-height:16px;font-weight:700}
.preview-with-erp .dashboard-table-scroll{overflow-x:auto;overscroll-behavior-x:contain}
.preview-with-erp .dashboard-table-scroll table{width:100%;font-size:12px;line-height:16px;border-collapse:collapse;text-align:start}
.preview-with-erp .dashboard-table-scroll th{background:#f8fafc;font-size:12px;font-weight:700;text-align:start;padding:14px;color:#64748b;border-bottom:1px solid #f1f5f9}
.preview-with-erp .dashboard-table-scroll td{padding:14px;white-space:nowrap;font-size:12px;line-height:16px;border-bottom:1px solid #f1f5f9;color:#64748b}
.preview-with-erp .dashboard-table-scroll th:first-child{display:table-cell}
.preview-with-erp .dashboard-table-scroll td:nth-child(2){white-space:normal;color:#1e293b}
.preview-with-erp .dashboard-table-scroll td strong{font-size:12px;color:#1e293b}
.preview-with-erp .dashboard-table-scroll td small{display:block;font-size:11px;line-height:16.5px;color:#94a3b8;margin-top:2px}
.preview-with-erp .movement-label{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:9999px;background:#d1fae5;color:#065f46;font-size:10px;line-height:15px;font-weight:700}
.preview-with-erp .movement-label.expense{background:#ffe4e6;color:#9f1239}
.preview-with-erp .dashboard-table-scroll .movement-money{font-weight:900;color:#059669}.preview-with-erp .dashboard-table-scroll .movement-money.expense{color:#e11d48}
.preview-with-erp .opening-note{font-size:10px;color:#64748b;padding:10px 20px;border-top:1px solid #f1f5f9}
.preview-with-erp .top-spending{padding:20px;display:flex;flex-direction:column;justify-content:space-between}
.preview-with-erp .top-spending-body>p{margin-top:4px;margin-bottom:16px}
.preview-with-erp .spending-category+.spending-category{margin-top:12px}
.preview-with-erp .spending-category>div:first-child{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:12px;line-height:16px;color:#334155;margin-bottom:4px}
.preview-with-erp .spending-category bdi{font-size:12px;font-weight:900;color:#0f172a}
.preview-with-erp .spending-track{height:8px;background:#f1f5f9;border-radius:9999px;overflow:hidden}
.preview-with-erp .spending-track>span{display:block;height:100%;background:#f43f5e;border-radius:9999px}
.preview-with-erp .spending-category small{font-size:10px;line-height:15px;color:#94a3b8;font-weight:500}
.preview-with-erp .report-preview-link{display:block;text-align:center;font-size:12px;line-height:16px;font-weight:700;margin-top:auto;padding:10px 12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;color:#334155}
.preview-with-erp .spending-report-footer{margin-top:20px;padding-top:16px;border-top:1px solid #f1f5f9}
.preview-with-erp .report-preview-link i{color:#4f46e5;margin-inline-end:4px}
@media(min-width:640px){
 .preview-with-erp .finens-dashboard{padding:24px}
 .preview-with-erp .dashboard-welcome{padding:28px}
 .preview-with-erp .dashboard-welcome h1{font-size:24px;line-height:32px}
 .preview-with-erp .dashboard-welcome .finens-actions .button{flex:0 1 auto;font-size:14px;line-height:20px}
 .preview-with-erp .monthly-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}
 .preview-with-erp .treasury-card h2{font-size:36px;line-height:40px}
 .preview-with-erp .dashboard-panel-heading{padding:20px}
}
@media(min-width:768px){.preview-with-erp .dashboard-welcome{flex-direction:row;align-items:center;justify-content:space-between}}
@media(min-width:1024px){
 .preview-with-erp .finens-dashboard{padding:32px}
 .preview-with-erp .dashboard-metrics{grid-template-columns:repeat(3,minmax(0,1fr))}
 .preview-with-erp .monthly-metrics{grid-column:span 2}
 .preview-with-erp .dashboard-detail-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
 .preview-with-erp .recent-transactions{grid-column:span 2}
}
@media(max-width:1200px){
 .preview-with-erp .finens-navbar{padding-inline:16px}.preview-with-erp .finens-toolbar-actions{gap:6px}.preview-with-erp .compact-actions .button{font-size:10px;padding-inline:8px}
 .preview-with-erp .treasury-pill>span{display:none}
}
@media(max-width:1000px){.preview-with-erp .compact-actions{display:none}}
@media(max-width:767px){
 .preview-with-erp .finens-navbar{padding:12px 16px;flex-wrap:wrap;gap:8px}.preview-with-erp .finens-navbar .language-link{display:flex}.preview-with-erp .treasury-pill{font-size:10px;padding:6px 8px}.preview-with-erp .treasury-pill strong{font-size:11px}
 .preview-with-erp .finens-navbar .company-context{flex:1}.preview-with-erp .finens-navbar .company-context small{font-size:9px}
 .preview-with-erp .office-picker select{min-height:44px}
}
'''
