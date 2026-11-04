<?php
declare(strict_types=1);

/** Small branch letterhead logo, re-encoded as PNG; never an executable/public upload. */
final class OfficeExpenseWorkspaceLogo
{
    public static function fromBytes(string $bytes): string
    {
        if($bytes===''||strlen($bytes)>131072)throw new InvalidArgumentException('Logo must be a PNG/JPEG image under 128 KiB.');
        $info=@getimagesizefromstring($bytes);
        if(!$info||!in_array($info['mime']??null,['image/png','image/jpeg'],true)||$info[0]<1||$info[1]<1||$info[0]>1024||$info[1]>1024||$info[0]*$info[1]>524288)throw new InvalidArgumentException('Logo dimensions or format are not supported.');
        if(!function_exists('imagecreatefromstring'))throw new RuntimeException('Native image capability unavailable.');
        $im=@imagecreatefromstring($bytes);if(!$im)throw new InvalidArgumentException('Logo cannot be decoded.');
        ob_start();try{imagealphablending($im,false);imagesavealpha($im,true);$ok=imagepng($im,null,8);$png=(string)ob_get_contents();}finally{ob_end_clean();imagedestroy($im);}
        if(!$ok||strlen($png)>131072)throw new InvalidArgumentException('Normalized logo exceeds the supported limit.');
        return 'data:image/png;base64,'.base64_encode($png);
    }
    public static function validate($data): string
    {
        if($data==='')return '';
        if(!is_string($data)||strlen($data)>174800||!str_starts_with($data,'data:image/png;base64,'))throw new InvalidArgumentException('Invalid logo.');
        $bytes=base64_decode(substr($data,22),true);if($bytes===false||base64_encode($bytes)!==substr($data,22))throw new InvalidArgumentException('Invalid logo encoding.');
        return self::fromBytes($bytes);
    }
    public static function upload(array $file): string
    {
        if(array_diff(array_keys($file),['name','type','tmp_name','error','size','full_path'])||($file['error']??null)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||!is_int($file['size']??null)||$file['size']>131072)throw new InvalidArgumentException('Invalid logo upload.');
        $bytes=file_get_contents($file['tmp_name'],false,null,0,131073);if(!is_string($bytes)||strlen($bytes)!==$file['size'])throw new InvalidArgumentException('Logo upload is incomplete.');return self::fromBytes($bytes);
    }
}
