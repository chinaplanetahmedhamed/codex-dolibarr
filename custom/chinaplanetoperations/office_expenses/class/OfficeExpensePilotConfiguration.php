<?php
declare(strict_types=1);

final class OfficeExpensePilotAccessDenied extends DomainException {}

/** Explicit administrator-only staging pilot. Never provisions schema, users, rights or storage. */
final class OfficeExpensePilotConfiguration
{
    public const PATH='/var/lib/chinaplanetos-office-expenses/pilot.json';
    private $config;
    public function __construct(array $config)
    {
        $keys=array('version','enabled','environment','entity','user_id','base_currency','offices','precision','private_root');
        if(count($config)!==count($keys)||array_diff(array_keys($config),$keys)
            ||($config['version']??null)!==1||($config['enabled']??null)!==true||($config['environment']??null)!=='backup-erp'
            ||!is_int($config['entity']??null)||$config['entity']<1||!is_int($config['user_id']??null)||$config['user_id']<1
            ||!in_array($config['base_currency']??null,array('EGP','CNY','USD'),true)
            ||($config['offices']??null)!==array('egypt'=>array('EGP'),'china'=>array('CNY'))
            ||($config['precision']??null)!==array('EGP'=>2,'CNY'=>2,'USD'=>2)
            ||($config['private_root']??null)!=='/var/lib/chinaplanetos-office-expenses/receipts')throw new InvalidArgumentException('Invalid or disabled staging pilot configuration.');
        $this->config=$config;
    }
    public function scope($user,$conf): array
    {
        if(!class_exists('User',false)||!$user instanceof User||(int)($user->id??0)!==$this->config['user_id']
            ||empty($user->admin)||(int)($user->status??0)!==1||!empty($user->socid)
            ||$user->isNotIntoValidityDateRange()||!$user->hasRight('expensereport','creer'))throw new OfficeExpensePilotAccessDenied('This pilot is restricted to its approved native administrator.');
        if(!is_object($conf)||($conf->entity??null)!==$this->config['entity']||($conf->currency??null)!==$this->config['base_currency']||empty($conf->expensereport->enabled))throw new DomainException('Pilot configuration differs from native entity, currency or module state.');
        return $this->config['offices'];
    }
    public function privateRoot(): string {return $this->config['private_root'];}
    public function precision(): array {return $this->config['precision'];}

    /** Only a root-owned read-only-for-the-app configuration outside the web root is accepted. */
    public static function read(string $path,string $webRoot): self
    {
        $web=realpath($webRoot);$real=realpath($path);
        if($web===false||$real===false||$real!==$path||$real===$web||strpos($real,$web.'/')===0)throw new RuntimeException('Private pilot configuration is unavailable.');
        for($parent=dirname($path);; $parent=dirname($parent)){
            $stat=@lstat($parent);
            if(!$stat||is_link($parent)||($stat['mode']&0170000)!==0040000||$stat['uid']!==0||($stat['mode']&0022)!==0)throw new RuntimeException('Unsafe pilot configuration directory.');
            if($parent==='/')break;
        }
        $before=@lstat($path);
        if(!$before||is_link($path)||($before['mode']&0170000)!==0100000||$before['uid']!==0||($before['mode']&0027)!==0||$before['nlink']!==1||$before['size']<2||$before['size']>8192)throw new RuntimeException('Unsafe pilot configuration file.');
        $raw=@file_get_contents($path);clearstatcache(true,$path);$after=@lstat($path);
        if(!is_string($raw)||strlen($raw)!==$before['size']||$before!==$after)throw new RuntimeException('Pilot configuration changed during read.');
        try{$config=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $error){throw new RuntimeException('Invalid private pilot configuration.',0,$error);}
        if(!is_array($config))throw new RuntimeException('Invalid private pilot configuration.');
        return new self($config);
    }
}
