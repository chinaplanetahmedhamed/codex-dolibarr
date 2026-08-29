<?php

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M068_RUNTIME=FAIL REASON=CLI_ONLY\n"); exit(1); }
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$documentRoot = rtrim((string) getenv('M068_DOL_DOCUMENT_ROOT'), '/');
if (!is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M068_RUNTIME=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';

$sourceRoot = dirname(__DIR__, 2);
require_once $documentRoot.'/user/class/user.class.php';
require_once $documentRoot.'/product/class/product.class.php';
require_once $documentRoot.'/commande/class/commande.class.php';
require_once $documentRoot.'/core/login/functions_dolibarr.php';
require_once $sourceRoot.'/custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php';
require_once $sourceRoot.'/custom/chinaplanetoperations/class/PublicRequestLifecycleService.class.php';
require_once $sourceRoot.'/custom/chinaplanetoperations/class/PublicRequestQuotationService.class.php';
require_once $sourceRoot.'/custom/chinaplanetoperations/class/PublicRequestPublicationService.class.php';
require_once $sourceRoot.'/custom/chinaplanetcustomercenter/class/PortalInvitationService.class.php';

$assert = static function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$marker = 'M068-'.strtoupper(bin2hex(random_bytes(5)));
$email = strtolower($marker).'@example.test';
$phone = '+999'.random_int(100000000, 999999999);
$idempotency = strtolower($marker).'-catalog-order-idempotency';
$hmacKey = str_repeat('m068-intake-key-', 3);
$inviteKey = str_repeat('m068-invite-key-', 3);
$requestIds = array();
$socid = 0; $foreignSocid = 0; $contactId = 0; $productId = 0; $proposalId = 0; $orderId = 0; $portalUserId = 0;
$tmpRoot = '/private/tmp/'.strtolower($marker).'-publications';

$adminRes = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE entity=".(int)$conf->entity." AND admin=1 AND statut=1 ORDER BY rowid LIMIT 1");
$adminRow = $adminRes ? $db->fetch_object($adminRes) : null;
if (!$adminRow) { fwrite(STDERR, "M068_RUNTIME=FAIL REASON=LOCAL_ADMIN_UNAVAILABLE\n"); exit(1); }
$staff = new User($db);
if ($staff->fetch((int)$adminRow->rowid) <= 0) { fwrite(STDERR, "M068_RUNTIME=FAIL REASON=LOCAL_ADMIN_FETCH_FAILED\n"); exit(1); }
$staff->loadRights();
$GLOBALS['user'] = $staff;

$cleanup = static function () use (&$db, &$conf, &$staff, &$requestIds, &$socid, &$foreignSocid, &$contactId, &$productId, &$proposalId, &$orderId, &$portalUserId, $marker, $tmpRoot) {
    if ($orderId > 0) { $order = new Commande($db); if ($order->fetch($orderId) > 0) $order->delete($staff, 1); }
    if ($proposalId > 0) { $proposal = new Propal($db); if ($proposal->fetch($proposalId) > 0) $proposal->delete($staff, 1); }
    foreach ($requestIds as $requestId) {
        $proposalIds=array();$proposalRes=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."propal WHERE entity=".(int)$conf->entity." AND ref_client IN (SELECT ref FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".(int)$conf->entity." AND rowid=".(int)$requestId.")");if($proposalRes)while($proposalRow=$db->fetch_object($proposalRes))$proposalIds[]=(int)$proposalRow->rowid;foreach($proposalIds as$cleanupProposalId){$cleanupProposal=new Propal($db);if($cleanupProposal->fetch($cleanupProposalId)>0)$cleanupProposal->delete($staff,1);}
        $hashes = array(); $res=$db->query("SELECT token_hash FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation WHERE entity=".(int)$conf->entity." AND fk_request=".(int)$requestId); if($res)while($o=$db->fetch_object($res))$hashes[]="'".$db->escape($o->token_hash)."'";
        if($hashes)$db->query("DELETE FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation_attempt WHERE entity=".(int)$conf->entity." AND token_hash IN (".implode(',',$hashes).")");
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation WHERE entity=".(int)$conf->entity." AND fk_request=".(int)$requestId);
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_public_request_publication WHERE entity=".(int)$conf->entity." AND fk_request=".(int)$requestId);
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_public_request_event WHERE entity=".(int)$conf->entity." AND fk_request=".(int)$requestId);
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_public_request_line WHERE entity=".(int)$conf->entity." AND fk_request=".(int)$requestId);
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".(int)$conf->entity." AND rowid=".(int)$requestId." AND ref LIKE 'WEB-%'");
    }
    if ($portalUserId > 0) { $db->query("DELETE FROM ".MAIN_DB_PREFIX."user_param WHERE entity=".(int)$conf->entity." AND fk_user=".(int)$portalUserId); $db->query("DELETE FROM ".MAIN_DB_PREFIX."usergroup_user WHERE entity=".(int)$conf->entity." AND fk_user=".(int)$portalUserId); $portal=new User($db); if($portal->fetch($portalUserId)>0)$portal->delete($staff); }
    if ($contactId > 0) { $contact=new Contact($db); if($contact->fetch($contactId)>0)$contact->delete($staff,1); }
    if ($foreignSocid > 0) { $foreignSoc=new Societe($db); $foreignSoc->delete($foreignSocid,$staff,1); }
    if ($socid > 0) { $soc=new Societe($db); $soc->delete($socid,$staff,1); }
    if ($productId > 0) { $product=new Product($db); if($product->fetch($productId)>0)$product->delete($staff,1); }
    if (is_dir($tmpRoot)) { foreach(glob($tmpRoot.'/*/*/*')?:array() as$file)if(is_file($file))unlink($file); foreach(array_reverse(glob($tmpRoot.'/*/*')?:array())as$dir)if(is_dir($dir))rmdir($dir); foreach(array_reverse(glob($tmpRoot.'/*')?:array())as$dir)if(is_dir($dir))rmdir($dir); rmdir($tmpRoot); }
};

