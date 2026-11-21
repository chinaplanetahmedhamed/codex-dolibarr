<?php
require_once __DIR__.'/../../chinaplanetlogistics/lib/shipment_customer.lib.php';
/**
 * Read-only customer project command-center aggregation and rendering.
 *
 * Every database query in this file is constrained by the current entity and
 * authenticated customer's third party.  The renderer accepts only the
 * already-owned card and customer-approved records returned by these helpers.
 */

function cpkanban_customer_timeline($visibleColumns, $currentColumnId)
{
    $timeline = array();
    $currentIndex = 0;
    $index = 0;
    foreach ($visibleColumns as $column) {
        if ((int) $column->rowid === (int) $currentColumnId) $currentIndex = $index;
        $index++;
    }

    $index = 0;
    foreach ($visibleColumns as $column) {
        $state = $index < $currentIndex ? 'done' : ($index === $currentIndex ? 'current' : 'future');
        $timeline[] = array(
            'id' => (int) $column->rowid,
            'label' => (string) $column->label,
            'label_en' => (string) $column->label_en,
            'color' => (string) $column->color,
            'state' => $state,
        );
        $index++;
    }
    return $timeline;
}

function cpkanban_customer_next_timeline_step($timeline)
{
    foreach ($timeline as $index => $step) {
        if ($step['state'] === 'current' && isset($timeline[$index + 1])) return $timeline[$index + 1];
    }
    return null;
}

function cpkanban_customer_project_shipments($db, $entity, $socid, $projectId, $directShipmentId)
{
    if ((int) $entity <= 0 || (int) $socid <= 0) return array();
    $links = array();
    if ((int) $projectId > 0) $links[] = 'l.fk_project='.(int) $projectId;
    if ((int) $directShipmentId > 0) $links[] = 'l.rowid='.(int) $directShipmentId;
    if (empty($links)) return array();

    $atdSelect = cpl_schema_has_column($db, 'chinaplanetlogistics', 'date_atd') ? 'l.date_atd' : 'NULL AS date_atd';
    $sql = "SELECT l.rowid,l.ref,l.entity,l.fk_soc,l.container_no,l.container_type,l.nb_containers,l.bl_no,l.vessel_name,l.port_loading,l.port_destination,l.date_etd,".$atdSelect.",l.date_eta,l.date_ata,l.freetime_end,l.fk_statut,l.tms";
    $sql .= " FROM ".MAIN_DB_PREFIX."chinaplanetlogistics l";
    $sql .= " WHERE l.entity=".(int) $entity." AND l.fk_soc=".(int) $socid." AND (".implode(' OR ', $links).")";
    $sql .= " ORDER BY l.date_eta ASC,l.rowid DESC";
    $rows = array();
    $resql = $db->query($sql);
    while ($resql && ($row = $db->fetch_object($resql))) {
        $events = cpl_shipment_events($db, $row, true);
        $row->customer_latest_update = cpl_latest_customer_update($events);
        $row->customer_eta_change = cpl_latest_eta_change($events);
        $rows[] = $row;
    }
    return $rows;
}

function cpkanban_customer_project_orders($db, $entity, $socid, $projectId, $currencyFallback)
{
    if ((int) $entity <= 0 || (int) $socid <= 0 || (int) $projectId <= 0) return array();
    $sql = "SELECT c.rowid,c.ref,c.date_commande,c.fk_statut,c.total_ttc,c.multicurrency_code,c.multicurrency_total_ttc";
    $sql .= " FROM ".MAIN_DB_PREFIX."commande c";
    $sql .= " WHERE c.entity=".(int) $entity." AND c.fk_soc=".(int) $socid." AND c.fk_projet=".(int) $projectId." AND c.fk_statut>0";
    $sql .= " ORDER BY c.date_commande DESC,c.rowid DESC";
    $rows = array();
    $resql = $db->query($sql);
    while ($resql && ($row = $db->fetch_object($resql))) {
        $hasMulti = trim((string) $row->multicurrency_code) !== '';
        $row->display_currency = $hasMulti ? (string) $row->multicurrency_code : (string) $currencyFallback;
        $row->display_total = $hasMulti ? (float) $row->multicurrency_total_ttc : (float) $row->total_ttc;
        $rows[] = $row;
    }
    return $rows;
}

