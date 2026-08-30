<?php
/** Synthetic Dolibarr fixture for M068-C01 configuration-tool tests only. */

define('MAIN_DB_PREFIX', 'llx_');

class M068C01FixtureResult
{
	public $rows;
	public $index = 0;
	public function __construct($rows) { $this->rows = array_values($rows); }
}

class M068C01FixtureDb
{
	public $rows = array();
	private $snapshot = null;
	private $stateFile;

	public function __construct($stateFile)
	{
		$this->stateFile = $stateFile;
		if (is_file($stateFile)) {
			$decoded = json_decode((string) file_get_contents($stateFile), true);
			if (is_array($decoded)) $this->rows = $decoded;
		}
	}

	private function event($value)
	{
		$path = (string) getenv('M068_C01_FIXTURE_EVENT_LOG');
		if ($path !== '' && dirname($path) === dirname($this->stateFile)) file_put_contents($path, $value."\n", FILE_APPEND);
	}

	public function escape($value) { return addslashes((string) $value); }
	public function query($sql)
	{
		if (strpos($sql, 'SELECT GET_LOCK(') === 0) {
			$value = getenv('M068_C01_FIXTURE_LOCK_BUSY') === '1' ? 0 : 1;
			$this->event('LOCK_ACQUIRE='.$value);
			return new M068C01FixtureResult(array((object) array('lock_acquired' => $value)));
		}
		if (strpos($sql, 'SELECT RELEASE_LOCK(') === 0) {
			$value = getenv('M068_C01_FIXTURE_FAIL_UNLOCK') === '1' ? 0 : 1;
			$this->event('LOCK_RELEASE='.$value);
			return new M068C01FixtureResult(array((object) array('lock_released' => $value)));
		}
		if (getenv('M068_C01_CONFIG_TEST_REQUIRE_LOCK') === '1' && strpos($sql, ' FOR UPDATE') === false) return false;
		if (!preg_match("/WHERE name='((?:\\\\.|[^'])+)'/", $sql, $match)) return false;
		$name = stripslashes($match[1]);
		$found = array();
		foreach ($this->rows as $row) {
			if ((string) $row['name'] === $name) $found[] = (object) $row;
		}
		usort($found, static function ($left, $right) {
			if ((int) $left->entity === (int) $right->entity) return (int) $left->rowid <=> (int) $right->rowid;
			return (int) $right->entity <=> (int) $left->entity;
		});
		return new M068C01FixtureResult($found);
	}

	public function fetch_object($result) { return $result->index < count($result->rows) ? $result->rows[$result->index++] : false; }
	public function begin()
	{
		if (getenv('M068_C01_FIXTURE_FAIL_BEGIN') === '1') { $this->event('BEGIN=0'); return 0; }
		$this->snapshot = $this->rows;
		$this->event('BEGIN=1');
		return 1;
	}
	public function commit()
	{
		if (getenv('M068_C01_FIXTURE_FAIL_COMMIT') === '1') { $this->event('COMMIT=0'); return 0; }
		$this->snapshot = null;
		$result = $this->save();
		$this->event('COMMIT='.$result);
		return $result;
	}
	public function rollback()
	{
		if (getenv('M068_C01_FIXTURE_FAIL_ROLLBACK') === '1') { $this->event('ROLLBACK=0'); return 0; }
		if ($this->snapshot !== null) $this->rows = $this->snapshot;
		$this->snapshot = null;
		$result = $this->save();
		$this->event('ROLLBACK='.$result);
		return $result;
	}
	public function save() { return file_put_contents($this->stateFile, json_encode(array_values($this->rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false ? 1 : 0; }
}

function dolEncrypt($value)
{
	if (strncmp((string) $value, 'dolcrypt:', 9) === 0) return (string) $value;
	return 'dolcrypt:test:'.base64_encode((string) $value);
}

function dolDecrypt($value)
{
	if (strncmp((string) $value, 'dolcrypt:test:', 14) !== 0) return (string) $value;
	$decoded = base64_decode(substr((string) $value, 14), true);
	return is_string($decoded) ? $decoded : (string) $value;
}

function dolibarr_set_const($db, $name, $value, $type, $visible, $note, $entity)
{
	if (getenv('M068_C01_FIXTURE_FAIL_SET') === '1') return -1;
	if (substr((string) $name, -4) === '_KEY') $value = dolEncrypt($value);
	foreach ($db->rows as &$row) {
		if ((string) $row['name'] === (string) $name && (int) $row['entity'] === (int) $entity) return -1;
	}
	unset($row);
	$next = 1;
	foreach ($db->rows as $row) $next = max($next, (int) $row['rowid'] + 1);
	$db->rows[] = array('rowid' => $next, 'entity' => (int) $entity, 'name' => (string) $name, 'value' => (string) $value, 'type' => (string) $type, 'visible' => (int) $visible, 'note' => (string) $note);
	return 1;
}

function dolibarr_del_const($db, $name, $entity)
{
	if (getenv('M068_C01_FIXTURE_FAIL_DELETE') === '1') return -1;
	$before = count($db->rows);
	$db->rows = array_values(array_filter($db->rows, static function ($row) use ($name, $entity) {
		return !((string) $row['name'] === (string) $name && (int) $row['entity'] === (int) $entity);
	}));
	return count($db->rows) < $before ? 1 : 0;
}

$stateFile = (string) getenv('M068_C01_CONFIG_TEST_STATE');
if ($stateFile === '') throw new RuntimeException('fixture_state_file_absent');
$db = new M068C01FixtureDb($stateFile);
$conf = (object) array('entity' => (int) (getenv('M068_C01_CONFIG_TEST_ENTITY') ?: 1));
$GLOBALS['db'] = $db;
$GLOBALS['conf'] = $conf;
