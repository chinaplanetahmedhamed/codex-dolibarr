"""Remaining Finens-style pages. Embedded synthetic data, no requests or persistence."""
from decimal import Decimal
import html
import json

from preview_dashboard_design import SAMPLES, figures, icon, money, office_picker, word


def esc(value):
    return html.escape(str(value), quote=True)


def text(lang, ar, en):
    return word(lang, ar, en)


def button(label, symbol='plus-circle', dialog=None, tone='indigo', extra=''):
    return '<button type="button" class="business-button ' + tone + '"' + (f' data-open="{dialog}"' if dialog else '') + ' ' + extra + '>' + icon(symbol) + esc(label) + '</button>'


def link(lang, page, label, symbol='arrow-left', tone='muted'):
    filename = lang + ('' if page == 'expenses' else '-' + page) + '.html'
    return f'<a class="business-button {tone}" href="{filename}">' + icon(symbol) + esc(label) + '</a>'


def frame(lang, view, title, description, actions='', before=''):
    return ('<main id="office-workspace" class="finens-dashboard treasury-preview business-preview" data-business-page="' + view + '">'
            + '<div class="business-heading"><div>' + before + '<h1>' + title + '</h1><p>' + description + '</p></div><div class="business-actions">' + actions + '</div></div>'
            + '<div class="treasury-preview-note"><p>' + icon('circle-info') + text(lang, 'معاينة تصميم · بيانات تجريبية · الحفظ والترحيل والصلاحيات الفعلية معطّلة', 'Design preview · Sample data · Saving, posting and actual permission changes disabled')
            + '</p>' + office_picker(lang) + '</div>')


def field(name, label, kind='text', value='', options=None, disabled=False, placeholder=''):
    attrs = f' id="{name}" name="{name}"' + (' disabled' if disabled else '')
    if kind == 'number':
        attrs += ' step="0.01" min="0" max="999999999.99"'
    if options is not None:
        control = '<select' + attrs + '>' + ''.join(f'<option value="{esc(v)}"' + (' selected' if str(v) == str(value) else '') + '>' + esc(label_) + '</option>' for v, label_ in options) + '</select>'
    elif kind == 'textarea':
        control = '<textarea rows="2"' + attrs + ' placeholder="' + esc(placeholder) + '">' + esc(value) + '</textarea>'
    else:
        control = '<input type="' + kind + '"' + attrs + ' value="' + esc(value) + '" placeholder="' + esc(placeholder) + '">'
    return '<div class="business-field"><label for="' + name + '">' + label + '</label>' + control + '</div>'


def checkbox(name, label, checked=False, disabled=False):
    return '<label class="business-check"><input type="checkbox" name="' + name + '"' + (' checked' if checked else '') + (' disabled' if disabled else '') + '>' + label + '</label>'


def dialog(lang, id_, title, fields, save, tone='indigo'):
    return ('<dialog class="treasury-deposit-dialog business-dialog" id="' + id_ + '" aria-labelledby="' + id_ + '-title"><div class="treasury-dialog-heading"><h2 id="'
            + id_ + '-title">' + title + '</h2><button type="button" data-close aria-label="' + text(lang, 'إغلاق', 'Close') + '">' + icon('xmark') + '</button></div><form data-business-form>'
            + fields + '<p class="business-form-note">' + text(lang, 'معاينة فقط. لا يتم حفظ بيانات أو تنفيذ أي إجراء فعلي.', 'Preview only. No data is saved and no actual action is performed.')
            + '</p><div class="business-form-actions"><button class="business-button ' + tone + '" type="button" disabled>' + save + text(lang, ' — معطّل', ' — disabled')
            + '</button><button class="business-button muted" type="button" data-close>' + text(lang, 'إلغاء', 'Cancel') + '</button></div></form></dialog>')


def prefill(values):
    return 'data-prefill="' + esc(json.dumps(values, ensure_ascii=False)) + '"'


def badge(label, tone='green'):
    return '<span class="business-badge ' + tone + '">' + label + '</span>'


CATEGORIES = (
    ('rent', 'إيجار المقر', 'Office rent', 'building'),
    ('utilities', 'فواتير ومرافق (كهرباء وإنترنت)', 'Utilities (electricity & internet)', 'bolt'),
    ('shipping', 'شحن ونقل وبضاعة', 'Shipping, transport & goods', 'truck-fast'),
    ('hospitality', 'ضيافة وبوفيه ونثريات', 'Hospitality & sundries', 'mug-hot'),
    ('supplies', 'أدوات مكتبية وصيانة', 'Office supplies & maintenance', 'wrench'),
    ('marketing', 'تسويق وإعلانات', 'Marketing & advertising', 'bullhorn'),
)


def categories(lang):
    t = lambda ar, en: text(lang, ar, en)
    back = '<a class="business-back" href="' + lang + '.html">' + t('← العودة للمصروفات', '← Back to expenses') + '</a>'
    out = [frame(lang, 'categories', t('بنود وتصنيفات المصروفات', 'Expense categories & classifications'),
                 t('تحديد بنود الصرف الخاصة بشركتك (إيجار، فواتير، بضاعة، نثريات، بوفيه...)', 'Define your company’s expense categories: rent, utilities, goods, sundries and hospitality'),
                 button(t('إضافة بند جديد', 'Add new category'), dialog='category-add'), back)]
    for office, sample in SAMPLES.items():
        out.append(f'<div class="category-grid" data-office="{office}"' + (' hidden' if office != 'egypt' else '') + '>')
        for key, ar, en, symbol in CATEGORIES:
            amount = Decimal('0')
            if office == 'egypt' and key in ('rent', 'supplies'):
                amount = Decimal('1900' if key == 'rent' else '750')
            if office == 'china' and key in ('utilities', 'shipping'):
                amount = Decimal('420' if key == 'utilities' else '180.29')
            label = ar if lang == 'ar' else en
            out.append('<article class="category-card"><div><div class="category-card-heading"><div><span class="category-icon">' + icon(symbol) + '</span><div><h2>'
                       + label + '</h2><small>' + str(int(amount > 0)) + t(' عملية صرف تجريبية', ' sample expense') + '</small></div></div>' + badge(t('نشط', 'Active'))
                       + '</div><div class="category-total"><span>' + t('إجمالي المصروف:', 'Total spent:') + '</span><strong>' + money(amount, sample['currency'])
                       + '</strong></div></div><footer>' + button(t('تعديل', 'Edit'), 'pen', 'category-edit', 'muted', prefill({'category-edit-name': label, 'category-active': True})) + '</footer></article>')
        out.append('</div>')
    icon_options = [(s, t(ar, en)) for _, ar, en, s in CATEGORIES]
    out.append(dialog(lang, 'category-add', t('إضافة بند مصروف جديد', 'Add expense category'),
                      field('category-add-name', t('اسم البند *', 'Category name *'), placeholder=t('مثال: صيانة سيارات، دعاية وإعلان...', 'Example: vehicle maintenance, advertising…'))
                      + field('category-icon', t('أيقونة البند', 'Category icon'), value='building', options=[('folder', t('مجلد عام', 'General folder'))] + icon_options), t('إضافة البند', 'Add category')))
    out.append(dialog(lang, 'category-edit', t('تعديل بند المصروف', 'Edit expense category'),
                      field('category-edit-name', t('اسم البند', 'Category name')) + checkbox('category-active', t('البند نشط ومتاح في قائمة المصروفات', 'Category is active and available for expenses'), True), t('حفظ التعديلات', 'Save changes')))
    return ''.join(out) + '</main>'


