<?php
declare(strict_types=1);
/** Release-owned pause switch. Business configuration remains a separate, default-disabled gate. */
final class OfficeExpenseFinanceGate
{
    public static function enabled(): bool {return self::read(dirname(__DIR__).'/finance-bridge.json');}
    /** The public reader is also used by the isolated filesystem acceptance fixture. */
    public static function read(string $path): bool
    {
        clearstatcache(true,$path);$before=@lstat($path);$owner=fileowner(__FILE__);
        if(!$before||is_link($path)||!is_file($path)||$before['nlink']!==1||$before['uid']!==$owner||($before['mode']&0022)!==0||$before['size']<1||$before['size']>128)return false;
        $handle=@fopen($path,'rb');if(!$handle)return false;
        try{$opened=fstat($handle);$raw=stream_get_contents($handle,129);$after=fstat($handle);}finally{fclose($handle);}
        clearstatcache(true,$path);$named=@lstat($path);if(!$named||!is_array($opened)||!is_array($after)||!is_string($raw))return false;
        foreach(['dev','ino','mode','uid','nlink','size','mtime','ctime']as$key)if($before[$key]!==$opened[$key]||$before[$key]!==$after[$key]||$before[$key]!==$named[$key])return false;
        return $raw==="{\"version\":1,\"enabled\":true}\n";
    }
    public static function assertEnabled(): void {if(!self::enabled())throw new DomainException('Financial execution is paused by the release operator; records and history remain available.');}
}
