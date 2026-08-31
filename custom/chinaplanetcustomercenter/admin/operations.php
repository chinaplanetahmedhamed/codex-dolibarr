<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../lib/admin_shell.lib.php';
require_once __DIR__.'/../lib/portal.lib.php';
require_once __DIR__.'/../lib/financial.lib.php';
require_once __DIR__.'/../lib/export.lib.php';
require_once __DIR__.'/../lib/operations.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
if (empty($user->id) || !empty($user->socid) || (!$user->admin && !$user->hasRight('chinaplanetcustomercenter','write'))) accessforbidden();

$socid=cpcc_portal_customer_scope_id($db,$user,GETPOSTINT('socid'));$projectid=GETPOSTINT('projectid');$form=new Form($db);
$customerOptions=array();$res=$db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom");if($res)while($o=$db->fetch_object($res))$customerOptions[(int)$o->rowid]=$o->nom;
$projectOptions=array();if($socid>0){$res=$db->query("SELECT rowid,ref,title FROM ".MAIN_DB_PREFIX."projet WHERE entity=".(int)$conf->entity." AND fk_soc=".$socid." ORDER BY fk_statut DESC,ref DESC");if($res)while($o=$db->fetch_object($res))$projectOptions[(int)$o->rowid]=$o->ref.' — '.$o->title;}
$project=null;if($projectid>0&&$socid>0){$res=$db->query("SELECT * FROM ".MAIN_DB_PREFIX."projet WHERE rowid=".$projectid." AND fk_soc=".$socid);$project=$res?$db->fetch_object($res):null;}
$currencyFallback=cpcc_financial_currency(!empty($conf->currency)?$conf->currency:'USD');

