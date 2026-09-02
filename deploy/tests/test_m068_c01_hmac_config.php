<?php
/** Functional local lifecycle/security test for the M068-C01 non-web tool. */

$root = dirname(__DIR__, 2);
$sourceTool = $root.'/deploy/tools/m068_c01_portal_hmac_config.php';
$fixture = $root.'/deploy/tests/fixtures/m068_c01_config_bootstrap.php';
$tmp = '/private/tmp/m068-c01-config-test.'.bin2hex(random_bytes(5));
if (!mkdir($tmp, 0700, true)) throw new RuntimeException('fixture_create_failed');
$tool = $tmp.'/m068_c01_portal_hmac_config.test.php';
copy($sourceTool, $tool);
copy($fixture, $tmp.'/bootstrap.php');
$state = $tmp.'/state.json';
$events = $tmp.'/events.log';
file_put_contents($state, "[]\n");
file_put_contents($events, '');

$cleanup = static function () use ($tmp) {
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $path) $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
	rmdir($tmp);
};
$assert = static function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$writeRows = static function ($rows) use ($state) { file_put_contents($state, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); };
$run = static function ($command, $extra = array()) use ($tool, $tmp, $state, $events) {
	$lockRequired = in_array($command, array('apply', 'rollback'), true) ? '1' : '0';
	$env = array_merge($_ENV, array(
		'M068_C01_CONFIG_TEST_MODE' => '1',
		'M068_C01_CONFIG_TEST_ROOT' => $tmp,
		'M068_C01_CONFIG_TEST_STATE' => $state,
		'M068_C01_CONFIG_TEST_ENTITY' => '1',
		'M068_C01_CONFIG_TEST_REQUIRE_LOCK' => $lockRequired,
		'M068_C01_FIXTURE_EVENT_LOG' => $events,
		'CHINAPLANET_PORTAL_INVITATION_HMAC_KEY' => '',
	), $extra);
	$descriptor = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
	$process = proc_open(array(PHP_BINARY, $tool, $command), $descriptor, $pipes, null, $env);
	if (!is_resource($process)) throw new RuntimeException('process_start_failed');
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
	return array(proc_close($process), $stdout, $stderr);
};
$resetEvents = static function () use ($events) { file_put_contents($events, ''); };
$readEvents = static function () use ($events) { return (string) file_get_contents($events); };

