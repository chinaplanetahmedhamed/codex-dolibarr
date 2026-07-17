<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/admin_shell.lib.php';
require_once __DIR__.'/lib/portal.lib.php';
require_once __DIR__.'/lib/export.lib.php';

cpcc_portal_require_access($user);
$scope = cpcc_portal_user_scope($user);
$isExternal = !empty($scope['is_external']);
$socid = cpcc_portal_customer_scope_id($db, $user, GETPOSTINT('socid'));
$id = GETPOSTINT('id');
$document = $socid > 0 ? cpcc_export_fetch($db, $id, $socid, $isExternal) : null;
if (!$document) cpcc_portal_access_denied($user, 'commercial_documents');
$totals = cpcc_export_totals($document);

llxHeader('', cpcc_export_type_label($document->document_type), '', '', 0, 0, array(), $isExternal?array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/export.css'):array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/export.css',cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
if ($isExternal) cpcc_portal_navigation($user, 'commercial_documents');
else cpcc_admin_shell_open('export', cpcc_export_type_label($document->document_type), 'معاينة وطباعة مستند التصدير', 'fa-file-export', array('socid'=>$socid,'projectid'=>(int)$document->fk_project));
$backUrl = cpcc_portal_scoped_url('/chinaplanetcustomercenter/commercial_documents.php',$isExternal,$socid);
?>
<div class="cpcc-print-actions" dir="rtl"><a href="<?php echo dol_escape_htmltag($backUrl); ?>">العودة للمستندات</a><button type="button" onclick="window.print()">طباعة / حفظ PDF</button></div>
<article class="cpcc-export-sheet" dir="ltr">
  <header class="cpcc-export-title"><div><span>VISION FOR IMPORT AND EXPORT LIMITED</span><h1><?php echo dol_escape_htmltag(strtoupper(cpcc_export_type_label($document->document_type))); ?></h1></div><div><b><?php echo dol_escape_htmltag($document->ref); ?></b><br><?php echo dol_escape_htmltag(dol_print_date($document->document_date,'day')); ?></div></header>
  <section class="cpcc-export-meta">
    <div><span>Shipper / Exporter</span><b><?php echo dol_escape_htmltag($document->shipper_name ?: 'Vision for Import and Export Limited'); ?></b></div>
    <div><span>Consignee</span><b><?php echo dol_escape_htmltag($document->consignee_name); ?></b></div>
    <div><span>Project</span><b><?php echo dol_escape_htmltag(trim($document->project_ref.' '.$document->project_title)); ?></b></div>
    <div><span>Shipment / Container / BL</span><b><?php echo dol_escape_htmltag(trim($document->shipment_ref.' '.$document->container_no.' '.$document->bl_no)); ?></b></div>
    <div><span>Port of Loading</span><b><?php echo dol_escape_htmltag($document->port_loading); ?></b></div>
    <div><span>Port of Destination</span><b><?php echo dol_escape_htmltag($document->port_destination); ?></b></div>
    <div><span>Incoterm</span><b><?php echo dol_escape_htmltag($document->incoterm); ?></b></div>
    <div><span>Currency</span><b><?php echo dol_escape_htmltag($document->currency_code); ?></b></div>
  </section>
  <table class="cpcc-export-table"><thead><tr><th>#</th><th>Description</th><th>HS Code</th><th>Qty</th><th>Unit</th><?php if($document->document_type==='commercial_invoice'){ ?><th>Unit Price</th><th>Amount</th><?php }else{ ?><th>Cartons</th><th>N.W.</th><th>G.W.</th><?php } ?><th>Origin</th></tr></thead><tbody>
  <?php foreach ($document->lines as $index=>$line) { ?><tr><td><?php echo $index+1; ?></td><td><?php echo dol_escape_htmltag($line->description); ?></td><td><?php echo dol_escape_htmltag($line->hs_code); ?></td><td><?php echo cpcc_portal_money($line->quantity); ?></td><td><?php echo dol_escape_htmltag($line->unit); ?></td><?php if($document->document_type==='commercial_invoice'){ ?><td><?php echo cpcc_portal_money($line->unit_price); ?></td><td><?php echo cpcc_portal_money($line->total_amount); ?></td><?php }else{ ?><td><?php echo cpcc_portal_money($line->cartons); ?></td><td><?php echo cpcc_portal_money($line->net_weight); ?></td><td><?php echo cpcc_portal_money($line->gross_weight); ?></td><?php } ?><td><?php echo dol_escape_htmltag($line->country_origin); ?></td></tr><?php } ?>
  </tbody></table>
  <?php if($document->document_type==='commercial_invoice'){ ?><div class="cpcc-export-total"><span>Total:</span><b><?php echo cpcc_portal_money($totals['amount']).' '.dol_escape_htmltag($document->currency_code); ?></b></div><?php }else{ ?><div class="cpcc-export-total"><span>Cartons: <b><?php echo cpcc_portal_money($totals['cartons']); ?></b></span><span>Net: <b><?php echo cpcc_portal_money($totals['net_weight']); ?> KG</b></span><span>Gross: <b><?php echo cpcc_portal_money($totals['gross_weight']); ?> KG</b></span></div><?php } ?>
  <?php if(!empty($document->notes_public)){ ?><p><?php echo nl2br(dol_escape_htmltag($document->notes_public)); ?></p><?php } ?>
</article>
<?php if(!$isExternal)cpcc_admin_shell_close();llxFooter();