def reports(lang):
    t = lambda ar, en: text(lang, ar, en)
    out = [frame(lang, 'reports', t('التقارير المالية وتحليل النفقات', 'Financial reports & expense analysis'),
                 t('تقرير شامل للإيداعات والمصروفات ومقارنة الشهور ورسوم بيانية تفاعلية', 'Deposits, expenses, monthly comparisons and interactive charts'),
                 button(t('طباعة التقرير المالي', 'Print financial report'), 'print', tone='dark', extra='data-business-print'))]
    for office, sample in SAMPLES.items():
        dep, exp, flow, _ = figures(sample)
        currency = sample['currency']
        out.append(f'<div class="office-dashboard report-office" data-office="{office}" data-report-rows="' + esc(json.dumps(sample['rows'], ensure_ascii=False)) + '"' + (' hidden' if office != 'egypt' else '') + '>')
        out.append('<section class="treasury-filters"><form data-report-filter>'
                   + field(office + '-month', t('تحديد الشهر', 'Select month'), 'month', '2026-11')
                   + field(office + '-from', t('من تاريخ', 'From date'), 'date', '2026-11-01')
                   + field(office + '-to', t('إلى تاريخ', 'To date'), 'date', '2026-11-30')
                   + '<div class="treasury-filter-actions"><button type="submit">' + icon('chart-line') + t('عرض التقرير', 'View report')
                   + '</button><button type="reset" aria-label="' + t('الشهر الحالي', 'Current month') + '">' + icon('rotate-right')
                   + '</button></div></form><p class="treasury-filter-error" role="alert" hidden>' + t('تاريخ البداية يجب أن يسبق تاريخ النهاية أو يساويه.', 'The start date must be on or before the end date.') + '</p></section>')
        out.append('<section class="report-banner"><header><div><span>' + t('تقرير الأداء المالي', 'Financial performance report') + '</span><h2>China Planet</h2></div><p data-report-period>'
                   + t('الفترة من:', 'Period from:') + ' <bdi>2026-11-01</bdi> ' + t('إلى:', 'to:') + ' <bdi>2026-11-30</bdi></p></header><div class="report-metrics">')
        for key, tone, label, amount, caption, symbol in (
            ('deposits', 'green', t('إجمالي الإيداعات', 'Total deposits'), dep, t('داخل الخزنة', 'Into treasury'), 'arrow-down-long'),
            ('expenses', 'rose', t('إجمالي المصروفات', 'Total expenses'), exp, t('نفقات تشغيلية وبنود', 'Operating expenses & categories'), 'arrow-up-long'),
            ('salaries', 'purple', t('رواتب وأجور الموظفين', 'Employee salaries & wages'), Decimal('0'), t('مسيرات الرواتب', 'Payroll'), 'users'),
            ('net', 'amber', t('صافي التدفق النقدي', 'Net cash flow'), flow, t('فائض سيولة', 'Cash surplus'), 'scale-balanced'),
        ):
            out.append(f'<div class="report-metric {tone}"><div><span>{label}</span><strong data-report-value="{key}">' + money(amount, currency) + '</strong><small>' + caption + '</small></div><i class="fa-solid fa-' + symbol + '" aria-hidden="true"></i></div>')
        out.append('</div></section><div class="report-charts"><section class="report-chart-panel"><h2>' + t('مقارنة الشهور (آخر 6 أشهر)', 'Monthly comparison (last 6 months)')
                   + '</h2><p>' + t('مقارنة حركة الإيداعات مقابل المصروفات شهرياً', 'Monthly deposits versus expenses') + '</p><div class="chart-legend"><span class="green-dot">'
                   + t('الإيداعات', 'Deposits') + '</span><span class="rose-dot">' + t('المصروفات', 'Expenses') + '</span></div><div class="monthly-bars" role="img" aria-label="'
                   + t('بيانات تجريبية لستة أشهر؛ الحركات في نوفمبر فقط', 'Six sample months; movements occur in November only') + '">')
        for month in range(6, 12):
            out.append('<div><div class="bar-pair"><span style="height:' + ('85' if month == 11 else '0') + '%" title="' + esc(t('إيداعات', 'Deposits')) + ': ' + (str(dep) if month == 11 else '0')
                       + ' ' + currency + '"></span><span style="height:' + (str((exp / dep * 85).quantize(Decimal('.1'))) if month == 11 else '0') + '%" title="'
                       + esc(t('مصروفات', 'Expenses')) + ': ' + (str(exp) if month == 11 else '0') + ' ' + currency + '"></span></div><small><bdi>2026-' + str(month).zfill(2) + '</bdi></small></div>')
        out.append('</div></section><section class="report-chart-panel"><h2>' + t('توزيع المصروفات حسب البند', 'Expense distribution by category') + '</h2><p>'
                   + t('نسبة إنفاق كل بند خلال الفترة المحددة', 'Category share during the selected period') + '</p><div class="report-donut" role="img" aria-label="'
                   + t('توزيع المصروفات التجريبية', 'Sample expense distribution') + '"></div><div class="report-category-legend" data-report-legend></div></section></div>')
        out.append('<section class="treasury-ledger"><div class="treasury-ledger-heading"><h2>' + t('جدول المقارنة الشهرية للأشهر الستة الأخيرة', 'Monthly comparison for the last six months')
                   + '</h2><span>' + t('حركات تجريبية', 'Sample movements') + '</span></div><div class="treasury-table-scroll report-desktop"><table><thead><tr>')
        for label in (t('الشهر', 'Month'), t('إجمالي الإيداعات', 'Total deposits'), t('إجمالي المصروفات', 'Total expenses'), t('صافي الشهر', 'Monthly net'), t('التقييم', 'Assessment')):
            out.append('<th scope="col">' + label + '</th>')
        out.append('</tr></thead><tbody>')
        for month in range(6, 12):
            out.append('<tr><td><bdi>2026-' + str(month).zfill(2) + '</bdi></td><td>' + money(dep if month == 11 else 0, currency) + '</td><td>'
                       + money(exp if month == 11 else 0, currency) + '</td><td>' + money(flow if month == 11 else 0, currency) + '</td><td>'
                       + badge(t('فائض', 'Surplus') if month == 11 else t('متوازن', 'Balanced'), 'green' if month == 11 else 'slate') + '</td></tr>')
        out.append('</tbody></table></div><div class="report-month-cards">')
        for month in range(6, 12):
            out.append('<article><header><strong><bdi>2026-' + str(month).zfill(2) + '</bdi></strong>' + badge(t('فائض', 'Surplus') if month == 11 else t('متوازن', 'Balanced'), 'green' if month == 11 else 'slate') + '</header><div>')
            for tone, label, value in (('green', t('إيداعات', 'Deposits'), dep), ('rose', t('مصروفات', 'Expenses'), exp), ('indigo', t('الصافي', 'Net'), flow)):
                out.append('<span class="' + tone + '"><small>' + label + '</small><strong>' + money(value if month == 11 else 0, currency) + '</strong></span>')
            out.append('</div></article>')
        out.append('</div></section></div>')
    return ''.join(out) + '</main>'


