<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseReceiptService.php';

class OfficeExpenseUploadTooLarge extends InvalidArgumentException {}
interface OfficeExpenseReceiptUploadSource
{
    public function effectiveLimit(int $configured): int;
    public function read(array $file,int $maxBytes): array;
}

/** Real PHP uploads only. CLI/local-path/synthetic fallbacks are deliberately absent. */
final class OfficeExpenseHttpUploadSource implements OfficeExpenseReceiptUploadSource
{
    public function effectiveLimit(int $configured): int
    {
        if(!in_array(strtolower((string)ini_get('file_uploads')),array('1','on','yes','true'),true))return 0;
        $limit=$configured;
        foreach(array('upload_max_filesize','post_max_size') as $name){
            $cap=$this->iniBytes((string)ini_get($name));if($cap<0)return 0;
            if($cap>0)$limit=min($limit,$name==='post_max_size'?max(0,$cap-65536):$cap);
        }
        return max(0,$limit);
    }
    public function read(array $file,int $maxBytes): array
    {
        if($maxBytes<=0||$maxBytes>16777216)throw new InvalidArgumentException('Receipt upload is unavailable.');
        if(!is_int($file['error']??null))throw new InvalidArgumentException('Invalid upload metadata.');
        if(in_array($file['error'],array(UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE),true))throw new OfficeExpenseUploadTooLarge('Receipt exceeds the server upload limit.');
        if($file['error']!==UPLOAD_ERR_OK||!is_string($file['name']??null)||!is_string($file['tmp_name']??null)||!is_int($file['size']??null)
            ||$file['size']<=0||array_diff(array_keys($file),array('name','tmp_name','error','size','type','full_path')))throw new InvalidArgumentException('Invalid single receipt upload.');
        OfficeExpenseReceiptService::validateName($file['name']);
        if($file['size']>$maxBytes)throw new OfficeExpenseUploadTooLarge('Receipt exceeds the allowed size.');
        if(is_link($file['tmp_name'])||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('A real HTTP upload is required.');
        clearstatcache(true,$file['tmp_name']);
        if(filesize($file['tmp_name'])!==$file['size'])throw new InvalidArgumentException('Receipt upload size changed.');
        $bytes=@file_get_contents($file['tmp_name'],false,null,0,$maxBytes+1);
        if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new InvalidArgumentException('Receipt upload could not be read.');
        return array('name'=>$file['name'],'bytes'=>$bytes); // Browser-supplied type/full_path are never trusted.
    }
    private function iniBytes(string $value): int
    {
        if(!preg_match('/^([0-9]+)\s*([kmgt]?)b?$/i',trim($value),$match))return -1;
        $bytes=(int)$match[1];$unit=strtolower($match[2]??'');$power=$unit===''?0:strpos(' kmgt',$unit);
        for($i=0;$i<$power;$i++){if($bytes>intdiv(PHP_INT_MAX,1024))return PHP_INT_MAX;$bytes*=1024;}return $bytes;
    }
}

final class OfficeExpenseReceiptHttpResponse
{
    public static function headers(array $download,string $receiptId): array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$receiptId)||!is_string($download['filename']??null)||!is_string($download['bytes']??null)
            ||strlen($download['bytes'])>16777216||($download['mime']??null)!=='application/octet-stream'||($download['disposition']??null)!=='attachment')throw new InvalidArgumentException('Invalid private download descriptor.');
        $name=OfficeExpenseReceiptService::validateName($download['filename']);$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        return array('Content-Type'=>'application/octet-stream','Content-Length'=>(string)strlen($download['bytes']),
            'Content-Disposition'=>'attachment; filename="receipt-'.$receiptId.'.'.$extension.'"; filename*=UTF-8\'\''.rawurlencode($name),
            'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff');
    }
}
