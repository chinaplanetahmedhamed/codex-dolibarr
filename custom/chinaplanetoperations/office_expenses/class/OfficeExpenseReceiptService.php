<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseDraftService.php';

interface OfficeExpenseReceiptStore
{
    public function add(array $draft,string $key,array $file,string $original,string $preview): array;
    public function listForDraft(array $draft): array;
    public function readForDraft(array $draft,string $id,string $variant): array;
    public function auditForDraft(array $draft): array;
    public function inspectForDraft(array $draft): array;
}
class OfficeExpenseReceiptConflict extends DomainException {}
class OfficeExpenseReceiptQuota extends DomainException {}

/** Scoped receipt domain service. No upload endpoint, file paths, native objects or financial writes. */
final class OfficeExpenseReceiptService
{
    private $drafts;private $store;private $policy;
    public function __construct(OfficeExpenseDraftService $drafts,OfficeExpenseReceiptStore $store,array $policy=array())
    {
        $defaults=array('max_bytes'=>5242880,'max_preview_bytes'=>10485760,'max_pixels'=>12000000,'max_dimension'=>10000,'formats'=>array('image/jpeg','image/png'));
        if(array_diff(array_keys($policy),array_keys($defaults)))throw new InvalidArgumentException('Unknown trusted receipt policy.');
        $this->policy=array_merge($defaults,$policy);
        foreach($this->policy as $key=>$value)if($key!=='formats'&&(!is_int($value)||$value<=0))throw new InvalidArgumentException('Invalid trusted receipt limit.');
        if(!is_array($this->policy['formats'])||!$this->policy['formats'])throw new InvalidArgumentException('Invalid receipt formats.');
        foreach($this->policy['formats'] as $mime)if(!is_string($mime)||!in_array($mime,array('image/jpeg','image/png'),true))throw new InvalidArgumentException('Unsupported receipt format policy.');
        $this->policy['formats']=array_values(array_unique($this->policy['formats']));
        if($this->policy['max_bytes']>16777216||$this->policy['max_preview_bytes']>16777216||$this->policy['max_pixels']>25000000||$this->policy['max_dimension']>20000)throw new InvalidArgumentException('Receipt resource limits exceed this stage.');
        $this->drafts=$drafts;$this->store=$store;
    }
    public function attach(string $draftId,string $key,string $name,string $bytes): array
    {
        $draft=$this->drafts->getOwn($draftId);
        if(!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$key))throw new InvalidArgumentException('Invalid stable receipt key.');
        $name=self::validateName($name);$size=strlen($bytes);
        if($size===0||$size>$this->policy['max_bytes'])throw new InvalidArgumentException('Receipt size is outside the limit.');
        if(!class_exists('finfo')||!function_exists('imagecreatefromstring'))throw new RuntimeException('Receipt image capabilities are unavailable.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        $allowed=array('png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg');
        if(($allowed[$extension]??null)!==$mime||!in_array($mime,$this->policy['formats'],true))throw new InvalidArgumentException('Receipt content or file type is not allowed.');
        $info=@getimagesizefromstring($bytes);
        if(!$info||$info[0]<=0||$info[1]<=0||$info[0]>$this->policy['max_dimension']||$info[1]>$this->policy['max_dimension']
            ||$info[0]*$info[1]>$this->policy['max_pixels'])throw new InvalidArgumentException('Receipt dimensions exceed the limit.');
        $image=@imagecreatefromstring($bytes);if(!$image)throw new InvalidArgumentException('Receipt image cannot be decoded.');
        ob_start();
        try{$ok=$mime==='image/png'?imagepng($image,null,6):imagejpeg($image,null,90);$preview=(string)ob_get_contents();}
        finally{ob_end_clean();imagedestroy($image);}
        if(!$ok||strlen($preview)===0||strlen($preview)>$this->policy['max_preview_bytes'])throw new InvalidArgumentException('Normalized receipt size exceeds the limit.');
        $file=array('original_name'=>$name,'mime'=>$mime,'size'=>$size,'sha256'=>hash('sha256',$bytes),'width'=>(int)$info[0],'height'=>(int)$info[1],
            'preview_mime'=>$mime,'preview_size'=>strlen($preview),'preview_sha256'=>hash('sha256',$preview));
        return $this->store->add($draft,$key,$file,$bytes,$preview);
    }
    public function listOwn(string $draftId): array {return $this->store->listForDraft($this->drafts->getOwn($draftId));}
    public function read(string $draftId,string $receiptId,string $variant='original'): array {return $this->store->readForDraft($this->drafts->getOwn($draftId),$receiptId,$variant);}
    public function auditOwn(string $draftId): array {return $this->store->auditForDraft($this->drafts->getOwn($draftId));}
    public function inspectOwn(string $draftId): array {return $this->store->inspectForDraft($this->drafts->getOwn($draftId));}
    public function usesDraftService(OfficeExpenseDraftService $drafts): bool {return $this->drafts===$drafts;}
    public function uploadPolicy(): array
    {
        $extensions=array();foreach(array('jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png') as $extension=>$mime)if(in_array($mime,$this->policy['formats'],true))$extensions[]=$extension;
        return array('max_bytes'=>$this->policy['max_bytes'],'formats'=>$this->policy['formats'],'extensions'=>$extensions);
    }
    public static function validateName(string $name): string
    {
        if($name===''||strlen($name)>160||trim($name)!==$name||$name[0]==='.'||preg_match('//u',$name)!==1
            ||preg_match('/[\/\\\\\x00-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u',$name)
            ||substr_count($name,'.')!==1||!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),array('png','jpg','jpeg'),true))throw new InvalidArgumentException('Receipt name/type is not allowed.');
        return $name;
    }
}