def stats(items):
    return '<section class="business-stats">' + ''.join('<div class="business-stat ' + tone + '"><div><p>' + label + '</p><strong>' + value
           + '</strong><small>' + caption + '</small></div><span>' + icon(symbol) + '</span></div>' for tone, label, value, caption, symbol in items) + '</section>'


def table(headers, body, heading=None):
    return ('<section class="treasury-ledger">' + ('<div class="treasury-ledger-heading"><h2>' + heading + '</h2></div>' if heading else '') + '<div class="treasury-table-scroll" tabindex="0" role="region" aria-label="'
            + (' · '.join(headers[:2])) + '"><table><thead><tr>' + ''.join('<th scope="col">' + h + '</th>' for h in headers) + '</tr></thead><tbody>' + body + '</tbody></table></div></section>')


STAFF = {'egypt': {'salary': '6000.00', 'ar': 'موظف تجريبي — مصر', 'en': 'Sample employee — Egypt'},
         'china': {'salary': '4500.00', 'ar': 'موظف تجريبي — الصين', 'en': 'Sample employee — China'}}


def employee_fields(lang, prefix, edit=False):
    t = lambda ar, en: text(lang, ar, en)
    content = field(prefix + '-name', t('اسم الموظف *', 'Employee name *')) + field(prefix + '-job', t('المسمى الوظيفي', 'Job title'))
    content += '<div class="business-two-fields">' + field(prefix + '-phone', t('رقم الهاتف', 'Phone number')) + field(prefix + '-salary', t('الراتب الأساسي', 'Base salary'), 'number', '0.00') + '</div>'
    content += field(prefix + '-status', t('حالة العمل', 'Employment status'), value='active', options=[('active', t('على رأس العمل (نشط)', 'Active employee')), ('inactive', t('متوقف / مستقيل', 'Inactive / resigned'))]) if edit else field(prefix + '-date', t('تاريخ التعيين', 'Hire date'), 'date', '2026-06-01')
    return content + field(prefix + '-notes', t('ملاحظات إضافية', 'Additional notes'), 'textarea')


def employees(lang):
    t = lambda ar, en: text(lang, ar, en)
    out = [frame(lang, 'employees', t('إدارة الموظفين والكوادر', 'Employee & staff management'), t('سجل الموظفين والوظائف والرواتب الأساسية وتاريخ التعيين', 'Employee records, roles, base salaries and hire dates'),
                 button(t('إضافة موظف جديد', 'Add new employee'), 'user-plus', 'employee-add', 'purple') + link(lang, 'payroll', t('مسير الرواتب', 'Payroll'), 'money-check-dollar', 'teal'))]
    for office, sample in SAMPLES.items():
        person = STAFF[office]
        out.append(f'<div class="office-dashboard" data-office="{office}"' + (' hidden' if office != 'egypt' else '') + '>')
        out.append(stats([('purple', t('إجمالي الموظفين', 'Total employees'), t('1 موظف', '1 employee'), '', 'users'),
                          ('teal', t('إجمالي مسير الرواتب الشهرية', 'Total monthly base salaries'), money(person['salary'], sample['currency']), t('بيانات تجريبية', 'Sample data'), 'wallet'),
                          ('green', t('حالة موديول الـ HR', 'HR module status'), t('معاينة التصميم', 'Design preview'), t('لا تغيير في حالة الموديول الفعلية', 'Actual module state unchanged'), 'circle-check')]))
        row = '<tr><td><div class="business-person"><span>' + icon('user-tie') + '</span><strong>' + person[lang] + '</strong></div></td><td>' + t('منسق مكتب', 'Office coordinator')
        row += '</td><td>—</td><td class="business-salary">' + money(person['salary'], sample['currency']) + '</td><td><bdi>2026-06-01</bdi></td><td>' + badge(t('نشط', 'Active')) + '</td><td>'
        row += button(t('تعديل', 'Edit'), 'pen', 'employee-edit', 'muted', prefill({'employee-edit-name': person[lang], 'employee-edit-job': t('منسق مكتب', 'Office coordinator'), 'employee-edit-phone': '', 'employee-edit-salary': person['salary'], 'employee-edit-status': 'active', 'employee-edit-notes': t('بيانات تجريبية', 'Sample data')})) + '</td></tr>'
        out.append(table([t('الموظف', 'Employee'), t('المسمى الوظيفي', 'Job title'), t('الهاتف', 'Phone'), t('الراتب الأساسي', 'Base salary'), t('تاريخ التعيين', 'Hire date'), t('الحالة', 'Status'), t('إجراءات', 'Actions')], row))
        out.append('</div>')
    out.append(dialog(lang, 'employee-add', t('إضافة موظف جديد', 'Add new employee'), employee_fields(lang, 'employee-add'), t('حفظ الموظف', 'Save employee'), 'purple'))
    out.append(dialog(lang, 'employee-edit', t('تعديل بيانات الموظف', 'Edit employee details'), employee_fields(lang, 'employee-edit', True), t('تحديث البيانات', 'Update details'), 'purple'))
    return ''.join(out) + '</main>'


