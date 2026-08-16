<?php

require_once __DIR__.'/InternalTaskService.class.php';

/** Secure, task-scoped M058 asset storage. No asset is customer-visible. */
class InternalTaskAssetService
{
    private $db;
    private $entity;
    private $tasks;
    private $auth;
    private $root;

    public function __construct($db, $actor = null, $entity = null, $root = '')
    {
        global $conf;
        $this->db = $db;
        $this->entity = $entity === null ? (int) $conf->entity : (int) $entity;
        $this->tasks = new InternalTaskService($db, $actor, $this->entity);
        $this->auth = $this->tasks->authorization();
        $base = $root !== '' ? $root : (string) $conf->chinaplanetoperations->dir_output;
        $this->root = rtrim($base, '/').'/internal_tasks/'.$this->entity;
    }

    public function listAssets($taskId, $history = false)
    {
        $task = $this->tasks->requireReadableTask($taskId);
        if (!$this->auth->canUseTaskFiles($task, false, $history)) throw new InternalTaskAccessException('Task access denied.');
        $sql = 'SELECT rowid,fk_task,asset_ref,parent_asset_id,asset_type,original_name,extension,mime_type,file_size,sha256,version_no,is_cover,status,date_creation';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'cpo_task_asset WHERE entity='.$this->entity.' AND fk_task='.(int) $taskId;
        $sql .= $history ? " AND status IN ('active','superseded','archived')" : " AND status='active'";
        $sql .= ' ORDER BY asset_ref,version_no DESC,rowid DESC';
        return $this->rows($sql);
    }

    public function getAsset($assetId, $history = false)
    {
        $sql = 'SELECT a.*,t.status task_status,t.date_archived task_archived,t.fk_user_assign,t.fk_usergroup,t.entity task_entity';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'cpo_task_asset a INNER JOIN '.MAIN_DB_PREFIX.'cpo_task t ON t.rowid=a.fk_task AND t.entity=a.entity';
        $sql .= ' WHERE a.entity='.$this->entity.' AND a.rowid='.(int) $assetId.' LIMIT 1';
        $res = $this->db->query($sql);
        $row = $res ? $this->db->fetch_object($res) : null;
        if (!$row) throw new InternalTaskAccessException('Task access denied.');
        $asset = get_object_vars($row);
        $task = array('entity'=>(int)$asset['task_entity'],'status'=>$asset['task_status'],'date_archived'=>$asset['task_archived'],'fk_user_assign'=>$asset['fk_user_assign'],'fk_usergroup'=>$asset['fk_usergroup']);
        if ($task['status'] === 'deleted' || !$this->auth->canUseTaskFiles($task, false, $history)) throw new InternalTaskAccessException('Task access denied.');
        if (!$history && $asset['status'] !== 'active') throw new InternalTaskAccessException('Task access denied.');
        if ($history && !in_array($asset['status'], array('active','superseded','archived'), true)) throw new InternalTaskAccessException('Task access denied.');
        return $asset;
    }

