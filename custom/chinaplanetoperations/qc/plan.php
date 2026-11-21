<?php
define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/OperationsService.class.php';
require_once __DIR__.'/../class/QcService.class.php';
require_once __DIR__.'/../class/QcAqlService.class.php';
require_once __DIR__.'/../class/QcTemplateService.class.php';
cpo_require($user, 'qc_plan');
$langs->load('chinaplanetoperations@chinaplanetoperations');
$id=GETPOSTINT('id');$operations=new OperationsService($db);$qc=new QcService($db);$operation=$operations->get($id);if(!$operation||$operation['service_type']!=='quality_control')accessforbidden();
$action=GETPOST('action','aZ09');if($action!==''&&$_SERVER['REQUEST_METHOD']!=='POST')accessforbidden();
try{
 if($action==='create_snapshot')$qc->createSourceSnapshot($id,(int)$user->id);
 elseif($action==='generate_order')$qc->generateOrderLineCheckpoints($id,(int)$user->id);
 elseif($action==='import_template')$qc->importApprovedTemplate($id,GETPOST('template_id','alphanohtml'),(int)$user->id);
 elseif($action==='assign_inspector')$qc->assignInspector($id,GETPOSTINT('inspector_id'),(int)$user->id);
 elseif($action==='add_manual')$qc->addManualCheckpoint($id,array('client_uuid'=>cpo_qc_uuid(),'scope_type'=>GETPOST('scope_type','alpha'),'snapshot_line_key'=>GETPOST('snapshot_line_key','alphanohtml'),'section_code'=>GETPOST('section_code','alphanohtml'),'section_label'=>GETPOST('section_label','alphanohtml'),'requirement_text'=>GETPOST('requirement_text','restricthtml'),'expected_value'=>GETPOST('expected_value','restricthtml'),'tolerance_text'=>GETPOST('tolerance_text','restricthtml'),'mandatory'=>GETPOSTINT('mandatory'),'min_evidence'=>GETPOSTINT('min_evidence')),(int)$user->id);
 elseif($action==='update_manual')$qc->updateDraftCheckpoint($id,GETPOSTINT('checkpoint_id'),array('client_uuid'=>GETPOST('client_uuid','alphanohtml'),'scope_type'=>GETPOST('scope_type','alpha'),'snapshot_line_key'=>GETPOST('snapshot_line_key','alphanohtml'),'section_code'=>GETPOST('section_code','alphanohtml'),'section_label'=>GETPOST('section_label','alphanohtml'),'requirement_text'=>GETPOST('requirement_text','restricthtml'),'expected_value'=>GETPOST('expected_value','restricthtml'),'tolerance_text'=>GETPOST('tolerance_text','restricthtml'),'mandatory'=>GETPOSTINT('mandatory'),'min_evidence'=>GETPOSTINT('min_evidence'),'position'=>GETPOSTINT('position')),GETPOSTINT('revision'),(int)$user->id);
 elseif($action==='batch_import_checkpoints'){
  $importMode=GETPOST('import_mode','alpha')?:'replace';
  $rawJson=GETPOST('checkpoints_json','none');
  $data=json_decode($rawJson,true);
  if(!is_array($data)||empty($data))throw new RuntimeException('Invalid imported checkpoints JSON or empty list.');
  if($importMode==='replace'){
   $existing=$qc->checkpoints($id);
   foreach($existing as $cp){
    if($cp['source_type']==='manual'){
     try{$qc->removeDraftCheckpoint($id,(int)$cp['rowid'],(int)$user->id);}catch(Throwable $ignored){}
    }
   }
  }
  $importedCount=0;
  foreach($data as $item){
   $req=trim((string)($item['requirement_text']??''));
   if($req==='')continue;
   $qc->addManualCheckpoint($id,array(
    'client_uuid'=>cpo_qc_uuid(),
    'scope_type'=>(!empty($item['scope_type'])&&$item['scope_type']==='order_line')?'order_line':'inspection',
    'snapshot_line_key'=>!empty($item['snapshot_line_key'])?(string)$item['snapshot_line_key']:null,
    'section_code'=>!empty($item['section_code'])?preg_replace('/[^a-z0-9_]/','_',strtolower((string)$item['section_code'])):'general',
    'section_label'=>!empty($item['section_label'])?(string)$item['section_label']:'General',
    'requirement_text'=>$req,
    'expected_value'=>(string)($item['expected_value']??''),
    'tolerance_text'=>(string)($item['tolerance_text']??''),
    'mandatory'=>isset($item['mandatory'])?(int)$item['mandatory']:1,
    'min_evidence'=>isset($item['min_evidence'])?(int)$item['min_evidence']:0
   ),(int)$user->id);
   $importedCount++;
  }
  setEventMessages("Successfully imported {$importedCount} checkpoints (".($importMode==='replace'?'Replaced draft manual checkpoints':'Appended').").",null,'mesgs');
 }
 elseif($action==='duplicate')$qc->duplicateManualCheckpoint($id,GETPOSTINT('checkpoint_id'),cpo_qc_uuid(),(int)$user->id);
 elseif($action==='remove')$qc->removeDraftCheckpoint($id,GETPOSTINT('checkpoint_id'),(int)$user->id);
 elseif($action==='aql')$qc->configureAql($id,GETPOSTINT('aql_enabled')===1,GETPOSTINT('lot_size'),array_filter(array('critical'=>GETPOST('aql_critical','alphanohtml'),'major'=>GETPOST('aql_major','alphanohtml'),'minor'=>GETPOST('aql_minor','alphanohtml')),function($value){return$value!=='';}),GETPOSTINT('critical_zero')===1,(int)$user->id);
 elseif($action==='move'){$rows=$qc->checkpoints($id);$ids=array_map(function($row){return(int)$row['rowid'];},$rows);$at=array_search(GETPOSTINT('checkpoint_id'),$ids,true);$other=GETPOST('direction','alpha')==='up'?$at-1:$at+1;if($at===false||!isset($ids[$other]))throw new RuntimeException($langs->trans('QcCheckpointMoveInvalid'));$tmp=$ids[$at];$ids[$at]=$ids[$other];$ids[$other]=$tmp;$qc->reorderDraftCheckpoints($id,$ids,(int)$user->id);}
 elseif($action==='lock')$qc->lockPlan($id,(int)$user->id);
 elseif($action==='create_requirement_revision'){$newId=$qc->createRequirementRevision($id,GETPOST('revision_reason','restricthtml'),(int)$user->id);header('Location: plan.php?id='.(int)$newId);exit;}
 if($action!=='')setEventMessages($langs->trans('Saved'),null,'mesgs');
}catch(Throwable $e){setEventMessages($e->getMessage(),null,'errors');}
$operation=$operations->get($id);$snapshot=$qc->sourceSnapshot($id);$state=$qc->planState($id);$checkpoints=$snapshot?$qc->checkpoints($id):array();$templates=array();if(QcTemplateService::configured())try{$templates=(new QcTemplateService())->listTemplates();}catch(Throwable$ignored){$templates=array();}$users=array();$res=$db->query('SELECT rowid,login,firstname,lastname FROM '.MAIN_DB_PREFIX.'user WHERE entity IN (0,'.(int)$conf->entity.') AND statut=1 AND COALESCE(fk_soc,0)=0 ORDER BY firstname,lastname,login');while($res&&($row=$db->fetch_object($res)))$users[(int)$row->rowid]=trim($row->firstname.' '.$row->lastname)?:$row->login;
llxHeader('',$langs->trans('QcPlan'),'','',0,0,array(),array(cpo_asset('/custom/chinaplanetoperations/css/operations.css')));
?>
<main class="cpo-page"><section class="cpo-hero"><div><span class="cpo-eyebrow"><?php echo dol_escape_htmltag($operation['ref']); ?> · M074</span><h1><?php echo $langs->trans('QcPlan'); ?></h1><p><?php echo dol_escape_htmltag($operation['title']); ?></p></div><div class="cpo-actions"><a class="button" href="../operations/view.php?id=<?php echo $id; ?>"><?php echo $langs->trans('Back'); ?></a><?php if($state&&$state['state']!=='draft'){ ?><a class="button button-save" href="app.php?id=<?php echo $id; ?>"><?php echo $langs->trans('QcOpenFieldApp'); ?></a><?php } ?></div></section>
<?php if(!$snapshot){ ?><section class="cpo-panel"><div class="cpo-notice"><strong><?php echo $langs->trans('QcSnapshotRequired'); ?></strong><br><?php echo $langs->trans('QcSnapshotHelp'); ?></div><?php cpo_qc_action_start($id,'create_snapshot'); ?><button class="button button-save"><?php echo $langs->trans('QcCreateSnapshot'); ?></button></form></section><?php }else{ ?>
<section class="cpo-stats"><div class="cpo-stat"><span><?php echo $langs->trans('QcPlanState'); ?></span><strong><?php echo dol_escape_htmltag($state['state']); ?></strong></div><div class="cpo-stat"><span><?php echo $langs->trans('DolibarrOrderId'); ?></span><strong><?php echo dol_escape_htmltag($snapshot['order_ref']); ?></strong></div><div class="cpo-stat"><span><?php echo $langs->trans('QcOrderLines'); ?></span><strong><?php echo count($snapshot['lines']); ?></strong></div><div class="cpo-stat"><span><?php echo $langs->trans('QcCheckpoints'); ?></span><strong><?php echo count($checkpoints); ?></strong></div></section>
<section class="cpo-panel"><div class="cpo-panel-head"><h2><?php echo $langs->trans('QcFrozenSource'); ?></h2><span class="cpo-badge">SHA-256 <?php echo dol_escape_htmltag(substr($snapshot['snapshot_sha256'],0,12)); ?>…</span></div><div class="div-table-responsive"><table class="cpo-table"><thead><tr><th>#</th><th><?php echo $langs->trans('Product'); ?></th><th><?php echo $langs->trans('Description'); ?></th><th><?php echo $langs->trans('Quantity'); ?></th></tr></thead><tbody><?php foreach($snapshot['lines'] as $line){ ?><tr><td><?php echo (int)$line['position']; ?></td><td><?php echo dol_escape_htmltag($line['product_ref'].' · '.$line['label']); ?></td><td><?php echo nl2br(dol_escape_htmltag($line['description'])); ?></td><td><?php echo dol_escape_htmltag($line['quantity'].' '.$line['unit']); ?></td></tr><?php } ?></tbody></table></div></section>
<?php if($state['state']==='draft'){ ?><section class="cpo-panel"><div class="cpo-panel-head"><h2><?php echo $langs->trans('QcInspector'); ?></h2></div><?php cpo_qc_action_start($id,'assign_inspector','cpo-inline-form'); ?><select required name="inspector_id"><option value="">—</option><?php foreach($users as $key=>$label){ ?><option value="<?php echo $key; ?>"<?php echo (int)$operation['fk_user_assign']===$key?' selected':''; ?>><?php echo dol_escape_htmltag($label); ?></option><?php } ?></select><button class="button"><?php echo $langs->trans('Save'); ?></button></form></section>
<section class="cpo-panel"><div class="cpo-panel-head"><h2>Checkpoint sources</h2><span class="cpo-badge">ORDER · TEMPLATE · MANUAL</span></div><div style="grid-column: 1 / -1; background: linear-gradient(135deg, #0b3558 0%, #176baf 100%); color: #fff; padding: 18px 20px; border-radius: 12px; margin-bottom: 15px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 15px; box-shadow: 0 4px 12px rgba(11,53,88,0.15);">
  <div style="max-width: 650px;">
    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
      <span style="background: #10b981; color: #fff; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 9999px; text-transform: uppercase;">Smart Import Engine</span>
      <span style="color: #93c5fd; font-size: 11px; font-family: monospace;">Row Boundary & Column Auto-Mapping</span>
    </div>
    <h3 style="margin: 0 0 6px 0; font-size: 17px; font-weight: 800; color: #fff;">Excel / CSV Smart Specifications Importer</h3>
    <p style="margin: 0; font-size: 12px; color: #e0f2fe; line-height: 1.4;">Upload customer order sheets or factory specs (.xlsx, .xls, .csv). Exclude unwanted header rows and footer totals with custom row range boundaries, auto-map columns with synonym recognition, and import directly.</p>
  </div>
  <div style="display: flex; gap: 10px; flex-wrap: wrap;">
    <button type="button" onclick="openExcelModal()" class="button button-save" style="background: #10b981; border: none; font-weight: 700; font-size: 13px; padding: 10px 18px; border-radius: 8px; cursor: pointer; color: #fff; box-shadow: 0 2px 8px rgba(16,185,129,0.3);">
      📊 Open Excel Smart Mapper
    </button>
  </div>
</div>
<div class="cpo-context-grid"><div><p>Generate one evidence-required conformity point for every frozen Sales Order line.</p><?php cpo_qc_action_start($id,'generate_order'); ?><button class="button">Generate from Sales Order</button></form></div><div><p>Import an administrator-approved, hash-pinned checklist.</p><?php if($templates){cpo_qc_action_start($id,'import_template','cpo-inline-form'); ?><select required name="template_id"><option value="">—</option><?php foreach($templates as$template){ ?><option value="<?php echo dol_escape_htmltag($template['id']); ?>"><?php echo dol_escape_htmltag($template['label'].' ('.$template['checkpoint_count'].')'); ?></option><?php } ?></select><button class="button">Import template</button></form><?php }else{ ?><span class="opacitymedium">No approved template dataset is configured.</span><?php } ?></div></div><p class="opacitymedium">Contract extraction proposals remain disabled until separate provider and privacy approval. Each proposal will require individual human acceptance; manual entry is always available.</p></section>
<section class="cpo-panel"><div class="cpo-panel-head"><h2>AQL</h2><span class="cpo-badge"><?php echo QcAqlService::configured()?'ISO 2859-1:2026':'DATASET REQUIRED'; ?></span></div><?php cpo_qc_action_start($id,'aql'); ?><div class="cpo-context-grid"><label class="cpo-field"><span><input type="checkbox" name="aql_enabled" value="1"<?php echo !empty($state['aql']['enabled'])?' checked':''; ?><?php echo QcAqlService::configured()?'':' disabled'; ?>> <?php echo $langs->trans('Enabled'); ?></span></label><label class="cpo-field"><span>Lot size</span><input type="number" min="1" name="lot_size"></label><label class="cpo-field"><span>Critical AQL</span><input name="aql_critical" inputmode="decimal"></label><label class="cpo-field"><span>Major AQL</span><input name="aql_major" inputmode="decimal"></label><label class="cpo-field"><span>Minor AQL</span><input name="aql_minor" inputmode="decimal"></label><label class="cpo-field"><span><input type="checkbox" name="critical_zero" value="1"> Critical zero tolerance</span></label></div><button class="button"<?php echo QcAqlService::configured()?'':' disabled'; ?>><?php echo $langs->trans('Save'); ?></button><?php if(!QcAqlService::configured()){ ?><p class="opacitymedium">Validated licensed ISO 2859-1:2026 dataset is not configured. Manual inspection remains available.</p><?php } ?></form></section>
<section class="cpo-panel"><div class="cpo-panel-head"><h2><?php echo $langs->trans('QcAddManualCheckpoint'); ?></h2><span class="cpo-badge">MANUAL</span></div><?php cpo_qc_action_start($id,'add_manual'); ?><div class="cpo-context-grid"><label class="cpo-field"><span><?php echo $langs->trans('QcScope'); ?></span><select name="scope_type" data-qc-scope><option value="inspection"><?php echo $langs->trans('QcWholeInspection'); ?></option><option value="order_line"><?php echo $langs->trans('QcOrderLine'); ?></option></select></label><label class="cpo-field"><span><?php echo $langs->trans('QcOrderLine'); ?></span><select name="snapshot_line_key" data-qc-line><option value="">—</option><?php foreach($snapshot['lines'] as $line){ ?><option value="<?php echo dol_escape_htmltag($line['line_key']); ?>"><?php echo dol_escape_htmltag($line['product_ref'].' · '.$line['label']); ?></option><?php } ?></select></label><label class="cpo-field"><span><?php echo $langs->trans('QcSection'); ?></span><input required name="section_label" value="General"></label><label class="cpo-field"><span><?php echo $langs->trans('QcSectionCode'); ?></span><input required pattern="[a-z0-9_]{1,64}" name="section_code" value="general"></label></div><label class="cpo-field"><span><?php echo $langs->trans('QcRequirement'); ?> *</span><textarea required name="requirement_text"></textarea></label><div class="cpo-context-grid"><label class="cpo-field"><span><?php echo $langs->trans('QcExpected'); ?></span><textarea name="expected_value"></textarea></label><label class="cpo-field"><span><?php echo $langs->trans('QcTolerance'); ?></span><textarea name="tolerance_text"></textarea></label><label class="cpo-field"><span><?php echo $langs->trans('QcMinEvidence'); ?></span><input type="number" min="0" max="20" name="min_evidence" value="0"></label><label class="cpo-field"><span><input type="checkbox" name="mandatory" value="1" checked> <?php echo $langs->trans('QcMandatory'); ?></span></label></div><button class="button button-save"><?php echo $langs->trans('QcAddManualCheckpoint'); ?></button></form></section><?php } ?>
<section class="cpo-panel"><div class="cpo-panel-head"><h2><?php echo $langs->trans('QcCheckpoints'); ?></h2><span><?php echo count($checkpoints); ?></span></div><?php if(!$checkpoints){ ?><p class="opacitymedium"><?php echo $langs->trans('QcNoCheckpoints'); ?></p><?php } ?><div class="cpo-qc-checkpoints"><?php foreach($checkpoints as $index=>$cp){ ?><article class="cpo-qc-checkpoint"><div><span class="cpo-badge"><?php echo dol_escape_htmltag(strtoupper($cp['source_type'])); ?></span> <span class="cpo-badge"><?php echo dol_escape_htmltag($cp['scope_type']); ?></span><h3><?php echo ($index+1).'. '.dol_escape_htmltag($cp['requirement_text']); ?></h3><p><strong><?php echo $langs->trans('QcExpected'); ?>:</strong> <?php echo nl2br(dol_escape_htmltag($cp['expected_value']?:'—')); ?></p><p><strong><?php echo $langs->trans('QcTolerance'); ?>:</strong> <?php echo nl2br(dol_escape_htmltag($cp['tolerance_text']?:'—')); ?></p></div><?php if($state['state']==='draft'&&$cp['source_type']==='manual'){ ?><div class="cpo-actions"><?php cpo_qc_action_start($id,'move'); ?><input type="hidden" name="checkpoint_id" value="<?php echo(int)$cp['rowid']; ?>"><button class="button smallpaddingimp" name="direction" value="up"<?php echo $index===0?' disabled':''; ?>>↑</button><button class="button smallpaddingimp" name="direction" value="down"<?php echo $index===count($checkpoints)-1?' disabled':''; ?>>↓</button></form><?php cpo_qc_action_start($id,'duplicate'); ?><input type="hidden" name="checkpoint_id" value="<?php echo(int)$cp['rowid']; ?>"><button class="button smallpaddingimp"><?php echo $langs->trans('QcDuplicate'); ?></button></form><?php cpo_qc_action_start($id,'remove'); ?><input type="hidden" name="checkpoint_id" value="<?php echo(int)$cp['rowid']; ?>"><button class="button smallpaddingimp"><?php echo $langs->trans('Delete'); ?></button></form></div><details><summary class="button smallpaddingimp"><?php echo $langs->trans('Edit'); ?></summary><?php cpo_qc_action_start($id,'update_manual'); ?><input type="hidden" name="checkpoint_id" value="<?php echo(int)$cp['rowid']; ?>"><input type="hidden" name="client_uuid" value="<?php echo dol_escape_htmltag($cp['client_uuid']); ?>"><input type="hidden" name="revision" value="<?php echo(int)$cp['revision']; ?>"><input type="hidden" name="position" value="<?php echo(int)$cp['position']; ?>"><div class="cpo-context-grid"><label class="cpo-field"><span><?php echo $langs->trans('QcScope'); ?></span><select name="scope_type" data-qc-scope><option value="inspection"<?php echo $cp['scope_type']==='inspection'?' selected':''; ?>><?php echo $langs->trans('QcWholeInspection'); ?></option><option value="order_line"<?php echo $cp['scope_type']==='order_line'?' selected':''; ?>><?php echo $langs->trans('QcOrderLine'); ?></option></select></label><label class="cpo-field"><span><?php echo $langs->trans('QcOrderLine'); ?></span><select name="snapshot_line_key" data-qc-line><option value="">—</option><?php foreach($snapshot['lines'] as $line){ ?><option value="<?php echo dol_escape_htmltag($line['line_key']); ?>"<?php echo $cp['snapshot_line_key']===$line['line_key']?' selected':''; ?>><?php echo dol_escape_htmltag($line['product_ref'].' · '.$line['label']); ?></option><?php } ?></select></label><label class="cpo-field"><span><?php echo $langs->trans('QcSection'); ?></span><input required name="section_label" value="<?php echo dol_escape_htmltag($cp['section_label']); ?>"></label><label class="cpo-field"><span><?php echo $langs->trans('QcSectionCode'); ?></span><input required pattern="[a-z0-9_]{1,64}" name="section_code" value="<?php echo dol_escape_htmltag($cp['section_code']); ?>"></label></div><label class="cpo-field"><span><?php echo $langs->trans('QcRequirement'); ?></span><textarea required name="requirement_text"><?php echo dol_escape_htmltag($cp['requirement_text']); ?></textarea></label><div class="cpo-context-grid"><label class="cpo-field"><span><?php echo $langs->trans('QcExpected'); ?></span><textarea name="expected_value"><?php echo dol_escape_htmltag($cp['expected_value']); ?></textarea></label><label class="cpo-field"><span><?php echo $langs->trans('QcTolerance'); ?></span><textarea name="tolerance_text"><?php echo dol_escape_htmltag($cp['tolerance_text']); ?></textarea></label><label class="cpo-field"><span><?php echo $langs->trans('QcMinEvidence'); ?></span><input type="number" min="0" max="20" name="min_evidence" value="<?php echo(int)$cp['min_evidence']; ?>"></label><label class="cpo-field"><span><input type="checkbox" name="mandatory" value="1"<?php echo(int)$cp['mandatory']===1?' checked':''; ?>> <?php echo $langs->trans('QcMandatory'); ?></span></label></div><button class="button button-save"><?php echo $langs->trans('Save'); ?></button></form></details><?php } ?></article><?php } ?></div></section>
<?php if($state['state']==='draft'){ ?><section class="cpo-panel"><div class="cpo-notice"><strong><?php echo $langs->trans('QcLockWarning'); ?></strong><br><?php echo $langs->trans('QcLockHelp'); ?></div><?php cpo_qc_action_start($id,'lock'); ?><button class="button button-save"><?php echo $langs->trans('QcLockPlan'); ?></button></form></section><?php }else{ ?><section class="cpo-panel"><div class="cpo-panel-head"><h2>New requirement revision</h2></div><div class="cpo-notice">Locked requirements never change in place. Create a linked fresh QC plan for a material requirement change or reinspection.</div><?php cpo_qc_action_start($id,'create_requirement_revision'); ?><label class="cpo-field"><span>Reason</span><textarea required minlength="10" maxlength="2000" name="revision_reason"></textarea></label><button class="button">Create linked QC plan</button></form></section><?php } ?><?php } ?></main>
<script nonce="<?php echo getNonce(); ?>">document.querySelectorAll('[data-qc-scope]').forEach(function(s){var l=s.form.querySelector('[data-qc-line]');function x(){l.required=s.value==='order_line';l.disabled=s.value!=='order_line';if(l.disabled)l.value='';}s.addEventListener('change',x);x();});</script>