def payroll(lang):
    t = lambda ar, en: text(lang, ar, en)
    out = [frame(lang, 'payroll', t('مسيرات وصرف الرواتب', 'Payroll & salary payments'),
                 t('احتساب مرتبات الموظفين، الحوافز والخصومات والخصم المباشر من الخزنة', 'Calculate employee salaries, bonuses, deductions and treasury withdrawals'),
                 button(t('صرف راتب موظف', 'Pay employee salary'), 'money-check-dollar', 'salary-preview', 'teal') + link(lang, 'employees', t('الموظفين', 'Employees'), 'users'))]
    for office, sample in SAMPLES.items():
        out.append(f'<div class="office-dashboard payroll-office" data-office="{office}"' + (' hidden' if office != 'egypt' else '') + '>')
        out.append('<div class="business-stats"><section class="business-month-card">' + field(office + '-payroll-month', t('الشهر الحالي المحدد', 'Selected month'), 'month', '2026-11') + '</section>')
        out.append(stats([('teal', t('إجمالي الرواتب المصروفة', 'Total salaries paid'), money(0, sample['currency']), t('لا مدفوعات تجريبية مسجلة', 'No sample payments recorded'), 'money-check-dollar'),
                          ('green', t('رصيد الخزنة المتاح', 'Available treasury balance'), money(figures(sample)[3], sample['currency']), t('رصيد توضيحي — الصرف معطّل', 'Illustrative balance — payment disabled'), 'vault')]).replace('<section class="business-stats">', '').replace('</section>', ''))
        out.append('</div>')
        out.append(table([t('الموظف', 'Employee'), t('الأساسي', 'Base'), t('المكافأة (+)', 'Bonus (+)'), t('الخصومات (-)', 'Deductions (−)'), t('صافي الراتب المستلم', 'Net salary'), t('تاريخ الصرف', 'Payment date'), t('حالة الخزنة', 'Treasury status')],
                         '<tr class="ledger-empty"><td colspan="7">' + icon('money-check-dollar') + t('لا توجد رواتب مصروفة في هذه المعاينة.', 'No salary payments in this preview.') + '</td></tr>',
                         t('سجل الرواتب المصروفة لشهر ', 'Salary payment register for ') + '<bdi data-payroll-month-label>2026-11</bdi>'))
        out.append('</div>')
    options = [('', t('-- اختر الموظف --', '-- Select employee --'))] + [(office, p[lang]) for office, p in STAFF.items()]
    fields = field('salary-person', t('اختر الموظف *', 'Select employee *'), options=options) + field('salary-month', t('عن شهر', 'For month'), 'month', '2026-11')
    fields += '<div class="business-three-fields">' + field('salary-base', t('الراتب الأساسي', 'Base salary'), 'number', '0.00', disabled=True)
    fields += field('salary-bonus', t('مكافآت (+)', 'Bonus (+)'), 'number', '0.00') + field('salary-deductions', t('خصومات (-)', 'Deductions (−)'), 'number', '0.00') + '</div>'
    fields += '<div class="salary-net"><span>' + t('صافي الراتب المستلم (توضيحي)', 'Illustrative net salary') + '</span><strong data-salary-net>0.00 EGP</strong></div>'
    fields += checkbox('salary-deposit', t('خصم الراتب تلقائياً من الخزنة الرئيسية — معطّل', 'Deduct salary from main treasury — disabled'), disabled=True) + field('salary-notes', t('ملاحظات', 'Notes'), 'textarea')
    out.append(dialog(lang, 'salary-preview', t('صرف راتب موظف', 'Pay employee salary'), fields, t('تأكيد صرف الراتب', 'Confirm salary payment'), 'teal'))
    return ''.join(out) + '</main>'


def sample_logo():
    return '<div class="settings-logo" aria-label="China Planet · Sample logo">' + icon('building') + '<strong>China Planet</strong></div>'


def settings(lang):
    t = lambda ar, en: text(lang, ar, en)
    out = [frame(lang, 'settings', t('إعدادات الشركة والمقر', 'Company & office settings'),
                 t('تعديل اسم الشركة، رقم الهاتف، واللوجو الذي يظهر في أعلى إيصالات الاستلام والفواتير المطبوعة والإلكترونية', 'Edit the company name, phone and logo used on printed and electronic receipts and invoices'),
                 link(lang, 'receipts', t('سجل الإيصالات', 'Receipt register'), 'receipt'))]
    out.append('<section class="settings-preview-card"><header><h2>' + icon('eye') + ' ' + t('معاينة مباشرة: رأس الفاتورة والإيصال المطبوع (A4)', 'Live preview: printed invoice & receipt header (A4)')
               + '</h2><span>' + t('هكذا سيظهر في أعلى وصولات الاستلام', 'As shown at the top of receipts') + '</span></header><div class="settings-letterhead"><div>' + sample_logo()
               + '<div><h2 data-settings-name>China Planet</h2><p>' + t('هاتف:', 'Phone:') + ' <bdi data-settings-phone>—</bdi></p></div></div><div class="settings-receipt-badge"><strong>'
               + t('إيصال استلام نقدية', 'Cash receipt voucher') + '</strong><small>CASH RECEIPT VOUCHER</small></div></div></section>')
    out.append('<section class="settings-form-card"><form data-business-form data-settings-form><div class="business-two-fields">'
               + field('settings-name', t('اسم الشركة / المقر الرسمي *', 'Official company / office name *'), value='China Planet')
               + field('settings-phone', t('رقم هاتف المقر والتواصل', 'Office phone & contact number'), placeholder=t('هاتف تجريبي فقط', 'Sample phone only')) + '</div><div class="business-two-fields">'
               + field('settings-currency', t('العملة الافتراضية للنظام', 'Default system currency'), options=[('EGP', 'EGP · ' + t('جنيه مصري', 'Egyptian pound')), ('CNY', 'CNY · ' + t('يوان صيني', 'Chinese yuan'))])
               + field('settings-code', t('كود الشركة بالمنصة', 'Company platform code'), value='CP-DEMO-EG', disabled=True) + '</div><section class="settings-logo-section"><h2>'
               + t('شعار المقر (Logo) بحجم كبير وواضح', 'Large, clear office logo') + '</h2><div class="settings-logo-grid"><div class="settings-current-logo"><span>'
               + t('شعار توضيحي للمعاينة', 'Illustrative preview logo') + '</span>' + sample_logo() + checkbox('settings-remove-logo', t('حذف هذا الشعار — معطّل', 'Remove this logo — disabled'), disabled=True)
               + '</div><div class="settings-logo-upload">' + icon('cloud-arrow-up') + '<p>' + t('رفع شعار جديد أو استبدال الشعار الحالي', 'Upload a new logo or replace the current one') + '</p><small>'
               + t('PNG, JPG, JPEG, WEBP, SVG — رفع الملفات معطّل في المعاينة', 'PNG, JPG, JPEG, WEBP, SVG — uploads disabled in preview')
               + '</small><input type="file" accept="image/*" disabled aria-label="' + t('رفع شعار — معطّل', 'Upload logo — disabled') + '"></div></div></section><p class="business-form-note">'
               + t('التعديلات هنا لتجربة الشكل فقط؛ لا تُحفظ ولا تغيّر بيانات الشركة أو الإيصالات.', 'Changes here preview the appearance only; they are not saved and do not alter company or receipt data.')
               + '</p><div class="business-form-actions settings-save">' + link(lang, 'receipts', t('إلغاء', 'Cancel')) + '<button class="business-button indigo" type="button" disabled>' + icon('floppy-disk')
               + t('حفظ وتحديث بيانات المقر والشعار — معطّل', 'Save office name & logo — disabled') + '</button></div></form></section>')
    return ''.join(out) + '</main>'