try {
    $payload = array(
        'schema_version'=>2, 'request_type'=>'catalog_order', 'idempotency_key'=>$idempotency, 'locale'=>'en',
        'contact'=>array('name'=>'Synthetic '.$marker,'company'=>'Synthetic '.$marker,'phone'=>$phone,'email'=>$email,'country_code'=>'EG'),
        'consent'=>array('privacy'=>true,'terms_version'=>'2026-08-30','accepted_at'=>'2026-08-30T00:00:00Z'),
        'details'=>array(
            'message'=>'Synthetic M068 runtime test only','currency'=>getDolCurrency(),'source_reference'=>'woocommerce','lines'=>array(
            array('lineId'=>'L001','source'=>'woocommerce','productId'=>900001,'variationId'=>0,'sku'=>$marker.'-READY','name'=>'Synthetic ready product','optionLabel'=>'','image'=>null,'cartons'=>3,'unitsPerCarton'=>12,'cbmPerCarton'=>0.08,'grossWeightKg'=>10,'packingComplete'=>true,'packingSource'=>'woocommerce_product','calculationReady'=>true,'missingFields'=>array(),'price'=>array('visibility'=>'public','mode'=>'fixed','unit'=>'piece','currency'=>getDolCurrency(),'unitPrice'=>9.5,'minUnitPrice'=>null,'maxUnitPrice'=>null)),
            array('lineId'=>'L002','source'=>'woocommerce','productId'=>900002,'variationId'=>0,'sku'=>$marker.'-REVIEW','name'=>'Synthetic review product','optionLabel'=>'','image'=>null,'cartons'=>2,'unitsPerCarton'=>null,'cbmPerCarton'=>null,'grossWeightKg'=>null,'packingComplete'=>false,'packingSource'=>'unknown','calculationReady'=>false,'missingFields'=>array('unitsPerCarton','cbmPerCarton','grossWeightKg','packingComplete'),'price'=>array('visibility'=>'request_quote','mode'=>'request','unit'=>'piece','currency'=>null,'unitPrice'=>null,'minUnitPrice'=>null,'maxUnitPrice'=>null)),
            ),
            'packing_plan'=>array('container'=>'40hq','calculationAvailable'=>true,'requestCartons'=>5,'calculatedCartons'=>3,'placements'=>array(),'excludedItems'=>array(array('lineId'=>'L002','reason'=>'packing_data_missing','missingFields'=>array('unitsPerCarton','cbmPerCarton','grossWeightKg','packingComplete')))),
        ),
    );
    $intake = new PublicWebsiteRequestService($db, (int)$conf->entity, $hmacKey);
    $created = $intake->create($payload, $staff); $requestIds[]=(int)$created['id'];
    $duplicate = $intake->create($payload, $staff);
    $assert(empty($created['duplicate']) && !empty($duplicate['duplicate']) && $created['ref']===$duplicate['ref'] && (int)$duplicate['schema_version']===2, 'idempotent intake failed');
    $changed=$payload;$changed['details']['message']='changed';$conflict=false;try{$intake->create($changed,$staff);}catch(PublicWebsiteRequestException $e){$conflict=$e->getPublicCode()==='idempotency_conflict'&&$e->getHttpStatus()===409;}$assert($conflict,'idempotency conflict failed');
    $immutableBefore=hash('sha256',json_encode($intake->get($created['id'])['request'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

    $lifecycle = new PublicRequestLifecycleService($db);
    $qualified = $lifecycle->createReviewedLead((int)$created['id'], 'Synthetic '.$marker, $staff);
    $socid=(int)$qualified['socid'];$contactId=(int)$qualified['contact_id'];
    $foreignSoc=new Societe($db);$foreignSoc->name='Synthetic foreign '.$marker;$foreignSoc->client=2;$foreignSoc->code_client='auto';$foreignSoc->status=1;$foreignSocid=(int)$foreignSoc->create($staff);$assert($foreignSocid>0,'synthetic foreign lead create failed');
    $wrongCustomerBlocked=false;try{$intake->promote((int)$created['id'],$foreignSocid,(int)$staff->id);}catch(PublicWebsiteRequestException $e){$wrongCustomerBlocked=$e->getPublicCode()==='qualification_link_conflict';}$assert($wrongCustomerBlocked,'qualified v2 request could be rebound during legacy promotion');
    $qualifiedCheck=$intake->get((int)$created['id']);$assert((int)$qualifiedCheck['fk_soc']===$socid && (int)$qualifiedCheck['fk_socpeople']===$contactId,'qualified v2 identity changed after rejected promotion');
    $lines=$lifecycle->workingLines((int)$created['id']);
    $assert(count($lines)===2 && (int)$lines[0]['submitted_units_per_carton']===12 && (int)$lines[0]['submitted_calculation_ready']===1 && $lines[1]['submitted_units_per_carton']===null,'v2 ready/incomplete line seed failed');

    $product=new Product($db);$product->ref=$marker.'-P';$product->label='Synthetic M068 product';$product->type=0;$product->status=1;$product->status_buy=0;$product->price=5;$product->price_base_type='HT';$product->tva_tx=0;$productId=$product->create($staff,1);$assert($productId>0,'synthetic product create failed');
    $privateMarker='PRIVATE-SUPPLIER-COST-'.$marker;
    foreach($lines as$index=>$line)$lifecycle->reviewLine((int)$created['id'],(int)$line['rowid'],array('fk_product'=>$productId,'units_per_carton'=>$index===0?12:20,'cbm_per_carton'=>$index===0?0.08:0.05,'gross_weight_kg'=>$index===0?10:7,'packing_verified'=>true,'verification_source'=>$index===0?'woocommerce_product':'staff_measurement','staff_note'=>$privateMarker.'-'.$index),$staff);
    $immutableAfter=hash('sha256',json_encode($intake->get($created['id'])['request'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));$assert(hash_equals($immutableBefore,$immutableAfter),'immutable intake was rewritten');

    $quotation=new PublicRequestQuotationService($db);$proposal=$quotation->saveDraft((int)$created['id'],$socid,$contactId,getDolCurrency(),'Synthetic customer-visible note','Synthetic private test note',array(array('fk_product'=>$productId,'price_source'=>'manual','manual_price'=>'5.25','public_description'=>'Customer-ready line'),array('fk_product'=>$productId,'price_source'=>'manual','manual_price'=>'6.50','public_description'=>'Customer-completed line')),$staff);$proposalId=(int)$proposal->id;
    $assert($proposalId>0 && (int)$proposal->status===Propal::STATUS_DRAFT && count($proposal->lines)===2,'standard draft proposal failed');
    foreach($proposal->lines as$proposalLine){$assert(strpos((string)$proposalLine->desc,$privateMarker)===false,'private working note leaked to proposal');$assert(strpos((string)$proposalLine->desc,'Customer-')!==false,'approved public line description missing');}
    $lifecycle->setVisibility((int)$created['id'],false,true,$staff);
    $visibilityRes=$db->query("SELECT request_customer_visible,proposal_customer_visible FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".(int)$conf->entity." AND rowid=".(int)$created['id']);$visibilityRow=$visibilityRes?$db->fetch_object($visibilityRes):null;$assert($visibilityRow&&(int)$visibilityRow->request_customer_visible===1&&(int)$visibilityRow->proposal_customer_visible===1,'proposal/request visibility invariant failed');

    // Exercise the native staff-owned acceptance/conversion path without validating the resulting order.
    $conf->global->MAIN_DISABLE_PDF_AUTOUPDATE = 1;
    $validationResult=$proposal->valid($staff,1);
    $assert($validationResult>0,'proposal validation failed: '.($proposal->error?:implode(';',(array)$proposal->errors)));
    $assert($proposal->fetch($proposalId)>0 && (int)$proposal->status===Propal::STATUS_VALIDATED,'validated proposal reload failed');
    $acceptanceResult=$proposal->closeProposal($staff,Propal::STATUS_SIGNED,'Synthetic M068 acceptance',1);
    $assert($acceptanceResult>0,'proposal acceptance failed: '.($proposal->error?:implode(';',(array)$proposal->errors)));
    $assert($proposal->fetch($proposalId)>0 && (int)$proposal->status===Propal::STATUS_SIGNED,'accepted proposal reload failed');
    $order=new Commande($db);$orderId=(int)$order->createFromProposal($proposal,$staff);
    $assert($orderId>0 && $order->fetch($orderId)>0 && (int)$order->status===Commande::STATUS_DRAFT && (int)$order->socid===$socid,'native draft order conversion failed');

    $publication=new PublicRequestPublicationService($db,null,$tmpRoot);$publication->publishReply((int)$created['id'],'Synthetic approved reply',$staff);
    $assert(count($publication->customerItems((int)$created['id'],$socid))===1 && count($publication->customerItems((int)$created['id'],$socid+999999))===0,'publication tenant isolation failed');
    $fileDir=$tmpRoot.'/'.(int)$conf->entity.'/'.$created['ref'];if(!mkdir($fileDir,0750,true)&&!is_dir($fileDir))throw new RuntimeException('synthetic_file_directory_failed');$filePath=$fileDir.'/synthetic.pdf';if(file_put_contents($filePath,"%PDF-1.4\nM068 synthetic\n")===false)throw new RuntimeException('synthetic_file_write_failed');chmod($filePath,0640);$fileToken=bin2hex(random_bytes(16));$fileSha=hash_file('sha256',$filePath);$storage=(int)$conf->entity.'/'.$created['ref'].'/synthetic.pdf';
    $db->query("INSERT INTO ".MAIN_DB_PREFIX."cpo_public_request_publication(entity,fk_request,public_token,item_type,original_name,storage_key,mime_type,file_size,file_sha256,customer_visible,status,fk_user_creat,date_creation) VALUES(".(int)$conf->entity.",".(int)$created['id'].",'".$fileToken."','file','synthetic.pdf','".$db->escape($storage)."','application/pdf',".filesize($filePath).",'".$fileSha."',1,1,".(int)$staff->id.",'".$db->idate(dol_now())."')");
    $assert($publication->authorizedDownload($fileToken,$socid)!==null && $publication->authorizedDownload($fileToken,$socid+999999)===null,'file tenant/path authorization failed');

    $invites=new PortalInvitationService($db,null,$inviteKey);for($i=0;$i<3;$i++){$result=$invites->issue((int)$created['id'],$staff,false);$assert(!isset($result['token'],$result['activation_url'])&&$result['status']==='invite_pending','invitation result leaked a secret');}
    $limited=false;try{$invites->issue((int)$created['id'],$staff,false);}catch(PortalInvitationException $e){$limited=$e->getMessage()==='portal_invitation_rate_limited';}$assert($limited,'3/hour invitation limit failed');
    $res=$db->query("SELECT rowid,fk_user FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation WHERE entity=".(int)$conf->entity." AND fk_request=".(int)$created['id']." AND status='pending' ORDER BY rowid DESC LIMIT 1");$inviteRow=$res?$db->fetch_object($res):null;$assert($inviteRow,'pending invitation missing');$portalUserId=(int)$inviteRow->fk_user;
    $knownToken=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$knownHash=hash('sha256',$knownToken);$db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_portal_invitation SET token_hash='".$knownHash."' WHERE rowid=".(int)$inviteRow->rowid);
    for($i=0;$i<5;$i++){try{$invites->accept($knownToken,'short','different',$staff,'192.0.2.10');}catch(PortalInvitationException $e){$assert($e->getMessage()==='portal_activation_password_invalid','unexpected activation failure');}}
    $attemptLimited=false;try{$invites->accept($knownToken,'short','different',$staff,'192.0.2.10');}catch(PortalInvitationException $e){$attemptLimited=$e->getMessage()==='portal_activation_rate_limited';}$assert($attemptLimited,'5/15-minute token/IP limit failed');
    $boundaryIp='192.0.2.12';for($i=0;$i<5;$i++){try{$invites->accept($knownToken,'short','different',null,$boundaryIp);}catch(PortalInvitationException $e){$assert($e->getMessage()==='portal_activation_password_invalid','unexpected rolling-window setup failure');}}
    $boundaryHmac=hash_hmac('sha256',$boundaryIp,$inviteKey);$boundaryRows=array();$boundaryRes=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation_attempt WHERE entity=".(int)$conf->entity." AND token_hash='".$knownHash."' AND ip_hmac='".$boundaryHmac."' ORDER BY rowid");if($boundaryRes)while($boundaryRow=$db->fetch_object($boundaryRes))$boundaryRows[]=(int)$boundaryRow->rowid;$assert(count($boundaryRows)===5,'rolling-window attempt events were not durable');
    $oldBoundaryRows=array_slice($boundaryRows,0,4);$assert($db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_portal_invitation_attempt SET last_attempt_at='".$db->idate(dol_now()-901)."' WHERE rowid IN (".implode(',',$oldBoundaryRows).")")!==false,'rolling-window boundary setup failed');
    for($i=0;$i<4;$i++){try{$invites->accept($knownToken,'short','different',null,$boundaryIp);}catch(PortalInvitationException $e){$assert($e->getMessage()==='portal_activation_password_invalid','rolling window rejected below five recent attempts');}}
    $rollingLimited=false;try{$invites->accept($knownToken,'short','different',null,$boundaryIp);}catch(PortalInvitationException $e){$rollingLimited=$e->getMessage()==='portal_activation_rate_limited';}$assert($rollingLimited,'rolling 15-minute boundary allowed more than five recent attempts');
    $password='M068!Synthetic-Password-29';$assert($invites->accept($knownToken,$password,$password,null,'192.0.2.11'),'anonymous activation failed');
    $res=$db->query("SELECT login,email,statut,pass,pass_crypted FROM ".MAIN_DB_PREFIX."user WHERE rowid=".$portalUserId);$portalRow=$res?$db->fetch_object($res):null;$assert($portalRow&&strtolower($portalRow->email)===$email&&(int)$portalRow->statut===1&&$portalRow->pass===null&&!empty($portalRow->pass_crypted),'email identity or credential storage failed');$assert(check_user_password_dolibarr($email,$password,(int)$conf->entity)===(string)$portalRow->login,'Dolibarr email authentication failed');
    $assert($invites->requestPasswordReset('unknown-'.strtolower($marker).'@example.test',false)===false,'unknown reset address was not handled generically');
    $assert($db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_portal_invitation SET date_creation='".$db->idate(dol_now()-3700)."' WHERE entity=".(int)$conf->entity." AND fk_request=".(int)$created['id'])!==false,'reset rate-window fixture failed');
    $assert($invites->requestPasswordReset($email,false)===true,'secure email reset request failed');
    $resetRes=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation WHERE entity=".(int)$conf->entity." AND fk_request=".(int)$created['id']." AND status='pending' ORDER BY rowid DESC LIMIT 1");$resetRow=$resetRes?$db->fetch_object($resetRes):null;$assert($resetRow,'reset invitation missing');
    $resetToken=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$resetHash=hash('sha256',$resetToken);$assert($db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_portal_invitation SET token_hash='".$resetHash."' WHERE rowid=".(int)$resetRow->rowid)!==false,'reset token fixture failed');
    $resetPassword='M068!Synthetic-Reset-Password-30';$assert($invites->accept($resetToken,$resetPassword,$resetPassword,null,'192.0.2.13'),'one-time password reset activation failed');$assert(check_user_password_dolibarr($email,$resetPassword,(int)$conf->entity)===(string)$portalRow->login && check_user_password_dolibarr($email,$password,(int)$conf->entity)!==(string)$portalRow->login,'email reset did not replace the previous credential');
    $inviteCountRes=$db->query("SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation WHERE entity=".(int)$conf->entity." AND fk_user=".$portalUserId);$inviteCountBefore=(int)(($inviteCountRes?$db->fetch_object($inviteCountRes):null)->total??-1);
    $rightCountSql="SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."usergroup_rights gr INNER JOIN ".MAIN_DB_PREFIX."usergroup g ON g.rowid=gr.fk_usergroup WHERE gr.entity=".(int)$conf->entity." AND gr.fk_id=500201 AND g.nom='Customers Portal' AND g.entity IN (0,".(int)$conf->entity.")";$rightCountRes=$db->query($rightCountSql);$rightCountBefore=(int)(($rightCountRes?$db->fetch_object($rightCountRes):null)->total??-1);
    $removeMembership="DELETE FROM ".MAIN_DB_PREFIX."usergroup_user WHERE entity=".(int)$conf->entity." AND fk_user=".$portalUserId." AND fk_usergroup IN (SELECT rowid FROM ".MAIN_DB_PREFIX."usergroup WHERE nom='Customers Portal' AND entity IN (0,".(int)$conf->entity."))";$assert($db->query($removeMembership)!==false,'negative reset membership fixture failed');
    $assert($invites->requestPasswordReset($email,false)===false,'public reset restored revoked portal access');
    $memberRes=$db->query("SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."usergroup_user gu INNER JOIN ".MAIN_DB_PREFIX."usergroup g ON g.rowid=gu.fk_usergroup WHERE gu.entity=".(int)$conf->entity." AND gu.fk_user=".$portalUserId." AND g.nom='Customers Portal'");$memberCount=(int)(($memberRes?$db->fetch_object($memberRes):null)->total??-1);
    $inviteCountRes=$db->query("SELECT COUNT(*) AS total FROM ".MAIN_DB_PREFIX."cpcc_portal_invitation WHERE entity=".(int)$conf->entity." AND fk_user=".$portalUserId);$inviteCountAfter=(int)(($inviteCountRes?$db->fetch_object($inviteCountRes):null)->total??-1);$rightCountRes=$db->query($rightCountSql);$rightCountAfter=(int)(($rightCountRes?$db->fetch_object($rightCountRes):null)->total??-1);$assert($memberCount===0&&$inviteCountAfter===$inviteCountBefore&&$rightCountAfter===$rightCountBefore,'revoked reset changed membership, invitation, or group rights');

    $formattedMobile=substr($phone,0,4).'.'.substr($phone,4,3).'/'.substr($phone,7);$assert($db->query("UPDATE ".MAIN_DB_PREFIX."societe SET phone=NULL WHERE rowid=".$socid)!==false && $db->query("UPDATE ".MAIN_DB_PREFIX."socpeople SET phone=NULL,phone_perso=NULL,phone_mobile='".$db->escape($formattedMobile)."' WHERE rowid=".$contactId)!==false,'mobile identity fixture setup failed');
    $second=$payload;$second['idempotency_key']=$idempotency.'-stop';$second['contact']['email']='alternate-'.strtolower($marker).'@example.test';$secondCreated=$intake->create($second,$staff);$requestIds[]=(int)$secondCreated['id'];$unqualifiedPromotionBlocked=false;try{$intake->promote((int)$secondCreated['id'],$socid,(int)$staff->id);}catch(PublicWebsiteRequestException $e){$unqualifiedPromotionBlocked=$e->getPublicCode()==='qualification_required';}$assert($unqualifiedPromotionBlocked,'unqualified v2 request entered legacy promotion');$mobileMatches=$lifecycle->matchCandidates($intake->get((int)$secondCreated['id']));$assert(!empty($mobileMatches['candidates']) && (int)$mobileMatches['candidates'][0]['contact_id']===$contactId,'formatted contact mobile identity was missed');$stopped=false;try{$lifecycle->createReviewedLead((int)$secondCreated['id'],'Duplicate '.$marker,$staff);}catch(PublicWebsiteRequestException $e){$stopped=$e->getPublicCode()==='qualification_existing_match_requires_choice';}$assert($stopped,'existing identity did not stop lead creation');
    $res=$db->query("SELECT qualification_status,match_status FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE rowid=".(int)$secondCreated['id']);$stopRow=$res?$db->fetch_object($res):null;$eventRes=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpo_public_request_event WHERE fk_request=".(int)$secondCreated['id']." AND event_type='qualification_stopped'");$assert($stopRow&&$stopRow->qualification_status==='stopped'&&$eventRes&&$db->fetch_object($eventRes),'stopping state/audit was rolled back');

    echo "M068_RUNTIME=PASS INTAKE=v2+dedupe LINES=ready+incomplete QUALIFICATION=safe-stop+ownership-locked PROPAL=draft+private-safe+accepted ORDER=draft PORTAL=tenant-isolated+visibility-invariant INVITE=email+3-per-hour ACTIVATION=one-time+rolling-5-per-15m RESET=one-time-email+no-regrant FILES=contained\n";
} catch (Throwable $e) {
    fwrite(STDERR, "M068_RUNTIME=FAIL REASON=".preg_replace('/[^A-Za-z0-9_.:-]+/','_',get_class($e).':'.$e->getMessage())."\n");
    $cleanup();
    exit(1);
}
$cleanup();
$leftovers=0;
foreach(array(
    "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND nom='".$db->escape('Synthetic '.$marker)."'",
    "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND nom='".$db->escape('Synthetic foreign '.$marker)."'",
    "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."product WHERE entity=".(int)$conf->entity." AND ref='".$db->escape($marker.'-P')."'",
    "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."user WHERE entity=".(int)$conf->entity." AND email='".$db->escape($email)."'",
    "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".(int)$conf->entity." AND email='".$db->escape($email)."'"
) as $cleanupSql){$cleanupResult=$db->query($cleanupSql);$cleanupRow=$cleanupResult?$db->fetch_object($cleanupResult):null;$leftovers+=(int)($cleanupRow->nb??1);}
if($leftovers!==0 || is_dir($tmpRoot)){fwrite(STDERR,"M068_RUNTIME_CLEANUP=FAIL LEFTOVERS=".$leftovers."\n");exit(1);}
echo "M068_RUNTIME_CLEANUP=PASS SYNTHETIC_ONLY=YES\n";
