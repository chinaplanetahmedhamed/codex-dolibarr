<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseWorkspaceService.php';
require_once __DIR__.'/OfficeExpenseReceiptHttp.php';

/** Private workspace evidence; existing workspace metadata/audit and provisioned private receipt root. */
final class OfficeExpenseWorkspaceEvidence
{
    public const MAX_BYTES=5242880;
    private $db;private int $entity;private int $actor;private OfficeExpenseWorkspaceService $service;private OfficeExpenseWorkspaceStore $store;private string $root;private string $web;
    public function __construct($db,OfficeExpenseWorkspaceService $service,int $entity,int $actor,string $root,string $web)
    {
        if($entity<1||$actor<1||!$service->usesNativeConnection($db,$entity,$actor))throw new DomainException('Native evidence context differs.');
        $this->db=$db;$this->entity=$entity;$this->actor=$actor;$this->service=$service;$this->store=new OfficeExpenseWorkspaceStore($db);$real=realpath($root);$public=realpath($web);
        if(!$real||!$public||$real===$public||str_starts_with($real,$public.'/')||str_starts_with($public,$real.'/'))throw new RuntimeException('Private evidence root is unavailable.');
        $this->root=$real;$this->web=$public;$this->rootCheck();
    }
    public function list(string $office,string $kind,string $parent,bool $verifyBytes=false): array
    {
        $this->service->get($office,$kind,$parent);$out=[];
        foreach($this->store->evidenceFor($this->entity,$office,$parent)as$file){$this->validateRow($file,$office,$kind,$parent);if($verifyBytes)$this->bytes($file,$office,$kind,$parent);$out[]=['id'=>$file['id'],'name'=>$file['content']['filename'],'mime'=>$file['content']['mime'],'size'=>$file['content']['size'],'sha256'=>$file['content']['sha256'],'uploader'=>$file['owner'],'at'=>$file['created_at']];}
        return $out;
    }
    public function attach(string $office,string $kind,string $parent,string $key,string $filename,string $bytes): array
    {
        $source=$this->service->get($office,$kind,$parent);$this->canAttach($source);$metadata=self::inspect($filename,$bytes);
        $createdPath=null;
        $saved=$this->store->addEvidence($this->entity,$this->actor,$parent,$key,$metadata,
            function($row)use($office,$kind){if($row['office']!==$office||$row['kind']!==$kind)throw new DomainException('Evidence source differs.');$this->canAttach($row);},
            function($ref,$row)use($bytes,&$createdPath){
                $dir=$this->directory($row['office'],$row['id'],true);$count=0;$total=0;
                foreach(scandir($dir)as$entry){if($entry==='.'||$entry==='..')continue;if(!preg_match('/^[a-f0-9]{32}\.blob$/D',$entry))throw new RuntimeException('Unknown evidence storage entry.');$path=$dir.'/'.$entry;$this->privateFile($path);$count++;$total+=filesize($path);}
                if($count>=10||$total+strlen($bytes)>26214400)throw new DomainException('Evidence limit includes retained unresolved files.');
                $path=$dir.'/'.$ref.'.blob';$handle=@fopen($path,'xb');if(!$handle)throw new RuntimeException('Private evidence file could not be created.');$createdPath=$path;
                try{if(!chmod($path,0600))throw new RuntimeException('Cannot protect evidence file.');$written=0;while($written<strlen($bytes)){$n=fwrite($handle,substr($bytes,$written));if(!$n)throw new RuntimeException('Evidence write failed.');$written+=$n;}if(!fflush($handle)||(function_exists('fsync')&&!fsync($handle)))throw new RuntimeException('Evidence persistence not confirmed.');}finally{fclose($handle);}
                $this->privateFile($path);if(hash_file('sha256',$path)!==hash('sha256',$bytes))throw new RuntimeException('Evidence byte readback differs.');
            },
            function($ref)use(&$createdPath,$office,$parent){if($createdPath!==null){$expected=$this->directory($office,$parent,false).'/'.$ref.'.blob';if($createdPath!==$expected)throw new RuntimeException('Evidence cleanup scope differs.');$this->privateFile($expected);if(!unlink($expected))throw new RuntimeException('Uncommitted evidence cleanup requires review.');}}
        );
        // A retry never rewrites the retained original; verify it before acknowledging success.
        $this->bytes($saved,$office,$kind,$parent);return ['id'=>$saved['id'],'name'=>$saved['content']['filename'],'sha256'=>$saved['content']['sha256']];
    }
    public function download(string $office,string $kind,string $parent,string $id): array
    {
        $this->service->get($office,$kind,$parent);
        return $this->store->evidenceRead($this->entity,$this->actor,$id,function($row)use($office,$kind,$parent){$this->service->get($office,$kind,$parent);$bytes=$this->bytes($row,$office,$kind,$parent);$this->service->get($office,$kind,$parent);return ['bytes'=>$bytes,'filename'=>$row['content']['filename'],'mime'=>'application/octet-stream'];});
    }
    private function canAttach(array $source): void
    {
        $this->service->get($source['office'],$source['kind'],$source['id']);if($source['kind']==='opening'&&$source['owner']!==$this->actor)throw new DomainException('Only the opening reconciliation owner can attach its evidence.');
        if(!in_array($source['kind'],['opening','expense','advance','advance_return','advance_settlement','receipt','deposit','payroll'],true)||!in_array($source['status'],['draft','rejected'],true))throw new DomainException('Only unsubmitted financial drafts accept evidence.');
        if(!$this->service->canPrepareFinance($source))throw new DomainException('Evidence attachment is not permitted.');
    }
    private function validateRow(array $row,string $office,string $kind,string $parent): void
    {
        $v=$row['content'];if($row['kind']!=='evidence'||$row['entity']!==$this->entity||$row['office']!==$office||$row['status']!=='active'||($v['parent_ref']??null)!==$parent||($v['parent_kind']??null)!==$kind||$row['subject']!==($v['parent_owner']??null)||$row['object_key']!=='evidence:'.$parent.':'.$row['id'])throw new DomainException('Evidence is not bound to this document.');
        self::name($v['filename']??'');if(!in_array($v['mime']??null,['image/jpeg','image/png','application/pdf'],true))throw new RuntimeException('Evidence MIME metadata differs.');if(!is_int($v['size']??null)||$v['size']<1||$v['size']>self::MAX_BYTES||!preg_match('/^[a-f0-9]{64}$/D',$v['sha256']??''))throw new RuntimeException('Evidence metadata is corrupt.');
    }
    private function bytes(array $row,string $office,string $kind,string $parent): string
    {
        $this->validateRow($row,$office,$kind,$parent);$path=$this->directory($office,$parent,false).'/'.$row['id'].'.blob';$this->privateFile($path);$size=$row['content']['size'];
        if(filesize($path)!==$size)throw new RuntimeException('Evidence size differs.');$bytes=file_get_contents($path,false,null,0,$size+1);if(!is_string($bytes)||strlen($bytes)!==$size||!hash_equals($row['content']['sha256'],hash('sha256',$bytes)))throw new RuntimeException('Evidence checksum differs.');return $bytes;
    }
    private function rootCheck(): void
    {
        clearstatcache(true,$this->root);$marker=$this->root.'/.cpo-office-expenses-private';
        if(is_link($this->root)||realpath($this->root)!==$this->root||(fileperms($this->root)&0077)!==0||is_link($marker)||!is_file($marker)||(fileperms($marker)&0077)!==0||file_get_contents($marker)!=='cpo-office-expenses-private-v1')throw new RuntimeException('Provisioned private evidence root changed.');
    }
    private function directory(string $office,string $parent,bool $create): string
    {
        if(!isset(OfficeExpenseStaffAccess::OFFICES[$office])||!preg_match('/^[a-f0-9]{32}$/D',$parent))throw new InvalidArgumentException('Invalid evidence scope.');$this->rootCheck();$path=$this->root;
        foreach(['workspace',(string)$this->entity,$office,$parent]as$part){$path.='/'.$part;clearstatcache(true,$path);if(is_link($path))throw new RuntimeException('Unsafe evidence storage path.');if(!file_exists($path)&&$create&&!mkdir($path,0700))throw new RuntimeException('Evidence storage cannot be provisioned.');if(!file_exists($path)&&!$create)throw new RuntimeException('Evidence directory missing.');if(!is_dir($path)||realpath($path)!==$path||(fileperms($path)&0077)!==0)throw new RuntimeException('Unsafe evidence storage directory.');}return $path;
    }
    private function privateFile(string $path): void
    {clearstatcache(true,$path);$s=@lstat($path);if(!$s||is_link($path)||!is_file($path)||$s['nlink']!==1||($s['mode']&0077)!==0)throw new RuntimeException('Private evidence file is missing or unsafe.');}
    public static function name(string $name): string
    {
        if($name===''||trim($name)!==$name||strlen($name)>160||preg_match('//u',$name)!==1||preg_match('/[\/\\\\\x00-\x1f\x7f\x{202a}-\x{202e}\x{2066}-\x{2069}]/u',$name)||$name[0]==='.'||!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['jpg','jpeg','png','pdf'],true))throw new InvalidArgumentException('Unsafe evidence filename/type.');return $name;
    }
    public static function inspect(string $name,string $bytes): array
    {
        self::name($name);$size=strlen($bytes);if($size<1||$size>self::MAX_BYTES)throw new InvalidArgumentException('Evidence exceeds the 5MB limit.');$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);$expected=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','pdf'=>'application/pdf'][strtolower(pathinfo($name,PATHINFO_EXTENSION))];
        if($mime!==$expected)throw new InvalidArgumentException('Evidence content does not match its extension.');
        if($mime==='application/pdf'){if(!str_starts_with($bytes,'%PDF-')||!str_contains(substr($bytes,-2048),'%%EOF'))throw new InvalidArgumentException('Invalid PDF evidence.');}
        else{$image=@getimagesizefromstring($bytes);if(!$image||$image[0]<1||$image[1]<1||$image[0]>10000||$image[1]>10000||$image[0]*$image[1]>12000000)throw new InvalidArgumentException('Evidence image is too large or invalid.');$decoded=@imagecreatefromstring($bytes);if(!$decoded)throw new InvalidArgumentException('Invalid evidence image.');imagedestroy($decoded);}
        return ['filename'=>$name,'mime'=>$mime,'size'=>$size,'sha256'=>hash('sha256',$bytes)];
    }
    public static function upload(array $file): array
    {
        $limit=(new OfficeExpenseHttpUploadSource())->effectiveLimit(self::MAX_BYTES);if($limit<1)throw new DomainException('Uploads are disabled by native server policy.');
        if(!is_int($file['error']??null)||$file['error']!==UPLOAD_ERR_OK||!is_string($file['name']??null)||!is_string($file['tmp_name']??null)||!is_int($file['size']??null)||$file['size']<1||$file['size']>$limit||array_diff(array_keys($file),['name','tmp_name','error','size','type','full_path']))throw new InvalidArgumentException('Invalid or oversized HTTP upload.');
        self::name($file['name']);if(is_link($file['tmp_name'])||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('A genuine HTTP upload is required.');$bytes=file_get_contents($file['tmp_name'],false,null,0,$limit+1);if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new InvalidArgumentException('Upload byte evidence differs.');return ['name'=>$file['name'],'bytes'=>$bytes];
    }
    public static function headers(array $file,string $id): array
    {
        self::name($file['filename']);if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('Invalid file reference.');
        return ['Content-Type'=>'application/octet-stream','Content-Disposition'=>'attachment; filename="evidence-'.$id.'.'.strtolower(pathinfo($file['filename'],PATHINFO_EXTENSION)).'"; filename*=UTF-8\'\''.rawurlencode($file['filename']),'Content-Length'=>(string)strlen($file['bytes']),'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"sandbox; default-src 'none'"];
    }
}