def users(lang):
    # Deferred import preserves the shared preview helpers without runtime dependencies.
    from preview_staff_access_design import users as native_staff_users
    return native_staff_users(lang)


RENDERERS = {'categories': categories, 'reports': reports, 'employees': employees, 'payroll': payroll, 'settings': settings, 'users': users}


SCRIPT = r'''
'use strict';
(() => {
  document.querySelectorAll('[data-business-form]').forEach(form=>form.addEventListener('submit',e=>e.preventDefault()));
  document.querySelectorAll('.business-dialog').forEach(dialog=>dialog.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();dialog.close();}}));
  document.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-prefill]');
    if(!trigger)return;
    const dialog=document.getElementById(trigger.dataset.open);
    if(!dialog)return;
    for(const [name,value] of Object.entries(JSON.parse(trigger.dataset.prefill))){
      const field=dialog.querySelector('[name="'+CSS.escape(name)+'"]');
      if(field){if(field.type==='checkbox')field.checked=value===true;else field.value=value;}
    }
  });
  document.querySelector('[data-business-print]')?.addEventListener('click',()=>window.print());
  const ar=document.documentElement.lang.startsWith('ar');
  const cash=(cents,currency)=>(cents/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})+' '+currency;
  document.querySelectorAll('[data-report-filter]').forEach(form=>{
    const office=form.closest('.report-office'),code=office.dataset.office,currency=code==='china'?'CNY':'EGP';
    const month=form.elements[code+'-month'],from=form.elements[code+'-from'],to=form.elements[code+'-to'];
    const update=()=>{
      const invalid=from.value&&to.value&&from.value>to.value;
      office.querySelector('.treasury-filter-error').hidden=!invalid;
      to.setAttribute('aria-invalid',invalid?'true':'false');
      if(invalid)return;
      const rows=JSON.parse(office.dataset.reportRows).filter(r=>(!from.value||r[0]>=from.value)&&(!to.value||r[0]<=to.value));
      const sum=kind=>rows.filter(r=>r[1]===kind).reduce((s,r)=>s+Math.round(Number(r[2])*100),0);
      const dep=sum('deposit'),exp=sum('expense');
      for(const [key,value]of Object.entries({deposits:dep,expenses:exp,salaries:0,net:dep-exp})){
        const amount=document.createElement('bdi');amount.textContent=cash(value,currency);office.querySelector('[data-report-value="'+key+'"]').replaceChildren(amount);
      }
      const caption=office.querySelector('[data-report-value="net"]').nextElementSibling;
      caption.textContent=dep===exp?(ar?'متوازن':'Balanced'):dep>exp?(ar?'فائض سيولة':'Cash surplus'):(ar?'عجز سيولة':'Cash deficit');
      office.querySelector('[data-report-period]').textContent=(ar?'الفترة من: ':'Period from: ')+from.value+(ar?' إلى: ':' to: ')+to.value;
      const expenses=rows.filter(r=>r[1]==='expense');
      const colors=['#6366f1','#f43f5e','#10b981','#f59e0b'];let offset=0;
      const stops=expenses.map((r,i)=>{const start=offset;offset+=Math.round(Number(r[2])*100)/Math.max(exp,1)*100;return colors[i%colors.length]+' '+start+'% '+offset+'%';});
      const donut=office.querySelector('.report-donut');donut.style.background=stops.length?'conic-gradient('+stops.join(',')+')':'#f1f5f9';
      const legend=office.querySelector('[data-report-legend]');legend.replaceChildren();
      for(const [i,row]of expenses.entries()){
        const label=document.createElement('span'),dot=document.createElement('i');dot.style.background=colors[i%colors.length];label.append(dot,document.createTextNode((ar?row[3]:row[4])+' · '+cash(Math.round(Number(row[2])*100),currency)));legend.append(label);
      }
      if(!expenses.length)legend.textContent=ar?'لا توجد مصروفات تجريبية في هذه الفترة':'No sample expenses in this period';
    };
    month.addEventListener('change',()=>{if(!/^\d{4}-\d{2}$/.test(month.value))return;from.value=month.value+'-01';const [y,m]=month.value.split('-').map(Number);to.value=month.value+'-'+new Date(y,m,0).getDate();});
    form.addEventListener('submit',e=>{e.preventDefault();update();});form.addEventListener('reset',()=>setTimeout(update,0));update();
  });
  document.querySelectorAll('.payroll-office input[type="month"]').forEach(input=>input.addEventListener('change',()=>{
    input.closest('.payroll-office').querySelector('[data-payroll-month-label]').textContent=input.value;
  }));
  const salaryPerson=document.querySelector('#salary-person');
  const salaryNet=()=>{
    if(!salaryPerson)return;
    const office=document.querySelector('[data-office-picker]').value;
    const currency=office==='china'?'CNY':'EGP';
    const base=salaryPerson.value?(office==='china'?450000:600000):0;
    document.querySelector('#salary-base').value=(base/100).toFixed(2);
    const bonus=document.querySelector('#salary-bonus'),deductions=document.querySelector('#salary-deductions');
    const valid=[bonus,deductions].every(e=>e.validity.valid&&/^\d{1,9}(\.\d{1,2})?$/.test(e.value));
    const net=base+Math.round(Number(bonus.value)*100)-Math.round(Number(deductions.value)*100);
    document.querySelector('[data-salary-net]').textContent=valid&&net>=0?cash(net,currency):(ar?'راجع المبالغ المدخلة':'Check the entered amounts');
  };
  const salaryOffice=()=>{
    if(!salaryPerson)return;
    const office=document.querySelector('[data-office-picker]').value;
    for(const option of salaryPerson.options)option.disabled=!!option.value&&option.value!==office;
    salaryPerson.value='';salaryNet();
  };
  document.querySelector('[data-office-picker]')?.addEventListener('change',salaryOffice);
  document.querySelector('#salary-preview form')?.addEventListener('input',salaryNet);
  salaryPerson?.addEventListener('change',salaryNet);salaryOffice();
  const settingsForm=document.querySelector('[data-settings-form]');
  if(settingsForm){
    const name=settingsForm.elements['settings-name'],phone=settingsForm.elements['settings-phone'];
    const update=()=>{document.querySelector('[data-settings-name]').textContent=name.value||'China Planet';document.querySelector('[data-settings-phone]').textContent=phone.value||'—';};
    name.addEventListener('input',update);phone.addEventListener('input',update);
    const officeUpdate=()=>{const china=document.querySelector('[data-office-picker]').value==='china';settingsForm.elements['settings-currency'].value=china?'CNY':'EGP';settingsForm.elements['settings-code'].value=china?'CP-DEMO-CN':'CP-DEMO-EG';};
    document.querySelector('[data-office-picker]').addEventListener('change',officeUpdate);officeUpdate();
  }
  document.querySelector('[data-business-page]')?.setAttribute('data-preview-ready','true');
})();
'''


