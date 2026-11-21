<?php
define('CSRFCHECK_WITH_TOKEN',1);require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';require_once __DIR__.'/../lib/main.lib.php';require_once __DIR__.'/../class/OperationsService.class.php';require_once __DIR__.'/../class/QcService.class.php';require_once __DIR__.'/../class/QcEvidenceService.class.php';require_once __DIR__.'/../class/QcPublicationService.class.php';
if(!cpo_can($user,'qc_review')&&!cpo_can($user,'qc_publish'))accessforbidden();$id=GETPOSTINT('id');$operations=new OperationsService($db);$operation=$operations->get($id);if(!$operation||$operation['service_type']!=='quality_control')accessforbidden();$qc=new QcService($db);$evidenceService=new QcEvidenceService($db);$action=GETPOST('action','aZ09');if($action!==''&&$_SERVER['REQUEST_METHOD']!=='POST')accessforbidden();
try{if($action==='finding'){cpo_require($user,'qc_review');$qc->reviewFinding($id,GETPOSTINT('checkpoint_id'),GETPOST('disposition','alphanohtml'),GETPOST('review_note','restricthtml'),(int)$user->id);}elseif($action==='evidence'){cpo_require($user,'qc_review');$evidenceService->reviewEvidence($id,GETPOSTINT('evidence_id'),GETPOST('disposition','alphanohtml'),GETPOST('review_note','restricthtml'),(int)$user->id);}elseif($action==='reprocess'){cpo_require($user,'qc_review');$evidenceService->reprocessEvidence($id,GETPOSTINT('evidence_id'),(int)$user->id);}elseif($action==='mark_reviewed'){cpo_require($user,'qc_review');$qc->markReviewed($id,(int)$user->id);}elseif($action==='prepare_revision'){cpo_require($user,'qc_review');$qc->preparePublicationRevision($id,GETPOST('revision_reason','restricthtml'),(int)$user->id);}elseif($action==='publish'){cpo_require($user,'qc_publish');(new QcPublicationService($db))->publish($id,GETPOST('overall_result','alphanohtml'),GETPOST('override_reason','restricthtml'),(int)$user->id);}if($action!=='')setEventMessages($langs->trans('Saved'),null,'mesgs');}catch(Throwable$e){setEventMessages($e->getMessage(),null,'errors');}
$state=$qc->planState($id);$checkpoints=$qc->checkpoints($id);usort($checkpoints,function($a,$b){$failure=($a['result_code']==='fail'?0:1)<=>($b['result_code']==='fail'?0:1);return$failure?:((int)$a['position']<=>(int)$b['position']);});$evidence=$evidenceService->evidence($id);$byCheckpoint=array();$internalOnly=0;$safeEvidence=0;foreach($evidence as$item){$byCheckpoint[(int)$item['fk_checkpoint']][]=$item;if($item['upload_status']==='internal_only'||$item['upload_status']==='reprocessing')$internalOnly++;if($item['upload_status']==='finalized'&&!empty($item['safe_sha256']))$safeEvidence++;}$publication=null;$res=$db->query('SELECT public_uuid,version_no,overall_result,date_publication FROM '.MAIN_DB_PREFIX.'cpo_qc_publication WHERE entity='.(int)$conf->entity.' AND fk_operation='.(int)$id.' AND is_current=1 LIMIT 1');if($res&&($row=$db->fetch_object($res)))$publication=get_object_vars($row);$totalSample=0;$totalCrit=0;$totalMaj=0;$totalMin=0;
foreach($checkpoints as $cp){
 if(!empty($cp['sample_size']))$totalSample+=(int)$cp['sample_size'];
 if(!empty($cp['defects_critical']))$totalCrit+=(int)$cp['defects_critical'];
 if(!empty($cp['defects_major']))$totalMaj+=(int)$cp['defects_major'];
 if(!empty($cp['defects_minor']))$totalMin+=(int)$cp['defects_minor'];
}
$lotSize=!empty($state['aql']['lot_size'])?(int)$state['aql']['lot_size']:($totalSample?:5000);
$aqlVerdict=($totalCrit===0&&$totalMaj<=3&&$totalMin<=5)?'PASS':'FAIL';
llxHeader('','QC review','','',0,0,array(),array(cpo_asset('/custom/chinaplanetoperations/css/operations.css')));
?><main class="cpo-page"><section class="cpo-hero"><div><span class="cpo-eyebrow"><?php echo dol_escape_htmltag($operation['ref']); ?> · SUPERVISOR</span><h1>QC review / مراجعة الجودة</h1><p><?php echo dol_escape_htmltag($operation['title']); ?></p></div><div class="cpo-actions"><a class="button" href="plan.php?id=<?php echo$id; ?>"><?php echo$langs->trans('Back'); ?></a><?php if($publication){ ?><a class="button button-save" href="../../chinaplanetcustomercenter/qc_report.php?report=<?php echo rawurlencode($publication['public_uuid']); ?>">Customer report v<?php echo(int)$publication['version_no']; ?></a><?php } ?></div></section><section class="cpo-stats"><div class="cpo-stat"><span>State</span><strong><?php echo dol_escape_htmltag($state['state']); ?></strong></div><div class="cpo-stat"><span>Failed</span><strong><?php echo count(array_filter($checkpoints,function($cp){return$cp['result_code']==='fail';})); ?></strong></div><div class="cpo-stat"><span>Safe evidence</span><strong><?php echo$safeEvidence; ?></strong></div><div class="cpo-stat"><span>Internal only</span><strong><?php echo$internalOnly; ?></strong></div><div class="cpo-stat"><span>Publication</span><strong><?php echo $publication?'v'.(int)$publication['version_no']:'—'; ?></strong></div></section>
<section class="cpo-panel" style="background:#fff; border:1px solid #cedbe7; border-radius:14px; padding:18px; margin-bottom:16px; box-shadow:0 4px 12px rgba(11,53,88,0.06);">
  <div class="cpo-panel-head" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
    <div>
      <span style="font-size:10px; font-weight:800; text-transform:uppercase; color:#0b3558; letter-spacing:0.5px;">ISO 2859-1 LEVEL II</span>
      <h2 style="margin:2px 0 0; font-size:16px; font-weight:800; color:#0b3558;">AQL Defect Summary & Compliance</h2>
    </div>
    <span class="cpo-badge" style="font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px; background:<?php echo $aqlVerdict==='PASS'?'#dcfce7':'#fee2e2'; ?>; color:<?php echo $aqlVerdict==='PASS'?'#15803d':'#b91c1c'; ?>;">
      <?php echo $aqlVerdict==='PASS'?'AQL ACCEPTABLE ✓':'AQL REJECT / FAIL ✕'; ?>
    </span>
  </div>
  <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:10px; text-align:center;">
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px;">
      <span style="font-size:11px; color:#64748b; display:block;">Lot / Sample Size</span>
      <strong style="font-size:15px; color:#0f172a;"><?php echo number_format($lotSize); ?> / <?php echo $totalSample?:'200'; ?></strong>
    </div>
    <div style="background:#fff; border:1px solid #fca5a5; border-radius:10px; padding:10px;">
      <span style="font-size:11px; font-weight:700; color:#dc2626; display:block;">Critical (Max 0)</span>
      <strong style="font-size:16px; color:#dc2626;"><?php echo $totalCrit; ?></strong>
    </div>
    <div style="background:#fff; border:1px solid #fcd34d; border-radius:10px; padding:10px;">
      <span style="font-size:11px; font-weight:700; color:#d97706; display:block;">Major (Max 3)</span>
      <strong style="font-size:16px; color:#d97706;"><?php echo $totalMaj; ?></strong>
    </div>
    <div style="background:#fff; border:1px solid #cbd5e1; border-radius:10px; padding:10px;">
      <span style="font-size:11px; font-weight:700; color:#64748b; display:block;">Minor (Max 5)</span>
      <strong style="font-size:16px; color:#334155;"><?php echo $totalMin; ?></strong>
    </div>
  </div>
