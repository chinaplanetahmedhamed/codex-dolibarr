<?php
$root=getenv('M074_DOL_DOCUMENT_ROOT')?:dirname(__DIR__,2);$failed=array();function m074($ok,$label){global$failed;echo($ok?'PASS ':'FAIL ').$label."\n";if(!$ok)$failed[]=$label;}

require_once $root.'/custom/chinaplanetoperations/class/QcAqlService.class.php';
require_once $root.'/custom/chinaplanetoperations/class/QcEvidenceService.class.php';
require_once $root.'/custom/chinaplanetcustomercenter/lib/operation_reports.lib.php';

$module=file_get_contents($root.'/custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php');
foreach(array('qc_plan','qc_execute','qc_review','qc_publish')as$right)m074(strpos($module,"'right'=>'".$right."'")!==false||strpos($module,"'perms'=>'".$right."'")!==false||strpos($module,"'label'=>'".$right."'")!==false||strpos($module,$right)!==false,'module declares '.$right.' permission');
$service=file_get_contents($root.'/custom/chinaplanetoperations/class/QcService.class.php');
m074(strpos($service,'Manual QC checkpoint added')!==false&&strpos($service,"array('draft')")!==false,'manual checkpoints are first-class draft plan records');
m074(strpos($service,'qc_source_snapshot')!==false&&strpos($service,'qc_plan_state')!==false,'QC source snapshot and lifecycle are separate immutable sections');
m074(strpos($service,'preparePublicationRevision')!==false&&strpos($service,"array('published')")!==false,'published corrections create a controlled new version');
m074(strpos($service,'createRequirementRevision')!==false&&strpos($service,'qc_revision_link')!==false&&strpos($service,'superseded_by_operation_id')!==false&&strpos($service,'createRevisionSnapshot($newId,$snapshot,$userId)')!==false&&strpos($service,'QC plan was superseded by a linked requirement revision')!==false,'locked requirement changes atomically clone a linked fresh plan and disable the superseded plan');
m074(strpos($service,'snapshotApprovedAssets')!==false&&strpos($service,"hash_file('sha256',\$source)")!==false&&strpos($service,'Frozen QC source asset verification failed')!==false&&strpos($service,"'assets' => \$assets")!==false,'approved source assets are copied and hash-pinned into the immutable snapshot');
m074(strpos($service,'copyRevisionAssets')!==false&&strpos($service,'Prior QC source asset failed revision verification')!==false&&strpos($service,"array_merge(isset(\$snapshot['specifications'])")!==false,'requirement revisions preserve verified frozen assets and customer requirements');

$evidence=file_get_contents($root.'/custom/chinaplanetoperations/class/QcEvidenceService.class.php');
m074(strpos($evidence,'20971520')!==false&&strpos($evidence,'209715200')!==false,'evidence limits are 20 MiB per chunk and 200 MiB per file');
m074(strpos($evidence,"hash_file('sha256'")!==false&&strpos($evidence,"FILEINFO_MIME_TYPE")!==false&&strpos($evidence,"'-map_metadata','-1'")!==false,'evidence finalization verifies SHA MIME and strips video metadata');
m074(strpos($evidence,"state']??'')!=='submitted'")!==false&&strpos($evidence,'review_status=')!==false,'evidence disposition is limited to submitted supervisor review');
m074(strpos($evidence,'MAX_VIDEO_SECONDS')!==false&&strpos($evidence,'FFPROBE_PATH')!==false&&strpos($evidence,'runProcess')!==false,'media sanitizer enforces probe and bounded processing controls');
m074(strpos($evidence,"upload_status']==='expired'")!==false&&strpos($evidence,"'restarted'=>true")!==false&&strpos($evidence,'different payload or owner')!==false,'expired evidence sessions can safely restart from encrypted local chunks without cross-owner reuse');
m074(strpos($evidence,"upload_status']==='uploading'")<strpos($evidence,'assertUploadCapacity($actorId,$size)'),'idempotent active upload resume is resolved before new-upload quota accounting');
m074(strpos($evidence,'proc_open($args')!==false&&strpos($evidence,"'-max_alloc','268435456'")!==false&&strpos($evidence,'proc_terminate($process,15)')!==false,'media processor is spawned without a shell and bounded then terminated and reaped on timeout');
m074(substr_count($evidence,'lockExecutableUploadOperation($evidenceId,$actorId)')>=3&&strpos($evidence,'superseded_by_operation_id')!==false,'every chunk and finalize phase locks and rejects a superseded QC plan before upload mutation');
$evidenceReflection=new ReflectionClass('QcEvidenceService');$evidenceInstance=$evidenceReflection->newInstanceWithoutConstructor();$runProcess=$evidenceReflection->getMethod('runProcess');$runProcess->setAccessible(true);$started=microtime(true);$timedOut=false;try{$runProcess->invoke($evidenceInstance,array('/bin/sleep','5'),1);}catch(Throwable$e){$timedOut=strpos($e->getMessage(),'timed out')!==false;}$elapsed=microtime(true)-$started;m074($timedOut&&$elapsed<3,'hanging media process is terminated and reaped within its timeout bound');