CSS = '''
.preview-with-erp .business-heading{display:flex;flex-direction:column;gap:16px}
.preview-with-erp .business-heading h1{font-size:24px;line-height:32px;font-weight:900;color:#0f172a}
.preview-with-erp .business-heading p{font-size:12px;line-height:16px;color:#64748b;margin-top:4px}
.preview-with-erp .business-actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px;flex-shrink:0}
.preview-with-erp .business-button{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 16px;border:0;border-radius:12px;font-size:12px;line-height:16px;font-weight:700;cursor:pointer;text-align:center;white-space:normal}
.preview-with-erp .business-button.indigo{background:#4f46e5;color:white}.preview-with-erp .business-button.purple{background:#9333ea;color:white}.preview-with-erp .business-button.teal{background:#0d9488;color:white}.preview-with-erp .business-button.dark{background:#1e293b;color:white}.preview-with-erp .business-button.muted{background:#f8fafc;color:#334155;border:1px solid #e2e8f0}
.preview-with-erp .business-button:hover:not(:disabled){filter:brightness(.96)}
.preview-with-erp .business-button:disabled{cursor:not-allowed;opacity:.55}
.preview-with-erp .business-back{display:block;font-size:12px;line-height:16px;font-weight:700;color:#4f46e5;margin-bottom:4px}
.preview-with-erp .category-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:16px}
.preview-with-erp .category-card{min-width:0;background:white;border:1px solid #e2e8f0cc;border-radius:16px;padding:20px;display:flex;flex-direction:column;justify-content:space-between}
.preview-with-erp .category-card:hover{border-color:#cbd5e1}
.preview-with-erp .category-card-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
.preview-with-erp .category-card-heading>div{display:flex;align-items:center;gap:12px;min-width:0}
.preview-with-erp .category-icon{width:40px;height:40px;flex:0 0 40px;border-radius:12px;background:#f1f5f9;color:#334155;display:grid;place-items:center;font-size:16px}
.preview-with-erp .category-card h2{font-size:16px;line-height:24px;font-weight:900;color:#0f172a}
.preview-with-erp .category-card small{font-size:11px;line-height:16.5px;color:#94a3b8}
.preview-with-erp .business-badge{display:inline-block;font-size:10px;line-height:15px;font-weight:700;border-radius:9999px;padding:2px 8px;white-space:nowrap}
.preview-with-erp .business-badge.green{background:#d1fae5;color:#065f46}.preview-with-erp .business-badge.slate{background:#f1f5f9;color:#475569}.preview-with-erp .business-badge.purple{background:#f3e8ff;color:#6b21a8}.preview-with-erp .business-badge.indigo{background:#e0e7ff;color:#3730a3}.preview-with-erp .business-badge.rose{background:#ffe4e6;color:#9f1239}
.preview-with-erp .category-total{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid #f1f5f9;font-size:12px;line-height:16px;color:#64748b;font-weight:700}
.preview-with-erp .category-total strong{font-size:14px;line-height:20px;color:#e11d48;font-weight:900}
.preview-with-erp .category-card footer{display:flex;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:1px solid #f1f5f9}
.preview-with-erp .category-card footer .business-button{padding:6px 12px}
.preview-with-erp .business-dialog{border-radius:24px}
.preview-with-erp .business-dialog h2{font-size:18px;line-height:28px;font-weight:900;margin:0}
.preview-with-erp .business-field{min-width:0}
.preview-with-erp .business-dialog .business-field>label{margin-top:16px}
.preview-with-erp .business-dialog .business-field:first-child>label{margin-top:0}
.preview-with-erp .business-field>label{display:block;font-size:12px;line-height:16px;color:#334155;font-weight:700;margin-bottom:4px}
.preview-with-erp .business-field :is(input,select,textarea){width:100%;min-width:0;background:#f8fafc;border:1px solid #cbd5e1;border-radius:12px;padding:8px 12px;font:12px/16px ui-sans-serif,system-ui,sans-serif;color:#1e293b}
.preview-with-erp .business-field :is(input,select,textarea):focus{outline:2px solid #6366f1;outline-offset:2px;background:white}
.preview-with-erp .business-field :disabled{cursor:not-allowed;opacity:.6}
.preview-with-erp .business-check{display:flex!important;align-items:center;gap:8px;font-size:12px;line-height:16px;color:#334155;font-weight:700;margin:16px 0!important}
.preview-with-erp .business-check input{width:16px!important;height:16px!important;min-height:0!important;margin:0;flex:0 0 16px}
.preview-with-erp .business-form-note{font-size:11px;line-height:16.5px;color:#64748b;margin-top:16px!important}
.preview-with-erp .business-form-actions{display:flex;align-items:center;gap:8px;margin-top:16px;padding-top:8px}
.preview-with-erp .business-form-actions>button:first-child{flex:1}
.preview-with-erp .business-two-fields{display:grid;grid-template-columns:minmax(0,1fr);gap:16px 12px}
@media(min-width:640px){.preview-with-erp .business-heading{flex-direction:row;align-items:center;justify-content:space-between}.preview-with-erp .business-heading>.business-actions>.business-button{font-size:14px;line-height:20px}.preview-with-erp .category-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.preview-with-erp .business-two-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(min-width:1024px){.preview-with-erp .category-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
'''

