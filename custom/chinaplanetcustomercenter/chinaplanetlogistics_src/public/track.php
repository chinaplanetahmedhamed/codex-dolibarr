<?php
/**
 * China Planet Logistics - Public Customer Tracking Portal
 * Search by: Client Code (shows ALL containers) | Container No | BL No
 * FIXED: freetime_end field name corrected (was date_freetime_end)
 */
define("NOLOGIN", 1);
define("NOCSRFCHECK", 1);
define("NOTOKENRENEWAL", 1);
define("NOREQUIREMENU", 1);
define("NOREQUIREHTML", 1);
define("NOREQUIREAJAX", 1);

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

$search        = trim(GETPOST('search', 'alpha'));
$found         = array();
$customer_name = '';
$search_mode   = ''; // 'client' or 'single'

if ($search) {
    $s = $db->escape($search);

    // 1) Try client code first
    $rsoc = $db->query("SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE code_client = '".$s."' LIMIT 1");
    if ($rsoc && $db->num_rows($rsoc) > 0) {
        $soc           = $db->fetch_object($rsoc);
        $customer_name = $soc->nom;
        $search_mode   = 'client';
        $sql = "SELECT t.*, s.nom as customer_name, s.code_client"
             . " FROM ".MAIN_DB_PREFIX."chinaplanetcustomercenter t"
             . " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = t.fk_soc"
             . " WHERE t.fk_soc = ".(int)$soc->rowid
             . " ORDER BY t.date_eta ASC";
    } else {
        // 2) Try container no or BL no
        $search_mode = 'single';
        $sql = "SELECT t.*, s.nom as customer_name, s.code_client"
             . " FROM ".MAIN_DB_PREFIX."chinaplanetcustomercenter t"
             . " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = t.fk_soc"
             . " WHERE t.container_no = '".$s."' OR t.bl_no = '".$s."'"
             . " ORDER BY t.date_eta ASC";
    }

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $found[] = $obj;
        }
    }
}

function daysBetween($date) {
    if (empty($date) || $date == '0000-00-00') return null;
    return (int) ceil((strtotime($date) - time()) / 86400);
}

function etaBadge($days) {
    if ($days === null) return '<span style="color:#999">—</span>';
    if ($days < 0)   return '<span class="badge" style="background:#6c757d">Arrived ('.abs($days).'d ago)</span>';
    if ($days <= 7)  return '<span class="badge" style="background:#dc3545">'.$days.'d ⚠️</span>';
    if ($days <= 14) return '<span class="badge" style="background:#fd7e14">'.$days.'d</span>';
    return '<span class="badge" style="background:#28a745">'.$days.'d</span>';
}

function ftBadge($days) {
    if ($days === null) return '<span style="color:#999">—</span>';
    if ($days < 0)   return '<span class="badge" style="background:#dc3545">Expired ('.abs($days).'d)</span>';
    if ($days <= 5)  return '<span class="badge" style="background:#dc3545">'.$days.'d 🔴</span>';
    if ($days <= 10) return '<span class="badge" style="background:#fd7e14">'.$days.'d 🟡</span>';
    return '<span class="badge" style="background:#28a745">'.$days.'d 🟢</span>';
}

