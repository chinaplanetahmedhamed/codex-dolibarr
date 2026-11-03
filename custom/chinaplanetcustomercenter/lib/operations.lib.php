<?php
require_once __DIR__.'/ui.lib.php';

require_once __DIR__.'/financial.lib.php';
require_once __DIR__.'/export.lib.php';
require_once __DIR__.'/../../chinaplanetlogistics/lib/chinaplanetlogistics.lib.php';

function cpcc_project_lifecycle($db, $socid, $projectid, $currencyFallback)
{
    $orderConfirmed = false;
    $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."commande WHERE entity=".(int)$GLOBALS['conf']->entity." AND fk_soc=".(int)$socid." AND fk_projet=".(int)$projectid." AND fk_statut>0 LIMIT 1");
    $orderConfirmed = $res && $db->num_rows($res) > 0;

    $charges = cpcc_financial_commitment_rows($db, $socid, $currencyFallback, $projectid);
    $supplierCharges = array_values(array_filter($charges, function ($row) { return !empty($row['is_supplier']); }));
    $supplierPaidAny = false;
    $supplierFullyPaid = !empty($supplierCharges);
    foreach ($supplierCharges as $charge) {
        if ($charge['paid'] > 0.005) $supplierPaidAny = true;
        if ($charge['remaining'] > 0.005) $supplierFullyPaid = false;
    }
    // M082 native supplier evidence supersedes the legacy manual commitment
    // projection when a guided supplier invoice exists. Only boolean lifecycle
    // state leaves this server-side query; supplier identity and cost stay private.
    $supplierMarkerReady = false;
    $tableCheck = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.'cpo_supplier_action')."'");
    if ($tableCheck && $db->num_rows($tableCheck) === 1) {
        $sql = "SELECT f.rowid,CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN f.multicurrency_total_ttc ELSE f.total_ttc END invoice_total,";
        $sql .= "COALESCE((SELECT SUM(CASE WHEN TRIM(COALESCE(f.multicurrency_code,''))<>'' THEN pf.multicurrency_amount ELSE pf.amount END) FROM ".MAIN_DB_PREFIX."paiementfourn_facturefourn pf WHERE pf.fk_facturefourn=f.rowid),0) paid_total ";
        $sql .= "FROM ".MAIN_DB_PREFIX."cpo_supplier_action a JOIN ".MAIN_DB_PREFIX."facture_fourn f ON f.rowid=a.fk_supplier_invoice AND f.entity=a.entity ";
        $sql .= "WHERE a.entity=".(int)$GLOBALS['conf']->entity." AND a.fk_soc_customer=".(int)$socid." AND a.fk_project=".(int)$projectid." AND a.action_type='supplier_invoice' AND a.status='confirmed' AND f.fk_statut>0";
        $res = $db->query($sql);
        $nativeCount = 0; $nativePaidAny = false; $nativeAllPaid = true;
        while ($res && ($native = $db->fetch_object($res))) {
            $nativeCount++;
            if ((float)$native->paid_total > 0.005) $nativePaidAny = true;
            if ((float)$native->invoice_total - (float)$native->paid_total > 0.005) $nativeAllPaid = false;
        }
        if ($nativeCount > 0) {
            $supplierMarkerReady = true;
            $supplierPaidAny = $nativePaidAny;
            $supplierFullyPaid = $nativeAllPaid;
        }
    }

    $invoiceCount = 0;
    $invoiceOpen = 0;
    $res = $db->query("SELECT COUNT(*) total,SUM(CASE WHEN fk_statut=1 AND paye=0 THEN 1 ELSE 0 END) open_count FROM ".MAIN_DB_PREFIX."facture WHERE entity=".(int)$GLOBALS['conf']->entity." AND fk_soc=".(int)$socid." AND fk_projet=".(int)$projectid." AND fk_statut>0 AND type IN (0,1,5)");
    if ($res && ($obj=$db->fetch_object($res))) {$invoiceCount=(int)$obj->total;$invoiceOpen=(int)$obj->open_count;}

    $shipmentCount = 0;
    $shipmentClosed = 0;
    $res = $db->query("SELECT COUNT(*) total,SUM(CASE WHEN fk_statut IN (".chinaplanetlogistics_status_sql_list('terminal').") THEN 1 ELSE 0 END) closed_count FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE entity=".(int)$GLOBALS['conf']->entity." AND fk_soc=".(int)$socid." AND fk_project=".(int)$projectid);
    if ($res && ($obj=$db->fetch_object($res))) {$shipmentCount=(int)$obj->total;$shipmentClosed=(int)$obj->closed_count;}

    $documents = cpcc_export_documents($db,$socid,$projectid,0,true);
    $documentCount = count($documents);
    $hasCommercialInvoice = false;
    $hasPackingList = false;
    foreach ($documents as $document) {
        if ($document->document_type === 'commercial_invoice') $hasCommercialInvoice = true;
        if ($document->document_type === 'packing_list') $hasPackingList = true;
    }
    $documentsComplete = $hasCommercialInvoice && $hasPackingList;
    $funded = false;
    if (cpcc_financial_schema_ready($db)) {
        $res = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpcc_financial_entry WHERE entity=".(int)$GLOBALS['conf']->entity." AND fk_soc=".(int)$socid." AND fk_project=".(int)$projectid." AND entry_type IN ('customer_receipt','opening_balance') AND direction='C' AND status=1 LIMIT 1");
        $funded = $res && $db->num_rows($res)>0;
    }

    return array(
        array('key'=>'confirmed','label'=>cpcc_ui_english_label('Order confirmed', 'تم تأكيد الطلب'),'en'=>'Order confirmed','done'=>$orderConfirmed,'rule'=>cpcc_ui_english_label('Validated customer order linked to project', 'طلب عميل معتمد مرتبط بالمشروع / Validated customer order linked to project')),
        array('key'=>'funded','label'=>cpcc_ui_english_label('Customer funds received', 'تم استلام تمويل العميل'),'en'=>'Customer funds received','done'=>$funded,'rule'=>cpcc_ui_english_label('Project-linked receipt or opening balance', 'حركة استلام أو رصيد افتتاحي مرتبطة بالمشروع / Project-linked receipt or opening balance')),
        array('key'=>'deposit','label'=>cpcc_ui_english_label('Supplier deposit paid', 'تم دفع عربون المورد'),'en'=>'Supplier deposit paid','done'=>$supplierPaidAny,'rule'=>cpcc_ui_english_label('Any amount allocated to a supplier commitment', 'أي مبلغ مخصص لالتزام مورد / Any amount allocated to a supplier commitment')),
        array('key'=>'supplier_paid','label'=>cpcc_ui_english_label('Supplier fully paid', 'اكتمل دفع المورد'),'en'=>'Supplier fully paid','done'=>$supplierFullyPaid,'rule'=>cpcc_ui_english_label('All supplier commitments have zero remaining', 'كل التزامات الموردين بلا رصيد متبقٍ / All supplier commitments have zero remaining')),
        array('key'=>'service_invoice','label'=>cpcc_ui_english_label('Invoice issued', 'صدرت الفاتورة'),'en'=>'Invoice issued','done'=>$invoiceCount>0,'rule'=>cpcc_ui_english_label('Validated customer invoice linked to project', 'فاتورة عميل معتمدة مرتبطة بالمشروع / Validated customer invoice linked to project')),
        array('key'=>'shipment','label'=>cpcc_ui_english_label('Shipment created', 'تم إنشاء الشحنة'),'en'=>'Shipment created','done'=>$shipmentCount>0,'rule'=>cpcc_ui_english_label('Shipment linked to customer and project', 'وجود شحنة مرتبطة بالعميل والمشروع / Shipment linked to customer and project')),
        array('key'=>'documents','label'=>cpcc_ui_english_label('Documents complete', 'اكتملت المستندات'),'en'=>'Documents complete','done'=>$documentsComplete,'rule'=>cpcc_ui_english_label('Customer-visible Commercial Invoice and Packing List', 'فاتورة تجارية وقائمة تعبئة ظاهرتان للعميل / Customer-visible Commercial Invoice and Packing List')),
        array('key'=>'closed','label'=>cpcc_ui_english_label('Operation complete', 'تمت العملية'),'en'=>'Operation finished','done'=>$orderConfirmed&&$funded&&$supplierFullyPaid&&$invoiceCount>0&&$invoiceOpen===0&&$shipmentCount>0&&$shipmentClosed===$shipmentCount&&$documentsComplete,'rule'=>cpcc_ui_english_label('All requirements complete', 'اكتمال كل المتطلبات وإغلاق كل الشحنات وسداد الفواتير / All requirements complete')),
    );
}
