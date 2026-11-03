<?php
require_once __DIR__.'/../chinaplanetcustomercenter/lib/ui.lib.php';
/**
 * China Planet Kanban - Board page.
 * Internal staff: full board across all customers, drag & drop enabled.
 * External users (customers): only their own visible cards, view-only.
 */
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once __DIR__.'/lib/main.lib.php';

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once __DIR__.'/class/cpkanbancard.class.php';
require_once __DIR__.'/class/cpkanbancolumn.class.php';
require_once __DIR__.'/lib/cpkanban.lib.php';
require_once __DIR__.'/lib/projectsync.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';

$langs->loadLangs(array("companies", "projects", "other"));

// Security
if (empty($user->id)) {
    accessforbidden();
}

$ctx = cpkanban_user_context($user);
$is_external = $ctx['is_external'];
$socid = $ctx['socid'];

// An external user with no linked company sees nothing
if ($is_external && $socid <= 0) {
    accessforbidden("No company linked to this user.");
}

// Customer Center has one canonical home. Keep this route for internal operations only.
if ($is_external) {
    header('Location: '.dol_buildpath('/chinaplanetcustomercenter/board.php', 1));
    exit;
}

$canwrite = (!$is_external && ($user->hasRight('cpkanban', 'write') || $user->hasRight('chinaplanetcustomercenter', 'write') || !empty($user->admin)));

if ($is_external && $socid > 0) {
    $syncSql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet WHERE entity IN (".getEntity('project').") AND fk_soc = ".(int) $socid." ORDER BY rowid ASC";
    $syncRes = $db->query($syncSql);
    if ($syncRes) {
        while ($syncRow = $db->fetch_object($syncRes)) {
            $project = new Project($db);
            if ($project->fetch((int) $syncRow->rowid) > 0) {
                cpcc_project_sync_kanban_card($db, $project, $user, 'modify');
            }
        }
    }
}

// Admin-side filters (ignored for customers)
$filters = array();
if (!$is_external) {
    if (GETPOST('search_soc', 'int'))    $filters['fk_soc'] = GETPOST('search_soc', 'int');
    if (GETPOST('search_user', 'int'))   $filters['fk_user_assign'] = GETPOST('search_user', 'int');
    if (GETPOST('search_pri', 'alpha'))  $filters['priority'] = GETPOST('search_pri', 'alpha');
    if (GETPOST('search_text', 'alpha')) $filters['search'] = GETPOST('search_text', 'alpha');
}

// Load columns. Customers see only customer-visible columns, but we also load all columns
// so cards in internal columns can be folded into a visible customer column without changing admin data.
$all_columns = CpKanbanColumn::getAll($db, false, true);
$columns = $is_external ? CpKanbanColumn::getAll($db, true, true) : $all_columns;
$board   = CpKanbanCard::getBoard($db, $socid, $filters);
if ($is_external) {
    $board = cpkanban_fold_customer_board($board, $columns, $all_columns);
}
$customerStats = $is_external ? cpkanban_customer_center_stats($db, $socid) : array();