function cpkanban_customer_project_assets($db, $entity, $socid, $cardId)
{
    if ((int) $entity <= 0 || (int) $socid <= 0 || (int) $cardId <= 0) return array('images'=>array(), 'documents'=>array());
    $sql = "SELECT a.rowid,a.asset_type,a.document_type,a.original_name,a.mime_type,a.file_size,a.caption,a.milestone,a.version_no,a.is_cover,a.tms";
    $sql .= " FROM ".MAIN_DB_PREFIX."cpkanban_asset a";
    $sql .= " WHERE a.entity=".(int) $entity." AND a.fk_soc=".(int) $socid." AND a.fk_card=".(int) $cardId;
    $sql .= " AND a.status=1 AND a.customer_visible=1 AND a.review_status IN ('approved','final_received')";
    $sql .= " AND a.asset_type IN ('image','document') ORDER BY a.is_cover DESC,a.tms DESC,a.rowid DESC";
    $rows = array('images'=>array(), 'documents'=>array());
    $resql = $db->query($sql);
    while ($resql && ($row = $db->fetch_object($resql))) {
        $rows[$row->asset_type === 'image' ? 'images' : 'documents'][] = $row;
    }
    return $rows;
}

function cpkanban_customer_invoice_totals($invoices)
{
    $totals = array();
    foreach ($invoices as $invoice) {
        $currency = (string) $invoice['currency'];
        if (!isset($totals[$currency])) $totals[$currency] = array('total'=>0.0, 'paid'=>0.0, 'remaining'=>0.0, 'count'=>0);
        $totals[$currency]['total'] += (float) $invoice['total'];
        $totals[$currency]['paid'] += (float) $invoice['paid'];
        $totals[$currency]['remaining'] += (float) $invoice['remaining'];
        $totals[$currency]['count']++;
    }
    return $totals;
}

function cpkanban_customer_latest_update($db, $card, $logs, $reports)
{
    $latest = array(
        'timestamp' => !empty($card->date_creation) ? (int) $card->date_creation : 0,
        'title' => 'تم إنشاء بطاقة المشروع',
        'summary' => '',
        'actor' => 'فريق China Planet',
    );
    if (!empty($logs[0])) {
        $log = $logs[0];
        $timestamp = $db->jdate($log->date_action);
        if ($timestamp >= $latest['timestamp']) {
            $latest = array(
                'timestamp' => $timestamp,
                'title' => $log->action_type === 'move' ? 'تم تحديث حالة المشروع' : 'تم تحديث بطاقة المشروع',
                'summary' => '',
                'actor' => !empty($log->public_actor) ? $log->public_actor : 'فريق China Planet',
            );
        }
    }
    if (!empty($reports[0])) {
        $report = $reports[0];
        $timestamp = !empty($report['tms']) ? strtotime($report['tms']) : 0;
        if ($timestamp >= $latest['timestamp']) {
            $latest = array(
                'timestamp' => $timestamp,
                'title' => (string) $report['title'],
                'summary' => (string) $report['note_public'],
                'actor' => 'فريق China Planet',
            );
        }
    }
    return $latest;
}

function cpkanban_customer_project_center_data($db, $card, $visibleColumns, $displayColumnId)
{
    global $conf;
    $entity = (int) $conf->entity;
    $socid = (int) $card->fk_soc;
    $projectId = (int) $card->fk_project;
    $currency = cpcc_financial_currency(!empty($conf->currency) ? $conf->currency : 'USD');
    $timeline = cpkanban_customer_timeline($visibleColumns, $displayColumnId);
    $reports = $projectId > 0 ? cpcc_operation_reports($db, $socid, $projectId) : array();
    $invoices = $projectId > 0 ? cpcc_financial_customer_invoices($db, $socid, $currency, $projectId) : array();
    $operationFiles = array();
    foreach ($reports as $report) {
        foreach (cpcc_operation_report_documents($db, (int) $report['rowid']) as $file) {
            $file['operation_title'] = $report['title'];
            $operationFiles[] = $file;
        }
    }

    $logs = $card->getLogs(1);
    $nextTimeline = cpkanban_customer_next_timeline_step($timeline);
    $nextAction = !empty($reports[0]['next_action']) ? (string) $reports[0]['next_action'] : ($nextTimeline ? $nextTimeline['label'] : 'اكتملت المراحل الحالية');
    $expected = !empty($reports[0]['date_deadline']) ? $reports[0]['date_deadline'] : $card->date_deadline;

    return array(
        'timeline' => $timeline,
        'next_action' => $nextAction,
        'expected_date' => $expected,
        'latest_update' => cpkanban_customer_latest_update($db, $card, $logs, $reports),
        'assets' => cpkanban_customer_project_assets($db, $entity, $socid, (int) $card->id),
        'shipments' => cpkanban_customer_project_shipments($db, $entity, $socid, $projectId, (int) $card->fk_shipment),
        'orders' => cpkanban_customer_project_orders($db, $entity, $socid, $projectId, $currency),
        'invoices' => $invoices,
        'invoice_totals' => cpkanban_customer_invoice_totals($invoices),
        'reports' => $reports,
        'operation_files' => $operationFiles,
        'export_documents' => $projectId > 0 ? cpcc_export_documents($db, $socid, $projectId, 0, true) : array(),
        'project_linked' => $projectId > 0,
    );
}

