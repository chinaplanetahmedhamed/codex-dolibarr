<?php
/** Shared native page/fixture view. Escapes all translated and record text. */
function cpd_documents_workspace_html(array $context, $editorUrl, callable $translate, $root, $returnto = 'workspace')
{
    $escape = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
    $label = function ($key) use ($translate, $escape) { return $escape($translate($key)); };
    $out = '<section class="cpd-workspace"><p>'.$label('DocumentsIntro').'</p>';
    $out .= '<div class="cpd-context">';
    foreach (array('customer', 'project') as $kind) {
        $record = $context[$kind];
        if (!$record) continue;
        $path = $kind === 'customer' ? '/societe/card.php' : '/projet/card.php';
        $title = $kind === 'customer' ? $record->name : $record->title;
        $out .= '<p><strong>'.$label($kind === 'customer' ? 'DocumentsCustomer' : 'DocumentsProject').': </strong>';
        $out .= '<a href="'.$escape($root.$path.'?id='.(int) $record->id).'">'.$escape($title).'</a>';
        $out .= ' <span class="opacitymedium">'.$escape($record->ref ?? '').'</span></p>';
    }
    $out .= '</div><p>'.$label('DocumentsContextNotice').'</p>';
    if (!empty($context['shipment'])) {
        $shipment = $context['shipment'];
        $title = trim(($shipment->container_no ?? '').' '.($shipment->vessel_name ?? ''));
        $out .= '<p><strong>'.$label('DocumentsShipment').': </strong><a href="'.$escape(DocumentsWorkspace::returnTarget($context, $root, 'shipment')).'">'.$escape($title ?: $translate('DocumentsShipment')).'</a> <span class="opacitymedium">'.$escape($shipment->ref).'</span></p>';
    }
    $out .= '<p><a class="butAction" href="'.$escape(DocumentsWorkspace::returnTarget($context, $root, $returnto)).'">'.$label('DocumentsReturn').'</a></p>';
    $url = DocumentsWorkspace::editorUrl($editorUrl);
    if ($url !== '') {
        $out .= '<p><a class="butAction" data-cpd-editor href="'.$escape($url).'" target="_blank" rel="noopener noreferrer">'.$label('DocumentsOpenEditor').'</a></p>';
    } else {
        $out .= '<p class="warning">'.$label('DocumentsEditorUnconfigured').'</p>';
    }
    $out .= '<h2>'.$label('DocumentsTemplates').'</h2><ul class="cpd-templates">';
    foreach (DocumentsWorkspace::templates() as $template) {
        $out .= '<li>'.$label('DocumentsTemplate'.$template).'</li>';
    }
    return $out.'</ul><p class="opacitymedium">'.$label('DocumentsPrivacyNotice').'</p></section>';
}

/** Only emitted when the administrator explicitly enables compatible intake. */
function cpd_documents_adapter_html(array $payload, $root)
{
    $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    $src = htmlspecialchars($root.'/custom/chinaplanetdocuments/js/launch-context.mjs', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<script type="application/json" id="cpd-editor-context">'.$json.'</script><script type="module" src="'.$src.'"></script>';
}

/** Staged GET selectors reset dependent IDs; no record is created or changed. */
function cpd_documents_selectors_html(array $context, $companyHtml, array $projects, array $shipments, callable $translate, $root, $returnto)
{
    $esc = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); };
    $t = function ($key) use ($translate, $esc) { return $esc($translate($key)); };
    $start = '<form method="get" action="'.$esc($root.'/custom/chinaplanetdocuments/index.php').'" class="cpd-select"><input type="hidden" name="returnto" value="'.$esc($returnto).'">';
    $out = '';
    if ($companyHtml !== '') $out .= $start.'<label for="socid">'.$t('DocumentsCustomer').'</label> '.$companyHtml.' <button type="submit" class="button">'.$t('DocumentsSelectCustomer').'</button></form>';
    if (empty($context['customer'])) return $out;
    $customer = '<input type="hidden" name="socid" value="'.(int) $context['customer']->id.'">';
    $options = function ($rows, $kind) use ($context, $esc) {
        $selected = $context[$kind]->id ?? 0;
        // Preserve a selected older record beyond the bounded choice list.
        if ($selected && !in_array((int) $selected, array_map(function ($r) { return (int) $r->id; }, $rows), true)) $rows[] = $context[$kind];
        $html = '<option value="0">—</option>';
        foreach ($rows as $row) {
            $title = $kind === 'project' ? $row->title : trim(($row->container_no ?? '').' '.($row->vessel_name ?? ''));
            $html .= '<option value="'.(int) $row->id.'"'.((int) $row->id === (int) $selected ? ' selected' : '').'>'.$esc($title).' — '.$esc($row->ref).'</option>';
        }
        return $html;
    };
    $out .= $start.$customer.'<label for="projectid">'.$t('DocumentsProject').'</label> <select id="projectid" name="projectid">'.$options($projects, 'project').'</select> <button type="submit" class="button">'.$t('DocumentsSelectProject').'</button></form>';
    if ($shipments || !empty($context['shipment'])) {
        $out .= $start.$customer.'<input type="hidden" name="projectid" value="'.(int) ($context['project']->id ?? 0).'">';
        $out .= '<label for="shipmentid">'.$t('DocumentsShipment').'</label> <select id="shipmentid" name="shipmentid">'.$options($shipments, 'shipment').'</select> <button type="submit" class="button">'.$t('DocumentsSelectShipment').'</button></form>';
    }
    return $out.'<p class="opacitymedium">'.$t('DocumentsChoiceLimit').'</p>';
}
