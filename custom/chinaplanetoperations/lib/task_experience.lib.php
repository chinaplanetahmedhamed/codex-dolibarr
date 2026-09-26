<?php
function cpo_details_editor(array $details,$editable=true)
{
    global $langs;
    $data=$details['data'];$e=function($s){return dol_escape_htmltag((string)$s);};
    ?><div class="cpt-details-editor" data-details-editor data-version="<?php echo (int)$details['version']; ?>"<?php echo !$editable?' data-readonly="1"':''; ?>>
    <script type="application/json" data-details-value><?php echo json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
    <section class="cpt-editor-section" id="task-dates"><h2><?php echo $langs->trans('CPTDatesCover'); ?></h2><div class="cpt-field-grid">
      <label class="cpo-field"><span><?php echo $langs->trans('CPTStartDate'); ?></span><input type="date" data-extra="start_date" value="<?php echo $e($data['start_date']); ?>"<?php echo !$editable?' disabled':''; ?>></label>
      <label class="cpo-field"><span><?php echo $langs->trans('CPTStartTime'); ?></span><input type="time" data-extra="start_time" value="<?php echo $e($data['start_time']??''); ?>"<?php echo !$editable?' disabled':''; ?>></label>
      <label class="cpo-field"><span><?php echo $langs->trans('CPTCoverColor'); ?></span><input type="color" data-extra="cover_color" value="<?php echo $e($data['cover_color']?:'#2563eb'); ?>"<?php echo !$editable?' disabled':''; ?>></label>
      <label class="cpo-field cpt-check"><input type="checkbox" data-color-enabled<?php echo $data['cover_color']?' checked':''; ?><?php echo !$editable?' disabled':''; ?>><?php echo $langs->trans('CPTUseColor'); ?></label>
    </div><p class="cpt-agenda-help"><?php echo $langs->trans('CPTAgendaHelp'); ?></p></section>
    <section class="cpt-editor-section" id="task-labels"><div class="cpt-section-title"><h2><?php echo $langs->trans('InternalTaskLabels'); ?></h2><?php if($editable){ ?><button type="button" class="cpt-text-button" data-add-label>+ <?php echo $langs->trans('CPTAddLabel'); ?></button><?php } ?></div><div data-extra-labels class="cpt-label-rows"></div></section>
    <section class="cpt-editor-section" id="task-fields"><div class="cpt-section-title"><h2><?php echo $langs->trans('CPTCustomFields'); ?></h2><?php if($editable){ ?><button type="button" class="cpt-text-button" data-add-field>+ <?php echo $langs->trans('CPTAddField'); ?></button><?php } ?></div><div data-extra-fields class="cpt-custom-rows"></div></section>
    <section class="cpt-editor-section" id="task-notes"><h2><?php echo $langs->trans('CPTNotes'); ?></h2><textarea data-extra="notes" rows="4" maxlength="10000" aria-label="<?php echo dol_escape_htmltag($langs->trans('CPTNotes')); ?>"<?php echo !$editable?' readonly':''; ?>><?php echo $e($data['notes']); ?></textarea></section>
    <template data-extra-ui data-name="<?php echo dol_escape_htmltag($langs->trans('Name')); ?>" data-value="<?php echo dol_escape_htmltag($langs->trans('CPTFieldValue')); ?>" data-remove="<?php echo dol_escape_htmltag($langs->trans('Remove')); ?>" data-text="<?php echo dol_escape_htmltag($langs->trans('CPTFieldText')); ?>" data-number="<?php echo dol_escape_htmltag($langs->trans('CPTFieldNumber')); ?>" data-date="<?php echo dol_escape_htmltag($langs->trans('CPTFieldDate')); ?>" data-checkbox="<?php echo dol_escape_htmltag($langs->trans('CPTFieldCheckbox')); ?>"></template>
    </div><?php
}
function cpo_experience_assets()
{
    global $langs;
    echo '<link rel="stylesheet" href="'.dol_escape_htmltag(cpo_asset('/custom/chinaplanetoperations/css/task_experience.css')).'">';
    echo '<script defer src="'.dol_escape_htmltag(cpo_asset('/custom/chinaplanetoperations/js/task_experience.js')).'"></script>';
    echo '<template id="cpt-messages" data-saving="'.dol_escape_htmltag($langs->trans('CPTSaving')).'" data-uploading="'.dol_escape_htmltag($langs->trans('InternalTaskUploading')).'" data-failed="'.dol_escape_htmltag($langs->trans('CPTSaveFailed')).'" data-saved="'.dol_escape_htmltag($langs->trans('TaskUpdated')).'" data-move="'.dol_escape_htmltag($langs->trans('InternalTaskMoveToMenu')).'" data-upload-failed="'.dol_escape_htmltag($langs->trans('CPTUploadRetry')).'" data-column="'.dol_escape_htmltag($langs->trans('CPTColumnName')).'" data-remove="'.dol_escape_htmltag($langs->trans('Remove')).'"></template>';
}