function cpkanban_customer_order_status_label($status)
{
    $labels = array(1=>'معتمد', 2=>'قيد التنفيذ', 3=>'مغلق');
    return isset($labels[(int) $status]) ? $labels[(int) $status] : 'نشط';
}

function cpkanban_render_customer_project_center($card, $column, $data, $isModal)
{
    global $langs;
    $langs->load('chinaplanetlogistics@chinaplanetlogistics');
    $latest = $data['latest_update'];
    print '<section class="cpk-command-overview" aria-label="ملخص متابعة المشروع">';
    print '<div class="cpk-command-primary">';
    print '<span class="cpk-command-kicker">PROJECT COMMAND CENTER · '.dol_escape_htmltag($card->ref).'</span>';
    if (!empty($card->description)) print '<p>'.nl2br(dol_escape_htmltag($card->description)).'</p>';
    if (!empty($card->note_public)) print '<div class="cpk-public-note">'.nl2br(dol_escape_htmltag($card->note_public)).'</div>';
    print '<div class="cpk-progress-track" aria-label="نسبة التقدم '.(int) $card->progress.' بالمئة"><span style="width:'.max(0, min(100, (int) $card->progress)).'%"></span></div>';
    print '<strong class="cpk-progress-label">'.(int) $card->progress.'% مكتمل</strong>';
    print '</div>';
    print '<div class="cpk-latest-update"><span>آخر تحديث</span><strong>'.dol_escape_htmltag($latest['title']).'</strong>';
    if ($latest['summary'] !== '') print '<p>'.nl2br(dol_escape_htmltag($latest['summary'])).'</p>';
    print '<small>'.($latest['timestamp'] ? dol_print_date($latest['timestamp'], 'dayhour') : '—').' · '.dol_escape_htmltag($latest['actor']).'</small></div>';
    print '</section>';

    print '<section class="cpk-command-snapshot" aria-label="الخطوة الحالية والتالية">';
    print '<div><span>الخطوة الحالية</span><strong>'.dol_escape_htmltag($column ? $column->label : 'قيد المتابعة').'</strong></div>';
    print '<div><span>الخطوة التالية</span><strong>'.dol_escape_htmltag($data['next_action']).'</strong></div>';
    print '<div><span>التاريخ المتوقع</span><strong>'.(!empty($data['expected_date']) ? dol_print_date($data['expected_date'], 'day') : 'سيتم التحديث قريبًا').'</strong></div>';
    print '</section>';

    print '<section class="cpk-command-section" aria-labelledby="cpk-timeline-title"><div class="cpk-command-heading"><div><span>رحلة المشروع</span><h2 id="cpk-timeline-title">Timeline من الطلب حتى التسليم</h2></div></div>';
    print '<ol class="cpk-project-timeline">';
    foreach ($data['timeline'] as $step) {
        print '<li class="is-'.dol_escape_htmltag($step['state']).'"><span class="cpk-timeline-dot" style="--step-color:'.dol_escape_htmltag($step['color']).'"></span><div><strong>'.dol_escape_htmltag($step['label']).'</strong>';
        if ($step['label_en'] !== '') print '<small>'.dol_escape_htmltag($step['label_en']).'</small>';
        print '</div></li>';
    }
    print '</ol></section>';

    print '<section class="cpk-command-section" aria-labelledby="cpk-gallery-title"><div class="cpk-command-heading"><div><span>صور المشروع</span><h2 id="cpk-gallery-title">المعرض</h2></div><b>'.count($data['assets']['images']).' صورة</b></div>';
    if (empty($data['assets']['images'])) print '<div class="cpk-command-empty">لم تُنشر صور إضافية لهذا المشروع بعد.</div>';
    else {
        print '<div class="cpk-project-gallery">';
        foreach ($data['assets']['images'] as $image) {
            $url = dol_buildpath('/cpkanban/asset.php', 1).'?id='.(int) $image->rowid;
            print '<a href="'.dol_escape_htmltag($url).'" target="_blank" rel="noopener" class="cpk-gallery-item"><img src="'.dol_escape_htmltag($url).'" alt="'.dol_escape_htmltag($image->caption ?: $image->original_name).'" loading="lazy"><span>'.dol_escape_htmltag($image->caption ?: 'صورة المشروع').'</span></a>';
        }
        print '</div>';
    }
    print '</section>';

    print '<section class="cpk-command-section" aria-labelledby="cpk-ships-title"><div class="cpk-command-heading"><div><span>الشحن واللوجستيات</span><h2 id="cpk-ships-title">الشحنات والحاويات المرتبطة</h2></div><b>'.count($data['shipments']).' شحنة</b></div>';
    if (empty($data['shipments'])) print '<div class="cpk-command-empty">لم تُربط شحنة بهذا المشروع حتى الآن.</div>';
    else {
        print '<div class="cpk-shipment-grid">';
        foreach ($data['shipments'] as $shipment) {
            $url = dol_buildpath('/chinaplanetlogistics/card.php', 1).'?id='.(int) $shipment->rowid.($isModal ? '&modal=1' : '');
            print '<a class="cpk-shipment-card" href="'.dol_escape_htmltag($url).'">';
            print '<div><span>'.dol_escape_htmltag($shipment->ref).'</span><strong>🚢 '.dol_escape_htmltag($shipment->container_no ?: 'لم يحدد رقم الحاوية').'</strong></div>';
            if (!empty($shipment->customer_eta_change)) print '<div class="cpk-shipment-alert" role="status">'.dol_escape_htmltag($langs->trans('CPLEtaChangedAlert')).'</div>';
            print '<dl><div><dt>الحالة</dt><dd>'.dol_escape_htmltag(cpkanban_shipment_status($shipment->fk_statut)).'</dd></div><div><dt>ETA</dt><dd>'.(!empty($shipment->date_eta) ? dol_print_date($shipment->date_eta, 'day') : '—').'</dd></div><div><dt>BL</dt><dd>'.dol_escape_htmltag($shipment->bl_no ?: '—').'</dd></div></dl>';
            if (!empty($shipment->customer_latest_update)) print '<small>'.dol_escape_htmltag($langs->trans('CPLLatestUpdate')).': '.dol_print_date($shipment->customer_latest_update['timestamp'], 'dayhour').' · '.dol_escape_htmltag(cpl_public_source_label($langs, $shipment->customer_latest_update['event']->source_type)).'</small>';
            print '<small>'.dol_escape_htmltag($shipment->port_loading ?: '—').' ← '.dol_escape_htmltag($shipment->port_destination ?: '—').'</small></a>';
        }
        print '</div>';
    }
    print '</section>';

    print '<section class="cpk-command-section" aria-labelledby="cpk-files-title"><div class="cpk-command-heading"><div><span>الملفات المنشورة</span><h2 id="cpk-files-title">الملفات النهائية والتقارير</h2></div></div>';
    $fileCount = count($data['assets']['documents']) + count($data['export_documents']) + count($data['operation_files']) + count($data['reports']);
    if ($fileCount === 0) print '<div class="cpk-command-empty">لا توجد ملفات معتمدة للنشر حتى الآن.</div>';
    else {
        print '<div class="cpk-file-grid">';
        foreach ($data['assets']['documents'] as $document) {
            $url = dol_buildpath('/cpkanban/asset.php', 1).'?id='.(int) $document->rowid;
            print '<a href="'.dol_escape_htmltag($url).'" class="cpk-file-card"><span>📎 ملف نهائي</span><strong>'.dol_escape_htmltag($document->original_name).'</strong><small>v'.(int) $document->version_no.'</small></a>';
        }
        foreach ($data['export_documents'] as $document) {
            $url = dol_buildpath('/chinaplanetcustomercenter/export_document.php', 1).'?id='.(int) $document->rowid;
            print '<a href="'.dol_escape_htmltag($url).'" class="cpk-file-card"><span>📄 '.dol_escape_htmltag(cpcc_export_type_label($document->document_type)).'</span><strong>'.dol_escape_htmltag($document->ref).'</strong><small>مستند تجاري معتمد</small></a>';
        }
        foreach ($data['operation_files'] as $document) {
            $url = dol_buildpath('/chinaplanetcustomercenter/operation_document.php', 1).'?id='.(int) $document['rowid'];
            print '<a href="'.dol_escape_htmltag($url).'" class="cpk-file-card"><span>🛡️ تقرير معتمد</span><strong>'.dol_escape_htmltag($document['original_name']).'</strong><small>'.dol_escape_htmltag($document['operation_title']).'</small></a>';
        }
        foreach ($data['reports'] as $report) {
            $url = dol_buildpath('/chinaplanetcustomercenter/operation_report.php', 1).'?id='.(int) $report['rowid'];
            $qcResultLabels = array('pass'=>'ناجح','fail'=>'غير ناجح','pending_corrective_action'=>'بانتظار الإجراء التصحيحي','reinspection_required'=>'إعادة فحص مطلوبة');
            $reportMeta = $report['service_type'] === 'quality_control'
                ? 'النتيجة '.dol_escape_htmltag($qcResultLabels[(string)$report['overall_result']] ?? str_replace('_', ' ', (string)$report['overall_result'])).' · الإصدار '.(int)$report['publication_version']
                : (int)$report['progress'].'%';
            print '<a href="'.dol_escape_htmltag($url).'" class="cpk-file-card"><span>📋 تقرير خدمة</span><strong>'.dol_escape_htmltag($report['title']).'</strong><small>'.$reportMeta.'</small></a>';
        }
        print '</div>';
    }
    print '</section>';

    print '<section class="cpk-command-section" aria-labelledby="cpk-finance-title"><div class="cpk-command-heading"><div><span>للقراءة فقط</span><h2 id="cpk-finance-title">الطلبات والفواتير والمدفوعات</h2></div><b>بيانات العميل فقط</b></div>';
    if (!$data['project_linked']) print '<div class="cpk-command-empty">ستظهر البيانات المالية بعد ربط البطاقة بمشروع Dolibarr.</div>';
    else {
        if (empty($data['invoice_totals'])) print '<div class="cpk-command-empty">لم تصدر فواتير مرتبطة بهذا المشروع.</div>';
        foreach ($data['invoice_totals'] as $currency => $totals) {
            print '<div class="cpk-finance-summary"><div><span>إجمالي الفواتير</span><strong>'.cpcc_portal_money($totals['total']).' '.dol_escape_htmltag($currency).'</strong></div><div><span>المدفوع</span><strong>'.cpcc_portal_money($totals['paid']).' '.dol_escape_htmltag($currency).'</strong></div><div><span>المتبقي</span><strong>'.cpcc_portal_money($totals['remaining']).' '.dol_escape_htmltag($currency).'</strong></div></div>';
        }
        print '<div class="cpk-finance-columns"><div><h3>الطلبات</h3>';
        if (empty($data['orders'])) print '<p class="cpk-command-empty">لا توجد طلبات مرتبطة.</p>';
        foreach ($data['orders'] as $order) {
            $url = dol_buildpath('/chinaplanetcustomercenter/order.php', 1).'?id='.(int) $order->rowid;
            print '<a class="cpk-finance-row" href="'.dol_escape_htmltag($url).'"><span><b>'.dol_escape_htmltag($order->ref).'</b><small>'.dol_escape_htmltag(cpkanban_customer_order_status_label($order->fk_statut)).'</small></span><strong>'.cpcc_portal_money($order->display_total).' '.dol_escape_htmltag($order->display_currency).'</strong></a>';
        }
        print '</div><div><h3>الفواتير</h3>';
        if (empty($data['invoices'])) print '<p class="cpk-command-empty">لا توجد فواتير مرتبطة.</p>';
        foreach ($data['invoices'] as $invoice) {
            $url = dol_buildpath('/chinaplanetcustomercenter/invoice.php', 1).'?id='.(int) $invoice['id'];
            print '<a class="cpk-finance-row" href="'.dol_escape_htmltag($url).'"><span><b>'.dol_escape_htmltag($invoice['ref']).'</b><small>مدفوع '.cpcc_portal_money($invoice['paid']).' · متبقي '.cpcc_portal_money($invoice['remaining']).'</small></span><strong>'.cpcc_portal_money($invoice['total']).' '.dol_escape_htmltag($invoice['currency']).'</strong></a>';
        }
        print '</div></div>';
    }
    print '<p class="cpk-finance-privacy">لا يعرض هذا القسم هوية المورد أو تكلفة المورد أو هامش الربح أو الحسابات البنكية.</p>';
    print '</section>';
}
