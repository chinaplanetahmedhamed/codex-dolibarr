"""Synthetic cash-voucher design, independent of private expense attachments and ledgers."""
from decimal import Decimal
import html

from preview_dashboard_design import SAMPLES, icon, money, office_picker, word


VOUCHERS = {
    'egypt': {'ref': 'REC-DEMO-EG-0001', 'date': '2026-11-01', 'amount': '10000.00',
              'ar': 'عميل تجريبي — مصر', 'en': 'Sample customer — Egypt',
              'words_ar': 'فقط عشرة آلاف جنيه مصري لا غير', 'words_en': 'Ten thousand Egyptian pounds only'},
    'china': {'ref': 'REC-DEMO-CN-0001', 'date': '2026-11-02', 'amount': '3000.00',
              'ar': 'عميل تجريبي — الصين', 'en': 'Sample customer — China',
              'words_ar': 'فقط ثلاثة آلاف يوان صيني لا غير', 'words_en': 'Three thousand Chinese yuan only'},
}


def receipt_link(lang, office, online=False):
    return f'{lang}-receipt-{office}' + ('-view' if online else '-a4') + f'.html?office={office}'


def logo():
    return '<div class="receipt-brand-logo" aria-label="China Planet · Sample logo">' + icon('building') + '<strong>China Planet</strong></div>'


