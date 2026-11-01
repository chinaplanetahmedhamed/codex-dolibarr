#!/usr/bin/env python3
"""Throwaway design preview: native ERP navigation above the existing Finens UI.

No native session, menu registration, data storage or accounting integration.
Uses the existing synthetic static renderer; only preview files are changed.
"""
import argparse
import hashlib
import html
import json
from pathlib import Path
import re
import subprocess
import preview_dashboard_design as dashboard_design
import preview_original_sidebar as original_sidebar
import preview_treasury_design as treasury_design
import preview_cash_receipts_design as cash_receipts_design
import preview_business_pages as business_pages
import preview_staff_access_design as staff_access_design

ROOT = Path(__file__).resolve().parents[3]
ITEMS = (
    ('Home','house'),('Members','user'),('Third parties','building'),
    ('Products | Services','cube'),('MRP','cubes'),('Projects','diagram-project'),
    ('Commerce','briefcase'),('Billing | Payment','file-invoice-dollar'),
    ('Office Expenses','receipt'),('Banks | Cash','building-columns'),
    ('Accounting','magnifying-glass-dollar'),('HRM','user-group'),
    ('Documents','folder-open'),('Agenda','calendar-days'),('Tickets','ticket'),
    ('Tools','screwdriver-wrench'),('Team Tasks','list-check'),
    ('Customer Center','address-card'),('Shipping Prices','truck-fast'),
    ('Websites','earth-americas'),('Kontava','circle-nodes'),
)

CSS = '''
/* Reference: backup ERP #id-top, rgb(38,60,92), 52px, native Arial menu. */
.erp-topbar{position:sticky;top:0;z-index:40;display:flex;align-items:stretch;gap:8px;height:54px;background:#263c5c;border-bottom:1px solid #63718a;color:#fff;direction:ltr;font:12px/1.2 Arial,Tahoma,sans-serif}
.erp-module-nav{display:flex;align-items:stretch;flex:1;min-width:0;overflow-x:auto;overscroll-behavior-x:contain;scrollbar-width:thin;scrollbar-color:#a6b5c9 #263c5c}
.erp-module{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;flex:0 0 58px;min-width:58px;max-width:64px;padding:7px 4px 6px;color:#fff;background:transparent;border:0;border-radius:0;font:12px/1 Arial,Tahoma,sans-serif;text-align:center;text-decoration:none;cursor:default}
.erp-module .fa-solid{font-size:14px;line-height:14px;color:#fff}
.erp-module>span{display:block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.erp-module[aria-disabled=true]{opacity:.92}
.erp-module.is-current{flex-basis:116px;min-width:116px;max-width:116px;background:#3b5272;font-weight:700;cursor:pointer;box-shadow:inset 0 -2px #fff}
.erp-module.is-current:after{content:"";position:absolute;bottom:0;border-inline:5px solid transparent;border-bottom:5px solid #fff}
.erp-module.is-current:hover{background:#496482}.erp-module:focus-visible{outline:2px solid #fff;outline-offset:-4px}
.erp-user-tools{display:flex;align-items:center;gap:12px;padding:0 12px;white-space:nowrap;flex-shrink:0;font-size:12px}
.erp-user-tools .erp-avatar{display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#344e70}
.erp-user-tools .fa-solid{font-size:14px}.erp-user-tools small{font:11px Arial,Tahoma,sans-serif;color:#e6edf8}
.preview-with-erp .app-layout{min-height:calc(100dvh - 54px)}
.preview-with-erp .app-navbar{top:54px}
.preview-with-erp #office-workspace{scroll-margin-top:140px}
@media(max-width:767px){
 .erp-topbar{gap:0}.erp-module{flex-basis:58px;min-width:58px;font-size:11px}
 .erp-module.is-current{order:-1;position:sticky;left:0;z-index:2;flex-basis:112px;min-width:112px;max-width:112px;background:#3b5272}
 .erp-user-tools{padding:0 8px;gap:0}.erp-user-tools>i,.erp-user-tools>small,.erp-user-tools>span:not(.erp-avatar){display:none}
 .preview-with-erp .app-navbar{position:relative;top:auto}
}
'''

