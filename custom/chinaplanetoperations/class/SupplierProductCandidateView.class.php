<?php

/** Escaped, read-only native-tab presentation. No costs or supplier-only details. */
class SupplierProductCandidateView
{
    public static function render($rows, $side, $langs, $urlRoot = '', $pagination = null)
    {
        if (!in_array($side, array('product','supplier'), true)) throw new InvalidArgumentException('Invalid side');
        $tr = function ($key) use ($langs) { return self::escape(method_exists($langs, 'transnoentities') ? $langs->transnoentities($key) : $langs->trans($key)); };
        $language = isset($langs->defaultlang) && preg_match('/^[a-z]{2}_[A-Z]{2}$/D', $langs->defaultlang)
            ? '&lang='.$langs->defaultlang : '';
        $h = '<section id="cpo-supplier-product-links" class="fichecenter" aria-label="'.$tr($side === 'product' ? 'SourcingPotentialSuppliers' : 'SourcingLinkedProducts').'">';
        $h .= '<h2>'.$tr($side === 'product' ? 'SourcingPotentialSuppliers' : 'SourcingLinkedProducts').' ('.($pagination ? (int) $pagination['total'] : count($rows)).')</h2>';
        $h .= '<p>'.$tr('SourcingTypeMatchExplanation').'</p>';
        if ($pagination && !empty($pagination['can_manage'])) {
            $h .= '<p><a href="'.self::escape($urlRoot.'/custom/chinaplanetoperations/sourcing/categories.php'.($language ? '?'.substr($language, 1) : '')).'">'.$tr('SourcingManageCategories').'</a></p>';
        }
        $h .= '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
        $h .= '<thead><tr class="liste_titre"><th>'.$tr($side === 'product' ? 'SourcingSupplier' : 'SourcingProduct').'</th>'
            .'<th>'.$tr('SourcingRelation').'</th><th>'.$tr('SourcingBuyingPrice').'</th><th>'.$tr('SourcingEvidence').'</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $productId = (int) $r['fk_product'];
            $supplierId = (int) $r['fk_supplier'];
            $url = $side === 'product' ? $urlRoot.'/societe/consumption.php?socid='.$supplierId
                : $urlRoot.'/product/price_suppliers.php?id='.$productId;
            $label = $side === 'product' ? $r['supplier_name'] : $r['ref'].' — '.$r['label'];
            $h .= '<tr class="oddeven" data-product-id="'.$productId.'" data-supplier-id="'.$supplierId.'">'
                .'<td><a href="'.self::escape($url).'">'.self::escape($label).'</a>'
                .((int) $r['supplier_status'] !== 1 ? ' <span class="badge">'.$tr('SourcingSupplierClosed').'</span>' : '').'</td>'
                .'<td>'.$tr(!empty($r['has_category']) ? 'SourcingCategoryMatch' : 'SourcingPotentialTypeMatch')
                .(!empty($r['has_category']) && !empty($r['has_direct']) ? '<br><small>'.$tr('SourcingExistingDirectLink').'</small>' : '').'</td><td>';
            if (!empty($r['has_native_price'])) {
                $h .= '<a href="'.self::escape($urlRoot.'/product/price_suppliers.php?id='.$productId).'">'.$tr('SourcingViewNativePrices').'</a>';
            } else {
                $h .= '<strong>'.$tr('SourcingPriceNotSet').'</strong>';
            }
            $h .= '</td><td>';
            if (!empty($r['has_category']) && !empty($r['product_category_name']) && !empty($r['supplier_category_name'])) {
                $categoryId = (int) $r[$side === 'product' ? 'fk_supplier_category' : 'fk_product_category'];
                $categoryName = $r[$side === 'product' ? 'supplier_category_name' : 'product_category_name'];
                $h .= '<a href="'.self::escape($urlRoot.'/categories/viewcat.php?id='.$categoryId.'&type='.($side === 'product' ? 1 : 0)).'">'
                    .self::escape($categoryName).'</a><br>';
            }
            if (SupplierProductCandidate::validEvidence($r['evidence_url'] ?? null)) {
                $h .= '<a target="_blank" rel="noopener noreferrer" href="'.self::escape($r['evidence_url']).'">'.$tr('SourcingCapabilitySource').'</a>';
            }
            $h .= '</td></tr>';
        }
        if (!$rows) $h .= '<tr><td colspan="4">'.$tr('SourcingNoLinks').'</td></tr>';
        $h .= '</tbody></table></div>';
        if ($pagination && (int) $pagination['total'] > (int) $pagination['page_size']) {
            $base = $side === 'product' ? '/product/price_suppliers.php?id=' : '/societe/consumption.php?socid=';
            $h .= '<nav aria-label="'.$tr('SourcingPages').'">';
            foreach (array(-1=>'SourcingPrevious',1=>'SourcingNext') as $delta=>$key) {
                $page = (int) $pagination['page'] + $delta;
                if ($page >= 0 && $page * (int) $pagination['page_size'] < (int) $pagination['total']) {
                    $h .= '<a class="butAction" href="'.self::escape($urlRoot.$base.(int) $pagination['id'].'&cpo_sourcing_page='.$page.$language.'#cpo-supplier-product-links').'">'.$tr($key).'</a> ';
                }
            }
            $h .= '<span>'.((int) $pagination['page'] + 1).' / '.(int) ceil($pagination['total'] / $pagination['page_size']).'</span></nav>';
        }
        return $h.'</section>';
    }

    private static function escape($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
