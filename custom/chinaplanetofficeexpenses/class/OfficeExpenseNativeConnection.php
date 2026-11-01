<?php
declare(strict_types=1);

/** Shared native-handle transaction guard. Does not connect, install schema or change access. */
final class OfficeExpenseNativeConnection
{
    private $db;private static $uncertain=array();
    public function __construct($db)
    {
        if(!is_object($db)||!isset($db->transaction_opened))throw new InvalidArgumentException('An existing native database handle is required.');
        foreach(array('query','escape','fetch_object','begin','commit','rollback') as $method)if(!method_exists($db,$method))throw new InvalidArgumentException('Incomplete native database handle.');
        $this->db=$db;$this->assertUsable();
    }
    public function assertUsable(): void
    {
        $id=spl_object_id($this->db);
        if(isset(self::$uncertain[$id])&&self::$uncertain[$id]->get()===$this->db)throw new RuntimeException('Native connection requires fresh physical connection and verification.');
    }
    public function retire(): void
    {
        foreach(self::$uncertain as $id=>$reference)if($reference->get()===null)unset(self::$uncertain[$id]);
        self::$uncertain[spl_object_id($this->db)]=WeakReference::create($this->db);
    }
    public function outer(): void
    {
        $this->assertUsable();if($this->db->transaction_opened!==0)throw new LogicException('Native operation must own its outer transaction.');
    }
    public function beginOwned(): void
    {
        $this->outer();
        try{$begun=$this->db->begin();}catch(Throwable $error){$this->retire();throw new RuntimeException('Native begin outcome requires fresh verification.',0,$error);}
        if(!$begun||$this->db->transaction_opened!==1){$this->retire();throw new RuntimeException('Native begin outcome requires fresh verification.');}
    }
    public function commitOwned(): void
    {
        $this->assertUsable();
        if($this->db->transaction_opened!==1||!$this->db->commit()||$this->db->transaction_opened!==0)throw new RuntimeException('Native commit was not confirmed.');
    }
    public function rollbackOwnedOnFailure(Throwable $error): void
    {
        $rolledBack=false;
        try{if($this->db->transaction_opened===1)$rolledBack=(bool)$this->db->rollback();}catch(Throwable $rollbackError){$rolledBack=false;}
        if(!$rolledBack||$this->db->transaction_opened!==0){$this->retire();throw new RuntimeException('Native transaction outcome requires fresh verification.',0,$error);}
    }
    public function query(string $sql)
    {
        $this->assertUsable();
        try{$result=$this->db->query($sql);if(!$result)throw new RuntimeException('Native query rejected.');return $result;}
        catch(Throwable $error){throw new RuntimeException('Native storage unavailable.',0,$error);}
    }
    public function rows(string $sql): array
    {
        $result=$this->query($sql);$rows=array();while($row=$this->db->fetch_object($result))$rows[]=(array)$row;return $rows;
    }
    public function one(string $sql): ?array {$rows=$this->rows($sql);if(count($rows)>1)throw new RuntimeException('Native unique read returned multiple rows.');return $rows[0]??null;}
    public function quote(string $value): string {$this->assertUsable();return "'".$this->db->escape($value)."'";}
}
