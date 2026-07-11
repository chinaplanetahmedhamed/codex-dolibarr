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
require_once __DIR__.'/class/cpkanbancard.class.php';
require_once __DIR__.'/class/cpkanbancolumn.class.php';
require_once __DIR__.'/lib/cpkanban.lib.php';

$langs->loadLangs(array("companies", "projects", "other"));

// Security
if (empty($user->id) || (!$user->hasRight('cpkanban', 'read') && empty($user->admin))) {
    accessforbidden();
}

$ctx = cpkanban_user_context($user);
$is_external = $ctx['is_external'];
$socid = $ctx['socid'];

// An external user with no linked company sees nothing
if ($is_external && $socid <= 0) {
    accessforbidden("No company linked to this user.");
}

$canwrite = (!$is_external && ($user->hasRight('cpkanban', 'write') || !empty($user->admin)));

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
    array('/custom/cpkanban/js/board.js?v=211'),
    array('/custom/cpkanban/css/board.css?v=211'));

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
    print '<a class="cpk-center-card" style="--tile-color:#C0392B" href="#cpk-project-board"><div class="cpk-tile-top"><span class="cpk-tile-ic">🗂️</span><span class="cpk-tile-num">'.(int)$customerStats['projects'].'</span></div><h3>مشاريعي</h3><p>لوحة Kanban لمتابعة كل مشروع ومرحلته الحالية.</p><span class="cpk-arrow">افتح اللوحة ↓</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#2c3e50" href="#"><div class="cpk-tile-top"><span class="cpk-tile-ic">🚢</span><span class="cpk-tile-num">'.(int)$customerStats['shipments'].'</span></div><h3>شحناتي</h3><p>ETA، الوقت الحر، وحالة الحاويات الخاصة بك فقط.</p><span class="cpk-arrow">قريباً ←</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#D4A017" href="#"><div class="cpk-tile-top"><span class="cpk-tile-ic">🧾</span><span class="cpk-tile-num">'.(int)$customerStats['invoices_open'].'</span></div><h3>الفواتير والرصيد</h3><p>المفتوح تقريباً: '.cpkanban_money($customerStats['invoices_total']).'</p><span class="cpk-arrow">قريباً ←</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#16a085" href="#"><div class="cpk-tile-top"><span class="cpk-tile-ic">📦</span><span class="cpk-tile-num">'.(int)$customerStats['orders'].'</span></div><h3>الطلبيات</h3><p>طلبات البيع المرتبطة بحساب شركتك.</p><span class="cpk-arrow">قريباً ←</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#8e44ad" href="#"><div class="cpk-tile-top"><span class="cpk-tile-ic">📋</span><span class="cpk-tile-num">'.(int)$customerStats['dol_projects'].'</span></div><h3>مشاريع Dolibarr</h3><p>صفحات المشاريع والمهام الأصلية عند الحاجة.</p><span class="cpk-arrow">قريباً ←</span></a>';
    print '<a class="cpk-center-card" style="--tile-color:#2980b9" href="#"><div class="cpk-tile-top"><span class="cpk-tile-ic">📄</span><span class="cpk-tile-num">↗</span></div><h3>المستندات</h3><p>ملفات ومستندات مرتبطة بالمشاريع والشحنات.</p><span class="cpk-arrow">قريباً ←</span></a>';
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
            $cardurl = dol_buildpath('/cpkanban/card.php', 1).'?id='.(int) $c->rowid;
        ?>
            <div class="cpk-card" data-id="<?php echo (int) $c->rowid; ?>"
                 <?php echo $canwrite ? 'draggable="true"' : ''; ?>
                 onclick="window.location='<?php echo $cardurl; ?>'">
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
            </div>
        <?php endforeach; ?>
        <?php if (empty($cards)): ?>
            <div class="cpk-empty">لا توجد بطاقات</div>
        <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

</div><!-- /cpk-wrap -->
<?php
llxFooter();
