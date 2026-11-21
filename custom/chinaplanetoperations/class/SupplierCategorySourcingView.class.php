<?php

/** Native category administration, escaped and independent of prices. */
class SupplierCategorySourcingView
{
    public static function render($categories, $maps, $langs, $token, $urlRoot = '')
    {
        $tr = function ($key) use ($langs) { return self::e($langs->transnoentities($key)); };
        $byType = array();
        foreach ($categories as $category) $byType[(int) $category['type']][(int) $category['rowid']] = $category;
        $form = function ($m) use ($token, $tr) {
            $h = '<form method="post"><input type="hidden" name="token" value="'.self::e($token).'">'
                .'<input type="hidden" name="action" value="save">';
            foreach (array('product_category'=>'fk_product_category','supplier_category'=>'fk_supplier_category','version'=>'version') as $name=>$column) {
                $h .= '<input type="hidden" name="'.$name.'" value="'.(int) $m[$column].'">';
            }
            return $h.'<label><input type="checkbox" name="descendants" value="1"'.(!empty($m['include_descendants']) ? ' checked' : '').'> '
                .$tr('SourcingIncludeChildren').'</label> <label><input type="checkbox" name="enabled" value="1"'
                .(!empty($m['status']) ? ' checked' : '').'> '.$tr('SourcingEnabled').'</label> '
                .'<button type="submit" class="button">'.$tr('SourcingSave').'</button></form>';
        };
        $h = '<section id="cpo-category-sourcing"><h1>'.$tr('SourcingManageCategories').'</h1><p>'.$tr('SourcingCategoryWorkflow').'</p>'
            .'<p>'.$tr('SourcingCategorySafety').'</p><div class="div-table-responsive-no-min"><table class="noborder centpercent">'
            .'<thead><tr class="liste_titre"><th>'.$tr('SourcingProductCategory').'</th><th>'.$tr('SourcingSupplierCategory').'</th><th>'.$tr('SourcingOptions').'</th></tr></thead><tbody>';
        foreach ($maps as $m) {
            $h .= '<tr class="oddeven"><td><a href="'.self::e($urlRoot.'/categories/viewcat.php?id='.(int) $m['fk_product_category'].'&type=0').'">'
                .self::e($m['product_category']).' (#'.(int) $m['fk_product_category'].')</a></td><td><a href="'
                .self::e($urlRoot.'/categories/viewcat.php?id='.(int) $m['fk_supplier_category'].'&type=1').'">'
                .self::e($m['supplier_category']).' (#'.(int) $m['fk_supplier_category'].')</a></td><td>'.$form($m).'</td></tr>';
        }
        if (!$maps) $h .= '<tr><td colspan="3">'.$tr('SourcingNoCategoryMaps').'</td></tr>';
        $h .= '</tbody></table></div><h2>'.$tr('SourcingAddMapping').'</h2><form method="post">'
            .'<input type="hidden" name="token" value="'.self::e($token).'"><input type="hidden" name="action" value="save">'
            .'<input type="hidden" name="version" value="0"><input type="hidden" name="enabled" value="1">';
        foreach (array(0=>'product',1=>'supplier') as $type=>$name) {
            $h .= '<p><label for="'.$name.'_category">'.$tr($type === 0 ? 'SourcingProductCategory' : 'SourcingSupplierCategory').'</label><br>'
                .'<select required name="'.$name.'_category" id="'.$name.'_category" style="max-width:100%;width:36em"><option value="">—</option>';
            foreach ($categories as $c) {
                if ((int) $c['type'] !== $type) continue;
                $h .= '<option value="'.(int) $c['rowid'].'">'.self::e(self::path($c, $byType[$type])).' (#'.(int) $c['rowid'].')</option>';
            }
            $h .= '</select></p>';
        }
        $h .= '<p><label><input type="checkbox" name="descendants" value="1"> '.$tr('SourcingIncludeChildren').'</label></p>'
            .'<button type="submit" class="button">'.$tr('SourcingAddMapping').'</button></form><p><a href="'
            .self::e($urlRoot.'/categories/card.php?action=create&type=supplier').'">'.$tr('SourcingNewSupplierCategory').'</a></p>'
            .'<p>'.$tr('SourcingNativeMembershipHelp').'</p></section>';
        return $h;
    }

    private static function path($category, $byId)
    {
        $parts = array(); $seen = array();
        while ($category && !isset($seen[(int) $category['rowid']]) && count($parts) < 32) {
            $seen[(int) $category['rowid']] = true;
            array_unshift($parts, $category['label']);
            $category = $byId[(int) $category['fk_parent']] ?? null;
        }
        if ($category) array_unshift($parts, '…');
        return implode(' / ', $parts);
    }

    private static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
