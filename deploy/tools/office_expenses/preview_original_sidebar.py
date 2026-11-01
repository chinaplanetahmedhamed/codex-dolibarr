"""Render the original Finens sidebar template in an isolated synthetic context."""
import hashlib
import html
from pathlib import Path
import re
import subprocess

REFERENCE = Path(__file__).with_name('reference')
SOURCE_HASH = 'd030f5a4afbc094d2e3424ad606e1f29ea0e5672d55e211afc7276b6115cee46'
PAGES = {
    'overview': 'dashboard.php', 'treasury': 'treasury.php', 'advances': 'treasury.php',
    'receipts': 'receipts.php', 'expenses': 'expenses.php', 'categories': 'expense_categories.php',
    'reports': 'reports.php', 'employees': 'employees.php', 'payroll': 'payroll.php',
    'settings': 'settings.php', 'users': 'users.php',
}
TRANSLATIONS = {
    'فينانس ساس': 'Finens SaaS', 'لوحة تحكم الشركات': 'Company dashboard',
    'القائمة الرئيسية': 'Main menu', 'لوحة التحكم': 'Dashboard',
    'المالية والحسابات': 'Finance & accounts', 'الخزنة والإيداعات': 'Treasury & deposits',
    'إيصالات الاستلام (سند قبض)': 'Cash receipt vouchers', 'سجل المصروفات': 'Expense register',
    'بنود المصروفات': 'Expense categories', 'التقارير المالية': 'Financial reports',
    'الموارد البشرية والرواتب': 'Human resources & payroll', 'دليل الموظفين': 'Employee directory',
    'مسيرات الرواتب': 'Payroll', 'إدارة الشركة': 'Company management',
    'إعدادات الشركة والمقر': 'Company & office settings', 'مستخدمي الشركة': 'Company users',
}

PHP_CONTEXT = r'''
function isSuperAdmin(){return false;}
function isCompanyAdmin(){return true;}
function hasModule($name){return true;}
define('APP_SHORT_NAME','Finens');
$currentUser=array('active_company_id'=>1,'name'=>$argv[3],'username'=>'preview');
$_SERVER['PHP_SELF']=$argv[2];
include $argv[1];
'''


def page_link(lang, view):
    return lang + ('' if view == 'expenses' else '-' + view) + '.html'


def render(lang, view):
    source = REFERENCE / 'finens-sidebar.php'
    if hashlib.sha256(source.read_bytes()).hexdigest() != SOURCE_HASH:
        raise ValueError('Original sidebar reference changed; inspect it before rendering.')
    # This template only: synthetic helper functions, no app bootstrap/database/config.
    result = subprocess.run(['php', '-r', PHP_CONTEXT, str(source), PAGES[view],
                             'حساب مراجعة التصميم' if lang == 'ar' else 'Design review account'],
                            check=True, capture_output=True, text=True)
    match = re.search(r'<aside id="app-sidebar".*?</aside>', result.stdout, re.S)
    if not match:
        raise ValueError('Original sidebar did not render.')
    sidebar = match.group()
    sidebar = re.sub(r'\s*<a\b(?=[^>]*href="logout.php")[^>]*>.*?</a>', '', sidebar, flags=re.S)
    # The original hosted revision adds this exact entry to the older source copy.
    setting = '''<a href="settings.php" class="flex items-center px-3.5 py-2.5 text-sm font-semibold rounded-xl transition text-slate-300 hover:bg-slate-800/80 hover:text-white">
                    <i class="fa-solid fa-building-gear w-6 text-center ml-2.5 text-indigo-400"></i>
                    <span>إعدادات الشركة والمقر</span>
                </a>'''
    if view == 'settings':
        setting = setting.replace('text-slate-300 hover:bg-slate-800/80 hover:text-white', 'bg-brand-600 text-white shadow-md').replace('text-indigo-400', 'text-white')
    sidebar = sidebar.replace('<a href="users.php"', setting + '\n<a href="users.php"', 1)
    sidebar = sidebar.replace('<aside ', '<aside data-original-sidebar ', 1)
    sidebar = sidebar.replace('class="no-print ', 'aria-label="' + ('القائمة الرئيسية' if lang == 'ar' else 'Main menu') + '" class="no-print ', 1)
    for destination, php_file in PAGES.items():
        if destination == 'advances':
            continue
        current = destination == view or (view == 'advances' and destination == 'treasury')
        sidebar = sidebar.replace(f'href="{php_file}"', f'href="{page_link(lang,destination)}"' + (' aria-current="page"' if current else ''))
    if lang == 'en':
        # Mirror the original RTL spacing for the translated LTR preview.
        sidebar = sidebar.replace(' space-x-reverse', '').replace(' ml-2.5', ' mr-2.5')
        for arabic, english in TRANSLATIONS.items():
            sidebar = sidebar.replace(arabic, html.escape(english))
    nav = re.search(r'<nav\b.*?</nav>', sidebar, re.S).group()
    brand = re.search(r'<div class="p-5\b.*?</a>\s*</div>', sidebar, re.S).group()
    footer = re.search(r'<!-- Sidebar Footer -->(.*?)</aside>', sidebar, re.S).group(1)
    return sidebar, nav, brand, footer