    public function upload($taskId, $expectedVersion, array $file, $replaceAssetId = 0)
    {
        $this->tasks->ensureReady();
        $this->assertUpload($file);
        $this->ensureDirectory($this->root, 0700);
        $quarantine = $this->root.'/.quarantine';
        $this->ensureDirectory($quarantine, 0700);
        $token = bin2hex(random_bytes(24));
        $raw = $quarantine.'/'.$token.'.upload';
        if (!move_uploaded_file($file['tmp_name'], $raw)) throw new RuntimeException('Unable to quarantine uploaded file.');
        try {
            $this->secureFile($raw);
        } catch (Throwable $e) {
            if (is_file($raw) && !unlink($raw)) throw new RuntimeException('Task file cleanup failed; operator action required.', 0, $e);
            throw $e;
        }
        $prepared = '';
        $target = '';
        try {
            $meta = $this->validateAndNormalize($raw, (string) $file['name']);
            $prepared = $meta['path'];
            $this->beginTransaction();
            $task = $this->tasks->getTask($taskId, true);
            if (!$task || !$this->auth->canUseTaskFiles($task, true, false)) throw new InternalTaskAccessException('Task access denied.');
            if ((int) $task['version'] !== (int) $expectedVersion) throw new InternalTaskConflictException('Task changed. Refresh and retry.');

            $parent = null;
            if ((int) $replaceAssetId > 0) {
                $res = $this->db->query('SELECT * FROM '.MAIN_DB_PREFIX.'cpo_task_asset WHERE entity='.$this->entity.' AND fk_task='.(int)$taskId.' AND rowid='.(int)$replaceAssetId." AND status='active' FOR UPDATE");
                $parent = $res ? $this->db->fetch_object($res) : null;
                if (!$parent) throw new InternalTaskAccessException('Task access denied.');
            }
            $quota = $this->activeQuota($taskId);
            $logical = (int)$quota['logical_count'] - ($parent ? 1 : 0) + 1;
            $bytes = (int)$quota['active_bytes'] - ($parent ? (int)$parent->file_size : 0) + (int)$meta['size'];
            if ($logical > 25 || $bytes > 500 * 1024 * 1024) throw new RuntimeException('Task file quota exceeded.');

            $assetRef = $parent ? (string)$parent->asset_ref : bin2hex(random_bytes(20));
            $version = $parent ? (int)$parent->version_no + 1 : 1;
            $stored = bin2hex(random_bytes(24)).'.'.$meta['extension'];
            $dir = $this->root.'/'.(int)$taskId;
            $this->ensureDirectory($dir, 0700);
            $target = $dir.'/'.$stored;
            if (file_exists($target) || is_link($target)) throw new RuntimeException('Task storage target already exists.');
            $cover = 0;
            if ($meta['type'] === 'image') {
                if ($parent && (int)$parent->is_cover === 1) {
                    $cover = 1;
                } elseif (!$parent) {
                    $res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task_asset WHERE entity='.$this->entity.' AND fk_task='.(int)$taskId." AND asset_type='image' AND status='active' ORDER BY rowid LIMIT 1 FOR UPDATE");
                    if (!$res) throw new RuntimeException('Unable to check task cover.');
                    $cover = $this->db->fetch_object($res) ? 0 : 1;
                }
            }
            $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpo_task_asset(entity,fk_task,asset_ref,parent_asset_id,asset_type,original_name,stored_name,extension,mime_type,file_size,sha256,version_no,is_cover,status,fk_user_creat,fk_user_modif,date_creation) VALUES(';
            $sql .= $this->entity.','.(int)$taskId.','.$this->q($assetRef).','.($parent?(int)$parent->rowid:'NULL').','.$this->q($meta['type']).','.$this->q($meta['name']).','.$this->q($stored).','.$this->q($meta['extension']).','.$this->q($meta['mime']).','.(int)$meta['size'].','.$this->q($meta['sha256']).','.$version.','.$cover.",'quarantine',".$this->auth->actorId().','.$this->auth->actorId().','.$this->q($this->now()).')';
            if (!$this->db->query($sql)) throw new RuntimeException('Unable to save task file metadata.');
            $assetId = (int)$this->db->last_insert_id(MAIN_DB_PREFIX.'cpo_task_asset');
            if (!rename($prepared, $target)) throw new RuntimeException('Unable to activate task file.');
            $prepared = '';
            $this->secureFile($target);
            if ($parent && !$this->db->query('UPDATE '.MAIN_DB_PREFIX."cpo_task_asset SET status='superseded',is_cover=0,fk_user_modif=".$this->auth->actorId().' WHERE entity='.$this->entity.' AND rowid='.(int)$parent->rowid)) throw new RuntimeException('Unable to supersede task file.');
            if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX."cpo_task_asset SET status='active' WHERE entity=".$this->entity.' AND rowid='.$assetId)) throw new RuntimeException('Unable to activate task file metadata.');
            $this->taskCas($taskId, $expectedVersion);
            $this->log($task, 'task_asset_uploaded', $meta['type']);
            $this->commitTransaction();
            return array('asset_id'=>$assetId,'version'=>(int)$expectedVersion+1);
        } catch (Throwable $e) {
            $this->db->rollback();
            foreach (array($prepared,$target,$raw) as $path) if ($path !== '' && is_file($path) && !@unlink($path)) throw new RuntimeException('Task file cleanup failed; operator action required.', 0, $e);
            throw $e;
        }
    }

    public function setCover($taskId, $assetId, $expectedVersion)
    {
        $this->beginTransaction();
        try {
            $task = $this->tasks->getTask($taskId, true);
            if (!$task || !$this->auth->canUseTaskFiles($task, true, false)) throw new InternalTaskAccessException('Task access denied.');
            $res = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'cpo_task_asset WHERE entity='.$this->entity.' AND fk_task='.(int)$taskId.' AND rowid='.(int)$assetId." AND status='active' AND asset_type='image' FOR UPDATE");
            if (!$res || !$this->db->fetch_object($res)) throw new InternalTaskAccessException('Task access denied.');
            if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_task_asset SET is_cover=0,fk_user_modif='.$this->auth->actorId().' WHERE entity='.$this->entity.' AND fk_task='.(int)$taskId." AND status='active'")) throw new RuntimeException('Unable to update cover.');
            if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_task_asset SET is_cover=1,fk_user_modif='.$this->auth->actorId().' WHERE entity='.$this->entity.' AND rowid='.(int)$assetId)) throw new RuntimeException('Unable to update cover.');
            $this->taskCas($taskId, $expectedVersion);
            $this->log($task, 'task_cover_changed', 'image');
            $this->commitTransaction();
            return (int)$expectedVersion + 1;
        } catch (Throwable $e) {$this->db->rollback();throw $e;}
    }

    public function clearCover($taskId, $expectedVersion)
    {
        $this->beginTransaction();
        try {
            $task=$this->tasks->getTask($taskId,true);
            if(!$task||!$this->auth->canUseTaskFiles($task,true,false))throw new InternalTaskAccessException('Task access denied.');
            if(!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_task_asset SET is_cover=0,fk_user_modif='.$this->auth->actorId().' WHERE entity='.$this->entity.' AND fk_task='.(int)$taskId." AND status='active'"))throw new RuntimeException('Unable to update cover.');
            $this->taskCas($taskId,$expectedVersion);$this->log($task,'task_cover_changed','none');$this->commitTransaction();return (int)$expectedVersion+1;
        }catch(Throwable $e){$this->db->rollback();throw $e;}
    }

    public function pathFor(array $asset)
    {
        $path = $this->root.'/'.(int)$asset['fk_task'].'/'.basename((string)$asset['stored_name']);
        $realRoot = realpath($this->root);
        $real = realpath($path);
        if ($realRoot === false || $real === false || strpos($real, $realRoot.DIRECTORY_SEPARATOR) !== 0 || !is_file($real) || is_link($path)) throw new InternalTaskAccessException('Task access denied.');
        return $real;
    }

    private function validateAndNormalize($path, $original)
    {
        $name = trim(basename(str_replace('\\','/', $original)));
        if ($name === '' || $name[0] === '.' || preg_match('/[\x00-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $name)) throw new RuntimeException('Unsafe file name.');
        if (substr_count($name, '.') !== 1) throw new RuntimeException('Double or hidden file extensions are not allowed.');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = filesize($path);
        if (!$size) throw new RuntimeException('Empty files are not allowed.');
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($path);
        $images = array('jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp');
        if (isset($images[$extension])) {
            if ($size > 10*1024*1024 || $mime !== $images[$extension]) throw new RuntimeException('Invalid image file.');
            $info = @getimagesize($path);
            if (!$info || $info[0] > 10000 || $info[1] > 10000 || $info[0]*$info[1] > 25000000) throw new RuntimeException('Image dimensions exceed the limit.');
            $normalized = dirname($path).'/'.bin2hex(random_bytes(20)).'.'.$extension;
            try {
                $source = $mime === 'image/jpeg' ? @imagecreatefromjpeg($path) : ($mime === 'image/png' ? @imagecreatefrompng($path) : @imagecreatefromwebp($path));
                if (!$source) throw new RuntimeException('Image could not be decoded.');
                $ok = $mime === 'image/jpeg' ? imagejpeg($source,$normalized,90) : ($mime === 'image/png' ? imagepng($source,$normalized,6) : imagewebp($source,$normalized,88));
                imagedestroy($source);
                if (!$ok) throw new RuntimeException('Image could not be normalized.');
                $this->secureFile($normalized);
                if (is_file($path) && !unlink($path)) throw new RuntimeException('Image quarantine cleanup failed.');
            } catch (Throwable $e) {
                if (is_file($normalized) && !unlink($normalized)) throw new RuntimeException('Task file cleanup failed; operator action required.', 0, $e);
                throw $e;
            }
            $path = $normalized;
            $size = filesize($path);
            $mime = $images[$extension];
            $type = 'image';
        } elseif ($extension === 'pdf') {
            if ($size > 50*1024*1024 || $mime !== 'application/pdf') throw new RuntimeException('Invalid PDF file.');
            $head = file_get_contents($path,false,null,0,1024);
            $tail = file_get_contents($path,false,null,max(0,$size-2048),2048);
            $content = file_get_contents($path);
            $decodedNames = preg_replace_callback('/#([0-9A-Fa-f]{2})/', function ($match) { return chr(hexdec($match[1])); }, (string) $content);
            $activePdfPattern = '#(<\?php|<script|<html|javascript:|/JavaScript|/JS\b|/Launch|/EmbeddedFile|/OpenAction|/AA\b|/RichMedia|/SubmitForm|/ImportData)#i';
            $filterOccurrences=preg_match_all('#/Filter\b#',(string)$decodedNames,$ignoredFilterOccurrences);$parsedFilters=preg_match_all('#/Filter\s*(/\w+|\[[^\]]+\]).*?stream\r?\n(.*?)\r?\nendstream#s',(string)$content,$streams,PREG_SET_ORDER);if($filterOccurrences!==$parsedFilters)throw new RuntimeException('Unsupported PDF filter reference.');
            if ($parsedFilters) {
                foreach($streams as $stream){$filters=array();preg_match_all('#/([A-Za-z0-9]+)#',$stream[1],$filterNames);$filters=$filterNames[1];if(count($filters)!==1)throw new RuntimeException('Chained PDF stream filters are not allowed.');$filter=$filters[0];if($filter==='FlateDecode'){$inflated=@gzuncompress($stream[2],10*1024*1024);if($inflated===false)throw new RuntimeException('Unsupported compressed PDF stream.');$inflatedNames=preg_replace_callback('/#([0-9A-Fa-f]{2})/',function($match){return chr(hexdec($match[1]));},$inflated);if(preg_match($activePdfPattern,$inflatedNames))throw new RuntimeException('Active compressed PDF content is not allowed.');}elseif(!in_array($filter,array('DCTDecode','JPXDecode','CCITTFaxDecode','JBIG2Decode'),true))throw new RuntimeException('Unsupported PDF stream filter.');}
            }
            $hasClassicXref = preg_match('/\bxref\s+\d+\s+\d+\s+/s',(string)$content) && preg_match('/\btrailer\s*<</s',(string)$content);
            $hasXrefStream = preg_match('#/Type\s*/XRef\b#',(string)$decodedNames);
            if (!preg_match('/^%PDF-1\.[0-7][\r\n]/',(string)$content) || !preg_match('/\d+\s+\d+\s+obj\b.*?endobj/s',(string)$content) || !preg_match('/startxref\s+\d+\s+%%EOF\s*$/s',(string)$content) || (!$hasClassicXref && !$hasXrefStream) || preg_match($activePdfPattern,(string)$decodedNames)) throw new RuntimeException('Invalid PDF content.');
            $type='document';
        } elseif (in_array($extension,array('docx','xlsx'),true)) {
            if ($size > 50*1024*1024) throw new RuntimeException('Office file exceeds the limit.');
            $this->validateOoxml($path,$extension);
            $mime = $extension==='docx'?'application/vnd.openxmlformats-officedocument.wordprocessingml.document':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $type='document';
        } elseif (in_array($extension,array('csv','txt'),true)) {
            if ($size > 10*1024*1024 || !in_array($mime,array('text/plain','text/csv','application/csv'),true)) throw new RuntimeException('Invalid text file.');
            $sample=file_get_contents($path,false,null,0,min($size,1024*1024));
            if (strpos((string)$sample,"\0")!==false || (function_exists('mb_check_encoding') && !mb_check_encoding($sample,'UTF-8'))) throw new RuntimeException('Unsafe text encoding.');
            $mime=$extension==='csv'?'text/csv':'text/plain';$type='document';
        } else throw new RuntimeException('File type is not allowed.');
        return array('path'=>$path,'name'=>$name,'extension'=>$extension,'mime'=>$mime,'type'=>$type,'size'=>(int)$size,'sha256'=>hash_file('sha256',$path));
    }

    private function validateOoxml($path, $extension)
    {
        $bytes = file_get_contents($path, false, null, 0, 4);
        if ($bytes !== "PK\x03\x04") throw new RuntimeException('Invalid Office package prefix.');
        $fileSize = filesize($path);
        $tailSize = min((int)$fileSize, 65557);
        $tail = file_get_contents($path, false, null, (int)$fileSize-$tailSize, $tailSize);
        $eocd = strrpos((string)$tail, "PK\x05\x06");
        if ($eocd === false || strlen($tail) < $eocd + 22) throw new RuntimeException('Invalid Office package ending.');
        $commentLength = unpack('vlength', substr($tail, $eocd + 20, 2));
        if ($eocd + 22 + (int)$commentLength['length'] !== strlen($tail)) throw new RuntimeException('Office package polyglot is not allowed.');
        $zip = new ZipArchive();
        if ($zip->open($path)!==true || $zip->numFiles<=0 || $zip->numFiles>2000) throw new RuntimeException('Invalid Office file.');
        $total=0;$hasContent=false;$hasRoot=false;
        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i);$name=(string)$stat['name'];$size=(int)$stat['size'];$compressed=max(1,(int)$stat['comp_size']);
            if ($name==='' || $name[0]==='/' || strpos($name,'\\')!==false || strpos($name,':')!==false || preg_match('#(^|/)\.\.(/|$)#',$name) || preg_match('/[\x00-\x1F\x7F]/',$name) || $size>$compressed*100) {$zip->close();throw new RuntimeException('Unsafe Office archive.');}
            $opsys=0;$attr=0;if($zip->getExternalAttributesIndex($i,$opsys,$attr) && (($attr>>16)&0170000)===0120000){$zip->close();throw new RuntimeException('Office symlinks are not allowed.');}
            $total+=$size;if($total>200*1024*1024){$zip->close();throw new RuntimeException('Office archive is too large.');}
            if($name==='[Content_Types].xml')$hasContent=true;
            if(($extension==='docx'&&$name==='word/document.xml')||($extension==='xlsx'&&$name==='xl/workbook.xml'))$hasRoot=true;
            if (substr($name,-1)!=='/' && !preg_match('/\.(xml|rels|png|jpe?g|gif|bmp|tiff?|emf|wmf)$/i',$name)) {$zip->close();throw new RuntimeException('Unexpected Office package part.');}
            if(preg_match('#(^|/)(embeddings|activeX|customUI)(/|$)|vbaProject|oleObject|\.bin$#i',$name)){$zip->close();throw new RuntimeException('Active Office content is not allowed.');}
            if (preg_match('/\.(xml|rels)$/i',$name)) {
                $xml = $zip->getFromIndex($i);
                if ($xml === false || preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) {$zip->close();throw new RuntimeException('Unsafe Office XML.');}
                $doc = new DOMDocument();
                $previous = libxml_use_internal_errors(true);
                $valid = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                if (!$valid) {$zip->close();throw new RuntimeException('Invalid Office XML.');}
                if (substr($name,-5)==='.rels') {
                    foreach ($doc->getElementsByTagName('Relationship') as $relationship) {
                        if (strtolower($relationship->getAttribute('TargetMode'))==='external') {$zip->close();throw new RuntimeException('External Office relationships are not allowed.');}
                        $type = strtolower($relationship->getAttribute('Type'));
                        if (strpos($type,'oleobject')!==false || strpos($type,'package')!==false || strpos($type,'attachedtemplate')!==false) {$zip->close();throw new RuntimeException('Embedded Office relationships are not allowed.');}
                    }
                }
            }
        }
        $contentTypes = $zip->getFromName('[Content_Types].xml');
        $requiredPart = $extension==='docx'?'/word/document.xml':'/xl/workbook.xml';
        $requiredType = $extension==='docx'?'wordprocessingml.document.main+xml':'spreadsheetml.sheet.main+xml';
        $rootName = $extension==='docx'?'word/document.xml':'xl/workbook.xml';
        $rootXml = $zip->getFromName($rootName);
        $zip->close();
        if(!$hasContent||!$hasRoot||$contentTypes===false||$rootXml===false)throw new RuntimeException('Office content type mismatch.');
        $typesDoc=new DOMDocument();$rootDoc=new DOMDocument();$previous=libxml_use_internal_errors(true);$typesValid=$typesDoc->loadXML($contentTypes,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);$rootValid=$rootDoc->loadXML($rootXml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($previous);
        if(!$typesValid||!$rootValid||!$typesDoc->documentElement||$typesDoc->documentElement->localName!=='Types'||$typesDoc->documentElement->namespaceURI!=='http://schemas.openxmlformats.org/package/2006/content-types')throw new RuntimeException('Office content type mismatch.');
        $overrideValid=false;foreach($typesDoc->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/content-types','Override') as $override){if($override->getAttribute('PartName')===$requiredPart&&strpos($override->getAttribute('ContentType'),$requiredType)!==false)$overrideValid=true;}
        $expectedRoot=$extension==='docx'?'document':'workbook';$expectedNamespace=$extension==='docx'?'http://schemas.openxmlformats.org/wordprocessingml/2006/main':'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        if(!$overrideValid||!$rootDoc->documentElement||$rootDoc->documentElement->localName!==$expectedRoot||$rootDoc->documentElement->namespaceURI!==$expectedNamespace)throw new RuntimeException('Office root part mismatch.');
    }

    private function assertUpload(array $file)
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || !isset($file['error']) || (int)$file['error']!==UPLOAD_ERR_OK) throw new RuntimeException('No valid uploaded file.');
        if ((int)$file['size']<=0 || (int)$file['size']>50*1024*1024) throw new RuntimeException('File exceeds the allowed size.');
    }

    private function activeQuota($taskId)
    {
        $res=$this->db->query('SELECT COUNT(*) logical_count,COALESCE(SUM(file_size),0) active_bytes FROM '.MAIN_DB_PREFIX."cpo_task_asset WHERE entity=".$this->entity.' AND fk_task='.(int)$taskId." AND status='active' FOR UPDATE");
        $row=$res?$this->db->fetch_object($res):null;if(!$row)throw new RuntimeException('Unable to check task quota.');return get_object_vars($row);
    }

    private function taskCas($taskId,$version)
    {
        $res=$this->db->query('UPDATE '.MAIN_DB_PREFIX.'cpo_task SET version=version+1,fk_user_modif='.$this->auth->actorId().' WHERE entity='.$this->entity.' AND rowid='.(int)$taskId.' AND version='.(int)$version);
        if(!$res||$this->db->affected_rows($res)!==1)throw new InternalTaskConflictException('Task changed. Refresh and retry.');
    }

    private function log(array $task,$code,$message)
    {
        $sql='INSERT INTO '.MAIN_DB_PREFIX.'cpo_activity(entity,fk_operation,fk_task,action_code,message,visibility,fk_user,date_action) VALUES('.$this->entity.','.(!empty($task['fk_operation'])?(int)$task['fk_operation']:'NULL').','.(int)$task['rowid'].','.$this->q($code).','.$this->q($message).",'internal',".$this->auth->actorId().','.$this->q($this->now()).')';
        if(!$this->db->query($sql))throw new RuntimeException('Unable to save task activity.');
    }

    private function ensureDirectory($path,$mode)
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $cursor = $path;
        while (!file_exists($cursor)) {
            $parent = dirname($cursor);
            if ($parent === $cursor) break;
            $cursor = $parent;
        }
        if (is_link($cursor) || ($cursor !== '' && !is_dir($cursor))) throw new RuntimeException('Unsafe task storage path.');
        if (!is_dir($path) && !mkdir($path,$mode,true)) throw new RuntimeException('Unable to create task storage.');
        $segment = $path;
        while (strlen($segment) >= strlen($cursor)) {
            if (is_link($segment)) throw new RuntimeException('Task storage symlinks are not allowed.');
            if ($segment === $cursor) break;
            $segment = dirname($segment);
        }
        if (!chmod($path,$mode)) throw new RuntimeException('Unable to secure task storage.');
        clearstatcache(true,$path);
        if (!is_dir($path) || is_link($path) || (fileperms($path)&0777)!==$mode || realpath($path)===false) throw new RuntimeException('Task storage permissions are unsafe.');
    }
    private function secureFile($path)
    {
        if (!is_file($path) || is_link($path) || !chmod($path,0600)) throw new RuntimeException('Unable to secure task file.');
        clearstatcache(true,$path);
        if ((fileperms($path)&0777)!==0600) throw new RuntimeException('Task file permissions are unsafe.');
    }
    private function beginTransaction(){if(!$this->db->begin())throw new RuntimeException('Unable to begin task file transaction.');}
    private function commitTransaction(){if(!$this->db->commit())throw new RuntimeException('Unable to commit task file transaction.');}
    private function rows($sql){$out=array();$res=$this->db->query($sql);if(!$res)throw new RuntimeException('Unable to read task files.');while($row=$this->db->fetch_object($res))$out[]=get_object_vars($row);return $out;}
    private function q($value){return "'".$this->db->escape((string)$value)."'";}
    private function now(){return date('Y-m-d H:i:s');}
}