<!-- EXCEL SMART MAPPER MODAL -->
<div id="cpo-excel-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.75); z-index:99999; overflow-y:auto; padding:20px; box-sizing:border-box; backdrop-filter:blur(4px);">
  <div style="max-width:980px; margin:20px auto; background:#fff; border-radius:16px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); overflow:hidden; font-family:inherit;">
    <!-- Modal Header -->
    <div style="background:linear-gradient(135deg, #0b3558, #176baf); color:#fff; padding:20px 24px; display:flex; justify-content:space-between; align-items:center;">
      <div>
        <div style="display:flex; align-items:center; gap:8px;">
          <span style="background:#10b981; color:#0f172a; font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; text-transform:uppercase;">SMART IMPORT ENGINE</span>
          <span style="color:#bfdbfe; font-size:12px; font-family:monospace;">Row Range & Column Synonym Mapper</span>
        </div>
        <h2 style="margin:4px 0 2px 0; font-size:18px; color:#fff; font-weight:800;">Excel / CSV Specification Smart Importer</h2>
        <p style="margin:0; font-size:12px; color:#e0f2fe;">Easily exclude top company logos, order metadata, and bottom payment/signature footers.</p>
      </div>
      <button type="button" onclick="closeExcelModal()" style="background:rgba(255,255,255,0.2); border:none; color:#fff; font-size:18px; width:36px; height:36px; border-radius:50%; cursor:pointer; font-weight:bold; display:flex; align-items:center; justify-content:center;">✕</button>
    </div>

    <!-- Modal Body -->
    <div style="padding:24px; max-height:calc(85vh - 120px); overflow-y:auto; box-sizing:border-box;">
      <!-- Step 1: Upload or Demo -->
      <div style="border:2px dashed #cbd5e1; border-radius:12px; padding:20px; text-align:center; background:#f8fafc; margin-bottom:20px;">
        <div style="font-size:32px; margin-bottom:8px;">📄</div>
        <h4 style="margin:0 0 6px 0; font-size:15px; font-weight:700; color:#1e293b;">Select Excel Spreadsheet (.xlsx, .xls) or CSV</h4>
        <p style="margin:0 0 14px 0; font-size:12px; color:#64748b;">Choose a file from your computer, or load our realistic factory spec preset with headers and footers.</p>
        <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap; align-items:center;">
          <input type="file" id="cpo-excel-file-input" accept=".xlsx,.xls,.csv" style="display:none;" onchange="handleExcelUpload(event)">
          <button type="button" onclick="document.getElementById('cpo-excel-file-input').click()" class="button" style="padding:8px 16px; font-weight:600; cursor:pointer;">
            📁 Choose Excel File
          </button>
          <button type="button" onclick="loadSampleExcelSpec()" class="button button-save" style="background:#0284c7; border:none; color:#fff; padding:8px 16px; font-weight:600; cursor:pointer;">
            ⚡ Load Realistic Sample Spec
          </button>
        </div>
        <div id="cpo-excel-file-badge" style="margin-top:12px; font-size:12px; font-family:monospace; color:#047857; font-weight:700; display:none;"></div>
      </div>

      <!-- Step 2: Row Range & Boundary Controls -->
      <div id="cpo-row-range-box" style="display:none; background:#f1f5f9; border-radius:12px; padding:18px; margin-bottom:20px; border:1px solid #e2e8f0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
          <div>
            <h4 style="margin:0; font-size:14px; font-weight:700; color:#0f172a;">1. Set Row Range Boundaries</h4>
            <span style="font-size:11px; color:#64748b;">Specify which row contains column titles and which rows contain item checkpoints.</span>
          </div>
          <span id="cpo-row-count-badge" style="background:#0284c7; color:#fff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:99px;"></span>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:14px;">
          <label style="display:block; font-size:12px; font-weight:600; color:#334155;">
            Header Row (Titles):
            <input type="number" min="1" id="cpo-input-header-row" onchange="onRowBoundariesChange()" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:4px; font-size:13px; box-sizing:border-box;">
          </label>
          <label style="display:block; font-size:12px; font-weight:600; color:#334155;">
            First Data Row (Start):
            <input type="number" min="1" id="cpo-input-start-row" onchange="onRowBoundariesChange()" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:4px; font-size:13px; box-sizing:border-box;">
          </label>
          <label style="display:block; font-size:12px; font-weight:600; color:#334155;">
            Last Data Row (End):
            <input type="number" min="1" id="cpo-input-end-row" onchange="onRowBoundariesChange()" style="width:100%; padding:6px 10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:4px; font-size:13px; box-sizing:border-box;">
          </label>
        </div>

        <!-- Raw Sheet Visualizer -->
        <div style="max-height:180px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:8px; background:#fff;">
          <table style="width:100%; border-collapse:collapse; font-size:11px; text-align:left;">
            <thead>
              <tr style="background:#e2e8f0; color:#334155; position:sticky; top:0;">
                <th style="padding:6px 8px; width:65px;">Row #</th>
                <th style="padding:6px 8px; width:130px;">Classification</th>
                <th style="padding:6px 8px;">Content Snippet</th>
                <th style="padding:6px 8px; text-align:right; width:170px;">Quick Action</th>
              </tr>
            </thead>
            <tbody id="cpo-raw-sheet-tbody"></tbody>
          </table>
        </div>
      </div>

      <!-- Step 3: Column Synonym Mapping -->
      <div id="cpo-mapping-box" style="display:none; background:#f8fafc; border-radius:12px; padding:18px; margin-bottom:20px; border:1px solid #e2e8f0;">
        <h4 style="margin:0 0 4px 0; font-size:14px; font-weight:700; color:#0f172a;">2. Column Smart Mapping</h4>
        <p style="margin:0 0 12px 0; font-size:11px; color:#64748b;">Columns were auto-matched using multilingual synonyms. You can adjust dropdowns below if needed.</p>
        <div id="cpo-mapping-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px;"></div>
      </div>

      <!-- Step 4: Import Mode (Replace vs Append) -->
      <div id="cpo-import-mode-box" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:14px 18px; margin-bottom:20px;">
        <span style="display:block; font-size:12px; font-weight:700; color:#166534; margin-bottom:6px;">3. Import Mode Selection:</span>
        <div style="display:flex; gap:20px; flex-wrap:wrap; font-size:12px; color:#14532d;">
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600;">
            <input type="radio" name="cpo_import_mode" value="replace" checked>
            <span>Replace Current Draft Manual Checkpoints</span>
          </label>
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer; font-weight:600;">
            <input type="radio" name="cpo_import_mode" value="append">
            <span>Append to Current Checkpoints</span>
          </label>
        </div>
        <small style="display:block; margin-top:4px; color:#15803d; font-size:11px;">Note: Frozen Sales Order snapshots are protected and never deleted.</small>
      </div>

      <!-- Step 5: Live Parsed Preview Table -->
      <div id="cpo-preview-box" style="display:none; margin-bottom:10px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <h4 style="margin:0; font-size:13px; font-weight:700; color:#0f172a;">Preview Parsed Checkpoints</h4>
          <span id="cpo-preview-count" style="font-size:12px; font-weight:700; color:#0284c7;"></span>
        </div>
        <div style="max-height:220px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:8px; background:#fff;">
          <table style="width:100%; border-collapse:collapse; font-size:11px; text-align:left;">
            <thead>
              <tr style="background:#e2e8f0; color:#334155; position:sticky; top:0;">
                <th style="padding:6px 8px; width:35px;">#</th>
                <th style="padding:6px 8px; width:100px;">Section</th>
                <th style="padding:6px 8px;">Requirement / Title</th>
                <th style="padding:6px 8px;">Specification / Tolerance</th>
                <th style="padding:6px 8px; width:65px;">Mandatory</th>
              </tr>
            </thead>
            <tbody id="cpo-preview-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Footer -->
    <div style="background:#f1f5f9; padding:16px 24px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:12px; align-items:center;">
      <button type="button" onclick="closeExcelModal()" class="button" style="padding:8px 16px;">Cancel</button>
      <form id="cpo-batch-import-form" method="post" style="display:inline;">
        <input type="hidden" name="token" value="<?php echo newToken(); ?>">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
        <input type="hidden" name="action" value="batch_import_checkpoints">
        <input type="hidden" name="import_mode" id="cpo-form-import-mode" value="replace">
        <input type="hidden" name="checkpoints_json" id="cpo-form-checkpoints-json" value="">
        <button type="button" id="cpo-btn-submit-import" onclick="executeExcelBatchImport()" class="button button-save" disabled style="background:#10b981; border:none; color:#fff; font-weight:700; padding:9px 20px; border-radius:6px; cursor:pointer;">
          ✓ Confirm & Import Checkpoints
        </button>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script nonce="<?php echo getNonce(); ?>">
