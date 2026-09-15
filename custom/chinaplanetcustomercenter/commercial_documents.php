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
$projectid = GETPOSTINT('projectid');
$documents = $socid > 0 ? cpcc_export_documents($db, $socid, $projectid, 0, $isExternal) : array();

llxHeader('', 'Commercial Documents / مستندات التصدير', '', '', 0, 0, array(), $isExternal?array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/export.css'):array('/custom/chinaplanetcustomercenter/css/customer_dashboard.css','/custom/chinaplanetcustomercenter/css/export.css',cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css')));
if($isExternal) cpcc_portal_navigation($user, 'commercial_documents'); else cpcc_admin_shell_open('export','Commercial Documents / المستندات التجارية','المستندات المنشأة والمتاحة للعملاء','fa-file-export',array('socid'=>$socid,'projectid'=>$projectid));
?>
<main class="cpcc-dashboard" dir="rtl">
  <header class="cpcc-page-head"><div><span class="cpcc-eyebrow">EXPORT DOCUMENTS</span><h1>المستندات التجارية والتصديرية</h1><p>Commercial Invoice وPacking List والمستندات المرتبطة بالمشروع والشحنة.</p></div></header>
  <?php cpcc_portal_customer_selector($db,$user,$socid); ?>
  <?php if ($socid <= 0) { ?><div class="warning">لا يوجد عميل محدد.</div>
  <?php } elseif (!cpcc_export_schema_ready($db)) { ?><div class="warning">جداول مستندات التصدير لم تُفعّل بعد.</div>
  <?php } elseif (empty($documents)) { ?><div class="cpcc-empty">لا توجد مستندات تجارية متاحة لهذا العميل.</div>
  <?php } else { ?><section class="cpcc-document-grid">
    <?php foreach ($documents as $document) {
        $url = cpcc_portal_scoped_url('/chinaplanetcustomercenter/export_document.php',$isExternal,$socid,array('id'=>(int)$document->rowid)); ?>
      <a class="cpcc-document-card" href="<?php echo dol_escape_htmltag($url); ?>"><span><?php echo dol_escape_htmltag(cpcc_export_type_label($document->document_type)); ?></span><strong><?php echo dol_escape_htmltag($document->ref); ?></strong><small><?php echo dol_escape_htmltag($document->project_ref ?: 'بدون مشروع'); ?> · <?php echo dol_escape_htmltag(cpcc_portal_date($document->document_date,'day')); ?></small><?php if(!empty($document->shipment_ref)){ ?><small>Shipment: <?php echo dol_escape_htmltag($document->shipment_ref); ?></small><?php } ?></a>
    <?php } ?>
  </section><?php } ?>
</main>
<?php if(!$isExternal) cpcc_admin_shell_close(); llxFooter();