llxHeader('', cpcc_ui_english_label('Customer Projects', 'Customer Projects / مشاريع العملاء'), '', '', 0, 0,
    array(cpcc_admin_shell_asset('/custom/cpkanban/js/board.js')),
    array('/custom/cpkanban/css/board.css', cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'), cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
if ($is_external) {
    cpcc_portal_navigation($user, 'dashboard');
} else {
    cpcc_admin_shell_open('dashboard', cpcc_ui_english_label('Operations Kanban', 'Operations Kanban / لوحة كانبان'), cpcc_ui_english_label('Internal operations task board.', 'لوحة مهام التشغيل الداخلية'), 'fa-columns');
}

// CSRF token for AJAX moves
$ajaxtoken = newToken();
?>
<div class="cpk-wrap" dir="<?php echo cpcc_ui_english_label('ltr', 'rtl'); ?>"
     data-ajax="<?php echo dol_buildpath('/cpkanban/ajax/move.php', 1); ?>"
     data-token="<?php echo $ajaxtoken; ?>"
     data-canwrite="<?php echo $canwrite ? 1 : 0; ?>">

<?php
if ($is_external) {
    $custName = cpcc_ui_english_label('China Planet customer', 'عميل كوكب الصين');
    if ($socid > 0) {
        $rsoc = $db->query("SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".(int) $socid);
        if ($rsoc && ($osoc = $db->fetch_object($rsoc))) $custName = $osoc->nom;
    }
    print '<div class="cpk-customer-hero">';
    print cpcc_ui_english_label('<div class="cpk-hero-brand"><span class="cpk-hero-dot">🌏</span><div><h2>Customer Center — ', '<div class="cpk-hero-brand"><span class="cpk-hero-dot">🌏</span><div><h2>مركز العميل — ').dol_escape_htmltag($custName).cpcc_ui_english_label('</h2><p>View your projects, shipments, invoices, orders and balance. Open a project card for details, full tracking and advanced Vision System records.</p></div></div>', '</h2><p>من هنا تتابع مشاريعك، الشحنات، الفواتير، الطلبات، والرصيد. اضغط على أي بطاقة مشروع لفتح التفاصيل، ثم انتقل منها إلى التتبع الكامل أو السجلات المتقدمة داخل Vision System.</p></div></div>');
    print '<div class="cpk-hero-stats">';
    print '<div class="cpk-hero-stat"><b>'.(int)$customerStats['projects'].cpcc_ui_english_label('</b><span>Visible projects</span></div>', '</b><span>مشاريع ظاهرة</span></div>');
    print '<div class="cpk-hero-stat"><b>'.(int)$customerStats['shipments'].cpcc_ui_english_label('</b><span>Active shipments</span></div>', '</b><span>شحنات نشطة</span></div>');
    print '<div class="cpk-hero-stat"><b>'.(int)$customerStats['invoices_open'].cpcc_ui_english_label('</b><span>Open invoices</span></div>', '</b><span>فواتير مفتوحة</span></div>');
    print '</div></div>';

    print '<div class="cpk-center-grid">';
    print '<a class="cpk-center-card" style="--tile-color:#C0392B" href="'.dol_buildpath('/chinaplanetcustomercenter/projects.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">🗂️</span><span class="cpk-tile-num">'.(int)$customerStats['projects'].cpcc_ui_english_label('</span></div><h3>My projects</h3><p>Your linked projects and their synchronization status.</p><span class="cpk-arrow">View projects →</span></a>', '</span></div><h3>مشاريعي</h3><p>عرض المشاريع الحقيقية المرتبطة بحسابك مع حالة المزامنة.</p><span class="cpk-arrow">عرض المشاريع ←</span></a>');
    print '<a class="cpk-center-card" style="--tile-color:#2c3e50" href="'.dol_buildpath('/chinaplanetlogistics/list.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">🚢</span><span class="cpk-tile-num">'.(int)$customerStats['shipments'].cpcc_ui_english_label('</span></div><h3>My shipments</h3><p>ETA, free time and status for your containers.</p><span class="cpk-arrow">View shipments →</span></a>', '</span></div><h3>شحناتي</h3><p>ETA، الوقت الحر، وحالة الحاويات الخاصة بك فقط.</p><span class="cpk-arrow">عرض الشحنات ←</span></a>');
    print '<a class="cpk-center-card" style="--tile-color:#D4A017" href="'.dol_buildpath('/chinaplanetcustomercenter/invoices.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">🧾</span><span class="cpk-tile-num">'.(int)$customerStats['invoices_open'].cpcc_ui_english_label('</span></div><h3>Invoices</h3><p>Approximate outstanding: ', '</span></div><h3>الفواتير</h3><p>المفتوح تقريباً: ').cpkanban_money($customerStats['invoices_total']).cpcc_ui_english_label('</p><span class="cpk-arrow">View invoices →</span></a>', '</p><span class="cpk-arrow">عرض الفواتير ←</span></a>');
    print '<a class="cpk-center-card" style="--tile-color:#16a085" href="'.dol_buildpath('/chinaplanetcustomercenter/orders.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">📦</span><span class="cpk-tile-num">'.(int)$customerStats['orders'].cpcc_ui_english_label('</span></div><h3>Orders</h3><p>Sales orders linked to your company account.</p><span class="cpk-arrow">View orders →</span></a>', '</span></div><h3>الطلبيات</h3><p>طلبات البيع المرتبطة بحساب شركتك.</p><span class="cpk-arrow">عرض الطلبيات ←</span></a>');
    print '<a class="cpk-center-card" style="--tile-color:#8e44ad" href="'.dol_buildpath('/chinaplanetcustomercenter/balance.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">💰</span><span class="cpk-tile-num">'.cpkanban_money($customerStats['invoices_total']).cpcc_ui_english_label('</span></div><h3>Balance</h3><p>Open and paid account summary.</p><span class="cpk-arrow">View balance →</span></a>', '</span></div><h3>الرصيد</h3><p>ملخص الحسابات المفتوحة والمدفوعة.</p><span class="cpk-arrow">عرض الرصيد ←</span></a>');
    print '<a class="cpk-center-card" style="--tile-color:#2980b9" href="'.dol_buildpath('/chinaplanetcustomercenter/documents.php', 1).cpcc_ui_english_label('"><div class="cpk-tile-top"><span class="cpk-tile-ic">📄</span><span class="cpk-tile-num">↗</span></div><h3>Documents</h3><p>Files and documents linked to projects and shipments.</p><span class="cpk-arrow">View files →</span></a>', '"><div class="cpk-tile-top"><span class="cpk-tile-ic">📄</span><span class="cpk-tile-num">↗</span></div><h3>المستندات</h3><p>ملفات ومستندات مرتبطة بالمشاريع والشحنات.</p><span class="cpk-arrow">عرض الملفات ←</span></a>');
    print '</div>';

    print cpcc_ui_english_label('<div class="cpk-banner">🔒 You can only see your company data. Internal columns, notes and files are hidden. The project board is read-only.</div>', '<div class="cpk-banner">🔒 أنت ترى بيانات شركتك فقط. الأعمدة والملاحظات والملفات الداخلية غير ظاهرة لك. لوحة المشاريع عرض فقط.</div>');
    print cpcc_ui_english_label('<div id="cpk-project-board" class="cpk-section-head"><div><h2>My projects on Kanban</h2><p>Open a card for details and linked shipments, invoices and orders.</p></div><span class="cpk-viewonly">🔒 Read-only — cards cannot be moved</span></div>', '<div id="cpk-project-board" class="cpk-section-head"><div><h2>مشاريعي على لوحة Kanban</h2><p>اضغط على أي كارت لفتح تفاصيله وروابط الشحنات والفواتير والطلبيات.</p></div><span class="cpk-viewonly">🔒 عرض فقط — لا يمكنك تحريك البطاقات</span></div>');
} else {
    print load_fiche_titre(cpcc_ui_english_label('Customer projects board', 'لوحة مشاريع العملاء'), '', 'project');
    // Admin toolbar: filter + new card
    print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" class="cpk-toolbar">';
    print '<input type="text" name="search_text" class="flat" value="'.dol_escape_htmltag(GETPOST('search_text','alpha')).cpcc_ui_english_label('" placeholder="Search by title">', '" placeholder="بحث بالعنوان">');
    print ' <input type="text" name="search_soc" class="flat" value="'.dol_escape_htmltag(GETPOST('search_soc','int')).cpcc_ui_english_label('" placeholder="Customer ID">', '" placeholder="معرّف العميل">');
    print cpcc_ui_english_label(' <select name="search_pri" class="flat"><option value="">All priorities</option>', ' <select name="search_pri" class="flat"><option value="">كل الأولويات</option>');
    foreach (array('high'=>cpcc_ui_english_label('High', 'عالية'),'med'=>cpcc_ui_english_label('Medium', 'متوسطة'),'low'=>cpcc_ui_english_label('Low', 'منخفضة')) as $k=>$v) {
        $sel = (GETPOST('search_pri','alpha') === $k) ? ' selected' : '';
        print '<option value="'.$k.'"'.$sel.'>'.$v.'</option>';
    }
    print '</select> ';
    print cpcc_ui_english_label('<input type="submit" class="button small" value="Filter">', '<input type="submit" class="button small" value="تصفية">');
    if ($canwrite) {
        print ' <a class="butActionNew" href="'.dol_buildpath('/cpkanban/card.php?action=create', 1).cpcc_ui_english_label('">＋ New card</a>', '">＋ بطاقة جديدة</a>');
    }
    if (!empty($user->admin) || $user->hasRight('chinaplanetcustomercenter', 'admin') || $user->hasRight('cpkanban', 'admin')) {
        print ' <a class="butAction" href="'.dol_buildpath('/chinaplanetcustomercenter/admin/columns.php', 1).cpcc_ui_english_label('">Manage columns</a>', '">إدارة الأعمدة</a>');
    }
    print cpcc_ui_english_label('<span class="cpk-hint">🖐️ Drag a card between columns to update its status and progress</span>', '<span class="cpk-hint">🖐️ اسحب أي بطاقة بين الأعمدة لتحديث الحالة والتقدّم</span>');
    print '</form>';
}
?>

<div class="cpk-board">
<?php foreach ($columns as $col):
    $cards = isset($board[$col->rowid]) ? $board[$col->rowid] : array();
?>
    <div class="cpk-col" data-col="<?php echo (int) $col->rowid; ?>" style="--col-color:<?php echo dol_escape_htmltag($col->color); ?>">
        <div class="cpk-col-head">
            <span class="cpk-swatch" style="background:<?php echo dol_escape_htmltag($col->color); ?>"></span>
            <h3><?php echo dol_escape_htmltag(cpcc_ui_english_label(isset($col->label_en) && trim((string) $col->label_en) !== '' ? $col->label_en : $col->label, $col->label)); ?></h3>
            <span class="cpk-count"><?php echo count($cards); ?></span>
        </div>
        <div class="cpk-cards">
        <?php foreach ($cards as $c):
            $pri = cpkanban_priority($c->priority);
            $cardurl = dol_buildpath('/chinaplanetcustomercenter/card.php', 1).'?id='.(int) $c->rowid;
        ?>
            <?php if ($is_external): ?><a class="cpk-card cpk-card-link" data-id="<?php echo (int) $c->rowid; ?>" href="<?php echo dol_escape_htmltag($cardurl); ?>"><?php else: ?><div class="cpk-card" data-id="<?php echo (int) $c->rowid; ?>" draggable="true" onclick="window.location='<?php echo dol_escape_js($cardurl); ?>'"><?php endif; ?>
                <?php if (!empty($c->cover_asset_id)): ?><img class="cpk-card-cover" src="<?php echo dol_escape_htmltag(dol_buildpath('/cpkanban/cover.php', 1).'?id='.(int)$c->cover_asset_id); ?>" alt="" loading="lazy"><?php endif; ?>
                <div class="cpk-tline" style="background:<?php echo dol_escape_htmltag($col->color); ?>"></div>
                <h4><?php echo dol_escape_htmltag($c->title); ?></h4>
                <?php if (!empty($c->description)): ?>
                    <div class="cpk-desc"><?php echo dol_escape_htmltag(dol_trunc($c->description, 90)); ?></div>
                <?php endif; ?>
                <div class="cpk-meta">
                    <span class="cpk-tag <?php echo $pri['cls']; ?>"><?php echo $pri['label']; ?></span>
                    <?php if (!empty($c->fk_shipment)): ?>
                        <span class="cpk-tag cpk-ship"><?php echo cpcc_ui_english_label('🚢 Linked shipment', '🚢 شحنة مرتبطة'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="cpk-progress"><span style="width:<?php echo (int) $c->progress; ?>%"></span></div>
                <div class="cpk-foot">
                    <span class="cpk-cust"><?php echo $is_external ? cpcc_ui_english_label('Your project', 'مشروعك') : dol_escape_htmltag($c->customer_name); ?></span>
                    <span class="cpk-prog-num"><?php echo (int) $c->progress; ?>%</span>
                </div>
            <?php echo $is_external ? '</a>' : '</div>'; ?>
        <?php endforeach; ?>
        <?php if (empty($cards)): ?>
            <div class="cpk-empty"><?php echo cpcc_ui_english_label('No cards', 'لا توجد بطاقات'); ?></div>
        <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($is_external): ?>
<dialog id="cpk-card-dialog" class="cpk-card-dialog" aria-labelledby="cpk-card-dialog-title">
    <div class="cpk-dialog-head"><strong id="cpk-card-dialog-title"><?php echo cpcc_ui_english_label('Project details', 'تفاصيل المشروع / Project details'); ?></strong><button type="button" class="cpk-dialog-close" aria-label="<?php echo cpcc_ui_english_label('Close project details', 'إغلاق تفاصيل المشروع'); ?>">×</button></div>
    <iframe class="cpk-dialog-frame" title="<?php echo cpcc_ui_english_label('Project card details', 'تفاصيل بطاقة المشروع'); ?>"></iframe>
</dialog>
<?php endif; ?>

</div><!-- /cpk-wrap -->
<?php
if (!$is_external) cpcc_admin_shell_close();
llxFooter();
