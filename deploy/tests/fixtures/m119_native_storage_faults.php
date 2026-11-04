<?php
declare(strict_types=1);
// Fault injection around an owned real native SQLite connection only. Never a runtime adapter.
final class M119NativeStorageFaults
{
    private $native;public $transaction_opened;public $mode;public $rollbackCalls=0;
    public function __construct(M119NativeSqlite $native,string $mode)
    {
        if(!defined('M119_SYNTHETIC_NATIVE')||!M119_SYNTHETIC_NATIVE||PHP_SAPI!=='cli')throw new LogicException('Synthetic CLI only.');
        $this->native=$native;$this->transaction_opened=&$native->transaction_opened;$this->mode=$mode;
    }
    public function query($sql){return $this->native->query($sql);}
    public function escape($value){return $this->native->escape($value);}
    public function fetch_object($result){return $this->native->fetch_object($result);}
    public function begin(){ $result=$this->native->begin();return $this->mode==='uncertain_begin'?0:$result; }
    public function commit()
    {
        if(in_array($this->mode,array('false_commit','rollback_cleared_counter','rollback_stuck'),true))return 0;
        if($this->mode==='accepted_open_commit')return 1;
        $result=$this->native->commit();
        if($this->mode==='lost_commit_response')throw new RuntimeException('SYNTHETIC lost response after durable commit.');
        return $result;
    }
    public function rollback()
    {
        $this->rollbackCalls++;
        if($this->mode==='rollback_cleared_counter'){$this->transaction_opened=0;return 0;}
        // Bound the old implementation's retry loop so the RED test cannot hang.
        if($this->mode==='rollback_stuck'){
            if($this->rollbackCalls>1)throw new RuntimeException('SYNTHETIC repeated rollback.');
            return 0;
        }
        return $this->native->rollback();
    }
}