let cpoRawMatrix = [];
let cpoHeaderRowIdx = 0;
let cpoStartRowIdx = 1;
let cpoEndRowIdx = 1;
let cpoDetectedHeaders = [];
let cpoColumnMappings = {};
let cpoParsedItems = [];

const CPO_SYNONYMS = {
  title: ["item", "checkpoint", "requirement", "product", "name", "title", "description", "بند", "عنصر", "متطلب", "اسم", "المنتج", "وصف"],
  expected: ["specification", "standard", "spec", "expected", "tolerance", "target", "criteria", "المعيار", "المواصفات", "المطلوب", "التفاوت"],
  section: ["section", "category", "group", "department", "type", "قسم", "فئة", "تصنيف", "مجموعة"],
  size: ["size", "dimension", "dimensions", "measurement", "length", "width", "height", "حجم", "مقاس", "أبعاد", "قياس"],
  color: ["color", "colour", "finish", "pantone", "coating", "لون", "تشطيب"],
  qty: ["qty", "quantity", "sample", "units", "pieces", "pcs", "lot", "كمية", "عينة", "عدد"],
  mandatory: ["mandatory", "required", "critical", "إلزامي", "ضروري", "إجباري"]
};

function openExcelModal() {
  document.getElementById('cpo-excel-modal').style.display = 'block';
}

