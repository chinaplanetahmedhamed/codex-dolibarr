<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require '../../main.inc.php';

if (empty($user->id) || empty($user->admin) || !empty($user->socid)) {
    accessforbidden('Administrator access required.');
}

$entity = (int) $conf->entity;
$currency = !empty($conf->currency) ? $conf->currency : 'USD';
$year = (int) date('Y');

function cpadScalar($db, $sql, $field = 'value')
{
    $resql = $db->query($sql);
    if (!$resql) return 0;
    $obj = $db->fetch_object($resql);
    return $obj && isset($obj->{$field}) ? $obj->{$field} : 0;
}

function cpadMoney($value, $currency)
{
    return number_format((float) $value, 0, '.', ',').' '.$currency;
}

function cpadPct($value, $max)
{
    if ($max <= 0) return 0;
    return max(3, min(100, round(((float) $value / (float) $max) * 100)));
}

$customers = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."societe WHERE entity IN (0,".$entity.") AND client IN (1,2,3)");
$activeProjects = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."projet WHERE entity = ".$entity." AND fk_statut = 1");
$yearOrders = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."commande WHERE entity = ".$entity." AND YEAR(date_commande) = ".$year." AND fk_statut > 0");
$yearRevenue = (float) cpadScalar($db, "SELECT COALESCE(SUM(total_ttc),0) value FROM ".MAIN_DB_PREFIX."facture WHERE entity = ".$entity." AND YEAR(datef) = ".$year." AND fk_statut > 0");
$unpaid = (float) cpadScalar($db, "SELECT COALESCE(SUM(GREATEST(f.total_ttc-COALESCE(p.paid,0),0)),0) value FROM ".MAIN_DB_PREFIX."facture f LEFT JOIN (SELECT fk_facture,SUM(amount) paid FROM ".MAIN_DB_PREFIX."paiement_facture GROUP BY fk_facture) p ON p.fk_facture=f.rowid WHERE f.entity = ".$entity." AND f.fk_statut = 1 AND f.paye = 0");
$shipTotal = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity);
$inTransit = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity." AND fk_statut IN (1,2)");
$arriving = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity." AND fk_statut < 3 AND date_eta BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)");
$arrived = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity." AND fk_statut = 3");
$critical = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity = ".$entity." AND fk_statut < 4 AND ((date_eta < CURDATE()) OR freetime_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))");
$cardsActive = (int) cpadScalar($db, "SELECT COUNT(*) value FROM ".MAIN_DB_PREFIX."cpkanban_card WHERE entity = ".$entity." AND status = 1");

$months = array();
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m', strtotime('-'.$i.' months'));
    $months[$key] = array('label' => date('M', strtotime($key.'-01')), 'value' => 0);
}
$resql = $db->query("SELECT DATE_FORMAT(datef,'%Y-%m') month_key, COALESCE(SUM(total_ttc),0) amount FROM ".MAIN_DB_PREFIX."facture WHERE entity = ".$entity." AND fk_statut > 0 AND datef >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 6 MONTH),'%Y-%m-01') GROUP BY month_key ORDER BY month_key");
if ($resql) while ($obj = $db->fetch_object($resql)) if (isset($months[$obj->month_key])) $months[$obj->month_key]['value'] = (float) $obj->amount;
$monthMax = 0;
foreach ($months as $month) $monthMax = max($monthMax, $month['value']);

$currencyRows = array();
$resql = $db->query("SELECT COALESCE(NULLIF(multicurrency_code,''),'".$db->escape($currency)."') code, COUNT(*) invoice_count, COALESCE(SUM(CASE WHEN multicurrency_total_ttc > 0 THEN multicurrency_total_ttc ELSE total_ttc END),0) amount FROM ".MAIN_DB_PREFIX."facture WHERE entity = ".$entity." AND fk_statut > 0 GROUP BY code ORDER BY amount DESC LIMIT 3");
if ($resql) while ($obj = $db->fetch_object($resql)) $currencyRows[] = array('code'=>$obj->code, 'count'=>(int)$obj->invoice_count, 'amount'=>(float)$obj->amount);
$currencyTotal = array_sum(array_column($currencyRows, 'amount'));
$p1 = $currencyTotal > 0 && isset($currencyRows[0]) ? round($currencyRows[0]['amount'] / $currencyTotal * 100) : 34;
$p2 = $currencyTotal > 0 && isset($currencyRows[1]) ? min(100, $p1 + round($currencyRows[1]['amount'] / $currencyTotal * 100)) : 67;

$recentShipments = array();
$sql = "SELECT l.rowid,l.ref,l.container_no,l.port_destination,l.date_eta,l.fk_statut,s.nom customer FROM ".MAIN_DB_PREFIX."chinaplanetlogistics l LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=l.fk_soc WHERE l.entity = ".$entity." ORDER BY COALESCE(l.date_eta,'2999-12-31') ASC,l.rowid DESC LIMIT 7";
$resql = $db->query($sql);
if ($resql) while ($obj = $db->fetch_object($resql)) $recentShipments[] = $obj;