try {
	list($rc, $out, $err) = $run('check');
	$assert($rc === 2 && strpos($out, 'M068_C01_CONFIG=ABSENT') === 0 && $err === '', 'absent check failed');

	$resetEvents();
	list($rc, $out, $err) = $run('apply', array('M068_C01_FIXTURE_FAIL_BEGIN' => '1'));
	$assert($rc !== 0 && strpos($err, 'TRANSACTION_BEGIN_FAILED') !== false, 'integer-zero begin failure accepted');
	$assert($readEvents() === "LOCK_ACQUIRE=1\nBEGIN=0\nLOCK_RELEASE=1\n", 'begin failure did not release advisory lock');
	$assert(json_decode((string) file_get_contents($state), true) === array(), 'begin failure changed state');

	$resetEvents();
	list($rc, $out, $err) = $run('apply');
	$assert($rc === 0 && strpos($out, 'M068_C01_CONFIG_APPLY=PASS STATE=CREATED') === 0 && $err === '', 'apply failed');
	$assert(strpos($readEvents(), "BEGIN=1\n") !== false && strpos($readEvents(), "COMMIT=1\nLOCK_RELEASE=1\n") !== false, 'successful apply transaction/lock lifecycle invalid');
	$rows = json_decode((string) file_get_contents($state), true);
	$assert(is_array($rows) && count($rows) === 1, 'apply wrote more than the exact constant');
	$stored = (string) $rows[0]['value'];
	$secret = base64_decode(substr($stored, 14), true);
	$assert($rows[0]['name'] === 'CHINAPLANET_PORTAL_INVITATION_HMAC_KEY' && (int) $rows[0]['entity'] === 1, 'wrong constant or entity');
	$assert(strpos($stored, 'dolcrypt:test:') === 0 && is_string($secret) && strlen($secret) === 64 && base64_decode($secret, true) !== false, 'encrypted-at-rest server secret format invalid');
	$assert($rows[0]['note'] === 'M068-C01 owned server-side portal invitation HMAC secret', 'static ownership note invalid');
	$assert(strpos($out.$err, $secret) === false && strpos($out.$err, $stored) === false, 'secret leaked from apply');

	list($rc, $out, $err) = $run('verify');
	$assert($rc === 0 && strpos($out, 'PRESENCE=YES LENGTH_OK=YES') !== false && strpos($out.$err, $secret) === false && strpos($out.$err, $stored) === false, 'verify failed or leaked');
	list($rc, $out, $err) = $run('apply');
	$assert($rc !== 0 && strpos($err, 'ABSENT_PRESTATE_REQUIRED') !== false, 'existing constant overwritten');

	$goodRows = $rows;
	$rows[0]['value'] = 'dolcrypt:test:'.base64_encode(str_repeat('x', 63));
	$writeRows($rows);
	list($rc, $out, $err) = $run('rollback');
	$assert($rc !== 0 && strpos($err, 'SECRET_LENGTH_INVALID') !== false, 'wrong-length replaced value was deleted');
	$assert(json_decode((string) file_get_contents($state), true) === $rows, 'replaced value changed after refused rollback');
	$writeRows($goodRows);

	list($rc, $out, $err) = $run('apply', array('M068_C01_FIXTURE_LOCK_BUSY' => '1'));
	$assert($rc !== 0 && strpos($err, 'CONFIG_LOCKED') !== false, 'advisory lock contention accepted');
	$assert(json_decode((string) file_get_contents($state), true) === $goodRows, 'lock contention changed state');

	list($rc, $out, $err) = $run('rollback', array('M068_C01_FIXTURE_FAIL_DELETE' => '1'));
	$assert($rc !== 0 && strpos($err, 'ROLLBACK_DELETE_FAILED') !== false, 'delete failure accepted');
	$assert(count(json_decode((string) file_get_contents($state), true)) === 1 && strpos($out.$err, $secret) === false && strpos($out.$err, $stored) === false, 'delete failure did not preserve exact constant or leaked');

	list($rc, $out, $err) = $run('rollback');
	$assert($rc === 0 && strpos($out, 'STATE=REMOVED') !== false && strpos($out.$err, $secret) === false && strpos($out.$err, $stored) === false, 'rollback failed or leaked');
	$assert(json_decode((string) file_get_contents($state), true) === array(), 'rollback did not restore absence');
	list($rc, $out, $err) = $run('rollback', array('M068_C01_FIXTURE_FAIL_UNLOCK' => '1'));
	$assert($rc !== 0 && strpos($err, 'CONFIG_LOCK_RELEASE_FAILED') !== false, 'absent rollback ignored advisory unlock failure');
	$assert(json_decode((string) file_get_contents($state), true) === array(), 'absent rollback unlock failure changed state');
	list($rc, $out, $err) = $run('rollback');
	$assert($rc === 0 && strpos($out, 'STATE=UNCHANGED') !== false && $err === '', 'absent rollback did not release advisory lock');

	list($rc, $out, $err) = $run('check', array('CHINAPLANET_PORTAL_INVITATION_HMAC_KEY' => 'do-not-read-or-print-this-value'));
	$assert($rc !== 0 && strpos($err, 'ENV_CONFIGURATION_CONFLICT') !== false && strpos($out.$err, 'do-not-read-or-print-this-value') === false, 'environment conflict accepted or leaked');

	$unowned = array(array('rowid' => 1, 'entity' => 1, 'name' => 'CHINAPLANET_PORTAL_INVITATION_HMAC_KEY', 'value' => str_repeat('u', 64), 'type' => 'chaine', 'visible' => 0, 'note' => 'unknown'));
	$writeRows($unowned);
	list($rc, $out, $err) = $run('apply');
	$assert($rc !== 0 && strpos($err, 'UNOWNED_OR_INVALID_CONFIGURATION') !== false, 'unowned constant overwritten');
	$assert(json_decode((string) file_get_contents($state), true) === $unowned, 'unowned constant changed');

	$global = array(array('rowid' => 1, 'entity' => 0, 'name' => 'CHINAPLANET_PORTAL_INVITATION_HMAC_KEY', 'value' => str_repeat('g', 64), 'type' => 'chaine', 'visible' => 0, 'note' => 'M068-C01 global conflict'));
	$writeRows($global);
	list($rc, $out, $err) = $run('apply');
	$assert($rc !== 0 && strpos($err, 'GLOBAL_ENTITY_CONFIGURATION') !== false, 'global constant accepted');

	$foreign = array(array('rowid' => 1, 'entity' => 2, 'name' => 'CHINAPLANET_PORTAL_INVITATION_HMAC_KEY', 'value' => 'dolcrypt:test:'.base64_encode(str_repeat('f', 64)), 'type' => 'chaine', 'visible' => 0, 'note' => 'M068-C01 foreign conflict'));
	$writeRows($foreign);
	list($rc, $out, $err) = $run('apply');
	$assert($rc !== 0 && strpos($err, 'FOREIGN_ENTITY_CONFIGURATION') !== false && count(json_decode((string) file_get_contents($state), true)) === 1, 'foreign entity constant accepted or changed');

	$duplicate = array($global[0], array('rowid' => 2, 'entity' => 1, 'name' => 'CHINAPLANET_PORTAL_INVITATION_HMAC_KEY', 'value' => str_repeat('d', 64), 'type' => 'chaine', 'visible' => 0, 'note' => 'M068-C01 duplicate conflict'));
	$writeRows($duplicate);
	list($rc, $out, $err) = $run('check');
	$assert($rc !== 0 && strpos($err, 'CONFIGURATION_AMBIGUOUS') !== false, 'duplicate constants accepted');

	$writeRows(array());
	$resetEvents();
	list($rc, $out, $err) = $run('rollback', array('M068_C01_FIXTURE_FAIL_ROLLBACK' => '1'));
	$assert($rc !== 0 && strpos($err, 'TRANSACTION_ROLLBACK_CLEANUP_FAILED') !== false, 'integer-zero rollback failure accepted');
	$assert($readEvents() === "LOCK_ACQUIRE=1\nBEGIN=1\nROLLBACK=0\nROLLBACK=0\nLOCK_RELEASE=1\n", 'rollback failure cleanup/lock release invalid');
	$assert(json_decode((string) file_get_contents($state), true) === array(), 'rollback failure changed state');

	list($rc, $out, $err) = $run('apply', array('M068_C01_CONFIG_TEST_FAULT' => 'AFTER_SECRET'));
	$assert($rc !== 0 && strpos($err, 'INJECTED_AFTER_SECRET') !== false, 'injected write failure accepted');
	$assert(json_decode((string) file_get_contents($state), true) === array(), 'transaction failure left a constant');

	list($rc, $out, $err) = $run('apply', array('M068_C01_FIXTURE_FAIL_SET' => '1'));
	$assert($rc !== 0 && strpos($err, 'SECRET_CREATE_FAILED') !== false, 'set failure accepted');
	$assert(json_decode((string) file_get_contents($state), true) === array(), 'set failure changed state');
	$resetEvents();
	list($rc, $out, $err) = $run('apply', array('M068_C01_FIXTURE_FAIL_COMMIT' => '1'));
	$assert($rc !== 0 && strpos($err, 'TRANSACTION_COMMIT_FAILED') !== false, 'commit failure accepted');
	$assert(json_decode((string) file_get_contents($state), true) === array(), 'commit failure left a constant');
	$assert($readEvents() === "LOCK_ACQUIRE=1\nBEGIN=1\nCOMMIT=0\nROLLBACK=1\nLOCK_RELEASE=1\n", 'integer-zero commit failure cleanup/lock release invalid');

	list($rc, $out, $err) = $run('status');
	$assert($rc !== 0 && strpos($err, 'COMMAND_NOT_ALLOWED') !== false, 'unapproved direct command accepted');

	echo "M068_C01_CONFIG_TEST=PASS EXACT_CONSTANT_COUNT=1 ALL_ENTITY_ABSENCE=YES ENTITY_SCOPED=YES ADVISORY_LOCK=PASS DOLIDB_INTEGER_TRANSACTIONS=PASS GENERATION=SERVER_SIDE ENCRYPTED_AT_REST=YES OUTPUT=NON_SECRET VALUE_DIGEST=NONE VERIFY=PRESENCE_LENGTH_ONLY ROLLBACK=OWNERSHIP_BOUND_EXACT ABSENCE_CONFLICTS=DENIED FAILURE_CLEANUP=PASS CUSTOMER_DATA_OUTPUT=NO SERVER_ACCESSED=NO PRODUCTION_ACCESSED=NO\n";
} finally {
	$cleanup();
}