def receipts(lang):
    t = lambda ar, en: word(lang, ar, en)
    out = ['<main id="office-workspace" class="finens-dashboard treasury-preview receipts-preview">',
           '<div class="treasury-heading"><div><h1>' + t('إيصالات استلام النقدية (سندات القبض)', 'Cash receipt vouchers') + '</h1><p>'
           + t('إصدار وصولات استلام فلوس فورية، تسجيل بيانات العميل والمستلم، وطباعة 3 وصولات في صفحة A4 واحدة',
               'Issue cash receipts, record payer and receiver details, and print three copies on one A4 sheet')
           + '</p></div><div class="treasury-heading-actions"><button class="treasury-deposit" type="button" data-open="create-cash-receipt">'
           + icon('file-circle-plus') + t('إصدار وصل استلام جديد', 'Issue new receipt')
           + '</button><button class="treasury-print" type="button" data-open="receipt-branding">' + icon('building')
           + t('تعديل اسم وشعار المقر', 'Edit office name & logo') + '</button></div></div>',
           '<div class="treasury-preview-note"><p>' + icon('circle-info')
           + t('معاينة تصميم · إيصالات تجريبية · لا إصدار أو حفظ أو إيداع فعلي', 'Design preview · Sample vouchers · No issuing, saving or treasury deposits')
           + '</p>' + office_picker(lang) + '</div>',
           '<section class="receipt-brand-banner"><div class="receipt-brand-identity">' + logo()
           + '<div><span>' + t('شعار واسم المقر المسجل على الإيصالات والفواتير', 'Office name and logo on receipts and invoices')
           + '</span><h2>China Planet</h2><p>' + t('هاتف المقر:', 'Office phone:') + ' <bdi>—</bdi> | '
           + t('المستلم المسجل:', 'Registered receiver:') + ' <strong>' + t('مستخدم تجريبي', 'Sample user') + '</strong></p></div></div>'
           + '<button type="button" data-open="receipt-branding">' + icon('pen-to-square') + t('تعديل اسم المقر والشعار', 'Edit office name & logo') + '</button></section>']
    for office, sample in SAMPLES.items():
        voucher = VOUCHERS[office]
        out.append(f'<div class="office-dashboard receipt-office" data-office="{office}"' + (' hidden' if office != 'egypt' else '') + '>')
        out.append('<section class="receipt-summary" aria-label="' + t('ملخص الإيصالات التجريبية', 'Sample receipt summary') + '">')
        for tone, label, value, caption, symbol in (
            ('green', t('إجمالي المبالغ المستلمة', 'Total amount received'), '<span data-receipt-total>' + money(voucher['amount'], sample['currency']) + '</span>',
             t('بحسب فترة البحث', 'For the selected period'), 'hand-holding-dollar'),
            ('indigo', t('عدد الإيصالات المصدرة', 'Number of receipts'), '<span data-receipt-number>1</span> ' + t('إيصال', 'receipt'),
             t('أرقام توضيحية — لا يتم إصدار أرقام فعلية', 'Illustrative references — no actual numbers issued'), 'receipt'),
            ('purple', t('تنسيق الطباعة A4', 'A4 print format'), t('3 وصولات بالورقة', '3 copies per sheet'),
             t('عميل + حسابات + أرشيف', 'Customer + accounts + archive'), 'print'),
        ):
            out.append(f'<div class="receipt-stat {tone}"><div><p>{label}</p><h2>{value}</h2><small>{caption}</small></div><span>' + icon(symbol) + '</span></div>')
        out.append('</section><section class="treasury-filters" aria-label="' + t('بحث الإيصالات التجريبية', 'Search sample receipts') + '"><form data-receipt-filter><div><label for="'
                   + office + '-receipt-search">' + t('بحث برقم الإيصال / اسم العميل / الهاتف', 'Search receipt ID / customer / phone')
                   + '</label><input id="' + office + '-receipt-search" name="search" placeholder="' + t('رقم الإيصال أو اسم العميل...', 'Receipt ID or customer name…') + '"></div>')
        for name, date, label in (('from', '2026-11-01', t('من تاريخ', 'From date')), ('to', '2026-11-02', t('إلى تاريخ', 'To date'))):
            out.append(f'<div><label for="{office}-receipt-{name}">{label}</label><input id="{office}-receipt-{name}" name="{name}" type="date" value="{date}"></div>')
        out.append('<div class="treasury-filter-actions"><button type="submit">' + icon('search') + t('بحث وتصفية', 'Search & filter')
                   + '</button><button type="reset" aria-label="' + t('إعادة تعيين', 'Reset filters') + '">' + icon('rotate-right')
                   + '</button></div></form><p class="treasury-filter-error" role="alert" hidden>' + t('تاريخ البداية يجب أن يسبق تاريخ النهاية أو يساويه.', 'The start date must be on or before the end date.')
                   + '</p></section><section class="treasury-ledger receipt-register"><div class="treasury-ledger-heading"><h2>'
                   + t('سجل إيصالات استلام النقدية', 'Cash receipt register') + '</h2><span data-receipt-count aria-live="polite">'
                   + t('1 إيصال مسجل', '1 recorded receipt') + '</span></div><div class="treasury-table-scroll" tabindex="0" role="region" aria-label="'
                   + t('إيصالات تجريبية — مرر لعرض كل الأعمدة', 'Sample receipts — scroll to view all columns') + '"><table><thead><tr>')
        for label in (t('رقم الإيصال (ID)', 'Receipt ID'), t('تاريخ الاستلام', 'Receipt date'), t('اسم العميل (المستلم منه)', 'Customer / payer'), t('المبلغ', 'Amount'),
                      t('طريقة الدفع', 'Payment method'), t('بيان وسبب الاستلام', 'Description / purpose'), t('المستلم', 'Receiver'), t('حالة الخزنة', 'Treasury status'), t('طباعة الوصل A4', 'Print A4 receipt')):
            out.append('<th scope="col">' + label + '</th>')
        out.append('</tr></thead><tbody><tr data-voucher-row data-date="' + voucher['date'] + '" data-search="'
                   + html.escape(voucher['ref'] + ' ' + voucher['ar'] + ' ' + voucher['en'], quote=True) + '" data-cents="' + str(int(Decimal(voucher['amount']) * 100))
                   + '"><td class="receipt-reference"><bdi>' + voucher['ref'] + '</bdi></td><td><bdi>' + voucher['date'] + '</bdi></td><td><strong>'
                   + voucher[lang] + '</strong><small>' + icon('phone') + ' —</small></td><td class="ledger-money">' + money(voucher['amount'], sample['currency'])
                   + '</td><td><span class="receipt-method">' + t('نقداً كاش', 'Cash') + '</span></td><td class="receipt-purpose">'
                   + t('تمويل تجريبي للمكتب', 'Sample office funding') + '</td><td>' + icon('user-check') + ' ' + t('مستخدم تجريبي', 'Sample user')
                   + '</td><td><span class="receipt-status">' + t('تجريبي — غير مرحّل', 'Sample — unposted') + '</span></td><td><div class="receipt-row-actions"><a href="'
                   + receipt_link(lang, office) + '">' + icon('print') + t('طباعة A4', 'Print A4') + '</a><a href="'
                   + receipt_link(lang, office, True) + '">' + icon('qrcode') + t('عرض', 'View') + '</a></div></td></tr><tr class="ledger-empty" hidden><td colspan="9">'
                   + icon('inbox') + t('لا توجد إيصالات تجريبية تطابق هذه التصفية.', 'No sample receipts match these filters.') + '</td></tr></tbody></table></div></section></div>')
    return ''.join(out) + dialogs(lang) + '</main>'


