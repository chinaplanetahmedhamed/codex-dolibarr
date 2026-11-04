<?php
/** Actual SQLite snapshot cleanup failure; explicit PDO metadata-restore fault seam. */
$keep=$argv;$argv=[__FILE__];ob_start();try{require __DIR__.'/test_m121_position_snapshot.php';}finally{ob_end_clean();$argv=$keep;}
class M121RestoreFaultPdo extends PDO{
 public $failRestore=false;
 public function exec(string $statement):int|false{if($this->failRestore&&$statement==='PRAGMA query_only=0')throw new RuntimeException('Synthetic read-only metadata restore failure');return parent::exec($statement);}
}
list($reader,$writer,$service)=ps_fixture();
$file=$reader->pdo->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file'];
$fault=new M121RestoreFaultPdo('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$reader->pdo=null;
$adapter=new M121SnapshotDb($fault,true);$GLOBALS['psConnections'][]=$adapter;$fault->failRestore=true;$service=new CustomerFundsService($adapter,1);
$result=$service->currentPosition(7);ps_unknown($result,'Metadata restore failure');
ps_assert(($result['connection_retired']??false)===true,'Retired snapshot must emit explicit stop signal');
ps_assert($adapter->pdo===null&&!$fault->inTransaction(),'Uncertain adapter quarantined after owned transaction ends');
ps_assert((int)$fault->query('PRAGMA query_only')->fetchColumn()===1,'Fault leaves physical connection read-only, never silently reused');
ps_assert(($service->currentPosition(7)['connection_retired']??false)===true,'Same retired service retains stop signal');
ps_assert(count($writer->rows('paiement'))===1&&count($writer->rows('paiementfourn'))===1,'Retirement does not recreate native cash');
$fault=null;
echo "PASS actual snapshot retirement signal, owned transaction cleanup, retained read-only flag and no cash recreation\n";