$status_map = array(
    0 => array('Inquiry',            '#95a5a6'),
    1 => array('Booking Confirmed',  '#6c757d'),
    2 => array('On Vessel 🚢',       '#0d6efd'),
    3 => array('Arrived Port ⚓',    '#fd7e14'),
    4 => array('Under Customs 📋',   '#6f42c1'),
    5 => array('Customs Cleared ✅', '#20c997'),
    6 => array('Delivered ✅',       '#28a745'),
    7 => array('On Hold ⏸',         '#dc3545'),
);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>China Planet — Shipment Tracker</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f4f8;color:#2c3e50}
.header{background:linear-gradient(135deg,#1a1a2e,#0f3460);color:#fff;padding:32px 20px;text-align:center}
.header h1{font-size:26px;font-weight:700;margin-bottom:6px}
.header p{font-size:14px;opacity:.8}
.search-wrap{background:#fff;padding:28px 20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.search-wrap form{display:flex;max-width:620px;margin:0 auto;gap:10px;flex-wrap:wrap;justify-content:center}
.search-wrap input{flex:1;min-width:260px;padding:13px 18px;border:2px solid #ddd;border-radius:8px;font-size:15px;outline:none}
.search-wrap input:focus{border-color:#0f3460}
.search-wrap button{padding:13px 30px;background:#0f3460;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer}
.search-wrap button:hover{background:#1a4a8a}
.hint{margin-top:10px;font-size:12px;color:#888}
.wrap{max-width:1100px;margin:28px auto;padding:0 15px}
.cust-header{background:#fff;border-left:5px solid #0f3460;border-radius:8px;padding:18px 22px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.cust-header h2{font-size:19px;color:#1a1a2e}
.cust-header p{font-size:13px;color:#666;margin-top:4px}
.summary{display:flex;gap:14px;margin-bottom:18px;flex-wrap:wrap}
.sc{flex:1;min-width:120px;background:#fff;border-radius:8px;padding:14px 18px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.sc .n{font-size:28px;font-weight:700}
.sc .l{font-size:12px;color:#666;margin-top:3px}
.tbl-wrap{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:700px}
th{background:#1a1a2e;color:#fff;padding:11px 13px;text-align:left;white-space:nowrap}
td{padding:11px 13px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#f8f9ff}
.badge{display:inline-block;padding:3px 10px;border-radius:12px;color:#fff;font-size:11px;font-weight:600;white-space:nowrap}
.status-badge{display:inline-block;padding:3px 10px;border-radius:12px;color:#fff;font-size:11px;font-weight:600}
.empty{text-align:center;padding:50px 20px;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);color:#999}
.empty .ico{font-size:48px;margin-bottom:10px}
.notfound{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:15px 20px;color:#856404;margin-bottom:20px}
.footer{text-align:center;padding:20px;color:#aaa;font-size:12px;margin-top:10px}
.single-card{background:#fff;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.08);overflow:hidden;margin-bottom:20px}
.single-head{background:#1a1a2e;color:#fff;padding:16px 22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.single-head .ref{font-size:18px;font-weight:700}
.countdowns{display:flex;gap:14px;padding:20px;flex-wrap:wrap}
.cd{flex:1;min-width:140px;text-align:center;padding:18px;border-radius:10px;color:#fff}
.cd .num{font-size:34px;font-weight:700}
.cd .lbl{font-size:13px;margin-top:4px;opacity:.95}
.dets{padding:0 20px 20px}
.dets table{width:100%;min-width:unset}
.dets td{font-size:14px;padding:9px 6px}
.dets td:first-child{color:#888;width:45%}
.dets td:last-child{font-weight:600}
@media(max-width:600px){.summary{flex-direction:column}}
</style>
</head>
<body>

<div class="header">
    <h1>🚢 China Planet Logistics</h1>
    <p>Shipment Tracking Portal — تتبع شحناتك</p>
</div>

<div class="search-wrap">
    <form method="GET">
        <input type="text" name="search"
               value="<?php echo dol_escape_htmltag($search); ?>"
               placeholder="Client Code / Container No / BL No"
               autofocus>
        <button type="submit">🔍 Track</button>
    </form>
    <div class="hint">
        Enter your <strong>Client Code</strong> to see all containers &nbsp;|&nbsp;
        Or search by <strong>Container No</strong> or <strong>BL No</strong>
    </div>
</div>

<div class="wrap">

<?php if ($search && empty($found)): ?>
<div class="notfound">⚠️ No shipments found for: <strong><?php echo dol_escape_htmltag($search); ?></strong>. Please verify the code or contact us.</div>
<?php endif; ?>

<?php if (!empty($found)):

    // ---- CLIENT CODE MODE: table with all containers ----
    if ($search_mode === 'client'):
        $total = count($found);
        $on_vessel = $arrived = $customs = $ft_crit = 0;
        foreach ($found as $r) {
            if ($r->fk_statut == 2) $on_vessel++;
            if ($r->fk_statut == 3) $arrived++;
            if ($r->fk_statut == 4) $customs++;
            // FIXED: use freetime_end (correct column name)
            $ftd = daysBetween(!empty($r->freetime_end) ? $r->freetime_end : '');
            if ($ftd !== null && $ftd >= 0 && $ftd <= 5) $ft_crit++;
        }
?>
    <div class="cust-header">
        <h2>👤 <?php echo dol_escape_htmltag($customer_name); ?></h2>
        <p>Client Code: <strong><?php echo dol_escape_htmltag($search); ?></strong> &nbsp;|&nbsp; <?php echo $total; ?> container(s) found</p>
    </div>

    <div class="summary">
        <div class="sc"><div class="n" style="color:#0f3460"><?php echo $total; ?></div><div class="l">Total</div></div>
        <div class="sc"><div class="n" style="color:#0d6efd"><?php echo $on_vessel; ?></div><div class="l">On Vessel 🚢</div></div>
        <div class="sc"><div class="n" style="color:#fd7e14"><?php echo $arrived; ?></div><div class="l">Arrived Port ⚓</div></div>
        <div class="sc"><div class="n" style="color:#6f42c1"><?php echo $customs; ?></div><div class="l">In Customs 📋</div></div>
        <?php if ($ft_crit > 0): ?><div class="sc"><div class="n" style="color:#dc3545"><?php echo $ft_crit; ?></div><div class="l">FT Critical 🔴</div></div><?php endif; ?>
    </div>

    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Container No</th>
                    <th>BL No</th>
                    <th>Line</th>
                    <th>Vessel</th>
                    <th>Destination Port</th>
                    <th>ETA</th>
                    <th>Days to Arrival</th>
                    <th>Free Time Left</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($found as $i => $r):
                $st      = isset($status_map[$r->fk_statut]) ? $status_map[$r->fk_statut] : array('Unknown','#999');
                $eta_fmt = (!empty($r->date_eta) && $r->date_eta != '0000-00-00') ? date('d/m/Y', strtotime($r->date_eta)) : '—';
                // FIXED: freetime_end (correct column name)
                $ft_end  = !empty($r->freetime_end) ? $r->freetime_end : '';
                $ft_fmt  = (!empty($ft_end) && $ft_end != '0000-00-00') ? date('d/m/Y', strtotime($ft_end)) : '';
                $eta_days = daysBetween(!empty($r->date_eta) ? $r->date_eta : '');
                $ft_days  = daysBetween($ft_end);
            ?>
            <tr>
                <td style="color:#999;font-size:12px"><?php echo $i+1; ?></td>
                <td><strong><?php echo dol_escape_htmltag($r->container_no ?: '—'); ?></strong></td>
                <td><?php echo dol_escape_htmltag($r->bl_no ?: '—'); ?></td>
                <td><?php echo dol_escape_htmltag($r->shipping_line ?: '—'); ?></td>
                <td><?php echo dol_escape_htmltag($r->vessel_name ?: '—'); ?></td>
                <td><?php echo dol_escape_htmltag($r->port_destination ?: '—'); ?></td>
                <td><?php echo $eta_fmt; ?></td>
                <td><?php echo etaBadge($eta_days); ?></td>
                <td><?php echo ftBadge($ft_days); ?><?php if ($ft_fmt): ?><br><span style="font-size:10px;color:#999">ends <?php echo $ft_fmt; ?></span><?php endif; ?></td>
                <td><span class="status-badge" style="background:<?php echo $st[1]; ?>"><?php echo $st[0]; ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php
    // ---- SINGLE SEARCH MODE: card view per container ----
    else:
        foreach ($found as $sh):
            $eta_days = daysBetween(!empty($sh->date_eta) ? $sh->date_eta : '');
            // FIXED: freetime_end (correct column name)
            $ft_end   = !empty($sh->freetime_end) ? $sh->freetime_end : '';
            $ft_days  = daysBetween($ft_end);
            $st = isset($status_map[$sh->fk_statut]) ? $status_map[$sh->fk_statut] : array('Unknown','#999');

            $eta_color = ($eta_days === null) ? '#999' : (($eta_days < 0) ? '#6c757d' : (($eta_days <= 7) ? '#dc3545' : (($eta_days <= 14) ? '#fd7e14' : '#28a745')));
            $ft_color  = ($ft_days  === null) ? '#999' : (($ft_days  < 0) ? '#dc3545' : (($ft_days  <= 5) ? '#dc3545' : (($ft_days  <= 10) ? '#fd7e14' : '#28a745')));
?>
    <div class="single-card">
        <div class="single-head">
            <span class="ref">📦 <?php echo dol_escape_htmltag($sh->container_no ?: $sh->ref); ?></span>
            <span class="status-badge" style="background:<?php echo $st[1]; ?>"><?php echo $st[0]; ?></span>
        </div>
        <div class="countdowns">
            <div class="cd" style="background:<?php echo $eta_color; ?>">
                <div class="num"><?php echo ($eta_days !== null ? ($eta_days < 0 ? 'Arrived' : $eta_days) : '—'); ?></div>
                <div class="lbl"><?php echo ($eta_days !== null && $eta_days >= 0) ? 'Days to Arrival' : 'Status'; ?></div>
            </div>
            <div class="cd" style="background:<?php echo $ft_color; ?>">
                <div class="num"><?php echo ($ft_days !== null ? ($ft_days < 0 ? 'Expired' : $ft_days) : '—'); ?></div>
                <div class="lbl">Free Time Days Left</div>
            </div>
        </div>
        <div class="dets">
            <table>
                <tr><td>Customer</td><td><?php echo dol_escape_htmltag($sh->customer_name ?: '—'); ?></td></tr>
                <tr><td>BL No</td><td><?php echo dol_escape_htmltag($sh->bl_no ?: '—'); ?></td></tr>
                <tr><td>Shipping Line</td><td><?php echo dol_escape_htmltag($sh->shipping_line ?: '—'); ?></td></tr>
                <tr><td>Vessel</td><td><?php echo dol_escape_htmltag($sh->vessel_name ?: '—'); ?></td></tr>
                <tr><td>Port of Loading</td><td><?php echo dol_escape_htmltag($sh->port_loading ?: '—'); ?></td></tr>
                <tr><td>Destination Port</td><td><?php echo dol_escape_htmltag($sh->port_destination ?: '—'); ?></td></tr>
                <tr><td>ETD</td><td><?php echo (!empty($sh->date_etd) && $sh->date_etd != '0000-00-00') ? date('d/m/Y', strtotime($sh->date_etd)) : '—'; ?></td></tr>
                <tr><td>ETA</td><td><?php echo (!empty($sh->date_eta) && $sh->date_eta != '0000-00-00') ? date('d/m/Y', strtotime($sh->date_eta)) : '—'; ?></td></tr>
                <tr><td>Free Time Ends</td><td><?php echo (!empty($ft_end) && $ft_end != '0000-00-00') ? date('d/m/Y', strtotime($ft_end)) : '—'; ?></td></tr>
                <?php if (!empty($sh->customer_note)): ?>
                <tr><td>Notes</td><td><?php echo nl2br(dol_escape_htmltag($sh->customer_note)); ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
<?php
        endforeach;
    endif; // end single mode
endif; // end if found

if (!$search):
?>
    <div class="empty">
        <div class="ico">🔍</div>
        <p style="font-size:16px;font-weight:600;color:#333;margin-bottom:8px">Track Your Shipments</p>
        <p>Enter your <strong>Client Code</strong> to see all your containers,<br>or search by <strong>Container No</strong> / <strong>BL No</strong> for a specific shipment.</p>
    </div>
<?php endif; ?>

</div>
<div class="footer">© <?php echo date('Y'); ?> China Planet Logistics — Vision Trading — Guangzhou, China</div>
</body>
</html>
