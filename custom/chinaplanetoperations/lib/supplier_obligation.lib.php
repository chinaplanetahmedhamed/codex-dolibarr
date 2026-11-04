<?php
/** Current presentation of state already authorized/resolved by the supplier owner.
 * No SQL, posting, ledger, currency conversion or additional record discovery.
 */
function cpo_supplier_obligation_projection(array $state)
{
    $number = static function ($value) {
        if (!is_numeric($value)) return null;
        $amount = (float) $value;
        return is_finite($amount) && $amount >= 0 ? $amount : null;
    };
    $currency = static function ($value) {
        $code = strtoupper(trim((string) $value));
        return preg_match('/^[A-Z]{3}$/D', $code) ? $code : null;
    };
    $out = array('order'=>null, 'invoice'=>null);
    $order = $state['supplier_order'] ?? null;
    if (is_object($order)) {
        $rawCode = trim((string) ($order->multicurrency_code ?? ''));
        $code = $currency($rawCode !== '' ? $rawCode : getDolCurrency());
        // Preserve the incumbent native summary's original/base amount selection.
        $amount = $number($rawCode !== '' ? ($order->multicurrency_total_ttc ?? null) : ($order->total_ttc ?? null));
        $status = (int) ($order->status ?? $order->statut ?? -1);
        $kind = in_array($status, array(1,2,3,4,5), true) ? 'committed' : ($status === 0 ? 'draft' : (in_array($status,array(6,7,9),true) ? 'cancelled' : 'review'));
        if ($code === null || $amount === null) $kind = 'review';
        $out['order'] = array('id'=>(int) ($order->id ?? 0), 'ref'=>(string) ($order->ref ?? ''),
            'supplier'=>(string) ($order->thirdparty->name ?? ''), 'amount'=>$amount, 'currency'=>$code, 'state'=>$kind);
    }
    $invoice = $state['supplier_invoice'] ?? null;
    if (is_object($invoice)) {
        $summary = $state['summary'] ?? array();
        $summary = is_array($summary) ? $summary : array();
        $total = $number($summary['total'] ?? null);
        $paid = $number($summary['paid'] ?? null);
        $remaining = $number($summary['remaining'] ?? null);
        $code = $currency($summary['currency'] ?? '');
        $status = (int) ($summary['status'] ?? -1);
        $kind = 'review';
        if ($total !== null && $paid !== null && $remaining !== null && $code !== null
            && $out['order'] !== null && $out['order']['currency'] === $code
            && (int) ($invoice->id ?? 0) > 0 && (int) ($invoice->type ?? -1) === 0
            && (int) ($invoice->status ?? $invoice->statut ?? -1) === $status
            && $paid <= $total && abs($total - $paid - $remaining) <= 0.00001) {
            if ($status === 0) $kind = 'draft';
            elseif ($status === 1 || ($status === 2 && $remaining === 0.0)) $kind = 'recorded_payments_only';
        }
        // A closed/abandoned/draft or inconsistent invoice never becomes a zero
        // obligation from workflow status. Native credits/closure need review.
        if ($kind !== 'recorded_payments_only') $remaining = null;
        if ($kind === 'review') $total = $paid = null;
        $out['invoice'] = array('id'=>(int) ($invoice->id ?? 0), 'ref'=>(string) ($invoice->ref ?? ''),
            'total'=>$total, 'paid'=>$kind === 'draft' ? null : $paid, 'remaining'=>$remaining, 'currency'=>$code, 'state'=>$kind);
    }
    return $out;
}

function cpo_supplier_obligation_html(array $position, $langs)
{
    $label = static function ($key) use ($langs) { return dol_escape_htmltag($langs->trans($key)); };
    $money = static function ($amount, $currency) use ($label) {
        return $amount === null || $currency === null ? $label('CPOSupplierObligationUnknown')
            : '<bdi>'.dol_escape_htmltag(price($amount).' '.$currency).'</bdi>';
    };
    $order = $position['order']; $invoice = $position['invoice'];
    $coverage = $invoice ? $invoice['state'] : ($order ? 'order_only' : 'not_started');
    $html = '<section class="cpo-supplier-position" data-obligation-coverage="'.dol_escape_htmltag($coverage).'">';
    $html .= '<h3>'.$label('CPOSupplierObligationTitle').'</h3><p>'.$label('CPOSupplierObligationScope').'</p>';
    if ($order) {
        $html .= '<p class="cpo-supplier-position-source"><strong><bdi>'.dol_escape_htmltag($order['supplier']).'</bdi></strong><br>';
        $text = $label('CPOSupplierObligationOrder').' · '.dol_escape_htmltag($order['ref']);
        $html .= $order['id'] > 0 ? '<a href="'.dol_escape_htmltag(DOL_URL_ROOT.'/fourn/commande/card.php?id='.$order['id']).'">'.$text.'</a>' : $text;
        $html .= '</p><dl><div><dt>'.$label('CPOSupplierObligationOrderAmount').'</dt><dd>'.$money($order['amount'],$order['currency']).'</dd><small>'.$label('CPOSupplierObligationOrder_'.$order['state']).'</small></div></dl>';
    }
    if ($invoice) {
        $html .= '<p class="cpo-supplier-position-source">';
        $text = $label('CPOSupplierObligationInvoice').' · '.dol_escape_htmltag($invoice['ref']);
        $html .= $invoice['id'] > 0 ? '<a href="'.dol_escape_htmltag(DOL_URL_ROOT.'/fourn/facture/card.php?facid='.$invoice['id']).'">'.$text.'</a>' : $text;
        $html .= '</p><dl>';
        foreach (array('total'=>'CPOSupplierObligationInvoiceTotal','paid'=>'CPOSupplierObligationRecordedPaid','remaining'=>'CPOSupplierObligationRemaining') as $key=>$title) {
            $html .= '<div><dt>'.$label($title).'</dt><dd>'.$money($invoice[$key],$invoice['currency']).'</dd></div>';
        }
        $html .= '</dl>';
        if ($invoice['state'] !== 'recorded_payments_only') $html .= '<p role="status">'.$label('CPOSupplierObligationInvoice_'.$invoice['state']).'</p>';
    } else $html .= '<p>'.$label('CPOSupplierObligationNoInvoice').'</p>';
    return $html.'<p class="cpo-commercial-boundary">'.$label('CPOSupplierObligationLimit').'</p></section>';
}
