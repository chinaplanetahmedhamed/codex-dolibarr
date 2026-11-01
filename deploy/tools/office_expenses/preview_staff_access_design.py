"""Review UI with verified staging identity snapshot. No live lookup or permission writes."""
from preview_business_pages import badge, button, checkbox, dialog, esc, field, frame, prefill, table, text
from preview_dashboard_design import icon


SECTIONS = (
    ('expenses', 'المصروفات', 'Expenses', 'CR'),
    ('advances', 'العهد والمرتجعات', 'Advances & returns', 'CR'),
    ('treasury', 'الخزنة', 'Treasury', ''),
    ('receipts', 'سندات القبض', 'Cash receipt vouchers', ''),
    ('categories', 'بنود المصروفات', 'Expense categories', ''),
    ('reports', 'التقارير', 'Reports', ''),
    ('employees', 'دليل الموظفين', 'Employee directory', ''),
    ('payroll', 'الرواتب', 'Payroll', ''),
)


def users(lang):
    t = lambda ar, en: text(lang, ar, en)
    out = [frame(lang, 'users', t('موظفو Dolibarr وصلاحيات الفروع', 'Dolibarr staff & branch permissions'),
                 t('شركة واحدة في Dolibarr · تعيين الموظفين لفروع مصر والصين وتحديد صلاحياتهم', 'One Dolibarr company · Assign existing staff to Egypt/China branches and define their permissions'),
                 button(t('تعيين موظف من Dolibarr', 'Assign Dolibarr staff'), 'user-plus', 'staff-assignment'))]
    out.append('<section class="staff-native-note">' + icon('link') + '<div><strong>' + t('نفس حساب الموظف في Dolibarr', 'The same Dolibarr staff account')
               + '</strong><p>' + t('الحسابات والأدوار أدناه مفعّلة في Dolibarr التجريبي بعد فحص عزل الفروع. هذه لقطة تصميم ثابتة؛ إدارة التعيينات الفعلية تتم من صفحة الوحدة على الخادم.',
                                   'These accounts and branch roles are active in staging Dolibarr with verified branch isolation. This is a static design snapshot; manage actual assignments through the deployed module.') + '</p></div></section>')
    out.append('<section class="staff-native-note"><div><strong>' + t('إدارة التعيينات الفعلية', 'Manage live assignments') + '</strong><p>' + t('هذه الصفحة للمعاينة. افتح صفحة Dolibarr لحفظ صلاحيات الموظفين.', 'This page is a preview. Open Dolibarr to save staff permissions.') + '</p><a class="business-button muted" href="https://backup-erp.visiontrading.biz/custom/chinaplanetofficeexpenses/users.php?office=egypt&amp;lang=' + lang + '">' + t('فرع مصر', 'Egypt branch') + '</a> <a class="business-button muted" href="https://backup-erp.visiontrading.biz/custom/chinaplanetofficeexpenses/users.php?office=china&amp;lang=' + lang + '">' + t('فرع الصين', 'China branch') + '</a></div></section>')
    out.append('<section class="staff-branch-examples"><div><span>' + icon('building') + '</span><strong>' + t('معاذ → فرع مصر', 'Moaz → Egypt branch') + '</strong><small>'
               + t('يرى بيانات مصر المسموح بها فقط', 'Sees permitted Egypt data only') + '</small></div><div><span>' + icon('building') + '</span><strong>' + t('رينا → فرع الصين', 'Rina → China branch')
               + '</strong><small>' + t('ترى بيانات الصين المسموح بها فقط', 'Sees permitted China data only') + '</small></div><div><span>' + icon('user-shield') + '</span><strong>'
               + t('المدير → الفرعان', 'Administrator → both branches') + '</strong><small>' + t('يدير التعيينات والصلاحيات حسب التفويض', 'Manages assignments and permissions as authorized') + '</small></div></section>')
    staff = (
        ('egypt', 'معاذ' if lang == 'ar' else 'Moaz', 'moaz', 5, 'manager', 'branch'),
        ('egypt', 'عمر' if lang == 'ar' else 'omar', 'omar', 7, 'entry', 'own'),
        ('china', 'رينا' if lang == 'ar' else 'rina', 'rina', 6, 'manager', 'branch'),
        ('china', 'شان شان' if lang == 'ar' else 'shan shan', 'shanshan', 8, 'entry', 'own'),
    )
    for office in ('egypt', 'china'):
        branch = t('مصر', 'Egypt') if office == 'egypt' else t('الصين', 'China')
        out.append('<div class="office-dashboard staff-office" data-office="' + office + '"' + (' hidden' if office != 'egypt' else '') + '>')
        rows = ''
        for assigned_office, person, login, native_id, role, scope in staff:
            if assigned_office != office:
                continue
            role_label = t('مدير الفرع', 'Branch manager') if role == 'manager' else t('موظف الفرع', 'Branch worker')
            details = prefill({'staff-person': str(native_id), 'staff-branch': office, 'staff-role': role, 'staff-record-scope': scope})
            row = '<tr><td><div class="business-person"><span>' + icon('user') + '</span><div><strong>' + person + '</strong><small>' + t('حساب Dolibarr مؤكد · رقم ', 'Verified Dolibarr account · ID ') + str(native_id)
            row += '</small></div></div></td><td><bdi>' + login + '</bdi></td><td>' + badge(branch, 'indigo') + '</td><td>' + role_label + '</td><td>' + badge(t('سجلات الفرع', 'Branch records') if scope == 'branch' else t('سجلاته فقط', 'Own records only'))
            row += '</td><td><div class="staff-row-actions"><a class="business-button muted" href="https://backup-erp.visiontrading.biz/user/card.php?id=' + str(native_id) + '" target="_blank" rel="noopener noreferrer">' + icon('arrow-up-right-from-square') + t('فتح حساب Dolibarr', 'Open Dolibarr account') + '</a>'
            row += button(t('الصلاحيات والتعيين', 'Permissions & assignment'), 'user-gear', 'staff-assignment', 'muted', details)
            row += button(t('إزالة من الفرع', 'Remove from branch'), 'user-minus', 'staff-remove', 'muted', prefill({'staff-remove-name': person + ' · ' + branch})) + '</div></td></tr>'
            rows += row
        out.append(table([t('موظف Dolibarr', 'Dolibarr staff member'), t('اسم الدخول', 'Login'), t('الفرع', 'Branch'), t('الدور المفعّل', 'Activated role'), t('نطاق السجلات', 'Record scope'), t('إجراءات', 'Actions')], rows,
                         t('حسابات وصلاحيات الفرع — لقطة من النسخة التجريبية', 'Native branch roles — staging snapshot')))
        out.append('</div>')
    out.append('<p class="staff-boundary-note">' + icon('shield-halved') + t('الفرع الآخر لا يظهر للموظف. الروابط المباشرة والبحث والتقارير والملفات يجب أن تخضع لنفس فحص الفرع على الخادم.',
                                                                                  'The other branch is not shown to staff. Direct routes, search, reports and files must enforce the same branch check on the server.') + '</p>')
    fields = '<p class="business-form-note">' + t('هذه صفحة مراجعة ثابتة والحفظ هنا معطّل. إدارة صلاحيات الموظفين متاحة داخل وحدة المصروفات المنشورة على نسخة Dolibarr التجريبية.', 'Saving is disabled in this static review. Manage actual staff access in the deployed staging Dolibarr Office Expenses module.') + '</p>'
    fields += field('staff-person', t('اختيار موظف Dolibarr داخلي', 'Select internal Dolibarr staff'), options=[('', t('-- اختر موظفًا موجودًا --', '-- Select existing staff --')), ('5', 'Moaz · moaz'), ('6', 'rina · rina'), ('7', 'omar · omar'), ('8', 'shan shan · shanshan')])
    fields += '<div class="business-two-fields">' + field('staff-branch', t('الفرع لهذا التعيين', 'Branch for this assignment'), options=[('egypt', t('مصر', 'Egypt')), ('china', t('الصين', 'China'))])
    fields += field('staff-role', t('قالب الصلاحيات', 'Permission template'), value='viewer', options=[('viewer', t('مشاهد', 'Viewer')), ('entry', t('موظف فرع', 'Branch worker')), ('manager', t('مدير فرع', 'Branch manager')), ('custom', t('مخصص', 'Custom'))]) + '</div>'
    fields += field('staff-record-scope', t('نطاق السجلات المسموح بقراءتها', 'Permitted record visibility'), value='branch', options=[('branch', t('سجلات الفرع المعين فقط', 'Assigned branch records only')), ('own', t('سجلات الموظف داخل الفرع فقط', 'Own records within assigned branch only'))])
    fields += '<p class="staff-matrix-help">' + t('كل تعيين يخص فرعًا واحدًا. إذا احتاج الموظف الفرعين، يُضاف تعيين مستقل لكل فرع بصلاحياته.', 'Each assignment belongs to one branch. Staff needing both branches receive a separate assignment and permissions for each.') + '</p>'
    fields += '<p class="staff-matrix-help">' + t('المتاح في التنفيذ الحالي: إنشاء وعرض مسودات المصروفات والعهد. التعديل والحذف وبقية الأقسام لم تُفعّل بعد.',
                                                 'Current implementation supports creating and reading expense/advance drafts. Update, delete and other sections are not enabled yet.') + '</p>'
    fields += '<div class="staff-matrix-scroll"><table class="staff-matrix"><thead><tr><th>' + t('القسم', 'Section') + '</th>'
    for action, label in (('C', t('إنشاء', 'Create')), ('R', t('قراءة', 'Read')), ('U', t('تعديل', 'Update')), ('D', t('إلغاء مسودة', 'Cancel draft'))):
        fields += '<th><bdi>' + action + '</bdi><small>' + label + '</small></th>'
    fields += '</tr></thead><tbody>'
    for key, ar, en, supported in SECTIONS:
        fields += '<tr><th scope="row">' + (ar if lang == 'ar' else en) + '</th>'
        for action in 'CRUD':
            fields += '<td>' + ('<input type="checkbox" data-staff-permission="' + key + ':' + action + '" aria-label="' + esc((ar if lang == 'ar' else en) + ' · ' + action) + '">' if action in supported else '<span aria-label="' + t('غير متاح', 'Unavailable') + '">—</span>') + '</td>'
        fields += '</tr>'
    fields += '</tbody></table></div><p class="staff-matrix-help">' + t('الاعتماد والصرف والترحيل وإدارة الموظفين والصلاحيات صلاحيات مستقلة. تعديل التعيين لا يمنح صلاحيات إدارية عامة في Dolibarr.',
                                                                                  'Approval, payment, posting and access administration are separate permissions. Editing an assignment does not grant general Dolibarr administration.') + '</p>'
    out.append(dialog(lang, 'staff-assignment', t('تعيين الفرع وصلاحيات الموظف', 'Staff branch assignment & permissions'), fields, t('حفظ التعيين والصلاحيات', 'Save assignment & permissions')))
    out.append(dialog(lang, 'staff-remove', t('إزالة وصول الموظف لهذا الفرع', 'Remove staff access to this branch'), field('staff-remove-name', t('الموظف والفرع', 'Staff member & branch'), disabled=True)
                      + '<p class="staff-matrix-help">' + t('سيُلغى تعيين هذا الفرع فقط. حساب Dolibarr يبقى، وسجلاته السابقة وسجل التدقيق لا تُحذف. الإجراء الفعلي معطّل في هذه المعاينة.',
                                                                  'Only this branch assignment would be revoked. The Dolibarr account, historical records and audit trail are retained. Actual removal is disabled in this preview.') + '</p>', t('إلغاء وصول الفرع', 'Revoke branch access')))
    return ''.join(out) + '</main>'