def dialogs(lang):
    t = lambda ar, en: word(lang, ar, en)
    close = '<button type="button" data-close aria-label="' + t('إغلاق', 'Close') + '">' + icon('xmark') + '</button>'
    out = ['<dialog id="create-cash-receipt" class="treasury-deposit-dialog receipt-dialog" aria-labelledby="create-receipt-title"><div class="treasury-dialog-heading"><div><span>'
           + icon('hand-holding-dollar') + '</span><div><h2 id="create-receipt-title">' + t('إصدار وصل استلام نقدية', 'Issue cash receipt')
           + '</h2><p>' + t('معاينة سند قبض — لا يتم إصدار رقم فعلي', 'Voucher preview — no actual reference is issued') + '</p></div></div>' + close + '</div>'
           + '<form data-receipt-draft><div class="receipt-receiver"><span>' + t('المستلم المسؤول:', 'Responsible receiver:') + ' <strong>'
           + t('مستخدم تجريبي', 'Sample user') + '</strong></span><small>' + t('رقم توضيحي:', 'Sample ID:') + ' <bdi>REC-DEMO-NEW</bdi></small></div>'
           + '<label for="cash-receipt-amount">' + t('المبلغ المستلم والعملة', 'Amount received & currency') + ' <span>*</span></label><div class="deposit-money-input">'
           + '<input id="cash-receipt-amount" type="number" step="0.01" min="0.01" max="999999999.99" placeholder="500.00" inputmode="decimal"><select aria-label="'
           + t('العملة', 'Currency') + '" data-receipt-currency><option>EGP</option><option>CNY</option></select></div><div class="receipt-words">' + icon('spell-check')
           + '<span>' + t('التفقيط:', 'In words:') + '</span><strong data-amount-words>' + t('أدخل المبلغ لعرض التفقيط', 'Enter an amount to preview words') + '</strong></div>']
    out.append('<div class="receipt-form-grid">')
    for id_, label, placeholder in (
        ('payer', t('اسم العميل / المستلم منه', 'Customer / payer name'), t('اسم عميل تجريبي...', 'Sample customer name…')),
        ('phone', t('رقم هاتف العميل', 'Customer phone number'), t('هاتف تجريبي فقط', 'Sample phone only')),
    ):
        out.append('<div><label for="cash-receipt-' + id_ + '">' + label + '</label><input id="cash-receipt-' + id_ + '" placeholder="' + placeholder + '"></div>')
    out.append('<div><label for="cash-receipt-method">' + t('طريقة الدفع', 'Payment method') + '</label><select id="cash-receipt-method">')
    for label in (t('نقداً كاش', 'Cash'), t('فودافون كاش / إنستاباي', 'Mobile wallet / InstaPay'), t('تحويل بنكي', 'Bank transfer'), t('شيك مصرفي', 'Bank cheque')):
        out.append('<option>' + label + '</option>')
    out.append('</select></div><div><label for="cash-receipt-date">' + t('تاريخ الاستلام', 'Receipt date') + '</label><input id="cash-receipt-date" type="date" value="2026-11-02"></div></div>'
               + '<label for="cash-receipt-purpose">' + t('بيان وسبب الاستلام (وذلك عن)', 'Description / purpose') + ' <span>*</span></label><input id="cash-receipt-purpose" placeholder="'
               + t('مثال: دفعة تحت الحساب، حجز بضاعة...', 'Example: advance payment, goods reservation…') + '"><label for="cash-receipt-notes">'
               + t('تفاصيل أو ملاحظات إضافية', 'Additional details or notes') + '</label><textarea id="cash-receipt-notes" rows="2" placeholder="'
               + t('اختياري...', 'Optional…') + '"></textarea><div class="receipt-safe-toggle"><input id="receipt-deposit-disabled" type="checkbox" disabled><label for="receipt-deposit-disabled">'
               + t('إيداع المبلغ في الخزنة تلقائياً — معطّل في المعاينة', 'Automatically deposit to treasury — disabled in preview') + '</label></div>'
               + '<p class="treasury-dialog-note">' + t('معاينة فقط. لا يتم إنشاء إيصال أو حفظ الحقول أو تغيير الرصيد.', 'Preview only. No receipt is issued, no fields are saved and no balance changes.')
               + '</p><div class="treasury-dialog-actions"><button type="button" disabled>' + t('إصدار وطباعة — معطّل في المعاينة', 'Issue & print — disabled in preview')
               + '</button><button type="button" data-close>' + t('إلغاء', 'Cancel') + '</button></div></form></dialog>')
    out.append('<dialog id="receipt-branding" class="treasury-deposit-dialog receipt-dialog" aria-labelledby="receipt-brand-title"><div class="treasury-dialog-heading"><div><span>'
               + icon('building') + '</span><h2 id="receipt-brand-title">' + t('إعدادات اسم المقر والشعار', 'Office name & logo settings') + '</h2></div>' + close + '</div><form data-receipt-draft>'
               + '<label for="receipt-company">' + t('اسم المقر / الشركة (الذي يظهر بأعلى الإيصال)', 'Office / company name printed on receipts')
               + '</label><input id="receipt-company" value="China Planet"><div class="receipt-form-grid"><div><label for="receipt-company-phone">'
               + t('رقم هاتف المقر', 'Office phone') + '</label><input id="receipt-company-phone" placeholder="—"></div><div><label for="receipt-default-currency">'
               + t('العملة الافتراضية', 'Default currency') + '</label><select id="receipt-default-currency" data-brand-currency><option>EGP</option><option>CNY</option></select></div></div>'
               + '<label for="receipt-logo-upload">' + t('شعار المقر المطبوع على الإيصالات والفواتير', 'Office logo printed on receipts and invoices') + '</label><div class="receipt-logo-upload">'
               + logo() + '<p>' + t('شعار توضيحي للمعاينة', 'Illustrative preview logo') + '</p><label class="receipt-remove-logo"><input type="checkbox" disabled>'
               + t('حذف الشعار والعودة للافتراضي — معطّل', 'Remove logo and use default — disabled') + '</label><input id="receipt-logo-upload" type="file" accept="image/*" disabled><small>'
               + t('رفع الملفات وحفظ التغييرات غير متاحين في المعاينة.', 'File uploads and saving are unavailable in this preview.') + '</small></div><div class="treasury-dialog-actions"><button type="button" disabled>'
               + t('حفظ بيانات المقر والشعار — معطّل', 'Save office name & logo — disabled') + '</button><button type="button" data-close>' + t('إلغاء', 'Cancel') + '</button></div></form></dialog>')
    return ''.join(out)


