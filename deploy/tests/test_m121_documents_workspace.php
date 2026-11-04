<?php
// Pure local checks: no Dolibarr bootstrap, database, network or real records.
$file = __DIR__.'/../../custom/chinaplanetdocuments/class/DocumentsWorkspace.class.php';
if (!is_file($file)) { fwrite(STDERR, "FAIL: Documents workspace service missing\n"); exit(1); }
require_once $file;
$checks = 0;
function check($ok, $label) { global $checks; ++$checks; if (!$ok) { throw new RuntimeException($label); } }
function denied($callback, $label) { try { $callback(); } catch (InvalidArgumentException $e) { check(true, $label); return; } throw new RuntimeException($label); }
class DocumentsTestUser {
    public $id = 1;
    public $socid = 0;
    public $admin = 0;
    public $statut = 1;
    public $read = true;
    public function hasRight($module, $right) { return $module === 'chinaplanetdocuments' && $right === 'workspace_read' && $this->read; }
}
$u = new DocumentsTestUser();
check(DocumentsWorkspace::canRead($u), 'staff read');
$u->read = false; check(!DocumentsWorkspace::canRead($u), 'no permission');
$u->admin = 1; check(DocumentsWorkspace::canRead($u), 'internal admin');
$u->socid = 42; check(!DocumentsWorkspace::canRead($u), 'external admin denied');
$u->socid = 0; $u->id = 0; check(!DocumentsWorkspace::canRead($u), 'anonymous admin denied');
$u->id = 1; $u->statut = 0; check(!DocumentsWorkspace::canRead($u), 'inactive denied');
foreach (array('', 'javascript:alert(1)', '//evil.test', 'http://evil.test', 'https://a.test/?token=secret', 'https://a.test/#x', 'https://user:pass@a.test', "https://a.test/\n") as $url) {
    check(DocumentsWorkspace::editorUrl($url) === '', 'reject unsafe URL');
}
check(DocumentsWorkspace::editorUrl('https://docstest.chinaplanet.org/') === 'https://docstest.chinaplanet.org/', 'configured HTTPS');
check(DocumentsWorkspace::editorUrl('http://127.0.0.1:18777/') === 'http://127.0.0.1:18777/', 'local loopback');
check(DocumentsWorkspace::editorUrl('http://127.0.0.1.evil.test/') === '', 'loopback suffix denied');
$c = (object) array('id'=>7, 'entity'=>1, 'name'=>'Synthetic customer <script>', 'ref'=>'C7');
$p = (object) array('id'=>9, 'entity'=>1, 'socid'=>7, 'title'=>'Synthetic project', 'ref'=>'P9');
check(DocumentsWorkspace::context(1, $c, $p)['project']->id === 9, 'matching context');
check(DocumentsWorkspace::context(1, null, null)['customer'] === null, 'unscoped workspace');
denied(function () use ($c, $p) { DocumentsWorkspace::context(2, $c, $p); }, 'foreign entity');
denied(function () use ($p) { DocumentsWorkspace::context(1, null, $p); }, 'project without customer');
$wrong = clone $p; $wrong->socid = 88;
denied(function () use ($c, $wrong) { DocumentsWorkspace::context(1, $c, $wrong); }, 'wrong customer');
$wrong = clone $p; $wrong->entity = 2;
denied(function () use ($c, $wrong) { DocumentsWorkspace::context(1, $c, $wrong); }, 'foreign project');
check(DocumentsWorkspace::templates() === array('CommercialInvoice', 'ProformaInvoice', 'PackingList', 'BillOfLading', 'CertificateOfOrigin', 'ShippingMarks', 'Quotation', 'BookingForm'), 'exact existing template catalog');
require_once __DIR__.'/../../custom/chinaplanetdocuments/lib/workspace.lib.php';
$translate = function ($key) { return $key; };
$html = cpd_documents_workspace_html(DocumentsWorkspace::context(1, $c, $p), '', $translate, '/erp');
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'context escaping');
check(strpos($html, 'target="_blank"') === false, 'unconfigured launch disabled');
check(strpos($html, '/erp/projet/card.php?id=9') !== false, 'native project return');
$html = cpd_documents_workspace_html(DocumentsWorkspace::context(1, $c, $p), 'https://docstest.chinaplanet.org/', $translate, '');
check(strpos($html, 'rel="noopener noreferrer"') !== false, 'safe launch');
check(strpos($html, 'href="https://docstest.chinaplanet.org/"') !== false, 'editor preserved without context parameters');
echo "PASS: $checks Documents workspace checks\n";