CSS += '''
.preview-with-erp .report-banner,.preview-with-erp .report-chart-panel{background:white;border:1px solid #e2e8f0cc;border-radius:24px;padding:20px}
.preview-with-erp .report-banner>header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding-bottom:16px;border-bottom:1px solid #f1f5f9}
.preview-with-erp .report-banner>header span{font-size:12px;font-weight:700;color:#4f46e5}
.preview-with-erp .report-banner h2{font-size:20px;line-height:28px;font-weight:900;color:#1e293b}
.preview-with-erp .report-banner>header p{font-size:12px;line-height:16px;color:#64748b;font-weight:500}
.preview-with-erp .report-metrics{display:grid;grid-template-columns:minmax(0,1fr);gap:12px;margin-top:20px}
.preview-with-erp .report-metric{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:16px;border:1px solid;border-radius:16px;min-width:0}
.preview-with-erp .report-metric span{font-size:12px;font-weight:700;line-height:16px;display:block}
.preview-with-erp .report-metric strong{font-size:20px;line-height:28px;font-weight:900;display:block;margin-top:4px;overflow-wrap:anywhere}
.preview-with-erp .report-metric small{font-size:11px;line-height:16.5px;display:block;margin-top:2px}
.preview-with-erp .report-metric>i{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:18px;flex:0 0 44px}
.preview-with-erp .report-metric.green{background:#ecfdf5b3;border-color:#a7f3d0cc;color:#065f46}.preview-with-erp .report-metric.green>i{background:#d1fae5;color:#059669}
.preview-with-erp .report-metric.rose{background:#fff1f2b3;border-color:#fecdd3cc;color:#9f1239}.preview-with-erp .report-metric.rose>i{background:#ffe4e6;color:#e11d48}
.preview-with-erp .report-metric.purple{background:#faf5ffb3;border-color:#e9d5ffcc;color:#6b21a8}.preview-with-erp .report-metric.purple>i{background:#f3e8ff;color:#9333ea}
.preview-with-erp .report-metric.amber{background:#fffbebb3;border-color:#fde68acc;color:#92400e}.preview-with-erp .report-metric.amber>i{background:#fef3c7;color:#d97706}
.preview-with-erp .report-charts{display:grid;grid-template-columns:minmax(0,1fr);gap:24px}
.preview-with-erp .report-chart-panel{min-width:0}
.preview-with-erp .report-chart-panel h2{font-size:16px;line-height:24px;font-weight:900;color:#1e293b}
.preview-with-erp .report-chart-panel>p{font-size:12px;line-height:16px;color:#94a3b8}
.preview-with-erp .chart-legend{display:flex;align-items:center;justify-content:center;gap:16px;font-size:11px;color:#64748b;margin:16px 0 0}
.preview-with-erp .chart-legend span:before{content:'';display:inline-block;width:10px;height:10px;margin-inline-end:4px;border-radius:2px;background:#10b981}.preview-with-erp .chart-legend .rose-dot:before{background:#f43f5e}
.preview-with-erp .monthly-bars{height:240px;display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;direction:ltr;padding-top:8px}
.preview-with-erp .monthly-bars>div{display:flex;flex-direction:column;min-width:0}.preview-with-erp .bar-pair{height:210px;display:flex;align-items:flex-end;justify-content:center;gap:4px;border-bottom:1px solid #e2e8f0;background:repeating-linear-gradient(to top,transparent 0,transparent calc(25% - 1px),#f1f5f9 calc(25% - 1px),#f1f5f9 25%)}
.preview-with-erp .bar-pair span{width:18px;max-width:40%;border-radius:4px 4px 0 0;background:#10b981}.preview-with-erp .bar-pair span+span{background:#f43f5e}
.preview-with-erp .monthly-bars small{font-size:10px;text-align:center;color:#64748b;margin-top:8px}
.preview-with-erp .report-donut{width:180px;height:180px;position:relative;margin:24px auto;border-radius:50%;background:#f1f5f9}.preview-with-erp .report-donut:after{content:'';position:absolute;inset:38px;border-radius:50%;background:white}
.preview-with-erp .report-category-legend{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:8px 16px;font-size:11px;color:#64748b}.preview-with-erp .report-category-legend i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-inline-end:4px}
.preview-with-erp .report-desktop{display:none}.preview-with-erp .report-month-cards article{padding:16px;border-bottom:1px solid #f1f5f9}.preview-with-erp .report-month-cards header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;font-size:14px}
.preview-with-erp .report-month-cards article>div{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;text-align:center}.preview-with-erp .report-month-cards article>div>span{padding:8px;border:1px solid;border-radius:12px;min-width:0}
.preview-with-erp .report-month-cards small{display:block;font-size:10px;font-weight:700}.preview-with-erp .report-month-cards strong{display:block;font-size:12px;font-weight:900;overflow-wrap:anywhere}
.preview-with-erp .report-month-cards .green{background:#ecfdf599;border-color:#d1fae5;color:#065f46}.preview-with-erp .report-month-cards .rose{background:#fff1f299;border-color:#ffe4e6;color:#9f1239}.preview-with-erp .report-month-cards .indigo{background:#eef2ff99;border-color:#e0e7ff;color:#3730a3}
@media(min-width:640px){.preview-with-erp .report-banner,.preview-with-erp .report-chart-panel{padding:24px}.preview-with-erp .report-metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.preview-with-erp .report-metric strong{font-size:24px;line-height:32px}}
@media(min-width:768px){.preview-with-erp .report-desktop{display:block}.preview-with-erp .report-month-cards{display:none}}
@media(min-width:1024px){.preview-with-erp .report-metrics{grid-template-columns:repeat(4,minmax(0,1fr))}.preview-with-erp .report-charts{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media print{.preview-with-erp .business-preview .business-actions,.preview-with-erp .report-charts{display:none!important}.preview-with-erp .report-desktop{display:block!important}.preview-with-erp .report-month-cards{display:none!important}.preview-with-erp .report-metrics{grid-template-columns:repeat(4,minmax(0,1fr))}}
'''