$topCustomers = array();
$sql = "SELECT s.rowid,s.nom,COUNT(f.rowid) invoice_count,COALESCE(SUM(f.total_ttc),0) amount FROM ".MAIN_DB_PREFIX."societe s LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.fk_soc=s.rowid AND f.fk_statut>0 WHERE s.entity IN (0,".$entity.") AND s.client IN (1,2,3) GROUP BY s.rowid,s.nom ORDER BY amount DESC,invoice_count DESC LIMIT 6";
$resql = $db->query($sql);
$topMax = 0;
if ($resql) while ($obj = $db->fetch_object($resql)) { $topCustomers[]=$obj; $topMax=max($topMax,(float)$obj->amount); }

$statusLabels = array(0=>'Inquiry',1=>'Booking',2=>'On vessel',3=>'Arrived',4=>'Closed');

llxHeader('', 'Admin Operations Dashboard', '', '', 0, 0, array(), array('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'));
?>
<div class="cpad-shell">
  <nav class="cpad-nav" aria-label="Customer Center navigation">
    <a class="active" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin_dashboard.php',1); ?>"><span class="fas fa-chart-line"></span> Admin Dashboard</a>
    <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/board.php',1); ?>"><span class="fas fa-th-large"></span> Portal Home</a>
    <a href="<?php echo dol_buildpath('/chinaplanetlogistics/index.php',1); ?>"><span class="fas fa-ship"></span> Shipment Tracking</a>
    <a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php',1); ?>"><span class="fas fa-list"></span> Shipment List</a>
    <a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/documents.php',1); ?>"><span class="fas fa-folder-open"></span> Shipment Documents</a>
    <a class="new" href="<?php echo dol_buildpath('/chinaplanetlogistics/card.php?action=create',1); ?>"><span class="fas fa-plus-circle"></span> New Shipment</a>
  </nav>
  <header class="cpad-head">
    <h1>VISION TRADE OPERATIONS</h1>
    <p>China Planet · Dolibarr Admin Command Center</p>
    <div class="cpad-live"><i></i><span>LIVE · <?php echo dol_escape_htmltag(date('Y-m-d H:i')); ?></span></div>
  </header>

  <section class="cpad-kpis">
    <div class="cpad-kpi"><b><?php echo $customers; ?></b><span>Customers / العملاء</span></div>
    <div class="cpad-kpi"><b><?php echo $yearOrders; ?></b><span><?php echo $year; ?> Orders / الطلبات</span></div>
    <div class="cpad-kpi gold"><b><?php echo dol_escape_htmltag(cpadMoney($yearRevenue, $currency)); ?></b><span>Invoiced this year / فواتير السنة</span></div>
    <div class="cpad-kpi"><b><?php echo $shipTotal; ?></b><span>Total shipments / الشحنات</span></div>
  </section>

  <div class="cpad-grid">
    <div class="cpad-stack">
      <section class="cpad-panel">
        <div class="cpad-title">Shipment Control <small>حالة الشحن</small></div>
        <div class="cpad-status">
          <div><b><?php echo $inTransit; ?></b><span>In transit / في الطريق</span></div>
          <div><b><?php echo $arriving; ?></b><span>ETA within 14 days</span></div>
          <div class="gold"><b><?php echo $arrived; ?></b><span>Arrived / وصلت</span></div>
          <div class="danger"><b><?php echo $critical; ?></b><span>Critical alerts / تنبيهات</span></div>
        </div>
      </section>

      <section class="cpad-panel">
        <div class="cpad-title">Currency Mix <small>توزيع العملات</small></div>
        <div class="cpad-donut-row">
          <div class="cpad-donut" style="--p1:<?php echo $p1; ?>%;--p2:<?php echo $p2; ?>%" data-total="<?php echo count($currencyRows); ?> FX"></div>
          <div class="cpad-legend">
            <?php if (empty($currencyRows)) { ?><div><span><i style="background:#14d9ff"></i>No invoices yet</span><b>—</b></div><?php } ?>
            <?php $colors=array('#14d9ff','#ffd84a','#2165d7'); foreach ($currencyRows as $idx=>$row) { ?>
              <div><span><i style="background:<?php echo $colors[$idx]; ?>"></i><?php echo dol_escape_htmltag($row['code']); ?></span><b><?php echo $row['count']; ?></b></div>
            <?php } ?>
          </div>
        </div>
      </section>

      <section class="cpad-panel">
        <div class="cpad-title">Workflow <small>المشاريع والمهام</small></div>
        <div class="cpad-status">
          <div><b><?php echo $activeProjects; ?></b><span>Active projects</span></div>
          <div><b><?php echo $cardsActive; ?></b><span>Active Kanban cards</span></div>
          <div class="gold"><b><?php echo $yearOrders; ?></b><span>Confirmed orders</span></div>
          <div class="danger"><b><?php echo dol_escape_htmltag(cpadMoney($unpaid, $currency)); ?></b><span>Customer balance due</span></div>
        </div>
      </section>
    </div>

    <main class="cpad-stack cpad-center">
      <section class="cpad-panel">
        <div class="cpad-title">Monthly Invoiced Amount <small>آخر 7 أشهر · <?php echo dol_escape_htmltag($currency); ?></small></div>
        <div class="cpad-bars">
          <?php foreach ($months as $month) { $height = $monthMax > 0 ? max(3, round($month['value']/$monthMax*100)) : 3; ?>
            <div class="cpad-bar" style="--h:<?php echo $height; ?>%"><em><?php echo number_format($month['value'],0); ?></em><span><?php echo dol_escape_htmltag($month['label']); ?></span></div>
          <?php } ?>
        </div>
      </section>

      <section class="cpad-panel">
        <div class="cpad-title">Live Shipments <small>الحاويات والموعد المتوقع</small></div>
        <?php if (empty($recentShipments)) { ?><div class="cpad-empty">No shipment records yet.</div><?php } else { ?>
        <table class="cpad-table">
          <thead><tr><th>REF / CONTAINER</th><th>CUSTOMER</th><th>DESTINATION</th><th>ETA</th><th>STATUS</th></tr></thead>
          <tbody><?php foreach ($recentShipments as $shipment) { $late=!empty($shipment->date_eta)&&$shipment->date_eta<date('Y-m-d')&&(int)$shipment->fk_statut<3; ?>
            <tr>
              <td><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/chinaplanetlogistics/card.php?id='.(int)$shipment->rowid,1); ?>"><?php echo dol_escape_htmltag($shipment->container_no ?: $shipment->ref); ?></a></td>
              <td><?php echo dol_escape_htmltag($shipment->customer ?: '—'); ?></td>
              <td><?php echo dol_escape_htmltag($shipment->port_destination ?: '—'); ?></td>
              <td><?php echo dol_escape_htmltag($shipment->date_eta ?: '—'); ?></td>
              <td><span class="cpad-badge <?php echo $late?'danger':((int)$shipment->fk_statut===1?'warn':''); ?>"><?php echo dol_escape_htmltag($statusLabels[(int)$shipment->fk_statut] ?? 'Unknown'); ?></span></td>
            </tr>
          <?php } ?></tbody>
        </table><?php } ?>
      </section>
    </main>

    <aside class="cpad-stack">
      <section class="cpad-panel">
        <div class="cpad-title">Top Customers <small>حسب قيمة الفواتير</small></div>
        <div class="cpad-ranking">
          <?php if (empty($topCustomers)) { ?><div class="cpad-empty">No customer activity yet.</div><?php } ?>
          <?php foreach ($topCustomers as $customer) { ?>
            <div class="cpad-rank"><span title="<?php echo dol_escape_htmltag($customer->nom); ?>"><?php echo dol_escape_htmltag($customer->nom); ?></span><div class="cpad-track"><div class="cpad-fill" style="--w:<?php echo cpadPct($customer->amount,$topMax); ?>%"></div></div><b><?php echo (int)$customer->invoice_count; ?></b></div>
          <?php } ?>
        </div>
      </section>

      <section class="cpad-panel">
        <div class="cpad-title">Operations Snapshot <small><?php echo $year; ?></small></div>
        <table class="cpad-table"><tbody>
          <tr><td>Customers</td><td><?php echo $customers; ?></td></tr>
          <tr><td>Active projects</td><td><?php echo $activeProjects; ?></td></tr>
          <tr><td>Kanban cards</td><td><?php echo $cardsActive; ?></td></tr>
          <tr><td>Shipments</td><td><?php echo $shipTotal; ?></td></tr>
          <tr><td>Unpaid exposure</td><td><?php echo dol_escape_htmltag(cpadMoney($unpaid,$currency)); ?></td></tr>
        </tbody></table>
      </section>

      <section class="cpad-panel">
        <div class="cpad-title">Quick Access <small>Dolibarr</small></div>
        <table class="cpad-table"><tbody>
          <tr><td><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/board.php',1); ?>">Customer Projects</a></td><td>→</td></tr>
          <tr><td><a href="<?php echo dol_buildpath('/chinaplanetlogistics/index.php',1); ?>">Logistics Dashboard</a></td><td>→</td></tr>
          <tr><td><a href="<?php echo dol_buildpath('/chinaplanetlogistics/list.php',1); ?>">Shipment List</a></td><td>→</td></tr>
          <tr><td><a href="<?php echo dol_buildpath('/chinaplanetlogistics/card.php?action=create',1); ?>">New Shipment</a></td><td>＋</td></tr>
          <tr><td><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/documents.php',1); ?>">Shipment Documents</a></td><td>→</td></tr>
          <tr><td><a href="<?php echo DOL_URL_ROOT; ?>/societe/list.php">Customers</a></td><td>→</td></tr>
        </tbody></table>
      </section>
    </aside>
  </div>
  <div class="cpad-footnote">Read-only admin prototype · Data refreshed on page load · Dolibarr <?php echo DOL_VERSION; ?></div>
</div>
<?php
llxFooter();
$db->close();