def print_page(lang, office, online=False):
    """Standalone, local sample-only A4 or single-voucher preview; no public route/QR."""
    t = lambda ar, en: word(lang, ar, en)
    voucher, sample = VOUCHERS[office], SAMPLES[office]
    title = t('معاينة إيصال تجريبي', 'Sample receipt preview') if online else t('معاينة طباعة ورقة A4 (3 وصولات)', 'A4 print preview (3 receipt copies)')
    out = ['<!doctype html><html lang="' + lang + '" dir="' + ('rtl' if lang == 'ar' else 'ltr') + '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           + '<meta name="robots" content="noindex,nofollow"><title>' + title + '</title><link rel="stylesheet" href="assets/fontawesome.css"><link rel="stylesheet" href="assets/fontawesome-solid.css">'
           + '<style>' + PRINT_CSS + '</style><script src="cash-receipts-preview.js" defer></script></head><body class="office-expenses-app receipt-paper-preview"><header class="paper-toolbar"><div><h1>' + title + '</h1><p>'
           + t('نموذج تجريبي — غير صالح للاستخدام المالي أو إثبات الدفع', 'Sample only — not valid for financial use or proof of payment') + '</p></div><div><a href="' + lang + '-receipts.html?office=' + office + '">'
           + t('العودة للسجل', 'Back to register') + '</a><button type="button" data-print-voucher>' + icon('print') + t('طباعة النموذج', 'Print sample') + '</button></div></header><main class="receipt-paper-scroll"><div class="a4-page">']
    copies = [t('أصل — نسخة العميل', 'Original — customer copy')] if online else [t('أصل — نسخة العميل', 'Original — customer copy'), t('نسخة الحسابات', 'Accounts copy'), t('نسخة الأرشيف', 'Archive copy')]
    for index, copy in enumerate(copies):
        if index:
            out.append('<div class="cut-line"><span>' + icon('scissors') + ' ' + t('قص من هنا', 'Cut here') + '</span></div>')
        out.append('<article class="receipt-slip"><div class="slip-watermark">' + t('نموذج تجريبي', 'SAMPLE') + '</div><header><div class="slip-brand">' + logo()
                   + '<div><h2>China Planet</h2><p>' + t('هاتف:', 'Phone:') + ' —</p></div></div><div class="slip-title"><strong>'
                   + t('إيصال استلام نقدية', 'Cash receipt voucher') + '</strong><small>CASH RECEIPT VOUCHER</small></div><div class="slip-reference"><span>'
                   + copy + '</span><p>' + t('رقم:', 'ID:') + ' <bdi>' + voucher['ref'] + '</bdi></p><small>' + t('التاريخ:', 'Date:') + ' <bdi>' + voucher['date'] + '</bdi></small></div></header>'
                   + '<div class="slip-body"><div class="slip-amount"><span>' + t('المبلغ المستلم:', 'Amount received:') + ' <strong>' + money(voucher['amount'], sample['currency'])
                   + '</strong></span><span>' + t('طريقة الدفع: نقداً كاش', 'Payment method: Cash') + '</span></div>')
        for label, value in ((t('استلمنا من السيد / السادة:', 'Received from:'), voucher[lang]), (t('مبلغ وقدره (كتابةً):', 'Amount in words:'), voucher['words_' + lang]),
                             (t('وذلك عن (البيان):', 'For / description:'), t('تمويل تجريبي للمكتب', 'Sample office funding'))):
            out.append('<div class="slip-line"><span>' + label + '</span><strong>' + value + '</strong></div>')
        out.append('<p class="slip-sample-note">' + t('نموذج تجريبي — غير صالح للاستخدام المالي أو إثبات الدفع', 'Sample only — not valid for financial use or proof of payment') + '</p></div><footer>')
        for label, value in ((t('المستلم المسؤول', 'Responsible receiver'), t('مستخدم تجريبي', 'Sample user')), (t('توقيع الدافع (العميل)', 'Payer signature'), '—'), (t('اعتماد الحسابات / الخاتم', 'Accounts approval / stamp'), '—')):
            out.append('<div><span>' + label + '</span><strong>' + value + '</strong><small>' + t('التوقيع:', 'Signature:') + ' ....................</small></div>')
        out.append('<div class="slip-qr"><div>' + icon('qrcode') + '</div><small>' + t('موضع QR للمعاينة فقط', 'QR position — preview only') + '</small></div></footer></article>')
    return ''.join(out) + '</div></main></body></html>'