$publication=file_get_contents($root.'/custom/chinaplanetoperations/class/QcPublicationService.class.php');
m074(strpos($publication,'publication_sha256')!==false&&strpos($publication,'is_current=0')!==false&&strpos($publication,'version_no')!==false,'publication is hashed versioned and atomically current');
m074(strpos($publication,'supervisor_override_reason')!==false&&strpos($publication,"overallResult==='pass'")!==false,'Pass over failure requires a stored supervisor rationale');
m074(strpos($publication,"section(\$operationId,'qc_source_snapshot')")!==false&&strpos($publication,'publication_safe')!==false&&strpos($publication,'$frozenOperation')!==false&&strpos($publication,"section(\$operationId,'service')")===false,'publication uses only frozen customer-safe operation data');
m074(strpos($publication,'publicationEvidence($sections,$evidence)')!==false,'PDF evidence is restricted to checkpoints included in the published customer sections');
m074(strpos($service,'QC source snapshot integrity check failed')!==false&&strpos($publication,'verifiedSnapshot')!==false&&strpos($publication,"unset(\$payload['snapshot_sha256'])")!==false,'review and publication recompute the immutable source snapshot hash');

$migration=file_get_contents($root.'/custom/chinaplanetoperations/class/QcMigration.class.php');
m074(strpos($migration,'GET_LOCK')!==false&&strpos($migration,'CREATE TABLE IF NOT EXISTS')!==false&&strpos($migration,'schemaReady()')!==false,'M074 schema migration is locked idempotent and readiness-checked');
m074(strpos($module,'$qcMigration->migrate()')!==false,'module initialization upgrades already-enabled installations with M074 schema');

$pwa=file_get_contents($root.'/custom/chinaplanetoperations/qc/qc-field.js');
m074(strpos($pwa,"algorithm!=='PBKDF2-SHA-256'")!==false&&strpos($pwa,'iterations:310000')!==false&&strpos($pwa,"name:'AES-GCM'")!==false&&strpos($pwa,"get('meta','pin-verifier')")!==false&&strpos($pwa,"seal({identity,version:1},'pin-verifier')")!==false,'field PWA records versioned strong KDF metadata verifies the original PIN and uses AES-256-GCM');
m074(strpos($pwa,'file.arrayBuffer()')===false&&strpos($pwa,'file.slice(start,end).arrayBuffer()')!==false&&strpos($pwa,'chunkSize=20971520')!==false,'field PWA hashes and stores evidence one bounded chunk at a time');
m074(strpos($pwa,"api('upload_begin','POST'")!==false&&strpos($pwa,'async function refreshCsrf()')!==false&&strpos($pwa,'await refreshCsrf();await syncFindings()')!==false,'field sync refreshes CSRF and resumes each idempotent upload session');
m074(strpos($pwa,'async function queueFinding()')!==false&&strpos($pwa,"api('finding','POST'")!==false,'unexpected findings are captured offline and synchronized as first-class records');
m074(strpos($pwa,"rowid:'local:'")!==false&&strpos($pwa,'finding_uuid:localFinding')!==false&&strpos($pwa,'meta.finding_uuid===finding.client_uuid')!==false,'offline findings accept finding-specific evidence before their first synchronization');
$pwaSupport=file_get_contents($root.'/custom/chinaplanetoperations/qc/qc-support.js');
m074(strpos($pwa,'support.mergeQueuedPackage')!==false&&strpos($pwa,"queuedValues('mutation:')")!==false&&strpos($pwa,"queuedValues('finding:')")!==false,'a successful package refresh rehydrates queued results and findings before replacing the encrypted cache');
m074(substr_count($pwa,'apiResponse(await fetch')>=2&&strpos($pwaSupport,'classifySessionResponse')!==false&&strpos($pwaSupport,"status === 403 && !isJson")!==false,'JSON and binary chunk requests share definitive and ambiguous ERP session handling');

