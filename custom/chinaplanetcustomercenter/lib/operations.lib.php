<?php

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
        array('key'=>'confirmed','label'=>'تم تأكيد الطلب','en'=>'Order confirmed','done'=>$orderConfirmed,'rule'=>'طلب عميل معتمد مرتبط بالمشروع / Validated customer order linked to project'),
        array('key'=>'funded','label'=>'تم استلام تمويل العميل','en'=>'Customer funds received','done'=>$funded,'rule'=>'حركة استلام أو رصيد افتتاحي مرتبطة بالمشروع / Project-linked receipt or opening balance'),
        array('key'=>'deposit','label'=>'تم دفع عربون المورد','en'=>'Supplier deposit paid','done'=>$supplierPaidAny,'rule'=>'أي مبلغ مخصص لالتزام مورد / Any amount allocated to a supplier commitment'),
        array('key'=>'supplier_paid','label'=>'اكتمل دفع المورد','en'=>'Supplier fully paid','done'=>$supplierFullyPaid,'rule'=>'كل التزامات الموردين بلا رصيد متبقٍ / All supplier commitments have zero remaining'),
        array('key'=>'service_invoice','label'=>'صدرت فاتورة خدمات Vision','en'=>'Vision service invoice issued','done'=>$invoiceCount>0,'rule'=>'فاتورة عميل معتمدة مرتبطة بالمشروع / Validated customer invoice linked to project'),
        array('key'=>'shipment','label'=>'تم إنشاء الشحنة','en'=>'Shipment created','done'=>$shipmentCount>0,'rule'=>'وجود شحنة مرتبطة بالعميل والمشروع / Shipment linked to customer and project'),
        array('key'=>'documents','label'=>'اكتملت مستندات التصدير','en'=>'Export documents complete','done'=>$documentsComplete,'rule'=>'فاتورة تجارية وقائمة تعبئة ظاهرتان للعميل / Customer-visible Commercial Invoice and Packing List'),
        array('key'=>'closed','label'=>'أُغلقت العملية','en'=>'Operation closed','done'=>$orderConfirmed&&$funded&&$supplierFullyPaid&&$invoiceCount>0&&$invoiceOpen===0&&$shipmentCount>0&&$shipmentClosed===$shipmentCount&&$documentsComplete,'rule'=>'اكتمال كل المتطلبات وإغلاق كل الشحنات وسداد الفواتير / All requirements complete'),
    );
}