CSS += '''
.preview-with-erp .business-stats{display:grid;grid-template-columns:minmax(0,1fr);gap:16px}
.preview-with-erp .business-stat{display:flex;align-items:center;justify-content:space-between;gap:8px;background:white;border:1px solid #e2e8f0cc;border-radius:16px;padding:20px;min-width:0}
.preview-with-erp .business-stat p{font-size:12px;line-height:16px;font-weight:700;color:#64748b}
.preview-with-erp .business-stat strong{display:block;font-size:24px;line-height:32px;font-weight:900;color:#1e293b;margin-top:4px;overflow-wrap:anywhere}
.preview-with-erp .business-stat small{display:block;font-size:11px;line-height:16.5px;color:#94a3b8;margin-top:2px}
.preview-with-erp .business-stat>span{display:grid;place-items:center;width:48px;height:48px;border-radius:12px;font-size:20px;flex:0 0 48px;background:#faf5ff;color:#9333ea}
.preview-with-erp .business-stat.teal>span{background:#f0fdfa;color:#0d9488}.preview-with-erp .business-stat.teal strong{color:#0d9488}
.preview-with-erp .business-stat.green>span{background:#ecfdf5;color:#059669}.preview-with-erp .business-stat.green strong{color:#059669;font-size:18px;line-height:28px}
.preview-with-erp .business-person{display:flex;align-items:center;gap:12px}.preview-with-erp .business-person>span{display:grid;place-items:center;width:36px;height:36px;border-radius:50%;background:#f3e8ff;color:#9333ea;flex:0 0 36px}
.preview-with-erp .business-person strong{font-weight:700;color:#1e293b}
.preview-with-erp .treasury-table-scroll .business-salary{font-weight:900;color:#0d9488}
.preview-with-erp .business-preview td .business-button{padding:4px 10px;border-radius:8px}
.preview-with-erp .business-month-card{background:white;border:1px solid #e2e8f0cc;border-radius:16px;padding:20px}
.preview-with-erp .business-month-card .business-field>label{color:#64748b;margin-bottom:8px}
.preview-with-erp .business-month-card input{font-size:14px;font-weight:700;line-height:20px}
.preview-with-erp .business-three-fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:16px}
.preview-with-erp .business-three-fields .business-field>label{margin-top:0}
.preview-with-erp .salary-net{background:#f0fdfa;border:1px solid #99f6e4;border-radius:12px;padding:12px;text-align:center;margin-top:16px;color:#0f766e}
.preview-with-erp .salary-net>span{display:block;font-size:12px;font-weight:700}.preview-with-erp .salary-net>strong{display:block;font-size:24px;line-height:32px;font-weight:900;margin-top:4px}
@media(min-width:640px){.preview-with-erp .business-stats{grid-template-columns:repeat(3,minmax(0,1fr))}}
'''

CSS += '''
.preview-with-erp [data-business-page=settings]{max-width:960px}
.preview-with-erp .settings-preview-card{padding:24px;border:1px solid #e2e8f0;border-radius:24px;background:white;box-shadow:0 1px 2px #0000000d}
.preview-with-erp .settings-preview-card>header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;border-bottom:1px solid #f1f5f9;padding-bottom:12px;margin-bottom:12px}
.preview-with-erp .settings-preview-card>header h2{font-size:12px;line-height:16px;font-weight:900;color:#334155}.preview-with-erp .settings-preview-card>header h2 i{color:#4f46e5}
.preview-with-erp .settings-preview-card>header span{font-size:11px;line-height:16.5px;font-weight:700;color:#94a3b8}
.preview-with-erp .settings-letterhead{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:16px;padding:16px}
.preview-with-erp .settings-letterhead>div:first-child{display:flex;align-items:center;gap:14px;min-width:0}
.preview-with-erp .settings-logo{height:64px;width:96px;flex:0 0 96px;border:1px solid #cbd5e1;border-radius:12px;background:white;color:#263c5c;display:flex;align-items:center;justify-content:center;flex-direction:column;font-size:24px}
.preview-with-erp .settings-logo strong{font-size:10px}
.preview-with-erp .settings-letterhead h2{font-size:18px;line-height:1.25;font-weight:900;overflow-wrap:anywhere}.preview-with-erp .settings-letterhead p{font-size:12px;line-height:16px;font-weight:700;color:#475569;margin-top:2px}
.preview-with-erp .settings-receipt-badge{display:none;text-align:center}.preview-with-erp .settings-receipt-badge strong{display:block;font-size:12px;line-height:16px;font-weight:900;letter-spacing:.05em;background:#0f172a;color:white;border-radius:8px;padding:4px 12px}
.preview-with-erp .settings-receipt-badge small{display:block;font-size:9px;line-height:13.5px;color:#94a3b8;font-weight:700;margin-top:2px}
.preview-with-erp .settings-form-card{background:white;border:1px solid #e2e8f0cc;border-radius:24px;padding:24px}
.preview-with-erp .settings-form-card form{display:flex;flex-direction:column;gap:24px}
.preview-with-erp .settings-form-card .business-field :is(input,select){font-size:14px;line-height:20px;padding:10px 16px}
.preview-with-erp .settings-logo-section{border-top:1px solid #f1f5f9;padding-top:16px}
.preview-with-erp .settings-logo-section h2{font-size:12px;line-height:16px;font-weight:700;color:#334155;margin-bottom:8px}
.preview-with-erp .settings-logo-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;align-items:center}
.preview-with-erp .settings-current-logo{border:2px solid #e2e8f0;border-radius:16px;background:#f8fafc;padding:16px;text-align:center}
.preview-with-erp .settings-current-logo>span{font-size:11px;line-height:16.5px;font-weight:700;color:#94a3b8;display:block;margin-bottom:8px}
.preview-with-erp .settings-current-logo .settings-logo{height:80px;width:128px;margin:auto}.preview-with-erp .settings-current-logo .business-check{justify-content:center;font-size:11px;color:#e11d48}
.preview-with-erp .settings-logo-upload{border:2px dashed #cbd5e1;border-radius:16px;background:#f8fafc;padding:20px;text-align:center;min-width:0}
.preview-with-erp .settings-logo-upload>i{font-size:30px;color:#94a3b8;margin-bottom:8px}
.preview-with-erp .settings-logo-upload p{font-size:12px;line-height:16px;font-weight:700;color:#334155;margin-bottom:4px}
.preview-with-erp .settings-logo-upload small{display:block;font-size:11px;line-height:16.5px;color:#94a3b8;margin-bottom:12px}
.preview-with-erp .settings-logo-upload input{font-size:11px;max-width:100%}
.preview-with-erp .settings-save{justify-content:flex-end;border-top:1px solid #f1f5f9;padding-top:16px;margin:0;flex-wrap:wrap}
.preview-with-erp .settings-save>.business-button:first-child{flex:0}
@media(min-width:640px){.preview-with-erp .settings-form-card{padding:32px}.preview-with-erp .settings-receipt-badge{display:block}.preview-with-erp .settings-letterhead h2{font-size:20px}.preview-with-erp .settings-logo-grid{grid-template-columns:minmax(0,1fr) minmax(0,2fr)}}
'''