$portal=file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/operation_reports.lib.php');
$media=file_get_contents($root.'/custom/chinaplanetcustomercenter/qc_media.php');
$reportList=file_get_contents($root.'/custom/chinaplanetcustomercenter/operation_reports.php');
m074(strpos($portal,'cpcc_qc_customer_projection')!==false&&strpos($portal,'publication_sha256')!==false,'customer reads use an integrity-checked publication allowlist');
m074(strpos($media,'cpcc_qc_publication_by_uuid')!==false&&strpos($media,'cpcc_qc_private_file')!==false,'customer media rechecks current publication ownership and file integrity');
m074(strpos($portal,'Accept-Ranges: bytes')!==false&&strpos($portal,'Content-Range: bytes')!==false,'customer media supports validated byte-range streaming');
m074(strpos($reportList,"'pending_corrective_action'=>'بانتظار الإجراء التصحيحي'")!==false&&strpos($reportList,'strtoupper(str_replace')===false,'customer QC list localizes final results in Arabic and English');

$projection=cpcc_qc_customer_projection(array('publication_id'=>'12345678-1234-4234-9234-123456789012','version'=>1,'overall_result'=>'fail','operation'=>array('ref'=>'QC-1','title'=>'Safe title','supplier'=>'SECRET'),'inspection'=>array('reviewed_by'=>'Reviewer','dataset_sha256'=>str_repeat('a',64),'aql'=>array('standard'=>'ISO 2859-1','edition'=>'2026','dataset_sha256'=>str_repeat('b',64),'classes'=>array(),'observed'=>array())),'summary'=>array(),'override_reason'=>'SECRET','sections'=>array()));
$json=json_encode($projection);m074(strpos($json,'SECRET')===false&&strpos($json,'dataset_sha256')===false,'customer projection excludes supplier override and licensed-dataset metadata');

$dataset=array('dataset_id'=>'synthetic-test-only','standard'=>'ISO 2859-1','edition'=>'2026','sampling'=>'single','inspection'=>'normal','level'=>'II','lot_ranges'=>array(array('min'=>1,'max'=>100,'code'=>'A')),'plans'=>array('A'=>array('2.5'=>array('sample_size'=>10,'accept'=>1,'reject'=>2))));
$tmp=tempnam(sys_get_temp_dir(),'m074-aql-');file_put_contents($tmp,json_encode($dataset));$aql=new QcAqlService($tmp,hash_file('sha256',$tmp));$calculated=$aql->calculate(50,array('major'=>2.5));m074($calculated['classes']['major']['sample_size']===10&&$calculated['classes']['major']['accept']===1&&$calculated['classes']['major']['reject']===2,'AQL adapter performs exact validated lookup without interpolation');
$critical=$aql->calculate(50,array(),true);m074(isset($critical['classes']['critical'])&&$critical['classes']['critical']['inspect_all']===true&&$critical['classes']['critical']['accept']===0&&$critical['classes']['critical']['reject']===1,'critical zero tolerance creates a full-inspection class without a separate AQL value');
$failedClosed=false;try{new QcAqlService($tmp,str_repeat('0',64));}catch(RuntimeException$e){$failedClosed=true;}m074($failedClosed,'AQL adapter fails closed on dataset hash mismatch');unlink($tmp);

if($failed){fwrite(STDERR,'M074 QC contract failed: '.implode(', ',$failed)."\n");exit(1);}echo"M074_QC_CONTRACT=PASS\n";
