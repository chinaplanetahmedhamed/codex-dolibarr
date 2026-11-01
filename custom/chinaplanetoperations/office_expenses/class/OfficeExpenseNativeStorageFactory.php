<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseNativeReceiptStore.php';

/** Per-request trusted binding only. Does not register globals, create roots/schema/users or enable posting. */
final class OfficeExpenseNativeStorageFactory
{
    private $db;private $user;private $owner;private $conf;private $entity;private $currency;private $drafts;private $receipts;private $schema;
    public function __construct($db,$user,$conf,array $offices,string $privateRoot,string $webRoot,array $receiptPolicy=array(),int $maxCount=10,int $maxBytes=26214400,array $precision=array('EGP'=>2,'CNY'=>2,'USD'=>2))
    {
        if(!is_object($conf)||!is_int($conf->entity??null)||$conf->entity<1||!is_string($conf->currency??null)||!class_exists('User',false)||!$user instanceof User)throw new DomainException('Actual native identity/configuration is required.');
        $this->db=$db;$this->user=$user;$this->owner=(int)($user->id??0);$this->conf=$conf;$this->entity=$conf->entity;$this->currency=$conf->currency;$this->context($db,$user,$conf);
        $sources=new OfficeExpenseNativeDraftStore($db);$this->drafts=new OfficeExpenseDraftService($sources,new OfficeExpenseMoney($precision),$user,$this->entity,$offices,$this->currency);$this->drafts->officeOptions();
        $this->schema=OfficeExpenseNativeSchemaMigration::forReceipts(new OfficeExpenseMysqliSchemaBackend($db));$this->schema->assertReady();
        $store=new OfficeExpenseNativeReceiptStore($db,$sources,$privateRoot,$webRoot,$maxCount,$maxBytes);$this->receipts=new OfficeExpenseReceiptService($this->drafts,$store,$receiptPolicy);
    }
    public function draftFactory($db,$user,$conf): OfficeExpenseDraftService {$this->context($db,$user,$conf);$this->drafts->officeOptions();$this->schema->assertReady();return $this->drafts;}
    public function receiptFactory($db,$user,$conf,OfficeExpenseDraftService $drafts): OfficeExpenseReceiptService
    {
        $this->context($db,$user,$conf);if($drafts!==$this->drafts)throw new DomainException('Receipt binding requires its identical authorized draft service.');$drafts->officeOptions();$this->schema->assertReady();return $this->receipts;
    }
    private function context($db,$user,$conf): void
    {
        if($db!==$this->db||$user!==$this->user||$this->owner<1||(int)($user->id??0)!==$this->owner||$conf!==$this->conf||($GLOBALS['db']??null)!==$db||($GLOBALS['user']??null)!==$user||($GLOBALS['conf']??null)!==$conf||($conf->entity??null)!==$this->entity||($conf->currency??null)!==$this->currency||empty($conf->expensereport->enabled))throw new DomainException('Native storage context differs or is disabled.');
        (new OfficeExpenseNativeConnection($db))->assertUsable();
    }
}