SCRIPT = r'''
'use strict';
(() => {
  const ar = document.documentElement.lang.startsWith('ar');
  const t = (arabic, english) => ar ? arabic : english;
  const format = (cents, currency) => (cents / 100).toLocaleString('en-US', {minimumFractionDigits:2,maximumFractionDigits:2}) + ' ' + currency;
  document.querySelectorAll('[data-receipt-filter]').forEach(form => {
    const office = form.closest('.receipt-office');
    const currency = office.dataset.office === 'china' ? 'CNY' : 'EGP';
    const update = () => {
      const from = form.elements.from.value, to = form.elements.to.value;
      const invalid = from && to && from > to;
      office.querySelector('.treasury-filter-error').hidden = !invalid;
      form.elements.to.setAttribute('aria-invalid', invalid ? 'true' : 'false');
      if (invalid) return;
      const search = form.elements.search.value.trim().toLocaleLowerCase();
      let count = 0, total = 0;
      office.querySelectorAll('[data-voucher-row]').forEach(row => {
        row.hidden = !!((search && !row.dataset.search.toLocaleLowerCase().includes(search)) || (from && row.dataset.date < from) || (to && row.dataset.date > to));
        if (!row.hidden) { count++; total += Number(row.dataset.cents); }
      });
      office.querySelector('[data-receipt-total]').textContent = format(total, currency);
      office.querySelector('[data-receipt-number]').textContent = count;
      office.querySelector('[data-receipt-count]').textContent = count + t(' إيصال مسجل', ' recorded receipts');
      office.querySelector('.ledger-empty').hidden = count !== 0;
    };
    form.addEventListener('submit', event => { event.preventDefault(); update(); });
    form.addEventListener('reset', () => setTimeout(update, 0));
  });
  const amount = document.querySelector('#cash-receipt-amount');
  const currency = document.querySelector('[data-receipt-currency]');
  function words(n) {
    if (!ar) {
      const ones=['zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen'];
      const tens=['','','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];
      if(n<20)return ones[n];
      if(n<100)return tens[Math.floor(n/10)]+(n%10?' '+ones[n%10]:'');
      if(n<1000)return ones[Math.floor(n/100)]+' hundred'+(n%100?' '+words(n%100):'');
      const base=n>=1000000?1000000:1000;
      return words(Math.floor(n/base))+(base===1000000?' million':' thousand')+(n%base?' '+words(n%base):'');
    }
    const ones=['صفر','واحد','اثنان','ثلاثة','أربعة','خمسة','ستة','سبعة','ثمانية','تسعة','عشرة','أحد عشر','اثنا عشر','ثلاثة عشر','أربعة عشر','خمسة عشر','ستة عشر','سبعة عشر','ثمانية عشر','تسعة عشر'];
    const tens=['','','عشرون','ثلاثون','أربعون','خمسون','ستون','سبعون','ثمانون','تسعون'];
    const hundreds=['','مائة','مائتان','ثلاثمائة','أربعمائة','خمسمائة','ستمائة','سبعمائة','ثمانمائة','تسعمائة'];
    if(n<20)return ones[n];
    if(n<100)return (n%10?ones[n%10]+' و':'')+tens[Math.floor(n/10)];
    if(n<1000)return hundreds[Math.floor(n/100)]+(n%100?' و'+words(n%100):'');
    const base=n>=1000000?1000000:1000, count=Math.floor(n/base);
    const unit=base===1000000?['مليون','مليونان','ملايين']:['ألف','ألفان','آلاف'];
    return (count===1?unit[0]:count===2?unit[1]:words(count)+' '+(count<=10?unit[2]:unit[0]))+(n%base?' و'+words(n%base):'');
  }
  const updateWords = () => {
    const target = document.querySelector('[data-amount-words]');
    if (!target || !amount || !currency) return;
    if (!/^\d{1,9}(\.\d{1,2})?$/.test(amount.value) || Number(amount.value)<=0) {
      target.textContent=t('أدخل مبلغاً موجباً بدقة منزلتين لعرض التفقيط', 'Enter a positive amount with up to two decimal places'); return;
    }
    const [whole, fraction=''] = amount.value.split('.'), minor=Number(fraction.padEnd(2,'0'));
    const yuan=currency.value==='CNY';
    target.textContent=t('فقط ','')+words(Number(whole))+' '+(yuan?t('يوان صيني','Chinese yuan'):t('جنيه مصري','Egyptian pounds'))+(minor?t(' و',' and ')+words(minor)+' '+(yuan?t('فن','fen'):t('قرشاً','piastres')):'')+t(' لا غير',' only');
  };
  amount?.addEventListener('input', updateWords);
  currency?.addEventListener('change', updateWords);
  const sync = () => {
    const office=document.querySelector('[data-office-picker]')?.value;
    for(const select of document.querySelectorAll('[data-receipt-currency],[data-brand-currency]')) select.value=office==='china'?'CNY':'EGP';
    if(amount?.value)updateWords();
  };
  document.querySelector('[data-office-picker]')?.addEventListener('change',sync);
  sync();
  document.querySelectorAll('[data-receipt-draft]').forEach(form=>form.addEventListener('submit',e=>e.preventDefault()));
  document.querySelectorAll('.receipt-dialog').forEach(dialog=>dialog.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();dialog.close();}}));
  document.querySelector('[data-print-voucher]')?.addEventListener('click',()=>window.print());
})();
'''