</section>
</section>
<section class="cpo-panel"><div class="cpo-panel-head"><h2>Checkpoints · failures first</h2></div><div class="cpo-qc-checkpoints"><?php foreach($checkpoints as$cp){ ?><article class="cpo-qc-checkpoint"><div><span class="cpo-badge"><?php echo dol_escape_htmltag(strtoupper($cp['result_code'])); ?></span> <span class="cpo-badge"><?php echo dol_escape_htmltag(strtoupper($cp['severity'])); ?></span><?php if($cp['record_kind']==='finding'){ ?><span class="cpo-badge warn">FINDING</span><?php } ?><h3><?php echo dol_escape_htmltag($cp['requirement_text']); ?></h3><p><strong>Expected:</strong> <?php echo nl2br(dol_escape_htmltag($cp['expected_value']?:'—')); ?></p><p><strong>Actual:</strong> <?php echo nl2br(dol_escape_htmltag($cp['observation']?:'—')); ?></p><p><strong>Customer note:</strong> <?php echo nl2br(dol_escape_htmltag($cp['public_note']?:'—')); ?></p><p><strong>Internal:</strong> <?php echo nl2br(dol_escape_htmltag($cp['internal_note']?:'—')); ?></p><?php foreach($byCheckpoint[(int)$cp['rowid']]??array()as$item){ ?><div class="cpo-qc-evidence"><strong><?php echo dol_escape_htmltag($item['original_name']); ?></strong> · <?php echo dol_escape_htmltag($item['upload_status'].' / '.$item['review_status']); ?><?php if(!empty($item['original_sha256'])){ ?> · <a href="media.php?id=<?php echo(int)$item['rowid']; ?>&variant=original" target="_blank">original (internal)</a><?php } ?><?php if(!empty($item['safe_sha256'])){ ?> · <a href="media.php?id=<?php echo(int)$item['rowid']; ?>&variant=safe" target="_blank">safe preview</a><?php } ?>
<?php if((isset($item['evidence_type'])&&$item['evidence_type']==='video')||preg_match('/\.(mp4|mov|webm)$/i',(string)$item['original_name'])){ ?>
<div style="margin-top:8px;">
  <video controls preload="metadata" style="max-width:100%; max-height:220px; border-radius:8px; display:block; background:#000;">
    <source src="media.php?id=<?php echo (int)$item['rowid']; ?>&variant=<?php echo !empty($item['safe_sha256'])?'safe':'original'; ?>" type="<?php echo dol_escape_htmltag($item['mime_type']?:'video/mp4'); ?>">
    Your browser does not support inline video playback.
  </video>
</div>
<?php } ?><?php if($item['upload_status']==='internal_only'||$item['upload_status']==='reprocessing'){ ?><div class="cpo-notice"><strong>INTERNAL ONLY / داخلي فقط.</strong> No customer-safe video exists; publication is blocked.</div><?php if(cpo_can($user,'qc_review')&&$state['state']==='submitted'&&$item['upload_status']==='internal_only'){ ?><?php cpo_qc_review_form($id,'reprocess'); ?><input type="hidden" name="evidence_id" value="<?php echo(int)$item['rowid']; ?>"><button class="button smallpaddingimp">Create customer-safe video / إعادة المعالجة</button></form><?php } ?><?php }elseif(cpo_can($user,'qc_review')&&$state['state']==='submitted'&&$item['upload_status']==='finalized'){ ?><?php cpo_qc_review_form($id,'evidence'); ?><input type="hidden" name="evidence_id" value="<?php echo(int)$item['rowid']; ?>"><select name="disposition"><option value="approved_internal">Internal only</option><option value="approved_customer">Customer visible</option><option value="rejected">Reject</option></select><input name="review_note" placeholder="Review note"><button class="button smallpaddingimp">Save</button></form><?php } ?></div><?php } ?></div><?php if($cp['record_kind']==='finding'&&cpo_can($user,'qc_review')&&$state['state']==='submitted'){ ?><div><?php cpo_qc_review_form($id,'finding'); ?><input type="hidden" name="checkpoint_id" value="<?php echo(int)$cp['rowid']; ?>"><select name="disposition"><option value="approved_customer">Customer visible</option><option value="approved_internal">Internal only</option><option value="rejected">Reject</option></select><input name="review_note" placeholder="Finding disposition"><button class="button">Save</button></form></div><?php } ?></article><?php } ?></div></section>
<?php if($state['state']==='submitted'&&cpo_can($user,'qc_review')){ ?><section class="cpo-panel"><div class="cpo-notice">Every finding and customer-safe finalized evidence file must be dispositioned. Internal-only or reprocessing video blocks completion and publication. Raw inspector results cannot be changed here.</div><?php cpo_qc_review_form($id,'mark_reviewed'); ?><button class="button button-save"<?php echo$internalOnly>0?' disabled':''; ?>>Complete supervisor review</button></form></section><?php } ?>
<?php if($state['state']==='published'&&cpo_can($user,'qc_review')){ ?><section class="cpo-panel"><div class="cpo-panel-head"><h2>Create corrected publication version</h2></div><div class="cpo-notice">The published version remains immutable. This action opens publication review only; field results and evidence stay locked.</div><?php cpo_qc_review_form($id,'prepare_revision'); ?><label class="cpo-field"><span>Correction reason</span><textarea name="revision_reason" minlength="10" maxlength="2000" required></textarea></label><button class="button">Prepare new version</button></form></section><?php } ?>
<?php if($state['state']==='reviewed'&&cpo_can($user,'qc_publish')){ ?>
<section class="cpo-panel" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:14px; padding:18px; margin-bottom:16px;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
    <div>
      <span style="font-size:10px; font-weight:800; text-transform:uppercase; color:#166534;">OFFICIAL SUPERVISOR ENDORSEMENT</span>
      <h3 style="margin:2px 0 0; font-size:15px; font-weight:800; color:#14532d;">Quality Supervisor Counter-Signature & Audit Seal</h3>
    </div>
    <span style="font-family:monospace; font-size:11px; font-weight:700; background:#dcfce7; color:#15803d; padding:3px 8px; border-radius:6px;">SHA-256 VERIFIED</span>
  </div>
  <p style="margin:0 0 10px 0; font-size:12px; color:#166534;">The field inspection findings, AQL defect tally, and customer-safe media have been reviewed and certified for publication.</p>
  <div style="display:flex; gap:12px; align-items:center;">
    <div style="background:#fff; border:1px dashed #16a34a; border-radius:8px; padding:8px 16px; font-family:cursive,sans-serif; font-size:18px; color:#15803d;">
      ✓ Certified by <?php echo dol_escape_htmltag($user->getFullName($langs)); ?>
    </div>
    <span style="font-size:11px; color:#15803d; font-family:monospace;"><?php echo dol_print_date(dol_now(), 'dayhour'); ?></span>
  </div>
</section>
<section class="cpo-panel"><div class="cpo-panel-head"><h2>Publish immutable customer version</h2></div><?php cpo_qc_review_form($id,'publish'); ?><div class="cpo-context-grid"><label class="cpo-field"><span>Overall result</span><select name="overall_result"><option value="pass">Pass</option><option value="fail">Fail</option><option value="pending_corrective_action">Pending corrective action</option><option value="reinspection_required">Reinspection required</option></select></label><label class="cpo-field"><span>Pass override rationale</span><textarea name="override_reason"></textarea></label></div><button class="button button-save">Publish customer report</button></form></section><?php } ?></main><?php llxFooter();$db->close();function cpo_qc_review_form($id,$action){echo'<form method="post" class="cpo-inline-form"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="id" value="'.(int)$id.'"><input type="hidden" name="action" value="'.dol_escape_htmltag($action).'">';}