def scoped_styles():
    css = (REFERENCE / 'finens-sidebar.utilities.css').read_text()
    css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
    scope = '.preview-with-erp [data-original-sidebar]'

    def transform(content):
        result = []
        position = 0
        while position < len(content):
            start = content.find('{', position)
            if start < 0:
                break
            selector = content[position:start].strip()
            depth, end = 1, start + 1
            while depth:
                if content[end] == '{': depth += 1
                elif content[end] == '}': depth -= 1
                end += 1
            body = content[start + 1:end - 1]
            if selector.startswith('@media'):
                result.append(selector + '{' + transform(body) + '}')
            else:
                selectors = []
                for part in selector.split(','):
                    part = part.strip()
                    if part in ('::before', '::after'):
                        selectors += [scope + part, scope + ' *' + part]
                    else:
                        selectors += [scope + ':is(' + part + ')', scope + ' ' + part]
                result.append(','.join(selectors) + '{' + body + '}')
            position = end
        return '\n'.join(result)

    # Only the ERP frame offset and inherited font need integration rules. Link
    # spacing, colours, typography, borders and states come from the source CSS.
    return transform(css) + '''
.preview-with-erp [data-original-sidebar]{font-family:ui-sans-serif,system-ui,sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";font-size:16px;line-height:1.5;text-size-adjust:100%;tab-size:4;font-feature-settings:normal;font-variation-settings:normal;-webkit-tap-highlight-color:transparent}
.preview-with-erp #app-sidebar[data-original-sidebar]{position:sticky;top:54px;height:calc(100dvh - 54px);min-height:calc(100dvh - 54px)}
.preview-with-erp [data-original-sidebar]>nav{min-height:0}
.preview-with-erp [data-original-sidebar] .mr-2\\.5{margin-right:0.625rem}
.preview-with-erp [data-original-sidebar]::-webkit-scrollbar,.preview-with-erp [data-original-sidebar] *::-webkit-scrollbar{width:6px;height:6px}
.preview-with-erp [data-original-sidebar]::-webkit-scrollbar-track,.preview-with-erp [data-original-sidebar] *::-webkit-scrollbar-track{background:#f1f5f9}
.preview-with-erp [data-original-sidebar]::-webkit-scrollbar-thumb,.preview-with-erp [data-original-sidebar] *::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:4px}
.preview-with-erp [data-original-sidebar]::-webkit-scrollbar-thumb:hover,.preview-with-erp [data-original-sidebar] *::-webkit-scrollbar-thumb:hover{background:#94a3b8}
'''