MENU = (
    ('main', 'القائمة الرئيسية', 'Main menu', (
        ('overview', 'لوحة التحكم', 'Dashboard', 'chart-pie', '#94a3b8'),
    )),
    ('finance', 'المالية والحسابات', 'Finance & accounts', (
        ('treasury', 'الخزنة والإيداعات', 'Treasury & deposits', 'vault', '#34d399'),
        ('advances', 'العهد والمرتجعات', 'Advances & returns', 'hand-holding-dollar', '#6ee7b7'),
        ('receipts', 'إيصالات الاستلام (سند قبض)', 'Cash receipt vouchers', 'hand-holding-dollar', '#6ee7b7'),
        ('expenses', 'المصروفات', 'Expenses', 'receipt', '#fb7185'),
        ('categories', 'بنود المصروفات', 'Expense categories', 'tags', '#fbbf24'),
        ('reports', 'التقارير المالية', 'Financial reports', 'file-invoice-dollar', '#60a5fa'),
    )),
    ('hr', 'الموارد البشرية', 'Human resources', (
        ('employees', 'دليل الموظفين', 'Employee directory', 'user-tie', '#c084fc'),
        ('payroll', 'مسيرات الرواتب', 'Payroll', 'money-check-dollar', '#2dd4bf'),
    )),
    ('company', 'إدارة الشركة', 'Company management', (
        ('settings', 'إعدادات الشركة والمقر', 'Company & office settings', 'building', '#94a3b8'),
        ('users', 'مستخدمي الشركة', 'Company users', 'users', '#94a3b8'),
    )),
)
DESTINATIONS = {item[0]: item for _, _, _, group in MENU for item in group}
EXISTING = ('expenses', 'overview', 'advances')

MENU_CSS = '''
/* Preview-only Finens navigation; product runtime and permissions are untouched. */
body.finens-app{margin:0}
.preview-with-erp .office-expenses-app{font-family:Cairo,ui-sans-serif,system-ui,sans-serif}
.preview-with-erp .navigation-drawer{padding:0;overflow:hidden;max-width:90vw}
.preview-with-erp .navigation-drawer[open]{display:flex;flex-direction:column}
.preview-with-erp .navigation-drawer>nav{flex:1;min-height:0;overscroll-behavior:contain}
.preview-with-erp .pending-panel{padding:32px;background:white;border:1px solid #e2e8f0;border-radius:16px;max-width:720px}
.preview-with-erp .pending-panel h2{font-size:20px;margin:16px 0 8px;color:#0f172a}
.preview-with-erp .pending-panel p{font-size:14px;line-height:1.8;color:#475569;margin:0 0 24px}
.preview-with-erp .pending-icon{display:grid;place-items:center;width:48px;height:48px;border-radius:12px;background:#eef2ff;color:#4f46e5;font-size:22px}
.preview-with-erp .bottom-nav>a[data-destination=treasury][aria-current=page]{color:#059669}
.preview-with-erp .bottom-nav>a[data-destination=expenses][aria-current=page]{color:#e11d48}
.preview-with-erp .bottom-nav>a[data-destination=reports][aria-current=page]{color:#2563eb}
@media(max-width:767px){
 .preview-with-erp .app-sidebar{display:none}
 .preview-with-erp .pending-panel{padding:24px}
 .preview-with-erp .bottom-nav>a{font-size:10px}
 .preview-with-erp .navigation-drawer{width:288px;height:100dvh}
}
'''


def link(lang, view):
    return lang + ('' if view == 'expenses' else '-' + view) + '.html'


def glyph(name):
    return '<i class="fa-solid fa-' + name + '" aria-hidden="true"></i>'


def navigation(lang, view):
    ar = lang == 'ar'
    label = 'القائمة الرئيسية' if ar else 'Main menu'
    sidebar, nav, brand, account = original_sidebar.render(lang, view)
    close = '<button type="button" class="icon-button" data-close aria-label="' + ('إغلاق القائمة' if ar else 'Close menu') + '">' + glyph('xmark') + '</button>'
    drawer = f'<dialog id="navigation-drawer" class="navigation-drawer" data-original-sidebar aria-label="{label}">' + brand.replace('</a>', '</a>' + close) + nav + account + '</dialog>'
    bottom = f'<nav class="bottom-nav" aria-label="{label}">'
    for destination, arabic, english, icon in (
        ('overview', 'الرئيسية', 'Home', 'chart-pie'),
        ('treasury', 'الخزنة', 'Treasury', 'vault'),
        ('add', '', '', 'plus'),
        ('expenses', 'المصروفات', 'Expenses', 'receipt'),
        ('reports', 'التقارير', 'Reports', 'file-invoice-dollar'),
    ):
        if destination == 'add':
            # A design action opens the existing local read-only expense form.
            bottom += '<button class="quick-add" type="button" data-open="expense-dialog" aria-label="' + ('تسجيل مصروف جديد' if ar else 'Record new expense') + '">' + glyph(icon) + '</button>'
        else:
            current = ' aria-current="page"' if destination == view else ''
            bottom += f'<a href="{link(lang,destination)}" data-destination="{destination}"{current}>' + glyph(icon) + '<span>' + (arabic if ar else english) + '</span></a>'
    return sidebar, drawer, bottom + '</nav>'


