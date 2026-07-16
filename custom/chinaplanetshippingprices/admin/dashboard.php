<?php
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';

$canImport = empty($user->socid) && (!empty($user->admin) || $user->hasRight('chinaplanetshippingprices', 'import'));
$canAdmin = empty($user->socid) && (!empty($user->admin) || $user->hasRight('chinaplanetshippingprices', 'admin'));
if (empty($user->id) || (!$canImport && !$canAdmin)) accessforbidden();

$stats = array('current_rates'=>0,'batches'=>0,'active_rules'=>0,'unmapped'=>0,'disabled_scopes'=>0);
$queries = array(
    'current_rates'=>"SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".(int)$conf->entity." AND status=1 AND is_current=1 AND cost_price>=".price2num((float)($conf->global->CHINAPLANETSP_IGNORE_BELOW??1000)),
    'batches'=>"SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE entity=".(int)$conf->entity,
    'active_rules'=>"SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."chinaplanetsp_rule WHERE entity=".(int)$conf->entity." AND status=1",
    'unmapped'=>"SELECT COUNT(DISTINCT pod) total FROM ".MAIN_DB_PREFIX."chinaplanetsp_rate WHERE entity=".(int)$conf->entity." AND (destination_country IS NULL OR destination_country='')",
    'disabled_scopes'=>"SELECT COUNT(*) total FROM ".MAIN_DB_PREFIX."chinaplanetsp_block WHERE entity=".(int)$conf->entity." AND status=1 AND (date_end IS NULL OR date_end>=CURDATE())",
);
foreach($queries as $key=>$sql){$res=$db->query($sql);if($res&&($obj=$db->fetch_object($res)))$stats[$key]=(int)$obj->total;}

$css=array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetshippingprices/css/shippingprices.css'));
llxHeader('','Shipping Prices Control Center','','',0,0,array(),$css);
cpcc_admin_shell_open('shippingprices','Shipping Prices Control Center / مركز إدارة أسعار الشحن','إدارة الملفات والأسعار والقواعد والتكاليف والتحذيرات','fa-sliders-h');
?>
<main class="chinaplanetsp-admin-dashboard">
<header class="chinaplanetsp-admin-hero"><div><span>ADMIN & INTERNAL OPERATIONS</span><h1>Full pricing control / التحكم الكامل بالأسعار</h1><p>Upload agent workbooks, review every import, manage margins, EXW, dangerous cargo, documents and destination fees.</p></div><a class="button" href="<?php echo dol_buildpath('/chinaplanetshippingprices/index.php',1); ?>"><span class="fas fa-search"></span> Open inquiry / فتح الاستعلام</a></header>
<section class="chinaplanetsp-admin-stats">
 <div><span>Current rates</span><strong><?php echo $stats['current_rates']; ?></strong><small>Above <?php echo price((float)($conf->global->CHINAPLANETSP_IGNORE_BELOW??1000)); ?> USD</small></div>
 <div><span>Import batches</span><strong><?php echo $stats['batches']; ?></strong><small>Draft and published files</small></div>
 <div><span>Active price rules</span><strong><?php echo $stats['active_rules']; ?></strong><small>Fees, cargo and documents</small></div>
 <div class="<?php echo $stats['unmapped']?'warn':''; ?>"><span>Unmapped ports</span><strong><?php echo $stats['unmapped']; ?></strong><small>Need country mapping</small></div>
</section>
<section class="chinaplanetsp-admin-actions">
<?php if($canImport){ ?><a href="import.php"><span class="fas fa-file-upload"></span><strong>Upload prices file</strong><small>Prepare XLS, XLSX or CSV as a draft before publishing.</small></a><a href="import.php#batches"><span class="fas fa-history"></span><strong>File & import history</strong><small>Review accepted, ignored and warning rows for every sheet.</small></a><?php } ?>
<?php if($canAdmin){ ?><a class="featured" href="adjust.php"><span class="fas fa-table"></span><strong>Manage shipping rates</strong><small>Show prices first, then add, edit or safely delete individual rate rows.</small></a><a href="disabled.php"><span class="fas fa-ban"></span><strong>Disabled prices</strong><small>Pause or restore a country, POD, POL, line, container or service. <?php echo $stats['disabled_scopes']; ?> active.</small></a><a href="rules.php"><span class="fas fa-layer-group"></span><strong>Pricing rules & cases</strong><small>Add or edit EXW, battery, dangerous goods, POD and document fees.</small></a><a href="setup.php"><span class="fas fa-coins"></span><strong>Margins & thresholds</strong><small>Container profit, USD threshold and customer history settings.</small></a><a href="ports.php"><span class="fas fa-map-marker-alt"></span><strong>Ports, countries & POL</strong><small>Map destination countries and assign UNKNOWN loading ports individually or in bulk.</small></a><?php } ?>
</section>
<section class="chinaplanetsp-admin-columns">
<div class="chinaplanetsp-admin-panel"><h2><span class="fas fa-file-alt"></span> Latest files / آخر الملفات</h2><div class="div-table-responsive"><table class="noborder centpercent"><tr class="liste_titre"><th>File / sheet</th><th>Effective</th><th>Status</th><th></th></tr><?php
$res=$db->query("SELECT rowid,original_name,source_sheet,effective_date,status FROM ".MAIN_DB_PREFIX."chinaplanetsp_batch WHERE entity=".(int)$conf->entity." ORDER BY rowid DESC LIMIT 8");
if($res)while($b=$db->fetch_object($res)){echo '<tr><td>'.dol_escape_htmltag($b->original_name).'<small>'.dol_escape_htmltag($b->source_sheet).'</small></td><td>'.dol_escape_htmltag($b->effective_date).'</td><td>'.((int)$b->status===2?'Published':'Draft').'</td><td>'.($canImport?'<a href="batch.php?id='.(int)$b->rowid.'">Review</a>':'—').'</td></tr>';}
?></table></div></div>
<div class="chinaplanetsp-admin-panel"><h2><span class="fas fa-tags"></span> Active cases / الحالات النشطة</h2><div class="chinaplanetsp-rule-preview"><?php
$res=$db->query("SELECT rowid,label,public_label,component_type,amount,currency_code,calc_type FROM ".MAIN_DB_PREFIX."chinaplanetsp_rule WHERE entity=".(int)$conf->entity." AND status=1 ORDER BY priority DESC,rowid DESC LIMIT 10");
if($res&&$db->num_rows($res)){while($rule=$db->fetch_object($res)){echo '<a href="rules.php?edit='.(int)$rule->rowid.'"><span>'.dol_escape_htmltag($rule->component_type).'</span><strong>'.dol_escape_htmltag($rule->label).'</strong><small>'.dol_escape_htmltag($rule->public_label).' · '.price($rule->amount).' '.dol_escape_htmltag($rule->calc_type==='percent'?'%':$rule->currency_code).'</small></a>';}}else echo '<p class="opacitymedium">No active rules yet.</p>';
?></div></div>
</section>
</main>
<?php cpcc_admin_shell_close();llxFooter();$db->close();
