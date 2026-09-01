<?php

$documentRoot = getenv('M061_DOL_DOCUMENT_ROOT');
if (!is_string($documentRoot) || $documentRoot === '') {
    fwrite(STDERR, "M061_RUNTIME=SKIP set M061_DOL_DOCUMENT_ROOT\n");
    exit(2);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
define('NOCSRFCHECK', 1);
require rtrim($documentRoot, '/').'/master.inc.php';
require_once dirname(__DIR__, 2).'/custom/chinaplanetcustomercenter/lib/financial.lib.php';

$failures = array();
function m061RuntimeAssert($condition, $message)
{
    global $failures;
    if ($condition) print 'PASS '.$message."\n";
    else $failures[] = $message;
}
function m061RuntimeScalar($db, $sql)
{
    $result = $db->query($sql);
    if (!$result) return null;
    $row = $db->fetch_row($result);
    return $row ? $row[0] : null;
}

$entity = (int) $conf->entity;
m061RuntimeAssert((int) m061RuntimeScalar($db, "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".MAIN_DB_PREFIX."cpcc_customer_document'") === 1, 'customer document publication table exists');
m061RuntimeAssert((int) m061RuntimeScalar($db, "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='".MAIN_DB_PREFIX."paiement_extrafields' AND column_name='cpcc_public_note'") === 1, 'payment public-note column exists on paiement extrafields');
m061RuntimeAssert((int) m061RuntimeScalar($db, "SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."extrafields WHERE entity=".$entity." AND elementtype='paiement' AND name='cpcc_public_note'") === 1, 'payment public-note definition uses the exact Dolibarr table element');
m061RuntimeAssert((int) m061RuntimeScalar($db, "SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".$entity." AND id=500205 AND module='chinaplanetcustomercenter' AND perms='approve' AND bydefault=0") === 1, 'local M040 approval permission remains intact');
m061RuntimeAssert((int) m061RuntimeScalar($db, "SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."rights_def WHERE entity=".$entity." AND id=500206 AND module='chinaplanetcustomercenter' AND perms='email_write' AND bydefault=0") === 1, 'M061 email permission is separate and disabled by default');

$fixtureSql = "SELECT p.rowid payment_id,f.fk_soc FROM ".MAIN_DB_PREFIX."paiement p";
$fixtureSql .= " INNER JOIN ".MAIN_DB_PREFIX."paiement_facture pf ON pf.fk_paiement=p.rowid";
$fixtureSql .= " INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid=pf.fk_facture AND f.entity=p.entity";
$fixtureSql .= " WHERE p.entity=".$entity." AND f.fk_statut>0 ORDER BY p.rowid LIMIT 1";
$fixtureResult = $db->query($fixtureSql);
$fixture = $fixtureResult ? $db->fetch_object($fixtureResult) : null;
m061RuntimeAssert($fixture !== null, 'a local paid-invoice fixture exists');

if ($fixture) {
    $paymentId = (int) $fixture->payment_id;
    $socid = (int) $fixture->fk_soc;
    $beforePayment = $db->query("SELECT note FROM ".MAIN_DB_PREFIX."paiement WHERE rowid=".$paymentId);
    $beforePaymentRow = $beforePayment ? $db->fetch_object($beforePayment) : null;
    $beforeExtra = $db->query("SELECT cpcc_public_note FROM ".MAIN_DB_PREFIX."paiement_extrafields WHERE fk_object=".$paymentId);
    $beforeExtraRow = $beforeExtra ? $db->fetch_object($beforeExtra) : null;
    $beforeExtraExists = $beforeExtraRow !== null;
    $beforePrivate = $beforePaymentRow ? $beforePaymentRow->note : null;
    $beforePublic = $beforeExtraRow ? $beforeExtraRow->cpcc_public_note : null;

    $publicMarker = 'M061_PUBLIC_NOTE_RUNTIME_ONLY';
    $privateMarker = 'M061_PRIVATE_NOTE_MUST_NOT_SURFACE';
    $db->begin();
    $privateUpdated = $db->query("UPDATE ".MAIN_DB_PREFIX."paiement SET note='".$db->escape($privateMarker)."' WHERE rowid=".$paymentId);
    $publicUpdated = $db->query("INSERT INTO ".MAIN_DB_PREFIX."paiement_extrafields (fk_object,cpcc_public_note) VALUES (".$paymentId.",'".$db->escape($publicMarker)."') ON DUPLICATE KEY UPDATE cpcc_public_note=VALUES(cpcc_public_note)");
    m061RuntimeAssert($privateUpdated && $publicUpdated, 'rollback-only payment note fixture is prepared');

    $ownedRows = cpcc_financial_native_receipts($db, $socid, 'USD');
    $ownedJson = json_encode($ownedRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    m061RuntimeAssert(strpos((string) $ownedJson, $publicMarker) !== false, 'explicit public payment note reaches the owned customer projection');
    m061RuntimeAssert(strpos((string) $ownedJson, $privateMarker) === false, 'private payment note never reaches the customer projection');

    $foreignSocid = (int) m061RuntimeScalar($db, "SELECT fk_soc FROM ".MAIN_DB_PREFIX."facture WHERE entity=".$entity." AND fk_soc<>".$socid." AND fk_statut>0 ORDER BY rowid LIMIT 1");
    if ($foreignSocid > 0) {
        $foreignJson = json_encode(cpcc_financial_native_receipts($db, $foreignSocid, 'USD'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        m061RuntimeAssert(strpos((string) $foreignJson, $publicMarker) === false, 'public payment note is absent from a foreign customer projection');
    }
    $db->rollback();

    $afterPayment = $db->query("SELECT note FROM ".MAIN_DB_PREFIX."paiement WHERE rowid=".$paymentId);
    $afterPaymentRow = $afterPayment ? $db->fetch_object($afterPayment) : null;
    $afterExtra = $db->query("SELECT cpcc_public_note FROM ".MAIN_DB_PREFIX."paiement_extrafields WHERE fk_object=".$paymentId);
    $afterExtraRow = $afterExtra ? $db->fetch_object($afterExtra) : null;
    m061RuntimeAssert(($afterPaymentRow ? $afterPaymentRow->note : null) === $beforePrivate, 'private payment note is restored after rollback');
    m061RuntimeAssert(($afterExtraRow !== null) === $beforeExtraExists && ($afterExtraRow ? $afterExtraRow->cpcc_public_note : null) === $beforePublic, 'public payment note is restored after rollback');
}

$db->close();
if ($failures) {
    fwrite(STDERR, "M061_RUNTIME=FAIL\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}
echo "M061_RUNTIME=PASS DATABASE_ISOLATION=PASS ROLLBACK=PASS\n";