$supplierOrders=array();$supplierInvoices=array();$customerOrders=array();$customerInvoices=array();$shipments=array();$lifecycle=array();
if($project){
 $res=$db->query("SELECT rowid,ref,date_commande,fk_statut,total_ttc,multicurrency_code,multicurrency_total_ttc FROM ".MAIN_DB_PREFIX."commande WHERE entity=".(int)$conf->entity." AND fk_soc=".$socid." AND fk_projet=".$projectid." ORDER BY rowid DESC");if($res)while($o=$db->fetch_object($res))$customerOrders[]=$o;
 $res=$db->query("SELECT o.rowid,o.ref,o.ref_supplier,o.date_commande,o.fk_statut,o.multicurrency_code,o.multicurrency_total_ttc,o.total_ttc,s.nom supplier FROM ".MAIN_DB_PREFIX."commande_fournisseur o JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=o.fk_soc WHERE o.entity=".(int)$conf->entity." AND o.fk_projet=".$projectid." ORDER BY o.rowid");if($res)while($o=$db->fetch_object($res))$supplierOrders[]=$o;
 $res=$db->query("SELECT f.rowid,f.ref,f.ref_supplier,f.datef,f.fk_statut,f.paye,f.multicurrency_code,f.multicurrency_total_ttc,f.total_ttc,s.nom supplier,COALESCE(p.paid,0) paid FROM ".MAIN_DB_PREFIX."facture_fourn f JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid=f.fk_soc LEFT JOIN (SELECT fk_facturefourn,SUM(multicurrency_amount) paid FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn GROUP BY fk_facturefourn) p ON p.fk_facturefourn=f.rowid WHERE f.entity=".(int)$conf->entity." AND f.fk_projet=".$projectid." ORDER BY f.rowid");if($res)while($o=$db->fetch_object($res))$supplierInvoices[]=$o;
 $res=$db->query("SELECT rowid,ref,datef,fk_statut,paye,multicurrency_code,multicurrency_total_ttc,total_ttc FROM ".MAIN_DB_PREFIX."facture WHERE entity=".(int)$conf->entity." AND fk_soc=".$socid." AND fk_projet=".$projectid." ORDER BY rowid");if($res)while($o=$db->fetch_object($res))$customerInvoices[]=$o;
 $res=$db->query("SELECT rowid,ref,container_no,bl_no,date_eta,fk_statut FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int)$conf->entity." AND fk_soc=".$socid." AND fk_project=".$projectid." ORDER BY rowid");if($res)while($o=$db->fetch_object($res))$shipments[]=$o;
 $lifecycle=cpcc_project_lifecycle($db,$socid,$projectid,$currencyFallback);
}

llxHeader('', 'Operations Center / مركز العمليات', '', '', 0, 0, array(), array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/customer_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/operations.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
cpcc_admin_shell_open('operations', 'Operations Center / مركز العمليات', 'متابعة دورة الطلب من التمويل ودفع المورد حتى الشحن والإغلاق', 'fa-route', array('socid'=>$socid,'projectid'=>$projectid));
?>
<main class="cpcc-dashboard" dir="rtl">
<form method="get" class="cpad-context-form"><div class="cpad-context-fields">
<label><span>العميل / Customer</span><?php echo Form::selectarray('socid',$customerOptions,$socid,'ابحث باسم العميل',0,0,'',0,0,0,'','minwidth300',1); ?></label>
<label><span>المشروع / Project</span><?php echo Form::selectarray('projectid',$projectOptions,$projectid,'اختر المشروع / Select project',0,0,'',0,0,0,'','minwidth300',1); ?></label>
<button class="button" type="submit">فتح العملية / Open</button>
</div></form>
<?php if(!$project){ ?><div class="info">اختر العميل ثم المشروع لمراجعة دورة العمل الكاملة.</div><?php }else{ ?>
<header class="cpcc-page-head"><div><span class="cpcc-eyebrow"><?php echo dol_escape_htmltag($project->ref); ?></span><h1><?php echo dol_escape_htmltag($project->title); ?></h1><p><?php echo dol_escape_htmltag($project->description); ?></p></div></header>
<div class="cpcc-lifecycle-note"><span class="fas fa-sync-alt"></span> الحالة محسوبة تلقائيًا من السجلات المرتبطة / Status is calculated automatically from linked records</div>
<section class="cpcc-lifecycle"><?php foreach($lifecycle as $stage){ ?><div class="cpcc-stage <?php echo $stage['done']?'done':''; ?>" title="<?php echo dol_escape_htmltag($stage['rule']); ?>" tabindex="0"><b><?php echo dol_escape_htmltag($stage['label']); ?></b><small><?php echo dol_escape_htmltag($stage['en']); ?></small></div><?php } ?></section>
<div class="cpcc-quick-actions"><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/admin/financial.php',1).'?socid='.$socid.'&projectid='.$projectid; ?>">تسجيل تمويل/دفعة / Record payment</a><a target="_blank" rel="noopener" href="<?php echo dol_buildpath('/compta/facture/card.php',1).'?action=create&socid='.$socid.'&projectid='.$projectid; ?>">إنشاء فاتورة في Vision System ↗ / Create invoice</a><a href="<?php echo dol_buildpath('/chinaplanetlogistics/card.php',1).'?action=create&socid='.$socid.'&projectid='.$projectid; ?>">إنشاء شحنة / New shipment</a><a href="<?php echo dol_buildpath('/chinaplanetcustomercenter/project.php',1).'?id='.$projectid.'&socid='.$socid; ?>">معاينة بوابة العميل / Portal preview</a></div>
<section class="cpcc-order-status"><h3>حالة طلب العميل / Customer Order Status</h3><p><a class="button" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/orders.php',1).'?socid='.$socid; ?>">عرض كل طلبات العميل وتغيير الحالة / View all customer orders</a></p>
<?php if(empty($customerOrders)){ ?><p>لا يوجد طلب عميل مرتبط بهذا المشروع / No customer order is linked to this project.</p><?php } ?>
<?php $orderStatus=array(-1=>'ملغي / Canceled',0=>'مسودة / Draft',1=>'معتمد / Validated',2=>'قيد التنفيذ / In progress',3=>'مغلق / Closed'); foreach($customerOrders as $o){ ?>
<div class="cpcc-order-row"><span><strong><?php echo dol_escape_htmltag($o->ref); ?></strong> — <?php echo dol_escape_htmltag(isset($orderStatus[(int)$o->fk_statut])?$orderStatus[(int)$o->fk_statut]:(string)$o->fk_statut); ?></span><a class="button" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/order.php',1).'?id='.(int)$o->rowid.'&socid='.$socid; ?>">تفاصيل الطلب / Order details</a></div>
<?php } ?></section>
<section class="cpcc-admin-grid">
<div class="cpcc-admin-panel"><h3>أوامر الموردين / Supplier Orders</h3><?php if(empty($supplierOrders))echo '<p>لا توجد أوامر موردين / No supplier orders.</p>';foreach($supplierOrders as $o){$currency=cpcc_financial_currency($o->multicurrency_code,$currencyFallback);$amount=!empty($o->multicurrency_code)?$o->multicurrency_total_ttc:$o->total_ttc;echo '<p><strong>'.dol_escape_htmltag($o->ref).'</strong> — '.dol_escape_htmltag($o->supplier).' — '.cpcc_portal_money($amount).' '.dol_escape_htmltag($currency).' <a target="_blank" rel="noopener" href="'.dol_buildpath('/fourn/commande/card.php',1).'?id='.(int)$o->rowid.'">Vision Advanced ↗</a></p>';} ?></div>
<div class="cpcc-admin-panel"><h3>فواتير ومدفوعات الموردين / Supplier Invoices & Payments</h3><?php if(empty($supplierInvoices))echo '<p>لا توجد فواتير موردين / No supplier invoices.</p>';foreach($supplierInvoices as $o){$currency=cpcc_financial_currency($o->multicurrency_code,$currencyFallback);$amount=!empty($o->multicurrency_code)?$o->multicurrency_total_ttc:$o->total_ttc;echo '<p><strong>'.dol_escape_htmltag($o->ref_supplier).'</strong> — '.dol_escape_htmltag($o->supplier).' — '.cpcc_portal_money($o->paid).' / '.cpcc_portal_money($amount).' '.dol_escape_htmltag($currency).' <a target="_blank" rel="noopener" href="'.dol_buildpath('/fourn/facture/card.php',1).'?facid='.(int)$o->rowid.'">Vision Advanced ↗</a></p>';} ?></div>
<div class="cpcc-admin-panel"><h3>الفواتير / Invoices</h3><?php if(empty($customerInvoices))echo '<p>لا توجد فواتير / No invoices.</p>';foreach($customerInvoices as $o){$currency=cpcc_financial_currency($o->multicurrency_code,$currencyFallback);$amount=!empty($o->multicurrency_code)?$o->multicurrency_total_ttc:$o->total_ttc;echo '<p><a href="'.dol_buildpath('/chinaplanetcustomercenter/invoice.php',1).'?id='.(int)$o->rowid.'&socid='.$socid.'">'.dol_escape_htmltag($o->ref).'</a> — '.cpcc_portal_money($amount).' '.dol_escape_htmltag($currency).' — '.($o->paye?'Paid':'Open').'</p>';} ?></div>
<div class="cpcc-admin-panel"><h3>الشحنات والمستندات / Shipments & Documents</h3><?php if(empty($shipments))echo '<p>لا توجد شحنات / No shipments.</p>';foreach($shipments as $o)echo '<p><a href="'.dol_buildpath('/chinaplanetlogistics/card.php',1).'?id='.(int)$o->rowid.'">'.dol_escape_htmltag($o->ref).'</a> — '.dol_escape_htmltag($o->container_no).' — '.dol_escape_htmltag(chinaplanetlogistics_status_label($o->fk_statut)).' — ETA '.dol_escape_htmltag(cpcc_portal_date($o->date_eta)).'</p>';echo '<p><a href="'.dol_buildpath('/chinaplanetcustomercenter/documents.php',1).'?socid='.$socid.'">فتح المستندات / Open documents</a></p>'; ?></div>
</section>
<?php } ?></main><?php cpcc_admin_shell_close(); llxFooter();