function closeExcelModal() {
  document.getElementById('cpo-excel-modal').style.display = 'none';
}

function handleExcelUpload(event) {
  const file = event.target.files && event.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    try {
      const data = e.target.result;
      if (typeof XLSX !== 'undefined') {
        const wb = XLSX.read(data, { type: 'binary' });
        const ws = wb.Sheets[wb.SheetNames[0]];
        const matrix = XLSX.utils.sheet_to_json(ws, { header: 1, defval: "" });
        initSheetMatrix(matrix, file.name);
      } else {
        const text = String.fromCharCode.apply(null, new Uint8Array(data));
        const lines = text.split(/\r?\n/).filter(l => l.trim().length > 0);
        const matrix = lines.map(line => line.split(',').map(c => c.trim().replace(/^["']|["']$/g, '')));
        initSheetMatrix(matrix, file.name);
      }
    } catch(err) {
      alert("Error reading file: " + err.message);
    }
  };
  reader.readAsBinaryString(file);
}

function loadSampleExcelSpec() {
  const sampleMatrix = [
    ["CHINA PLANET INDUSTRIAL GROUP — FACTORY PURCHASE ORDER SPECIFICATION SHEET", "", "", "", "", "", ""],
    ["Order Ref: CO2608-0114", "Date: 2026-09-07", "Factory: Yiwu Zenith Metalware Co.", "Standard: ISO 2859-1 Level II", "", "", ""],
    ["", "", "", "", "", "", ""],
    ["Item / Checkpoint", "Technical Specification", "Dimensions / Size", "Color & Finish", "Order Qty", "Mandatory?", "Section / Category"],
    ["Vacuum Flask Double-Wall Body", "SUS304 Food Grade Stainless Steel. Height 265mm ±2mm, OD 72mm. High vacuum seal.", "265mm x 72mm", "Matte Midnight Blue (Pantone 534C)", "5,000 Pcs", "Yes", "Dimensions & Material"],
    ["BPA-Free Tritan Cap & Silicone Gasket", "Leak-proof under 0.5 Bar pressure for 60s. Food grade test report certified.", "58mm Cap Dia", "Brushed Silver Top + Black Base", "5,000 Pcs", "Yes", "Sealing & Safety"],
    ["6-Hour Thermal Retention Temperature", "Boiling water 100°C must remain > 65°C after 6 hours. Ambient temp 24°C.", "500ml Capacity", "All Colors", "5,000 Pcs", "Yes", "Custom Testing"],
    ["Laser Engraved Customer Logo", "Al-Madina Arabic/English logo. Centered 45mm from bottom. Resists 3M 610 tape test.", "35mm x 25mm Logo", "Laser Etched Silver", "5,000 Pcs", "No", "Visual & Labeling"],
    ["Export Corrugated Master Carton", "5-ply double wall, burst strength > 14 kg/cm². Pass 1-meter drop test on 1 corner, 3 edges, 6 faces.", "52cm x 44cm x 30cm", "Kraft Brown + Black Print", "200 Cartons", "Yes", "Packaging & Carton Drop"],
    ["TOTAL ORDER QUANTITY: 5,000 PCS", "", "", "", "", "", ""],
    ["PAYMENT TERMS: 30% DEPOSIT, 70% AGAINST SIGNED QC PASS REPORT", "", "", "", "", "", ""],
    ["AUTHORIZED FACTORY SIGNATURE: ____________________", "INSPECTOR APPROVAL: ____________________", "", "", "", "", ""]
  ];
  initSheetMatrix(sampleMatrix, "Factory_Spec_CO2608_Sample.xlsx");
}

function initSheetMatrix(matrix, filename) {
  if (!matrix || matrix.length === 0) {
    alert("The file contains no data rows.");
    return;
  }
  cpoRawMatrix = matrix;
  const badge = document.getElementById('cpo-excel-file-badge');
  badge.textContent = '✓ Loaded: ' + filename + ' (' + matrix.length + ' total rows detected)';
  badge.style.display = 'block';

  autoDetectBoundaries();
  document.getElementById('cpo-row-range-box').style.display = 'block';
  document.getElementById('cpo-mapping-box').style.display = 'block';
  document.getElementById('cpo-import-mode-box').style.display = 'block';
  document.getElementById('cpo-preview-box').style.display = 'block';
  document.getElementById('cpo-btn-submit-import').removeAttribute('disabled');
}

function autoDetectBoundaries() {
  let bestHeader = 0, maxScore = -1;
  cpoRawMatrix.forEach((row, idx) => {
    let score = 0;
    const nonEmpties = row.filter(c => String(c).trim().length > 0);
    if (nonEmpties.length >= 2) {
      nonEmpties.forEach(cell => {
        const str = String(cell).toLowerCase();
        Object.keys(CPO_SYNONYMS).forEach(k => {
          if (CPO_SYNONYMS[k].some(syn => str.includes(syn))) score += 2;
        });
      });
    }
    if (score > maxScore) {
      maxScore = score;
      bestHeader = idx;
    }
  });

  cpoHeaderRowIdx = bestHeader;
  cpoStartRowIdx = cpoHeaderRowIdx + 1;

  let detectedEnd = cpoRawMatrix.length - 1;
  for (let i = cpoStartRowIdx; i < cpoRawMatrix.length; i++) {
    const row = cpoRawMatrix[i];
    const rowStr = row.map(c => String(c).trim()).join(' ').toLowerCase();
    if (rowStr.includes('total') || rowStr.includes('payment') || rowStr.includes('signature') || rowStr.includes('terms') || row.filter(c => String(c).trim().length > 0).length === 0) {
      detectedEnd = i - 1;
      break;
    }
  }
  if (detectedEnd < cpoStartRowIdx) detectedEnd = cpoStartRowIdx;
  cpoEndRowIdx = detectedEnd;

  syncInputs();
  applyBoundaries();
}

function syncInputs() {
  document.getElementById('cpo-input-header-row').value = cpoHeaderRowIdx + 1;
  document.getElementById('cpo-input-start-row').value = cpoStartRowIdx + 1;
  document.getElementById('cpo-input-end-row').value = cpoEndRowIdx + 1;
}

function onRowBoundariesChange() {
  const h = parseInt(document.getElementById('cpo-input-header-row').value, 10) - 1;
  const s = parseInt(document.getElementById('cpo-input-start-row').value, 10) - 1;
  const e = parseInt(document.getElementById('cpo-input-end-row').value, 10) - 1;
  if (!isNaN(h) && h >= 0) cpoHeaderRowIdx = h;
  if (!isNaN(s) && s >= 0) cpoStartRowIdx = s;
  if (!isNaN(e) && e >= cpoStartRowIdx) cpoEndRowIdx = Math.min(e, cpoRawMatrix.length - 1);
  applyBoundaries();
}

function setRowRole(role, idx) {
  if (role === 'header') {
    cpoHeaderRowIdx = idx;
    if (cpoStartRowIdx <= cpoHeaderRowIdx) cpoStartRowIdx = cpoHeaderRowIdx + 1;
  } else if (role === 'start') {
    cpoStartRowIdx = idx;
    if (cpoStartRowIdx <= cpoHeaderRowIdx) cpoHeaderRowIdx = Math.max(0, cpoStartRowIdx - 1);
    if (cpoEndRowIdx < cpoStartRowIdx) cpoEndRowIdx = cpoStartRowIdx;
  } else if (role === 'end') {
    cpoEndRowIdx = idx;
    if (cpoEndRowIdx < cpoStartRowIdx) cpoStartRowIdx = cpoEndRowIdx;
  }
  syncInputs();
  applyBoundaries();
}

function applyBoundaries() {
  if (!cpoRawMatrix || cpoRawMatrix.length === 0) return;
  const count = Math.max(0, cpoEndRowIdx - cpoStartRowIdx + 1);
  document.getElementById('cpo-row-count-badge').textContent = 'Importing Rows ' + (cpoStartRowIdx + 1) + ' to ' + (cpoEndRowIdx + 1) + ' (' + count + ' items)';

  // Extract column headers
  const headerRow = cpoRawMatrix[cpoHeaderRowIdx] || [];
  cpoDetectedHeaders = headerRow.map((c, i) => String(c).trim() || 'Column ' + (i + 1));

  renderRawVisualizer();
  autoMatchColumns();
  renderMappingSelectors();
  parseAndRenderPreview();
}

function renderRawVisualizer() {
  const tbody = document.getElementById('cpo-raw-sheet-tbody');
  tbody.innerHTML = cpoRawMatrix.map((row, idx) => {
    const rowNum = idx + 1;
    const snippet = row.filter(c => String(c).trim().length > 0).slice(0, 3).join(' | ');
    let badge = '', bg = '';
    if (idx === cpoHeaderRowIdx) {
      badge = '<span style="background:#fef08a; color:#854d0e; font-weight:800; padding:2px 6px; border-radius:4px;">HEADER ROW</span>';
      bg = 'background:#fefce8;';
    } else if (idx >= cpoStartRowIdx && idx <= cpoEndRowIdx) {
      badge = '<span style="background:#bbf7d0; color:#166534; font-weight:700; padding:2px 6px; border-radius:4px;">DATA ROW</span>';
      bg = 'background:#f0fdf4;';
    } else if (idx < cpoHeaderRowIdx) {
      badge = '<span style="background:#e2e8f0; color:#64748b; padding:2px 6px; border-radius:4px;">IGNORED (HEADER)</span>';
      bg = 'opacity:0.6;';
    } else {
      badge = '<span style="background:#e2e8f0; color:#64748b; padding:2px 6px; border-radius:4px;">IGNORED (FOOTER)</span>';
      bg = 'opacity:0.6;';
    }
    return '<tr style="' + bg + ' border-bottom:1px solid #f1f5f9;">' +
      '<td style="padding:5px 8px; font-weight:700; color:#64748b;">Row ' + rowNum + '</td>' +
      '<td style="padding:5px 8px;">' + badge + '</td>' +
      '<td style="padding:5px 8px; font-family:monospace; font-size:10px; color:#334155; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' + (snippet || '—') + '</td>' +
      '<td style="padding:5px 8px; text-align:right;">' +
        '<button type="button" onclick="setRowRole(\'header\',' + idx + ')" style="padding:2px 5px; font-size:9px; font-weight:700; margin-right:2px; cursor:pointer;">Set Header</button>' +
        '<button type="button" onclick="setRowRole(\'start\',' + idx + ')" style="padding:2px 5px; font-size:9px; font-weight:700; margin-right:2px; cursor:pointer;">Set Start</button>' +
        '<button type="button" onclick="setRowRole(\'end\',' + idx + ')" style="padding:2px 5px; font-size:9px; font-weight:700; cursor:pointer;">Set End</button>' +
      '</td>' +
    '</tr>';
  }).join('');
}

function autoMatchColumns() {
  cpoColumnMappings = { title: null, expected: null, section: null, size: null, color: null, qty: null, mandatory: null };
  Object.keys(CPO_SYNONYMS).forEach(k => {
    const syns = CPO_SYNONYMS[k];
    for (const h of cpoDetectedHeaders) {
      const clean = h.toLowerCase().replace(/[^a-z0-9\u0600-\u06FF]/g, ' ').trim();
      if (syns.some(s => clean === s || clean.includes(s) || s.includes(clean))) {
        cpoColumnMappings[k] = h;
        break;
      }
    }
  });
}

function renderMappingSelectors() {
  const grid = document.getElementById('cpo-mapping-grid');
  const defs = [
    { key: "title", label: "Item / Requirement Title *", req: true },
    { key: "expected", label: "Specification / Standard *", req: true },
    { key: "section", label: "Section / Category", req: false },
    { key: "size", label: "Size / Dimensions", req: false },
    { key: "color", label: "Color / Finish", req: false },
    { key: "qty", label: "Quantity / Sample", req: false },
    { key: "mandatory", label: "Mandatory Flag", req: false }
  ];

  grid.innerHTML = defs.map(d => {
    const currentVal = cpoColumnMappings[d.key] || "";
    const isMatched = !!currentVal;
    return '<div style="background:#fff; border:1px solid ' + (isMatched ? '#86efac' : '#cbd5e1') + '; padding:10px; border-radius:8px;">' +
      '<div style="display:flex; justify-content:space-between; margin-bottom:4px;">' +
        '<span style="font-size:11px; font-weight:700; color:#1e293b;">' + d.label + '</span>' +
        (isMatched ? '<span style="font-size:9px; background:#dcfce7; color:#15803d; font-weight:700; padding:1px 5px; border-radius:3px;">Matched ✓</span>' : '<span style="font-size:9px; color:#94a3b8;">Optional</span>') +
      '</div>' +
      '<select onchange="updateColumnMapping(\'' + d.key + '\', this.value)" style="width:100%; font-size:11px; padding:4px; border:1px solid #cbd5e1; border-radius:4px;">' +
        '<option value="">(None / Do Not Map)</option>' +
        cpoDetectedHeaders.map(h => '<option value="' + h.replace(/"/g, '&quot;') + '"' + (h === currentVal ? ' selected' : '') + '>' + h + '</option>').join('') +
      '</select>' +
    '</div>';
  }).join('');
}

function updateColumnMapping(field, colName) {
  cpoColumnMappings[field] = colName || null;
  parseAndRenderPreview();
}

function parseAndRenderPreview() {
  cpoParsedItems = [];
  for (let r = cpoStartRowIdx; r <= cpoEndRowIdx && r < cpoRawMatrix.length; r++) {
    const row = cpoRawMatrix[r];
    const getVal = (col) => {
      if (!col) return "";
      const idx = cpoDetectedHeaders.indexOf(col);
      return idx >= 0 && row[idx] !== undefined ? String(row[idx]).trim() : "";
    };

    const title = getVal(cpoColumnMappings.title) || (row[0] ? String(row[0]).trim() : 'Checkpoint ' + (r + 1));
    if (!title) continue;

    let spec = getVal(cpoColumnMappings.expected) || 'Conform to approved standard / sample.';
    const size = getVal(cpoColumnMappings.size);
    const color = getVal(cpoColumnMappings.color);
    const qty = getVal(cpoColumnMappings.qty);
    const section = getVal(cpoColumnMappings.section) || 'General';

    let expectedParts = [];
    if (spec) expectedParts.push(spec);
    if (size) expectedParts.push('Size: ' + size);
    if (color) expectedParts.push('Color: ' + color);
    if (qty) expectedParts.push('Qty: ' + qty);

    let isMandatory = 1;
    if (cpoColumnMappings.mandatory) {
      const mandVal = getVal(cpoColumnMappings.mandatory).toLowerCase();
      isMandatory = (mandVal.includes('no') || mandVal === '0' || mandVal.includes('false')) ? 0 : 1;
    }

    cpoParsedItems.push({
      requirement_text: title,
      expected_value: expectedParts.join(' | '),
      tolerance_text: size ? 'Standard tolerance apply' : '',
      section_label: section,
      section_code: section.toLowerCase().replace(/[^a-z0-9_]/g, '_'),
      scope_type: 'inspection',
      mandatory: isMandatory,
      min_evidence: 1
    });
  }

  document.getElementById('cpo-preview-count').textContent = cpoParsedItems.length + ' Checkpoints Ready';
  const tbody = document.getElementById('cpo-preview-tbody');
  tbody.innerHTML = cpoParsedItems.map((item, idx) => {
    return '<tr style="border-bottom:1px solid #f1f5f9;">' +
      '<td style="padding:6px 8px; font-weight:700; color:#64748b;">' + (idx + 1) + '</td>' +
      '<td style="padding:6px 8px;"><span style="background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; font-weight:700; font-size:10px;">' + item.section_label + '</span></td>' +
      '<td style="padding:6px 8px; font-weight:700; color:#0f172a;">' + item.requirement_text + '</td>' +
      '<td style="padding:6px 8px; color:#475569;">' + item.expected_value + '</td>' +
      '<td style="padding:6px 8px;">' + (item.mandatory ? '<span style="color:#dc2626; font-weight:700;">Yes</span>' : '<span style="color:#64748b;">No</span>') + '</td>' +
    '</tr>';
  }).join('');
}

function executeExcelBatchImport() {
  if (!cpoParsedItems || cpoParsedItems.length === 0) {
    alert("No checkpoints parsed to import.");
    return;
  }
  const mode = document.querySelector('input[name="cpo_import_mode"]:checked').value;
  document.getElementById('cpo-form-import-mode').value = mode;
  document.getElementById('cpo-form-checkpoints-json').value = JSON.stringify(cpoParsedItems);
  document.getElementById('cpo-batch-import-form').submit();
}
</script>

<?php llxFooter();$db->close();
function cpo_qc_action_start($id,$action,$class=''){echo '<form method="post"'.($class?' class="'.dol_escape_htmltag($class).'"':'').'><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.(int)$id.'"><input type="hidden" name="action" value="'.dol_escape_htmltag($action).'">';}
function cpo_qc_uuid(){$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