def apply_menu(text, lang, view):
    sidebar, drawer, bottom = navigation(lang, view)
    for pattern, replacement in (
        (r'<aside class="app-sidebar".*?</aside>', sidebar),
        (r'<dialog id="navigation-drawer".*?</dialog>', drawer),
        (r'<nav class="bottom-nav".*?</nav>', bottom),
    ):
        text, count = re.subn(pattern, lambda _: replacement, text, count=1, flags=re.S)
        assert count == 1, pattern
    profile = 'مراجعة التصميم · جميع الأقسام' if lang == 'ar' else 'Design review · All sections'
    text = text.replace('حساب الموظف · مسودات المكتب', profile).replace('Employee account · Office drafts', profile)
    alternate = link('en' if lang == 'ar' else 'ar', view)
    text, count = re.subn(r'(<a class="language-link" href=")[^"]+("[^>]*>)', lambda match: match[1] + alternate + match[2], text, count=1)
    assert count == 1
    return text


def pending_main(text, lang, view):
    ar = lang == 'ar'
    _, arabic, english, icon, _ = DESTINATIONS[view]
    title = html.escape(arabic if ar else english)
    heading = 'هذه الصفحة لم تُنفّذ بعد' if ar else 'This page is not implemented yet'
    copy = 'هذه معاينة لموقع الصفحة وشكل القائمة فقط. الوظائف والبيانات ستُضاف في مرحلة التنفيذ.' if ar else 'This preview shows the page location and menu design. Its features and data will be added during implementation.'
    notice = 'معاينة تصميم القائمة · بيانات تجريبية · الحفظ معطّل' if ar else 'Menu design preview · Sample data · Saving disabled'
    # Keep the existing read-only quick-entry dialog for the central + action.
    dialog = re.search(r'<dialog class="expense-dialog".*?</dialog>', text, flags=re.S)
    assert dialog
    advance_link = ('<a class="button button-muted" href="' + link(lang, 'advances') + '">' + ('العهد والمرتجعات' if ar else 'Advances & returns') + '</a>') if view == 'treasury' else ''
    content = '<main id="office-workspace"><div class="page-heading"><div><h1>' + title + '</h1></div></div><p class="environment-note">' + glyph('circle-info') + notice + '</p><section class="pending-panel"><span class="pending-icon">' + glyph(icon) + '</span><h2>' + heading + '</h2><p>' + copy + '</p><a class="button button-brand" href="' + link(lang, 'expenses') + '">' + ('عرض معاينة المصروفات' if ar else 'View expense preview') + '</a>' + advance_link + '</section>' + dialog.group() + '</main>'
    text, count = re.subn(r'<main id="office-workspace">.*?</main>', lambda _: content, text, count=1, flags=re.S)
    assert count == 1
    return re.sub(r'<title>.*?</title>', '<title>' + title + ' · ChinaPlanetOS</title>', text, count=1)


