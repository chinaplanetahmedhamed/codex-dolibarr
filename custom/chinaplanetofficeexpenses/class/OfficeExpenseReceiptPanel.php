<?php
declare(strict_types=1);

/** Finens-styled own-draft receipt panel; consumes only already-authorized controller state. */
final class OfficeExpenseReceiptPanel
{
    public static function translations(string $lang): array
    {
        return $lang==='ar'?array('receipts'=>'الإيصالات','receipt_attach'=>'إرفاق إيصال','receipt_choose'=>'صورة الإيصال','receipt_hint'=>'الأنواع:','receipt_max'=>'الحد الأقصى:',
            'receipt_help'=>'يبقى الأصل محفوظاً مع نسخة عرض منفصلة. أعد اختيار الصورة نفسها عند إعادة المحاولة.','receipt_empty'=>'لا توجد إيصالات لهذه المسودة بعد.',
            'receipt_original'=>'تنزيل الأصل','receipt_preview'=>'تنزيل نسخة العرض','receipt_close'=>'إغلاق المرفقات','receipt_saved'=>'حُفظ الإيصال وربط بالمسودة.',
            'receipt_after_save'=>'يمكنك إرفاق الإيصال بعد حفظ المسودة.','receipt_invalid'=>'اختر صورة سليمة من الأنواع الموضّحة ضمن الحد المسموح، ثم حاول مجدداً.',
            'receipt_too_large'=>'الصورة تتجاوز حد الرفع. اختر صورة أصغر.','receipt_quota'=>'بلغت المسودة حد المرفقات؛ اطلب مراجعة المسؤول.',
            'receipt_conflict'=>'هذا الطلب محفوظ بملف مختلف؛ أعد فتح المرفقات لإضافة ملف آخر.','receipt_unavailable'=>'المرفقات غير متاحة حالياً. احتفظ بالصورة وأعد المحاولة بنفس الطلب.',
            'receipt_save_first'=>'احفظ المسودة أولاً، ثم أرفق الإيصال من قسم الإيصالات.','receipt_size'=>'حجم الأصل'):
        array('receipts'=>'Receipts','receipt_attach'=>'Attach receipt','receipt_choose'=>'Receipt image','receipt_hint'=>'Formats:','receipt_max'=>'Maximum:',
            'receipt_help'=>'The original is kept with a separate preview. Select the same image again when retrying.','receipt_empty'=>'No receipts attached to this draft yet.',
            'receipt_original'=>'Download original','receipt_preview'=>'Download preview','receipt_close'=>'Close attachments','receipt_saved'=>'Receipt saved and linked to the draft.',
            'receipt_after_save'=>'You can attach the receipt after saving the draft.','receipt_invalid'=>'Choose a valid image in the listed formats within the size limit and try again.',
            'receipt_too_large'=>'The image exceeds the upload limit. Choose a smaller image.','receipt_quota'=>'This draft reached its attachment limit. Ask an administrator to review it.',
            'receipt_conflict'=>'This request already saved a different file. Reopen attachments to add another file.','receipt_unavailable'=>'Attachments are temporarily unavailable. Keep the image and retry the same request.',
            'receipt_save_first'=>'Save the draft first, then attach its receipt from the receipts section.','receipt_size'=>'Original size');
    }
    public function render(array $state,string $language,string $csrf,bool $static=false): string
    {
        if(empty($state['receipt_enabled'])||empty($state['selected_draft']))return '';
        $t=self::translations($language);$e=static function($v){return htmlspecialchars(is_string($v)||is_numeric($v)?(string)$v:'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');};
        $draft=$state['selected_draft'];$values=$state['receipt_values'];$disabled=$static||$state['receipt_upload_limit']<=0||($state['receipt_can_attach']??true)===false;
        $labels=array('image/jpeg'=>'JPEG','image/png'=>'PNG');$formats=array_map(static function($mime)use($labels){return $labels[$mime];},$state['receipt_policy']['formats']);
        $accept=implode(',',array_merge($state['receipt_policy']['formats'],array_map(static function($extension){return '.'.$extension;},$state['receipt_policy']['extensions'])));
        ob_start(); ?>
<section id="receipts" class="register-panel receipt-panel" aria-labelledby="receipt-heading" tabindex="-1">
<div class="receipt-heading"><div><h2 id="receipt-heading"><i class="fa-solid fa-paperclip" aria-hidden="true"></i> <?= $e($t['receipts']) ?></h2><p><?= $e($draft['purpose']) ?></p></div><bdi class="receipt-money"><?= $e($draft['amount'].' '.$draft['currency']) ?></bdi><?php if(!$static){ ?><a class="icon-button" href="index.php?lang=<?= $e($language) ?>&amp;view=<?= $e($state['view']) ?>" aria-label="<?= $e($t['receipt_close']) ?>"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a><?php } ?></div>
<?php if(!empty($state['receipt_saved'])){ ?><div class="flash success" role="status"><?= $e($t['receipt_saved']) ?></div><?php } ?>
<?php if(!empty($state['receipt_error'])){ ?><div class="flash error" role="alert"><?= $e($t[$state['receipt_error']]??$t['receipt_unavailable']) ?></div><?php } ?>
<form method="post" action="index.php" enctype="multipart/form-data" class="receipt-upload" data-receipt-form>
<input type="hidden" name="action" value="attach_receipt"><input type="hidden" name="token" value="<?= $e($csrf) ?>"><input type="hidden" name="lang" value="<?= $e($language) ?>"><input type="hidden" name="draft_id" value="<?= $e($draft['id']) ?>"><input type="hidden" name="receipt_key" value="<?= $e($values['receipt_key']) ?>">
<div><label for="receipt-image"><?= $e($t['receipt_choose']) ?></label><input id="receipt-image" type="file" name="receipt" accept="<?= $e($accept) ?>" required aria-describedby="receipt-limit receipt-help"<?= $disabled?' disabled':'' ?>><small id="receipt-limit"><?php if($state['receipt_upload_limit']>0){ ?><?= $e($t['receipt_hint']) ?> <bdi><?= $e(implode(' / ',$formats)) ?></bdi> · <?= $e($t['receipt_max']) ?> <bdi><?= $e($this->size($state['receipt_upload_limit'])) ?></bdi><?php }else{ ?><?= $e($t['receipt_unavailable']) ?><?php } ?></small></div>
<button class="button button-brand" type="submit"<?= $disabled?' disabled':'' ?>><i class="fa-solid fa-paperclip" aria-hidden="true"></i> <?= $e($t['receipt_attach']) ?></button><p id="receipt-help"><?= $e($t['receipt_help']) ?></p></form>
<ul class="receipt-list" aria-label="<?= $e($t['receipts']) ?>">
<?php if(!$state['receipts']){ ?><li class="receipt-empty"><?= $e($t['receipt_empty']) ?></li><?php }foreach($state['receipts'] as $receipt){ ?>
<li class="receipt-item" data-receipt-id="<?= $e($receipt['id']) ?>"><span class="receipt-file-icon"><i class="fa-solid fa-file-image" aria-hidden="true"></i></span><div class="receipt-file-meta"><strong><?= $e($receipt['original_name']) ?></strong><small><?= $e($t['receipt_size']) ?>: <bdi><?= $e($this->size($receipt['size'])) ?></bdi></small></div>
<form method="post" action="index.php" class="receipt-download"><input type="hidden" name="action" value="download_receipt"><input type="hidden" name="token" value="<?= $e($csrf) ?>"><input type="hidden" name="lang" value="<?= $e($language) ?>"><input type="hidden" name="draft_id" value="<?= $e($draft['id']) ?>"><input type="hidden" name="receipt_id" value="<?= $e($receipt['id']) ?>"><button class="button button-muted" type="submit" name="variant" value="original" aria-label="<?= $e($t['receipt_original'].' — '.$receipt['original_name']) ?>"<?= $static?' disabled':'' ?>><?= $e($t['receipt_original']) ?></button><button class="button button-muted" type="submit" name="variant" value="preview" aria-label="<?= $e($t['receipt_preview'].' — '.$receipt['original_name']) ?>"<?= $static?' disabled':'' ?>><?= $e($t['receipt_preview']) ?></button></form></li>
<?php } ?></ul></section>
<?php return (string)ob_get_clean();
    }
    private function size(int $bytes): string
    {
        $unit=$bytes>=1048576?'MiB':($bytes>=1024?'KiB':'B');$base=$unit==='MiB'?1048576:($unit==='KiB'?1024:1);
        return rtrim(rtrim(number_format($bytes/$base,2,'.',''),'0'),'.').' '.$unit;
    }
}
