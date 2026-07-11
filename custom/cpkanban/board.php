<?php
/**
 * China Planet Kanban - Board page.
 * Internal staff: full board across all customers, drag & drop enabled.
 * External users (customers): only their own visible cards, view-only.
 */
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once __DIR__.'/class/cpkanbancard.class.php';
require_once __DIR__.'/class/cpkanbancolumn.class.php';
require_once __DIR__.'/lib/cpkanban.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/projectsync.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/portal.lib.php';

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

llxHeader('', 'Customer Projects / مشاريع العملاء', '', '', 0, 0,
    array('/custom/cpkanban/js/board.js'),
    array('/custom/cpkanban/css/board.css'));
cpcc_portal_navigation($user, 'dashboard');

// CSRF token for AJAX moves
$ajaxtoken = newToken();
?>
<div class="cpk-wrap" dir="rtl"
     data-ajax="<?php echo dol_buildpath('/cpkanban/ajax/move.php', 1); ?>"
     data-token="<?php echo $ajaxtoken; ?>"
     data-canwrite="<?php echo $canwrite ? 1 : 0; ?>">

<?php
if ($is_external) {
    $custName = 'عميل كوكب الصين';
    if ($socid > 0) {
        $rsoc = $db->query("SELECT nom FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".(int) $socid);
        if ($rsoc && ($osoc = $db->fetch_object($rsoc))) $custName = $osoc->nom;
    }
    print '<div class="cpk-customer-hero">';
    print '<div class="cpk-hero-brand"><span class="cpk-hero-dot">🌏</span><div><h2>مركز العميل — '.dol_escape_htmltag($custName).'</h2><p>من هنا تتابع مشاريعك، الشحنات، الفواتير، الطلبات، والرصيد. اضغط على أي بطاقة مشروع لفتح التفاصيل، ثم انتقل منها إلى التتبع الكامل أو الصفحات الأصلية داخل Dolibarr.</p></div></div>';
    print '<div class="cpk-hero-stats">';
    print '<div class="cpk-hero-stat"><b>'.(int)$customerStats['projects'].'</b><span>مشاريع ظاهرة</span></div>';
    print '<div class="cpk-hero-stat"><b>'.(int)$customerStats['shipments'].'</b><span>شحنات نشطة</span></div>';
    print '<div class="cpk-hero-stat"><b>'.(int)$customerStats['invoices_open'].'</b><span>فواتير مفتوحة</span></div>';
    print '</div></div>';

    print '<div class="cpk-center-grid">';
    print '<a class="cpk-center-card" style="--tile-color:#C0392B" href="'.dol_buildpath('/chinaplanetcustomercenter/projects.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">🗂️</span><span class="cpk-tile-num">'.(int)$customerStats['projects'].'</span></div><h3>مشاريعي</h3><p>عرض المشاريع الحقيقية المرتبطة بحسابك مع حالة المزامنة.</p><span class="cpk-arrow">عرض المشاريع ←</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#2c3e50" href="'.dol_buildpath('/chinaplanetlogistics/list.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">🚢</span><span class="cpk-tile-num">'.(int)$customerStats['shipments'].'</span></div><h3>شحناتي</h3><p>ETA، الوقت الحر، وحالة الحاويات الخاصة بك فقط.</p><span class="cpk-arrow">عرض الشحنات ←</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#D4A017" href="'.dol_buildpath('/chinaplanetcustomercenter/invoices.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">🧾</span><span class="cpk-tile-num">'.(int)$customerStats['invoices_open'].'</span></div><h3>الفواتير</h3><p>المفتوح تقريباً: '.cpkanban_money($customerStats['invoices_total']).'</p><span class="cpk-arrow">عرض الفواتير ←</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#16a085" href="'.dol_buildpath('/chinaplanetcustomercenter/orders.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">📦</span><span class="cpk-tile-num">'.(int)$customerStats['orders'].'</span></div><h3>الطلبيات</h3><p>طلبات البيع المرتبطة بحساب شركتك.</p><span class="cpk-arrow">عرض الطلبيات ←</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#8e44ad" href="'.dol_buildpath('/chinaplanetcustomercenter/balance.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">💰</span><span class="cpk-tile-num">'.cpkanban_money($customerStats['invoices_total']).'</span></div><h3>الرصيد</h3><p>ملخص الحسابات المفتوحة والمدفوعة.</p><span class="cpk-arrow">عرض الرصيد ←</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#2980b9" href="'.dol_buildpath('/chinaplanetcustomercenter/documents.php', 1).'"><div class="cpk-tile-top"><span class="cpk-tile-ic">📄</span><span class="cpk-tile-num">↗</span></div><h3>المستندات</h3><p>ملفات ومستندات مرتبطة بالمشاريع والشحنات.</p><span class="cpk-arrow">عرض الملفات ←</span></a>';
    print '</div>';

    print '<div class="cpk-banner">🔒 أنت ترى بيانات شركتك فقط. الأعمدة والملاحظات والملفات الداخلية غير ظاهرة لك. لوحة المشاريع عرض فقط.</div>';
    print '<div id="cpk-project-board" class="cpk-section-head"><div><h2>مشاريعي على لوحة Kanban</h2><p>اضغط على أي كارت لفتح تفاصيله وروابط الشحنات والفواتير والطلبيات.</p></div><span class="cpk-viewonly">🔒 عرض فقط — لا يمكنك تحريك البطاقات</span></div>';
} else {
    print load_fiche_titre('لوحة مشاريع العملاء', '', 'project');
    // Admin toolbar: filter + new card
    print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" class="cpk-toolbar">';
    print '<input type="text" name="search_text" class="flat" value="'.dol_escape_htmltag(GETPOST('search_text','alpha')).'" placeholder="بحث بالعنوان">';
    print ' <input type="text" name="search_soc" class="flat" value="'.dol_escape_htmltag(GETPOST('search_soc','int')).'" placeholder="معرّف العميل">';
    print ' <select name="search_pri" class="flat"><option value="">كل الأولويات</option>';
    foreach (array('high'=>'عالية','med'=>'متوسطة','low'=>'منخفضة') as $k=>$v) {
        $sel = (GETPOST('search_pri','alpha') === $k) ? ' selected' : '';
        print '<option value="'.$k.'"'.$sel.'>'.$v.'</option>';
    }
    print '</select> ';
    print '<input type="submit" class="button small" value="تصفية">';
    if ($canwrite) {
        print ' <a class="butActionNew" href="'.dol_buildpath('/cpkanban/card.php?action=create', 1).'">＋ بطاقة جديدة</a>';
    }
    if (!empty($user->admin) || $user->hasRight('chinaplanetcustomercenter', 'admin') || $user->hasRight('cpkanban', 'admin')) {
        print ' <a class="butAction" href="'.dol_buildpath('/chinaplanetcustomercenter/admin/columns.php', 1).'">إدارة الأعمدة</a>';
    }
    print '<span class="cpk-hint">🖐️ اسحب أي بطاقة بين الأعمدة لتحديث الحالة والتقدّم</span>';
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
            <h3><?php echo dol_escape_htmltag($col->label); ?></h3>
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
                        <span class="cpk-tag cpk-ship">🚢 شحنة مرتبطة</span>
                    <?php endif; ?>
                    <?php if ($is_external && !empty($c->_cpk_folded_from_label)): ?>
                        <span class="cpk-tag cpk-money">داخلياً: <?php echo dol_escape_htmltag($c->_cpk_folded_from_label); ?></span>
                    <?php endif; ?>
                </div>
                <div class="cpk-progress"><span style="width:<?php echo (int) $c->progress; ?>%"></span></div>
                <div class="cpk-foot">
                    <span class="cpk-cust"><?php echo $is_external ? 'مشروعك' : dol_escape_htmltag($c->customer_name); ?></span>
                    <span class="cpk-prog-num"><?php echo (int) $c->progress; ?>%</span>
                </div>
            <?php echo $is_external ? '</a>' : '</div>'; ?>
        <?php endforeach; ?>
        <?php if (empty($cards)): ?>
            <div class="cpk-empty">لا توجد بطاقات</div>
        <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if ($is_external): ?>
<dialog id="cpk-card-dialog" class="cpk-card-dialog" aria-labelledby="cpk-card-dialog-title">
    <div class="cpk-dialog-head"><strong id="cpk-card-dialog-title">تفاصيل المشروع / Project details</strong><button type="button" class="cpk-dialog-close" aria-label="إغلاق تفاصيل المشروع">×</button></div>
    <iframe class="cpk-dialog-frame" title="تفاصيل بطاقة المشروع"></iframe>
</dialog>
<?php endif; ?>

</div><!-- /cpk-wrap -->
<?php
llxFooter();
