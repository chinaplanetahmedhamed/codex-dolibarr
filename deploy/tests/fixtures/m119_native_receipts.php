<?php
declare(strict_types=1);
// Synthetic native identities, new SQLite metadata and owned private generated images only.
$nreceiptRoots=array();
function nsetup(int $maxCount=10,int $maxBytes=26214400): array
{
    global $db,$conf,$nreceiptRoots;m119IdentityDatabase();$native=$db;$native->query('INSERT INTO llx_user_rights(entity,fk_user,fk_id) VALUES(1,8,90001)');$user=m119FetchIdentity('synthetic-7');$other=m119FetchIdentity('synthetic-8');
    $backend=new M119MigrationBackend($native);$baseMigration=new OfficeExpenseNativeSchemaMigration($backend);$baseMigration->install($baseMigration->plan()['manifest_hash']);$migration=OfficeExpenseNativeSchemaMigration::forReceipts($backend);$migration->install($migration->plan()['manifest_hash']);
    $base=DOL_DATA_ROOT.'/m119-native-receipts-'.bin2hex(random_bytes(8));mkdir($base,0700);$nreceiptRoots[]=$base;mkdir($base.'/private',0700);mkdir($base.'/web',0700);file_put_contents($base.'/private/.cpo-office-expenses-private','cpo-office-expenses-private-v1');chmod($base.'/private/.cpo-office-expenses-private',0600);
    $c=array('db'=>$native,'backend'=>$backend,'user'=>$user,'other'=>$other,'conf'=>$conf,'base'=>$base,'root'=>$base.'/private','web'=>$base.'/web','maxCount'=>$maxCount,'maxBytes'=>$maxBytes);return nservices($c);
}
function nservices(array $c): array
{
    $c['sources']=new OfficeExpenseNativeDraftStore($c['backend']);$c['drafts']=new OfficeExpenseDraftService($c['sources'],new OfficeExpenseMoney(),$c['user'],1,array('egypt'=>array('EGP'),'china'=>array('CNY')),'EGP');
    $c['store']=new OfficeExpenseNativeReceiptStore($c['backend'],$c['sources'],$c['root'],$c['web'],$c['maxCount'],$c['maxBytes']);$c['receipts']=new OfficeExpenseReceiptService($c['drafts'],$c['store']);return $c;
}
function nreopen(array $c): array {$c['db']=new M119NativeSqlite($c['db']->database_name);$c['backend']=new M119MigrationBackend($c['db']);return nservices($c);}
function npayload(): array {return array('kind'=>'expense_claim','office'=>'china','amount'=>'180.29','currency'=>'CNY','occurred_on'=>'2026-11-01','source_ref'=>'SYNTHETIC 中国','purpose'=>'SYNTHETIC native office expense','quote'=>array('rate'=>'7.25','date'=>'2026-11-01','source'=>'SYNTHETIC estimate'));}
function ndraft(array $c,string $key='synthetic-native-receipt-source'): array {return $c['drafts']->submit(npayload(),$key);}
function nimage(): string {$image=imagecreatetruecolor(2,2);ob_start();imagepng($image);$bytes=(string)ob_get_clean();imagedestroy($image);return $bytes;}
function ncount(array $c,string $table): int {return (int)$c['db']->db->querySingle('SELECT COUNT(*) FROM llx_'.$table);}
function nprivateDir(array $c,array $d,bool $create=false): string
{
    $p=$c['root'];foreach(array($d['entity'],$d['owner_id'],$d['id']) as $part){$p.='/'.$part;if($create&&!is_dir($p))mkdir($p,0700);}return $p;
}
function nrestricted(array $c,string $kind): OfficeExpenseReceiptService
{
    global $db;$db=$c['db'];$user=$c['user'];$entity=1;$offices=array('china'=>array('CNY'));
    if($kind==='employee')$user=$c['other'];elseif($kind==='entity')$entity=2;elseif($kind==='office')$offices=array('egypt'=>array('EGP'));
    elseif($kind==='revoked'){$db->query('DELETE FROM llx_user_rights WHERE fk_user=7');$user=m119FetchIdentity('synthetic-7');}
    $drafts=new OfficeExpenseDraftService($c['sources'],new OfficeExpenseMoney(),$user,$entity,$offices,'EGP');return new OfficeExpenseReceiptService($drafts,$c['store']);
}
function nbind(array $c): OfficeExpenseNativeStorageFactory
{
    $GLOBALS['db']=$c['backend'];$GLOBALS['user']=$c['user'];$GLOBALS['conf']=$c['conf'];return new OfficeExpenseNativeStorageFactory($c['backend'],$c['user'],$c['conf'],array('egypt'=>array('EGP'),'china'=>array('CNY')),$c['root'],$c['web']);
}
final class M119NativeReceiptUpload implements OfficeExpenseReceiptUploadSource
{
    public $reads=0;
    public function effectiveLimit(int $configured): int {return $configured;}
    public function read(array $file,int $maxBytes): array {$this->reads++;if(strlen($file['bytes'])>$maxBytes)throw new OfficeExpenseUploadTooLarge('Synthetic file too large.');return array('name'=>$file['name'],'bytes'=>$file['bytes']);}
}
register_shutdown_function(function()use(&$nreceiptRoots){
    $clean=function($p)use(&$clean){if(is_file($p)||is_link($p)){unlink($p);return;}foreach(scandir($p) as $n)if($n!=='.'&&$n!=='..')$clean($p.'/'.$n);rmdir($p);};
    foreach($nreceiptRoots as $root)if(dirname($root)===DOL_DATA_ROOT&&preg_match('/^m119-native-receipts-[a-f0-9]{16}$/D',basename($root))&&is_dir($root))$clean($root);
    // Native shutdown runs first; finish its owned-root removal after this fixture's private directories.
    m119NativeCleanup();
});