SCRIPT = r'''
'use strict';
(() => {
  const dialog=document.querySelector('#staff-assignment');
  if(!dialog)return;
  const role=dialog.querySelector('#staff-role');
  const applyRole=()=>{
    const matrix={viewer:['expenses:R','advances:R'],entry:['expenses:C','expenses:R','advances:C','advances:R'],manager:['expenses:C','expenses:R','advances:C','advances:R']};
    if(role.value==='custom')return;
    if(role.value==='entry')dialog.querySelector('#staff-record-scope').value='own';
    if(role.value==='manager')dialog.querySelector('#staff-record-scope').value='branch';
    for(const box of dialog.querySelectorAll('[data-staff-permission]'))box.checked=(matrix[role.value]||[]).includes(box.dataset.staffPermission);
  };
  role.addEventListener('change',applyRole);
  dialog.addEventListener('change',event=>{
    const box=event.target.closest('[data-staff-permission]');
    if(!box)return;
    const [section,action]=box.dataset.staffPermission.split(':');
    if(box.checked&&action!=='R')dialog.querySelector('[data-staff-permission="'+section+':R"]').checked=true;
    if(!box.checked&&action==='R')for(const other of dialog.querySelectorAll('[data-staff-permission^="'+section+':"]'))other.checked=false;
    role.value='custom';
  });
  document.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-open="staff-assignment"]');
    if(!trigger)return;
    if(!trigger.hasAttribute('data-prefill')){
      dialog.querySelector('#staff-person').value='';
      dialog.querySelector('#staff-branch').value=document.querySelector('[data-office-picker]').value;
      dialog.querySelector('#staff-record-scope').value='branch';role.value='viewer';
    }
    applyRole();
  });
  applyRole();
  document.querySelector('[data-business-page="users"]')?.setAttribute('data-staff-ready','true');
})();
'''


