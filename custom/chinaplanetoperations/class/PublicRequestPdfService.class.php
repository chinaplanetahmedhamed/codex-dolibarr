<?php
require_once __DIR__.'/PublicWebsiteRequestService.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/** Private, immutable customer PDF copies. No publication or customer login is granted. */
class PublicRequestPdfService
{
    const MAX_PDF_BYTES = 6000000;
    private $db;
    private $entity;
    private $root;
    private $key;
    public function __construct($db, $entity = null)
    {
        $this->db = $db;
        $this->entity = $entity === null ? (int)$GLOBALS['conf']->entity : (int)$entity;
        $this->root = DOL_DATA_ROOT.'/chinaplanetoperations/website-pdf/'.$this->entity;
        $this->key = (string)getenv('CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY');
        if (strlen($this->key) < 32 && function_exists('getDolGlobalString')) $this->key = getDolGlobalString('CHINAPLANET_PUBLIC_REQUEST_HMAC_KEY');
        if (strlen($this->key) < 32) $this->fail('intake_service_not_configured',503);
    }
    public function prepare(array $input, $user)
    {
        if (($input['request_type'] ?? '') !== 'catalog_order' || empty($input['details']['lines']) || count($input['details']['lines']) > 100 || empty($input['consent']['privacy'])) $this->fail('invalid_pdf_enquiry');
        $phone = preg_replace('/[ ()-]/', '', $input['contact']['phone'] ?? '');
        $accepted = strtotime($input['consent']['accepted_at'] ?? '');
        if (!preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone) || !$accepted || $accepted > time()+300 || $accepted < time()-30*86400) $this->fail('invalid_pdf_contact');
        $input['contact']['phone'] = $phone;
        // One lock per entity serializes prospect matching and duplicate request preparation.
        return $this->locked('prepare', function() use ($input,$user) {
            $intake = new PublicWebsiteRequestService($this->db,$this->entity,$this->key);
            $result = $intake->create($input,$user);
            $row = $intake->get($result['id']);
            if (!$row || !empty($row['date_anonymized'])) $this->fail('request_not_found',404);
            $ref = $row['ref'];
            return $this->locked($ref, function() use ($row,$ref,$result,$input,$user) {
                $meta = $this->metadata($ref);
                if (!$meta) {
                    $link = $this->linkProspect($row,$user);
                    $meta = array('reference'=>$ref,'created_at'=>gmdate('c'),'status'=>'awaiting_pdf','sha256'=>null,'bytes'=>0,'download_requests'=>0,'last_download_requested_at'=>null,'prospect_match'=>$link,'source'=>'catalogue_pdf','document_kind'=>'customer_rendered_copy');
                    $this->write($this->directory($ref).'/snapshot.json', json_encode($input,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                    $this->saveMetadata($ref,$meta);
                }
                $expires = time()+7*86400;
                $token = $expires.'.'.$this->signature($ref,$expires);
                return array('request_ref'=>$ref,'token'=>$token,'created_at'=>$meta['created_at'],'archived'=>$meta['status']==='archived','duplicate'=>(bool)$result['duplicate']);
            });
        });
    }
    public function execute(array $input, $user)
    {
        $ref = (string)($input['reference'] ?? '');
        $this->validateReference($ref);
        $parts = explode('.', (string)($input['token'] ?? ''));
        if (count($parts)!==2 || !ctype_digit($parts[0]) || (int)$parts[0]<time() || (int)$parts[0]>time()+7*86400+300 || !hash_equals($this->signature($ref,$parts[0]),$parts[1])) $this->fail('pdf_receipt_expired',403);
        $row = $this->byReference($ref);
        if (!$row || !empty($row['date_anonymized'])) $this->fail('request_not_found',404);
        return $this->locked($ref,function() use ($ref,$input,$user) {
            $meta = $this->metadata($ref);
            if (!$meta) $this->fail('pdf_not_prepared',409);
            $path = $this->directory($ref).'/order.pdf';
            if (($input['action'] ?? '') === 'archive') {
                if ($meta['status'] !== 'archived') {
                    $encoded = $input['pdf'] ?? '';
                    if (!is_string($encoded) || strlen($encoded)>8000000) $this->fail('pdf_too_large',413);
                    $bytes = base64_decode($encoded,true);
                    if ($bytes===false || strlen($bytes)<100 || strlen($bytes)>self::MAX_PDF_BYTES || substr($bytes,0,5)!=='%PDF-' || strpos(substr($bytes,-50),'%%EOF')===false) $this->fail('invalid_pdf');
                    // Only the authenticated website gateway authors these raster-only files.
                    $this->write($path,$bytes);
                    $meta['sha256']=hash('sha256',$bytes);$meta['bytes']=strlen($bytes);$meta['status']='archived';$meta['archived_at']=gmdate('c');
                    $this->saveMetadata($ref,$meta);
                }
                return array('reference'=>$ref,'sha256'=>$meta['sha256']);
            }
            if (($input['action'] ?? '') !== 'download') $this->fail('invalid_pdf_action');
            $bytes = $this->readPdf($ref,$meta);
            $event = array('type'=>'download_requested','at'=>gmdate('c'),'sha256'=>$meta['sha256'],'actor'=>'website_customer');
            if (file_put_contents($this->directory($ref).'/events.ndjson',json_encode($event)."\n",FILE_APPEND|LOCK_EX)===false) $this->fail('pdf_storage_unavailable',503);
            $meta['download_requests']++;$meta['last_download_requested_at']=$event['at'];$this->saveMetadata($ref,$meta);
            return array('reference'=>$ref,'sha256'=>$meta['sha256'],'pdf'=>base64_encode($bytes));
        });
    }
    public function staffRecord($id)
    {
        $row=(new PublicWebsiteRequestService($this->db,$this->entity,$this->key))->get($id);
        if (!$row || !empty($row['date_anonymized'])) $this->fail('request_not_found',404);
        $meta=$this->metadata($row['ref']);
        return array('request'=>$row,'metadata'=>$meta);
    }
    public function staffPdf($id)
    {
        $record=$this->staffRecord($id);
        return array('reference'=>$record['request']['ref'],'bytes'=>$this->readPdf($record['request']['ref'],$record['metadata']));
    }
    private function readPdf($ref,$meta)
    {
        $path=$this->directory($ref).'/order.pdf';
        if (!$meta || $meta['status']!=='archived' || !is_file($path) || is_link($path)) $this->fail('pdf_not_archived',409);
        $bytes=file_get_contents($path);
        if ($bytes===false || !hash_equals($meta['sha256'],hash('sha256',$bytes))) $this->fail('pdf_storage_unavailable',503);
        return $bytes;
    }
    private function linkProspect(array $row,$user)
    {
        if (!empty($row['fk_soc'])) return 'linked';
        // Matching only links internal records. It never authenticates a visitor or exposes a match.
        $phone=preg_replace('/\D/','',$row['phone']);$email=strtolower(trim($row['email'] ?? ''));
        $conditions=array();
        foreach (array('s.phone','sp.phone','sp.phone_perso','sp.phone_mobile') as $column) {
            $normalized="COALESCE(".$column.",'')";
            foreach(array('+',' ','-','(',')','.', '/') as $separator)$normalized="REPLACE(".$normalized.",'".$this->db->escape($separator)."','')";
            $conditions[]=$normalized."='".$this->db->escape($phone)."'";
        }
        if ($email!=='') foreach(array('s.email','sp.email') as $column)$conditions[]="LOWER(TRIM(".$column."))='".$this->db->escape($email)."'";
        $res=$this->db->query("SELECT DISTINCT s.rowid FROM ".MAIN_DB_PREFIX."societe s LEFT JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.fk_soc=s.rowid AND sp.statut=1 WHERE s.entity=".$this->entity." AND s.status=1 AND (".implode(' OR ',$conditions).") LIMIT 3");
        if (!$res) $this->fail('prospect_unavailable',503);
        $matches=array();while($obj=$this->db->fetch_object($res))$matches[]=(int)$obj->rowid;
        if (count($matches)>1) return 'ambiguous_staff_review';
        $this->db->begin();
        try {
            if ($matches) { $socid=$matches[0];$state='existing'; }
            else {
                $soc=new Societe($this->db);$soc->entity=$this->entity;
                $soc->name=$row['company_name'] ?: $row['contact_name'];$soc->client=2;$soc->code_client='auto';$soc->status=1;
                $soc->email=$email;$soc->phone=$row['phone'];$soc->country_code=$row['country_code'];
                $country=$this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."c_country WHERE code='".$this->db->escape($row['country_code'])."'");
                $c=$country?$this->db->fetch_object($country):null;if($c)$soc->country_id=(int)$c->rowid;
                $soc->note_private='Unverified website prospect from PDF enquiry '.$row['ref'].'. Staff review required.';
                // No outgoing notifications or automatic downstream workflows on anonymous intake.
                $socid=$soc->create($user,1);if($socid<=0)$this->fail('prospect_unavailable',503);$state='new_prospect';
            }
            if (!$this->db->query("UPDATE ".MAIN_DB_PREFIX."cpo_public_request SET fk_soc=".(int)$socid." WHERE entity=".$this->entity." AND rowid=".(int)$row['rowid']." AND fk_soc IS NULL")) $this->fail('prospect_unavailable',503);
            $this->db->commit();return $state;
        } catch(Throwable $e) { $this->db->rollback();throw $e; }
    }
    private function byReference($ref)
    {
        $res=$this->db->query("SELECT rowid,date_anonymized FROM ".MAIN_DB_PREFIX."cpo_public_request WHERE entity=".$this->entity." AND integration_source='chinaplanet-main-website' AND request_type='catalog_order' AND ref='".$this->db->escape($ref)."'");
        return $res?($this->db->fetch_array($res) ?: null):null;
    }
    private function signature($ref,$expires) { return hash_hmac('sha256','catalog-pdf-v1|'.$this->entity.'|'.$ref.'|'.$expires,$this->key); }
    private function validateReference($ref) { if(!preg_match('/^WEB-[A-Za-z0-9-]{8,60}$/D',$ref))$this->fail('invalid_reference'); }
    private function directory($ref) { $this->validateReference($ref);return $this->root.'/'.$ref; }
    private function metadata($ref) { $path=$this->directory($ref).'/metadata.json';return is_file($path)?json_decode(file_get_contents($path),true):null; }
    private function saveMetadata($ref,$meta) { $this->write($this->directory($ref).'/metadata.json',json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }
    private function write($path,$bytes)
    {
        $dir=dirname($path);if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir))$this->fail('pdf_storage_unavailable',503);
        if(is_link($dir)||is_link($path))$this->fail('pdf_storage_unavailable',503);
        $tmp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
        if(file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes)){$this->fail('pdf_storage_unavailable',503);}
        chmod($tmp,0600);if(!rename($tmp,$path)){unlink($tmp);$this->fail('pdf_storage_unavailable',503);}
    }
    private function locked($name,$callback)
    {
        if(!is_dir($this->root) && !mkdir($this->root,0700,true) && !is_dir($this->root))$this->fail('pdf_storage_unavailable',503);
        $path=$this->root.'/'.$name.'.lock';if(is_link($path))$this->fail('pdf_storage_unavailable',503);
        $lock=fopen($path,'c');if(!$lock||!flock($lock,LOCK_EX))$this->fail('pdf_storage_unavailable',503);
        try{return $callback();}finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function fail($code,$status=422) { throw new PublicWebsiteRequestException($code,$status); }
}
