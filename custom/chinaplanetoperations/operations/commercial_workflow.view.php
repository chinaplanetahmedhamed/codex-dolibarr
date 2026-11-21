<?php if ($commercialEligible) { ?><div class="cpo-page cpo-commercial-host">
<section class="cpo-panel cpo-commercial-workflow" id="commercial-workflow">
  <div class="cpo-panel-head">
    <div><h2>Customer quotation → sales order / عرض السعر ← طلب البيع</h2><p>مسار الموظف داخل China Planet OS، والسجلات الأصلية تُحفظ في الخلفية.</p></div>
    <span class="cpo-badge"><?php echo $commercialOrder ? 'ORDER LINKED' : ($commercialProposal ? 'QUOTATION LINKED' : 'NOT STARTED'); ?></span>
  </div>
  <?php if (!$commercialCanRead) { ?>
    <div class="cpo-notice cpo-notice-warning">لا تملك صلاحيات قراءة عروض الأسعار والمنتجات وطلبات البيع.</div>
  <?php } else { ?>
    <ol class="cpo-commercial-steps">
      <li class="<?php echo $commercialProposal ? 'done' : 'active'; ?>"><strong>1</strong><span>مسودة عرض السعر<small>Draft quotation</small></span></li>
      <li class="<?php echo $commercialProposal && (int)$commercialProposal->status >= 1 ? 'done' : ''; ?>"><strong>2</strong><span>اعتماد العرض<small>Validate</small></span></li>
      <li class="<?php echo $commercialProposal && (int)$commercialProposal->status === 2 ? 'done' : ''; ?>"><strong>3</strong><span>قبول العميل<small>Accepted</small></span></li>
      <li class="<?php echo $commercialOrder ? 'done' : ''; ?>"><strong>4</strong><span>إنشاء الطلب<small>Create order</small></span></li>
      <li class="<?php echo $commercialOrder && (int)$commercialOrder->status > 0 ? 'done' : ''; ?>"><strong>5</strong><span>اعتماد الطلب<small>Publish to portal</small></span></li>
    </ol>

    <?php if ($commercialProposal || $commercialOrder) {
      $proposalCurrency = $commercialProposal ? strtoupper((string)($commercialProposal->multicurrency_code ?: getDolCurrency())) : '';
      $proposalTotal = $commercialProposal ? ($proposalCurrency === strtoupper((string)getDolCurrency()) ? (float)$commercialProposal->total_ttc : (float)$commercialProposal->multicurrency_total_ttc) : 0;
    ?>
      <div class="cpo-commercial-summary">
        <?php if ($commercialProposal) { ?><div><span>Quotation</span><strong><?php echo dol_escape_htmltag($commercialProposal->ref); ?></strong></div>
        <div><span>Status</span><strong><?php echo dol_escape_htmltag(cpo_commercial_status('proposal', $commercialProposal->status)); ?></strong></div>
        <div><span>Total</span><strong><?php echo price($proposalTotal).' '.dol_escape_htmltag($proposalCurrency); ?></strong></div><?php } ?>
        <?php if ($commercialOrder) {
          $orderCurrency = strtoupper((string)($commercialOrder->multicurrency_code ?: getDolCurrency()));
          $orderTotal = $orderCurrency === strtoupper((string)getDolCurrency()) ? (float)$commercialOrder->total_ttc : (float)$commercialOrder->multicurrency_total_ttc;
        ?>
          <div><span>Sales order</span><strong><?php echo dol_escape_htmltag($commercialOrder->ref); ?></strong></div>
          <div><span>Order status</span><strong><?php echo dol_escape_htmltag(cpo_commercial_status('order', $commercialOrder->status)); ?></strong></div>
          <div><span>Order total</span><strong><?php echo price($orderTotal).' '.dol_escape_htmltag($orderCurrency); ?></strong></div>
        <?php } ?>
      </div>
    <?php } ?>

    <?php if ($commercialCanWrite && !$commercialOrder && (!$commercialProposal || (int)$commercialProposal->status === 0)) {
      $draftLines = $commercialProposal ? (array)$commercialProposal->lines : array();
      $selectedCurrency = $commercialProposal ? strtoupper((string)$commercialProposal->multicurrency_code) : strtoupper((string)getDolCurrency());
    ?>
      <form method="post" class="cpo-commercial-form">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="action" value="commercial_save_quote">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <div class="cpo-context-grid">
          <label class="cpo-field"><span>Currency / العملة</span><select name="quote_currency"<?php echo $commercialProposal ? ' disabled' : ''; ?>><?php foreach($commercialCurrencies as $currency){ ?><option value="<?php echo dol_escape_htmltag($currency); ?>"<?php echo $currency===$selectedCurrency?' selected':''; ?>><?php echo dol_escape_htmltag($currency); ?></option><?php } ?></select><?php if($commercialProposal){ ?><input type="hidden" name="quote_currency" value="<?php echo dol_escape_htmltag($selectedCurrency); ?>"><?php } ?></label>
          <label class="cpo-field"><span>Validity days / مدة الصلاحية</span><input type="number" min="1" max="90" name="quote_validity_days" value="<?php echo $commercialProposal ? (int)$commercialProposal->duree_validite : 15; ?>"></label>
        </div>
        <div class="div-table-responsive"><table class="cpo-table cpo-commercial-lines"><thead><tr><th>Product / المنتج</th><th>Quantity / الكمية</th><th>Unit price / سعر الوحدة</th><th>Description / الوصف</th></tr></thead><tbody>
        <?php for($lineIndex=0;$lineIndex<5;$lineIndex++){ $draftLine=$draftLines[$lineIndex]??null; $draftForeign=$draftLine ? ($selectedCurrency===strtoupper((string)getDolCurrency())?(float)$draftLine->subprice:(float)$draftLine->multicurrency_subprice) : ''; ?>
          <tr>
            <td><select name="quote_lines[<?php echo $lineIndex; ?>][fk_product]"<?php echo $draftLine?' disabled':''; ?>><option value="">—</option><?php foreach($commercialProducts as $product){ ?><option value="<?php echo (int)$product['id']; ?>"<?php echo $draftLine&&(int)$draftLine->fk_product===(int)$product['id']?' selected':''; ?>><?php echo dol_escape_htmltag($product['ref'].' — '.$product['label']); ?></option><?php } ?></select><?php if($draftLine){ ?><input type="hidden" name="quote_lines[<?php echo $lineIndex; ?>][fk_product]" value="<?php echo (int)$draftLine->fk_product; ?>"><?php } ?></td>
            <td><input type="number" min="0" step="0.001" name="quote_lines[<?php echo $lineIndex; ?>][qty]" value="<?php echo $draftLine?dol_escape_htmltag((string)$draftLine->qty):''; ?>"></td>
            <td><input type="number" min="0" step="0.0001" name="quote_lines[<?php echo $lineIndex; ?>][unit_price]" value="<?php echo $draftLine?dol_escape_htmltag((string)$draftForeign):''; ?>"></td>
            <td><input maxlength="1000" name="quote_lines[<?php echo $lineIndex; ?>][description]" value="<?php echo $draftLine?dol_escape_htmltag((string)$draftLine->desc):''; ?>"></td>
          </tr>
        <?php } ?>
        </tbody></table></div>
        <button class="button button-save">Save native quotation draft / حفظ مسودة عرض السعر</button>
      </form>
    <?php } ?>

    <?php if ($commercialCanWrite) { ?><div class="cpo-commercial-actions">
      <?php if ($commercialProposal && (int)$commercialProposal->status === 0) { ?><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><input type="hidden" name="action" value="commercial_validate_quote"><button class="button button-save">2. Validate quotation / اعتماد العرض</button></form><?php } ?>
      <?php if ($commercialProposal && (int)$commercialProposal->status === 1) { ?><form method="post" class="cpo-commercial-accept" onsubmit="return confirm('Confirm that the customer accepted this quotation?');"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><input type="hidden" name="action" value="commercial_accept_quote"><label class="cpo-field"><span>Acceptance evidence / مرجع قبول العميل *</span><input required maxlength="255" name="acceptance_reference" placeholder="Email, message, or signed document reference"></label><button class="button button-save">3. Record customer acceptance / تسجيل قبول العميل</button></form><?php } ?>
      <?php if ($commercialProposal && (int)$commercialProposal->status === 2 && !$commercialOrder) { ?><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><input type="hidden" name="action" value="commercial_create_order"><button class="button button-save">4. Create native sales order / إنشاء طلب البيع</button></form><?php } ?>
      <?php if ($commercialOrder && (int)$commercialOrder->status === 0) { ?><form method="post" onsubmit="return confirm('Validate this order and make it visible in the customer portal?');"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><input type="hidden" name="action" value="commercial_validate_order"><button class="button button-save">5. Validate order / اعتماد الطلب وإظهاره للعميل</button></form><?php } ?>
    </div><?php } ?>
    <p class="cpo-commercial-boundary">No email, invoice, payment, supplier order, or shipment is created by this panel.</p>
  <?php } ?>
</section>
</div><?php } ?>