CSS = '''
.preview-with-erp .staff-native-note{display:flex;align-items:flex-start;gap:12px;background:#eef2ff;border:1px solid #e0e7ff;border-radius:16px;padding:16px;color:#3730a3}
.preview-with-erp .staff-native-note>i{font-size:20px;margin-top:2px;flex-shrink:0}.preview-with-erp .staff-native-note strong{font-size:14px;line-height:20px;font-weight:900}.preview-with-erp .staff-native-note p{font-size:12px;line-height:20px;margin-top:4px;color:#475569}
.preview-with-erp .staff-branch-examples{display:grid;grid-template-columns:minmax(0,1fr);gap:16px}
.preview-with-erp .staff-branch-examples>div{background:white;border:1px solid #e2e8f0cc;border-radius:16px;padding:20px}
.preview-with-erp .staff-branch-examples>div>span{width:40px;height:40px;border-radius:12px;background:#eef2ff;color:#4f46e5;display:grid;place-items:center;margin-bottom:12px;font-size:18px}
.preview-with-erp .staff-branch-examples strong{display:block;font-size:16px;line-height:24px;font-weight:900;color:#0f172a}.preview-with-erp .staff-branch-examples small{display:block;font-size:11px;line-height:16.5px;color:#64748b;margin-top:4px}
.preview-with-erp .staff-office .business-person small{display:block;font-size:10px;line-height:15px;color:#94a3b8;margin-top:4px;white-space:normal;max-width:180px}
.preview-with-erp .staff-office td:nth-child(4){white-space:normal;min-width:96px}
.preview-with-erp .staff-row-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center}.preview-with-erp .staff-row-actions .business-button{white-space:normal;font-size:11px}
.preview-with-erp .staff-boundary-note{font-size:12px;line-height:20px;color:#64748b;display:flex;gap:8px;align-items:flex-start}
.preview-with-erp #staff-assignment{max-width:760px}
.preview-with-erp .staff-matrix-help{font-size:12px;line-height:20px;color:#64748b;margin-top:16px!important}
.preview-with-erp .staff-matrix-scroll{overflow-x:auto;margin-top:12px;border:1px solid #e2e8f0;border-radius:12px}
.preview-with-erp .staff-matrix{width:100%;border-collapse:collapse;font-size:12px;line-height:16px}
.preview-with-erp .staff-matrix th,.preview-with-erp .staff-matrix td{padding:10px;text-align:center;border-bottom:1px solid #f1f5f9}
.preview-with-erp .staff-matrix thead th{background:#f8fafc;font-weight:900;color:#334155;min-width:58px}
.preview-with-erp .staff-matrix th:first-child{display:table-cell;text-align:start;min-width:110px;font-weight:700}
.preview-with-erp .staff-matrix th small{display:block;font-size:10px;line-height:15px;font-weight:500;color:#64748b;margin-top:2px}
.preview-with-erp .staff-matrix input[type=checkbox]{width:18px;height:18px;min-height:0;padding:0;margin:0;accent-color:#4f46e5;cursor:pointer}
@media(min-width:640px){.preview-with-erp .staff-branch-examples{grid-template-columns:repeat(3,minmax(0,1fr))}}
'''
