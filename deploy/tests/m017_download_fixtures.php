<?php
/**
 * M017-C03 additive synthetic download fixtures.
 *
 * Usage:
 *   M017_DOL_DOCUMENT_ROOT=/path/to/htdocs php deploy/tests/m017_download_fixtures.php inspect
 *   M017_DOL_DOCUMENT_ROOT=/path/to/htdocs php deploy/tests/m017_download_fixtures.php apply
 *   M017_DOL_DOCUMENT_ROOT=/path/to/htdocs php deploy/tests/m017_download_fixtures.php cleanup
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "M017_DOWNLOAD_FIXTURES=FAIL REASON=CLI_ONLY\n"); exit(1); }
if ($argc !== 2 || !in_array($argv[1], array('inspect', 'apply', 'cleanup'), true)) { fwrite(STDERR, "M017_DOWNLOAD_FIXTURES=FAIL REASON=COMMAND_NOT_ALLOWED\n"); exit(1); }

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);
$documentRoot = getenv('M017_DOL_DOCUMENT_ROOT');
if (!is_string($documentRoot) || $documentRoot === '') $documentRoot = '/var/www/html';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M017_DOWNLOAD_FIXTURES=FAIL REASON=DOLIBARR_ROOT_INVALID\n"); exit(1); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
require $documentRoot.'/master.inc.php';
global $db;

$fixture = require __DIR__.'/fixtures/m017_synthetic_fixture.php';
$customerRef = (string) $fixture['marker'].'_E1_A';
$cardRef = $customerRef.'_PJ1_CARD';

function m017DownloadFail($reason, $created = array())
{
	global $db;
	if (is_object($db)) $db->rollback();
	foreach ($created as $path) if (is_file($path)) @unlink($path);
	fwrite(STDERR, 'M017_DOWNLOAD_FIXTURES=FAIL REASON='.$reason."\n");
	exit(1);
}

function m017DownloadEsc($value)
{
	global $db;
	return "'".$db->escape((string) $value)."'";
}

function m017DownloadQuery($sql, $reason, $created = array())
{
	global $db;
	$result = $db->query($sql);
	if (!$result) m017DownloadFail($reason, $created);
	return $result;
}

function m017DownloadPngChunk($type, $data)
{
	return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
}

function m017DownloadPng()
{
	$width = 96; $height = 96; $row = "\x00".str_repeat("\xD9\x14\x36", $width); $raw = str_repeat($row, $height);
	return "\x89PNG\r\n\x1a\n".
		m017DownloadPngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).
		m017DownloadPngChunk('IDAT', gzcompress($raw, 9)).
		m017DownloadPngChunk('IEND', '');
}

function m017DownloadPdf()
{
	$stream = "BT\n/F1 18 Tf\n72 720 Td\n(M017 Synthetic Download Test) Tj\n0 -28 Td\n/F1 11 Tf\n(No customer data - local testing only) Tj\nET\n";
	$objects = array(
		'<< /Type /Catalog /Pages 2 0 R >>',
		'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
		'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
		'<< /Length '.strlen($stream).' >>'."\nstream\n".$stream."endstream",
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
	);
	$pdf = "%PDF-1.4\n%M017\n"; $offsets = array();
	foreach ($objects as $index => $object) { $number = $index + 1; $offsets[$number] = strlen($pdf); $pdf .= $number." 0 obj\n".$object."\nendobj\n"; }
	$xref = strlen($pdf); $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
	foreach ($offsets as $offset) $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
	$pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
	return $pdf;
}

function m017DownloadSpecifications()
{
	return array(
		array('original' => 'M017 Download Test.pdf', 'stored' => 'm017-c03-download-test.pdf', 'mime' => 'application/pdf', 'content' => m017DownloadPdf(), 'type' => 'final'),
		array('original' => 'M017 Download Test Image.png', 'stored' => 'm017-c03-download-test.png', 'mime' => 'image/png', 'content' => m017DownloadPng(), 'type' => 'final'),
		array('original' => 'M017 Download Test Data.csv', 'stored' => 'm017-c03-download-test.csv', 'mime' => 'text/csv', 'content' => "reference,status,amount,currency\nM017-C03,synthetic,100,USD\n", 'type' => 'final'),
	);
}

function m017DownloadCard($cardRef, $required = true)
{
	global $db;
	$sql = 'SELECT c.rowid,c.entity,c.fk_soc,c.fk_user_creat,s.ref_ext FROM '.MAIN_DB_PREFIX.'cpkanban_card c INNER JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=c.fk_soc AND s.entity=c.entity WHERE c.ref='.m017DownloadEsc($cardRef).' AND c.status=1 AND c.cust_visible=1 LIMIT 1';
	$result = $db->query($sql); $row = $result ? $db->fetch_object($result) : null;
	if (!$row && $required) m017DownloadFail('SYNTHETIC_CARD_NOT_FOUND');
	return $row;
}

function m017DownloadInspect($cardRef)
{
	global $db;
	$card = m017DownloadCard($cardRef, false); $rows = 0; $files = 0; $valid = 0;
	if ($card) foreach (m017DownloadSpecifications() as $spec) {
		$result = $db->query('SELECT rowid,stored_name,mime_type,file_size,review_status,customer_visible,status FROM '.MAIN_DB_PREFIX.'cpkanban_asset WHERE entity='.(int) $card->entity.' AND fk_soc='.(int) $card->fk_soc.' AND fk_card='.(int) $card->rowid.' AND original_name='.m017DownloadEsc($spec['original']).' LIMIT 1');
		$row = $result ? $db->fetch_object($result) : null; if (!$row) continue; $rows++;
		$path = DOL_DATA_ROOT.'/cpkanban/card-'.(int) $card->rowid.'/'.(string) $row->stored_name; if (is_file($path)) $files++;
		if (is_file($path) && is_readable($path) && filesize($path) === (int) $row->file_size && hash_file('sha256', $path) === hash('sha256', $spec['content']) && (string) $row->mime_type === $spec['mime'] && in_array((string) $row->review_status, array('approved', 'final_received'), true) && (int) $row->customer_visible === 1 && (int) $row->status === 1) $valid++;
	}
	echo 'M017_DOWNLOAD_FIXTURES_INSPECT=PASS ROWS='.$rows.' FILES='.$files.' VALID='.$valid."\n";
	return array($rows, $files, $valid);
}

if ($argv[1] === 'inspect') { m017DownloadInspect($cardRef); exit(0); }

$card = m017DownloadCard($cardRef);
if ((string) $card->ref_ext !== $customerRef || (int) $card->entity !== 1) m017DownloadFail('SYNTHETIC_OWNERSHIP_MISMATCH');
$dir = DOL_DATA_ROOT.'/cpkanban/card-'.(int) $card->rowid;
if (!is_dir($dir) && !mkdir($dir, 0770, true)) m017DownloadFail('CREATE_DIRECTORY');

if ($argv[1] === 'cleanup') {
	$db->begin(); $paths = array();
	foreach (m017DownloadSpecifications() as $spec) {
		$result = m017DownloadQuery('SELECT rowid,stored_name FROM '.MAIN_DB_PREFIX.'cpkanban_asset WHERE entity='.(int) $card->entity.' AND fk_soc='.(int) $card->fk_soc.' AND fk_card='.(int) $card->rowid.' AND original_name='.m017DownloadEsc($spec['original']).' LIMIT 1', 'CLEANUP_LOOKUP');
		$row = $db->fetch_object($result); if (!$row) continue;
		m017DownloadQuery('DELETE FROM '.MAIN_DB_PREFIX.'cpkanban_asset WHERE rowid='.(int) $row->rowid.' AND entity='.(int) $card->entity.' AND fk_soc='.(int) $card->fk_soc.' AND fk_card='.(int) $card->rowid, 'CLEANUP_ROW');
		$paths[] = $dir.'/'.basename((string) $row->stored_name);
	}
	$db->commit(); foreach ($paths as $path) if (is_file($path)) @unlink($path);
	m017DownloadInspect($cardRef); echo "M017_DOWNLOAD_FIXTURES_CLEANUP=PASS\n"; exit(0);
}

$db->begin(); $created = array();
foreach (m017DownloadSpecifications() as $spec) {
	$result = m017DownloadQuery('SELECT rowid,stored_name,file_size,mime_type FROM '.MAIN_DB_PREFIX.'cpkanban_asset WHERE entity='.(int) $card->entity.' AND fk_soc='.(int) $card->fk_soc.' AND fk_card='.(int) $card->rowid.' AND original_name='.m017DownloadEsc($spec['original']).' LIMIT 1', 'LOOKUP_EXISTING', $created);
	$row = $db->fetch_object($result);
	if ($row) continue;
	$path = $dir.'/'.$spec['stored']; if (file_exists($path)) m017DownloadFail('FILE_COLLISION', $created);
	if (file_put_contents($path, $spec['content'], LOCK_EX) !== strlen($spec['content'])) m017DownloadFail('WRITE_FILE', array_merge($created, array($path)));
	@chmod($path, 0640); $created[] = $path;
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'cpkanban_asset(entity,fk_soc,fk_card,asset_type,document_type,original_name,stored_name,mime_type,file_size,caption,version_no,review_status,customer_visible,status,fk_user_creat,date_creation) VALUES('.(int) $card->entity.','.(int) $card->fk_soc.','.(int) $card->rowid.",'document',".m017DownloadEsc($spec['type']).','.m017DownloadEsc($spec['original']).','.m017DownloadEsc($spec['stored']).','.m017DownloadEsc($spec['mime']).','.strlen($spec['content']).','.m017DownloadEsc('M017-C03 synthetic download fixture').",1,'approved',1,1,".(int) $card->fk_user_creat.','.m017DownloadEsc(gmdate('Y-m-d H:i:s')).')';
	m017DownloadQuery($sql, 'CREATE_ASSET', $created);
}
$db->commit();
$result = m017DownloadInspect($cardRef);
if ($result !== array(3, 3, 3)) m017DownloadFail('POST_APPLY_VALIDATION');
echo "M017_DOWNLOAD_FIXTURES_APPLY=PASS\n";