CSS = '''
.preview-with-erp .receipt-brand-banner{display:flex;flex-direction:column;gap:16px;border-radius:24px;padding:20px;background:linear-gradient(to right,#0f172a,#1e1b4b,#0f172a);color:white;box-shadow:0 20px 25px -5px #0000001a,0 8px 10px -6px #0000001a}
.preview-with-erp .receipt-brand-identity{display:flex;align-items:center;gap:16px;min-width:0}
.preview-with-erp .receipt-brand-logo{width:112px;height:80px;flex-shrink:0;padding:4px;background:white;border:1px solid #fff3;border-radius:16px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#263c5c;box-shadow:0 10px 15px -3px #0000001a,0 4px 6px -4px #0000001a}
.preview-with-erp .receipt-brand-logo i{font-size:24px}
.preview-with-erp .receipt-brand-logo strong{font-size:12px}
.preview-with-erp .receipt-brand-identity>div>span{font-size:12px;line-height:16px;font-weight:700;color:#fcd34d}
.preview-with-erp .receipt-brand-identity h2{font-size:20px;line-height:28px;font-weight:900;color:white;margin-top:2px}
.preview-with-erp .receipt-brand-identity p{font-size:12px;line-height:16px;color:#cbd5e1;margin-top:2px}
.preview-with-erp .receipt-brand-identity p strong{color:#34d399;font-weight:700}
.preview-with-erp .receipt-brand-banner>button{display:flex;align-items:center;justify-content:center;gap:6px;align-self:flex-start;background:#ffffff26;color:white;border:1px solid #fff3;font-weight:700;font-size:12px;line-height:16px;padding:10px 16px;border-radius:12px;cursor:pointer;flex-shrink:0}
.preview-with-erp .receipt-brand-banner>button:hover{background:#ffffff40}
.preview-with-erp .receipt-summary{display:grid;grid-template-columns:minmax(0,1fr);gap:16px}
.preview-with-erp .receipt-stat{display:flex;align-items:center;justify-content:space-between;gap:8px;background:white;border:1px solid #e2e8f0cc;border-radius:16px;padding:20px;min-width:0}
.preview-with-erp .receipt-stat p{font-size:12px;line-height:16px;color:#64748b;font-weight:700}
.preview-with-erp .receipt-stat h2{font-size:24px;line-height:32px;color:#1e293b;font-weight:900;margin-top:4px}
.preview-with-erp .receipt-stat.green h2{color:#059669}
.preview-with-erp .receipt-stat small{font-size:11px;line-height:16.5px;color:#94a3b8}
.preview-with-erp .receipt-stat>span{display:flex;align-items:center;justify-content:center;width:48px;height:48px;flex:0 0 48px;border-radius:12px;font-size:20px;line-height:28px;background:#ecfdf5;color:#059669}
.preview-with-erp .receipt-stat.indigo>span{background:#eef2ff;color:#4f46e5}
.preview-with-erp .receipt-stat.purple>span{background:#faf5ff;color:#9333ea}
.preview-with-erp .receipt-stat.purple h2{font-size:16px;line-height:24px}
.preview-with-erp .receipt-stat.purple small{font-weight:600;color:#059669}
.preview-with-erp .receipt-register td small{display:block;font-size:11px;line-height:16.5px;color:#94a3b8;margin-top:4px}
.preview-with-erp .receipt-register .receipt-reference{font-weight:900;color:#4338ca}
.preview-with-erp .receipt-register td>strong{font-weight:700;color:#1e293b}
.preview-with-erp .receipt-register .receipt-purpose{white-space:normal;min-width:140px;color:#475569}
.preview-with-erp .receipt-method,.preview-with-erp .receipt-status{display:inline-block;font-size:10px;line-height:15px;padding:2px 8px;border-radius:6px;background:#f1f5f9;color:#475569;font-weight:700}
.preview-with-erp .receipt-method{border:1px solid #e2e8f0}
.preview-with-erp .receipt-row-actions{display:flex;align-items:center;justify-content:center;gap:6px}
.preview-with-erp .receipt-row-actions a{display:inline-flex;align-items:center;gap:4px;padding:6px 10px;border-radius:8px;background:#4f46e5;color:white;font-size:11px;line-height:16px;font-weight:700}
.preview-with-erp .receipt-row-actions a:last-child{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.preview-with-erp .receipt-row-actions a:hover{filter:brightness(.95)}
.preview-with-erp .receipt-dialog{max-width:512px;border-radius:24px}
.preview-with-erp .receipt-dialog .treasury-dialog-heading p{font-size:12px;line-height:16px;color:#94a3b8;margin:0}
.preview-with-erp .receipt-dialog .treasury-dialog-heading h2{font-weight:900}
.preview-with-erp .receipt-dialog .treasury-dialog-heading span{width:36px;height:36px;border-radius:12px}
.preview-with-erp .receipt-dialog .receipt-receiver{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:4px;background:#eef2ffcc;border:1px solid #e0e7ff;border-radius:16px;padding:12px;font-size:12px;line-height:16px;color:#64748b;font-weight:700}
.preview-with-erp .receipt-receiver strong{font-weight:900;color:#4338ca}
.preview-with-erp .receipt-receiver small{font-size:11px;color:#94a3b8;font-weight:400}
.preview-with-erp .receipt-dialog .deposit-money-input input{font-size:24px;line-height:32px;font-weight:900}
.preview-with-erp .receipt-dialog .deposit-money-input select{width:160px}
.preview-with-erp .receipt-words{display:flex;align-items:center;gap:8px;background:#ecfdf5b3;border:1px solid #a7f3d099;border-radius:12px;margin-top:8px;padding:10px;font-size:12px;line-height:16px}
.preview-with-erp .receipt-words i{color:#059669}
.preview-with-erp .receipt-words>span{font-weight:700;color:#64748b;flex-shrink:0}
.preview-with-erp .receipt-words strong{font-weight:900;color:#065f46}
.preview-with-erp .receipt-form-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:0 12px}
.preview-with-erp .receipt-form-grid>div{min-width:0}
.preview-with-erp .receipt-dialog :is(input,select,textarea){font-size:12px;line-height:16px}
.preview-with-erp .receipt-safe-toggle{display:flex;align-items:center;gap:8px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:12px;margin:16px 0}
.preview-with-erp .receipt-dialog .receipt-safe-toggle input{width:16px;height:16px;min-height:0;flex-shrink:0}
.preview-with-erp .receipt-dialog .receipt-safe-toggle label{margin:0;color:#064e3b}
.preview-with-erp .receipt-dialog .treasury-dialog-actions button{font-size:12px;line-height:16px}
.preview-with-erp .receipt-logo-upload{padding:16px;border:2px dashed #cbd5e1;background:#f8fafc;border-radius:16px;text-align:center}
.preview-with-erp .receipt-logo-upload .receipt-brand-logo{margin:0 auto 8px;border:1px solid #e2e8f0;width:128px;border-radius:12px}
.preview-with-erp .receipt-logo-upload p{font-size:12px;color:#64748b;font-weight:700;margin-bottom:8px}
.preview-with-erp .receipt-dialog .receipt-remove-logo{display:flex;align-items:center;justify-content:center;gap:8px;font-size:12px;color:#e11d48;margin:0 0 12px}
.preview-with-erp .receipt-dialog .receipt-remove-logo input{width:16px;height:16px;min-height:0}
.preview-with-erp .receipt-logo-upload small{display:block;font-size:11px;line-height:16.5px;color:#94a3b8;margin-top:8px}
.preview-with-erp .receipt-dialog :disabled{cursor:not-allowed}
@media(min-width:640px){
 .preview-with-erp .receipt-brand-banner{padding:24px;flex-direction:row;align-items:center;justify-content:space-between}
 .preview-with-erp .receipt-summary{grid-template-columns:repeat(3,minmax(0,1fr))}
 .preview-with-erp .receipt-dialog{padding:32px}
 .preview-with-erp .receipt-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
'''


