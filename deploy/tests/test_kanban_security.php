<?php
// Actual domain method with ephemeral SQLite fixtures, no network or ERP records.
class CommonObject { public $error; }
define('MAIN_DB_PREFIX', 'llx_');
function dol_now() { return 1700000000; }
$conf = (object) ['entity' => 1];
class SecurityFixtureDb {
    public $pdo;
    public $failLog = false;
    public $changeProgress = false;
    public function __construct() { $this->pdo = new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
    public function query($sql) {
        if ($this->failLog && strpos($sql, 'INSERT INTO llx_cpkanban_log') === 0) return false;
        if ($this->changeProgress && strpos($sql, 'UPDATE llx_cpkanban_card') === 0) {
            $this->pdo->exec('UPDATE llx_cpkanban_card SET progress=42 WHERE rowid=100');
        }
        return $this->pdo->query($sql);
    }
    public function fetch_object($r) { return $r->fetchObject(); }
    public function affected_rows($r) { return $r->rowCount(); }
    public function begin() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->rollBack(); }
    public function escape($v) { return str_replace("'", "''", $v); }
    public function idate($v) { return gmdate('Y-m-d H:i:s', $v); }
    public function lasterror() { return 'Synthetic failure'; }
}
require dirname(__DIR__, 2).'/custom/cpkanban/class/cpkanbancard.class.php';
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
$db = new SecurityFixtureDb();
$db->query('CREATE TABLE llx_cpkanban_column(rowid INTEGER PRIMARY KEY,entity INTEGER,active INTEGER,auto_progress INTEGER)');
$db->query('CREATE TABLE llx_cpkanban_card(rowid INTEGER PRIMARY KEY,entity INTEGER,fk_column INTEGER,progress INTEGER)');
$db->query('CREATE TABLE llx_cpkanban_log(entity INTEGER,fk_card INTEGER,fk_column_old INTEGER,fk_column_new INTEGER,action_type TEXT,note TEXT,fk_user INTEGER,visible_cust INTEGER,date_action TEXT)');
$db->query('INSERT INTO llx_cpkanban_column VALUES(10,1,1,0),(11,1,1,50),(20,2,1,90),(12,1,0,70),(13,1,1,NULL)');
$user = (object) ['id' => 1];
$cases = [
 'valid' => [11,1,1,10,true,50], 'foreign destination' => [20,1,1,10,false,0],
 'inactive destination' => [12,1,1,10,false,0], 'missing destination' => [999999,1,1,10,false,0],
 'foreign card object' => [11,2,1,10,false,0], 'foreign persisted card' => [11,1,2,10,false,0],
 'unchanged active column' => [10,1,1,10,true,0], 'unchanged inactive column' => [12,1,1,12,false,0],
 'unchanged foreign column' => [20,1,1,20,false,0], 'log failure rolls back' => [11,1,1,10,false,0],
 'manual progress preserved' => [13,1,1,10,true,42]
];
foreach ($cases as $name => [$dest,$objectEntity,$dbEntity,$old,$allowed,$progress]) {
 $db->failLog = $name === 'log failure rolls back'; $db->changeProgress = $name === 'manual progress preserved';
 $db->query('DELETE FROM llx_cpkanban_card'); $db->query('DELETE FROM llx_cpkanban_log');
 $db->query("INSERT INTO llx_cpkanban_card VALUES(100,$dbEntity,$old,0)");
 $card = new CpKanbanCard($db); $card->id=100; $card->entity=$objectEntity; $card->fk_column=$old; $card->progress=0;
 $result = $card->moveToColumn($user, $dest);
 $row = $db->query('SELECT * FROM llx_cpkanban_card')->fetch(PDO::FETCH_ASSOC);
 $logs = (int) $db->query('SELECT COUNT(*) FROM llx_cpkanban_log')->fetchColumn();
 check(($result > 0) === $allowed, "$name result");
 check((int)$row['fk_column'] === ($allowed ? $dest : $old) && (int)$row['progress'] === $progress, "$name persistent state");
 check($logs === ($allowed && $dest !== $old ? 1 : 0), "$name audit state");
 check((int)$card->progress === $progress, "$name response progress");
}