def bar(lang):
    output=['<header class="erp-topbar" aria-label="ChinaPlanetOS" dir="ltr" lang="en"><nav class="erp-module-nav" aria-label="ERP modules">']
    for label,icon in ITEMS:
        name=html.escape(label)
        if label=='Office Expenses':
            output.append(f'<a class="erp-module is-current" aria-current="page" href="{lang}-overview.html" title="{name}"><i class="fa-solid fa-{icon}" aria-hidden="true"></i><span>{name}</span></a>')
        else:
            output.append(f'<button class="erp-module" type="button" disabled aria-disabled="true" title="{name} · Design preview"><i class="fa-solid fa-{icon}" aria-hidden="true"></i><span>{name}</span></button>')
    output.append('</nav><div class="erp-user-tools" aria-label="Preview account"><i class="fa-solid fa-star" aria-hidden="true"></i><i class="fa-solid fa-circle-question" aria-hidden="true"></i><small>24.0.1</small><span class="erp-avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span>Preview</span></div></header>')
    return ''.join(output)


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('output',type=Path)
    args=parser.parse_args()
    output=args.output
    if not output.is_absolute() or output.is_symlink():
        raise ValueError('Use an absolute owned preview directory.')
    marker=output/'.m119-topbar-design'
    if output.exists() and any(output.iterdir()) and not marker.is_file():
        raise ValueError('Refusing to overwrite a directory without this preview marker.')
    output.mkdir(mode=0o700,parents=True,exist_ok=True)
    marker.write_text('M119 synthetic throwaway topbar design preview only\n')
    subprocess.run(['php',str(Path(__file__).with_name('generate_preview.php')),str(output)],check=True,cwd=ROOT)
    (output/'dashboard-preview.js').write_text(dashboard_design.SCRIPT)
    (output/'treasury-preview.js').write_text(treasury_design.SCRIPT)
    (output/'cash-receipts-preview.js').write_text(cash_receipts_design.SCRIPT)
    (output/'business-preview.js').write_text(business_pages.SCRIPT)
    (output/'staff-access-preview.js').write_text(staff_access_design.SCRIPT)
    manifest={'kind':'synthetic-static-design-preview','native_menu_installed':False,'posting_enabled':False,'dashboard_ledger_binding':False,'preview_assets':{'dashboard-preview.js':hashlib.sha256(dashboard_design.SCRIPT.encode()).hexdigest()},'reference_header_background':'#263c5c','implemented_preview_views':list(EXISTING),'pending_views':[view for view in DESTINATIONS if view not in EXISTING],'files':{}}
    manifest['designed_preview_views'] = [*EXISTING, 'treasury', 'receipts', *business_pages.RENDERERS]
    for designed_view in ('treasury', 'receipts', *business_pages.RENDERERS):
        manifest['pending_views'].remove(designed_view)
    manifest['preview_assets']['treasury-preview.js'] = hashlib.sha256(treasury_design.SCRIPT.encode()).hexdigest()
    manifest['preview_assets']['cash-receipts-preview.js'] = hashlib.sha256(cash_receipts_design.SCRIPT.encode()).hexdigest()
    manifest['preview_assets']['business-preview.js'] = hashlib.sha256(business_pages.SCRIPT.encode()).hexdigest()
    manifest['preview_assets']['staff-access-preview.js'] = hashlib.sha256(staff_access_design.SCRIPT.encode()).hexdigest()
    for lang in ('ar','en'):
        templates={view:(output/link(lang,view)).read_text() for view in EXISTING}
        for view in DESTINATIONS:
            path=output/link(lang,view)
            text=templates[view if view in EXISTING else 'expenses']
            assert text.count('<div class="app-layout">')==1
            text=text.replace('<body class="finens-app">','<body class="finens-app preview-with-erp">')
            text=text.replace('<div class="app-layout">',bar(lang)+'<div class="app-layout">',1)
            text=text.replace('<header class="app-navbar">','<header class="app-navbar" role="region" aria-label="Office expenses toolbar">',1)
            text=text.replace('<main>','<main id="office-workspace">',1)
            text=apply_menu(text,lang,view)
            if view not in EXISTING:
                text=pending_main(text,lang,view)
            notice='معاينة تصميم الشريط العلوي والقائمة · بيانات تجريبية · الحفظ معطّل' if lang=='ar' else 'Top bar and menu design preview · Sample data · Saving disabled'
            text,count=re.subn(r'<p class="environment-note">.*?</p>','<p class="environment-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> '+notice+'</p>',text,count=1,flags=re.S)
            assert count==1
            text,count=re.subn(r'<header class="app-navbar".*?</header>',lambda _:dashboard_design.toolbar(lang,view),text,count=1,flags=re.S)
            assert count==1
            renderers = {'overview': dashboard_design.dashboard, 'treasury': treasury_design.treasury, 'receipts': cash_receipts_design.receipts, **business_pages.RENDERERS}
            if view in renderers:
                expense_dialog=re.search(r'<dialog class="expense-dialog".*?</dialog>',text,flags=re.S)
                assert expense_dialog
                renderer = renderers[view]
                content=renderer(lang).replace('</main>',expense_dialog.group()+'</main>')
                text,count=re.subn(r'<main id="office-workspace">.*?</main>',lambda _:content,text,count=1,flags=re.S)
                assert count==1
            text=text.replace('<dialog id="navigation-drawer"',treasury_design.deposit_dialog(lang)+'<dialog id="navigation-drawer"',1)
            receipts_assets = ('<style>' + cash_receipts_design.CSS + '</style><script src="cash-receipts-preview.js" defer></script>') if view == 'receipts' else ''
            business_assets = ('<style>' + business_pages.CSS + '</style><script src="business-preview.js" defer></script>') if view in business_pages.RENDERERS else ''
            if view == 'users':
                business_assets += '<style>' + staff_access_design.CSS + '</style><script src="staff-access-preview.js" defer></script>'
            text=text.replace('</head>','<meta name="robots" content="noindex,nofollow"><style>'+CSS+MENU_CSS+dashboard_design.CSS+treasury_design.CSS+original_sidebar.scoped_styles()+'</style><script src="dashboard-preview.js" defer></script><script src="treasury-preview.js" defer></script>'+receipts_assets+business_assets+'</head>',1)
            path.write_text(text)
            manifest['files'][path.name]=hashlib.sha256(text.encode()).hexdigest()
        for office in cash_receipts_design.VOUCHERS:
            for online in (False, True):
                filename = cash_receipts_design.receipt_link(lang, office, online).split('?')[0]
                paper = cash_receipts_design.print_page(lang, office, online)
                (output/filename).write_text(paper)
                manifest['files'][filename] = hashlib.sha256(paper.encode()).hexdigest()
    (output/'topbar-manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
    print(f'22 bilingual menu previews and 8 sample voucher previews generated; {len(manifest["pending_views"])} destinations per language remain pending. No ERP menu or runtime changed.')


if __name__=='__main__':main()