PRINT_CSS = '''
@font-face{font-family:Cairo;src:url('assets/Cairo.ttf') format('truetype');font-weight:300 900;font-display:swap}
*{box-sizing:border-box}body{margin:0;padding:24px 16px;background:#f1f5f9;color:#0f172a;font:12px/1.5 Cairo,ui-sans-serif,system-ui,sans-serif;print-color-adjust:exact;-webkit-print-color-adjust:exact}h1,h2,p{margin:0}button,a{font:inherit;cursor:pointer}a{text-decoration:none;color:inherit}button:focus-visible,a:focus-visible{outline:2px solid #4f46e5;outline-offset:3px}
.paper-toolbar{max-width:896px;margin:0 auto 24px;background:white;border:1px solid #e2e8f0;border-radius:16px;padding:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;box-shadow:0 10px 15px -3px #0001}.paper-toolbar h1{font-size:16px;font-weight:900}.paper-toolbar p{font-size:11px;color:#be123c}.paper-toolbar>div:last-child{display:flex;gap:8px}.paper-toolbar a,.paper-toolbar button{padding:8px 12px;border:0;border-radius:8px;background:#f1f5f9;font-weight:700}.paper-toolbar button{background:#0f172a;color:white}.paper-toolbar i{margin-inline-end:6px}
.receipt-paper-scroll{overflow-x:auto}.a4-page{width:194mm;margin:0 auto;background:white;box-shadow:0 25px 50px -12px #0004}.receipt-slip{height:87mm;max-height:87mm;box-sizing:border-box;border:2px solid #1e293b;border-radius:10px;padding:8px 14px;display:flex;flex-direction:column;justify-content:space-between;break-inside:avoid;background:white;position:relative;overflow:hidden}.slip-watermark{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:64px;font-weight:900;opacity:.04;transform:rotate(-15deg);pointer-events:none}.receipt-slip>header{display:flex;align-items:center;justify-content:space-between;gap:8px;padding-bottom:8px;border-bottom:1px solid #cbd5e1;position:relative}.slip-brand{display:flex;align-items:center;gap:12px}.receipt-brand-logo{height:64px;width:96px;border:1px solid #cbd5e1;border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:white;color:#263c5c}.receipt-brand-logo i{font-size:24px}.receipt-brand-logo strong{font-size:10px}.slip-brand h2{font-size:18px;font-weight:900;line-height:1.25}.slip-brand p{font-size:11px;color:#475569;font-weight:700;margin-top:2px}.slip-title{text-align:center}.slip-title strong{display:inline-block;padding:4px 12px;background:#0f172a;color:white;border-radius:8px;font-size:12px;font-weight:900;letter-spacing:.05em}.slip-title small{display:block;font-size:9px;color:#94a3b8;font-weight:700;letter-spacing:.1em;margin-top:2px}.slip-reference{text-align:end}.slip-reference>span{display:inline-block;font-size:9px;font-weight:900;background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;border-radius:4px;padding:2px 8px;margin-bottom:4px}.slip-reference p{font-size:12px;font-weight:900}.slip-reference bdi{font-family:ui-monospace,monospace;color:#e11d48}.slip-reference small{font-size:10px;font-weight:700;color:#475569}.slip-body{padding:8px 0;flex:1;position:relative}.slip-amount{display:flex;align-items:center;justify-content:space-between;gap:8px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:6px 12px;margin-bottom:6px;font-size:11px;font-weight:700}.slip-amount strong{font-size:16px;font-weight:900;color:#047857;margin-inline-start:8px}.slip-line{display:flex;align-items:start;gap:8px;font-size:12px;margin-top:6px}.slip-line>span{width:144px;flex-shrink:0;font-weight:700;color:#475569}.slip-line>strong{flex:1;border-bottom:1px dotted #94a3b8;padding-bottom:2px}.slip-sample-note{font-size:9px;font-weight:700;color:#be123c;text-align:center;margin-top:6px}.receipt-slip>footer{border-top:1px solid #cbd5e1;padding-top:8px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;text-align:center;position:relative}.receipt-slip>footer>div:not(:last-child){border-inline-end:1px solid #e2e8f0;padding-inline-end:4px}.receipt-slip footer span{font-size:10px;font-weight:700;color:#64748b;display:block}.receipt-slip footer strong{display:block;font-size:11px;font-weight:900}.receipt-slip footer small{display:block;font-size:9px;color:#94a3b8;margin-top:8px}.slip-qr{display:flex;flex-direction:column;align-items:center}.slip-qr>div{width:60px;height:60px;border:1px solid #94a3b8;border-radius:8px;display:grid;place-items:center;font-size:36px;color:#94a3b8}.slip-qr small{font-size:8.5px!important;margin-top:2px!important;color:#475569!important}.cut-line{height:7mm;display:flex;align-items:center;justify-content:center;position:relative}.cut-line:before{content:'';position:absolute;inset-inline:0;top:50%;border-top:1.5px dashed #94a3b8}.cut-line span{background:white;padding:0 10px;position:relative;font-size:10px;color:#64748b;font-weight:700}
@page{size:A4 portrait;margin:6mm 8mm}
@media print{body{margin:0;padding:0;background:white}.paper-toolbar{display:none}.receipt-paper-scroll{overflow:visible}.a4-page{box-shadow:none;width:100%;margin:0;padding:0}.receipt-slip{border-color:black}}
'''
