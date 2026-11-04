#!/usr/bin/env python3
"""Exercise actual entry PHP with synthetic bootstrap/objects, no ERP/DB/network.

These are route contract tests, not authenticated native-runtime acceptance.
"""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
MODULE = ROOT / 'custom/chinaplanetdocuments'
BOOTSTRAP = r'''<?php
define('DOL_DOCUMENT_ROOT', __DIR__);
define('DOL_URL_ROOT', '/erp');
$case = getenv('CPD_TEST_CASE');
class TestUser {
 public $id=1; public $socid=0; public $admin=0; public $statut=1;
 function hasRight($module, $right, ...$extra) { return getenv('CPD_TEST_CASE') !== 'no-right' && !(getenv('CPD_TEST_CASE') === 'no-logistics' && $module === 'chinaplanetlogistics'); }
}
$user = new TestUser();
if ($case === 'external') { $user->socid=7; $user->admin=1; }
if ($case === 'anonymous') $user->id=0;
if ($case === 'inactive') $user->statut=0;
$conf = (object) array('entity'=>1);
define('MAIN_DB_PREFIX', 'qa_');
$db = new class {
 function close() {}
 function query($sql) { return getenv('CPD_TEST_CASE') === 'choices-failure' ? false : (object) array('rows'=>array()); }
 function fetch_object($r) { return array_shift($r->rows); }
};
$langs = new class { function loadLangs($x) {} function trans($x) { return $x; } };
function isModEnabled($module) { return getenv('CPD_TEST_CASE') !== 'disabled'; }
function accessforbidden() { echo 'DENIED'; exit; }
function restrictedArea($user, $module, $id, $table='') {
 if (getenv('CPD_TEST_CASE') === 'native-denied') accessforbidden();
 echo 'CHECK:'.$module.';';
}
function dol_include_once($path) { require_once DOL_DOCUMENT_ROOT.'/custom'.$path; }
function dol_escape_htmltag($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function GETPOST($key, $filter='') { return (string) ($_GET[$key] ?? ''); }
function GETPOSTINT($key) { return (int) ($_GET[$key] ?? 0); }
function getDolGlobalString($key) { return $key === 'CHINAPLANETDOCUMENTS_CONTEXT_ADAPTER' ? (in_array(getenv('CPD_TEST_CASE'),array('adapter-enabled','adapter-unscoped')) ? '1' : '') : 'https://docstest.chinaplanet.org/'; }
function llxHeader(...$args) { echo 'HEADER;'; }
function load_fiche_titre(...$args) { return 'TITLE;'; }
function llxFooter() { echo 'FOOTER;'; }
$_GET = array('projectid'=>9);
if ($case === 'mismatch') $_GET['socid']=8;
if (in_array($case,array('shipment','shipment-only','foreign-shipment','wrong-shipment-customer','wrong-shipment-project','no-logistics','missing-shipment'))) $_GET['shipmentid']=3;
if ($case === 'shipment-only') unset($_GET['projectid']);
if ($case === 'unsafe-return') $_GET['returnto']='https://evil.test';
if ($case === 'shipment') $_GET['returnto']='shipment';
if ($case === 'negative') $_GET['projectid']=-1;
if (in_array($case,array('unscoped','adapter-unscoped'))) $_GET=array();
$_SERVER['REQUEST_METHOD'] = $case === 'post' ? 'POST' : 'GET';
register_shutdown_function(function () { echo 'STATUS:'.(http_response_code() ?: 200); });
'''
PROJECT = r'''<?php
class Project {
 public $id=9; public $entity=1; public $socid=7;
 public $title='Synthetic project'; public $ref='TEST-P9';
 function __construct($db) {}
 function fetch($id) {
  $this->id=$id;
  if (getenv('CPD_TEST_CASE') === 'foreign-project') $this->entity=2;
  if (getenv('CPD_TEST_CASE') === 'missing') return 0;
  return 1;
 }
 function getProjectsAuthorizedForUser(...$args) { return array(9); }
 function restrictedProjectArea($user, $right) { return getenv('CPD_TEST_CASE') === 'private-project' ? -1 : 1; }
}
'''
SHIPMENT = r'''<?php
class ChinaPlanetLogistics {
 public $id=3; public $entity=1; public $fk_soc=7; public $fk_project=9;
 public $container_no='TEST-CONT'; public $vessel_name='TEST-VESSEL'; public $ref='S3';
 function __construct($db) {}
 function fetch($id) {
  if (getenv('CPD_TEST_CASE') === 'foreign-shipment') $this->entity=2;
  if (getenv('CPD_TEST_CASE') === 'wrong-shipment-customer') $this->fk_soc=8;
  if (getenv('CPD_TEST_CASE') === 'wrong-shipment-project') $this->fk_project=10;
  return getenv('CPD_TEST_CASE') === 'missing-shipment' ? 0 : 1;
 }
}
'''
FORM = r'''<?php
class Form { function __construct($db) {} function select_company(...$args) { return '<select id="socid" name="socid"><option value="7">Synthetic customer</option></select>'; } }
'''
CUSTOMER = r'''<?php
class Societe {
 public $id=7; public $entity=1; public $name='Synthetic customer'; public $ref='TEST-C7';
 function __construct($db) {}
 function fetch($id) {
  $this->id=$id;
  if (getenv('CPD_TEST_CASE') === 'foreign-customer') $this->entity=2;
  return 1;
 }
}
'''

def run_suite(entry_source):
    with tempfile.TemporaryDirectory(prefix='m121-documents-route-') as folder:
        root = Path(folder)
        module = root / 'custom/chinaplanetdocuments'
        module.mkdir(parents=True)
        (module / 'index.php').write_text(entry_source)
        for sub in ['class', 'lib']:
            shutil.copytree(MODULE / sub, module / sub)
        for relative, content in [('main.inc.php', BOOTSTRAP),
                                  ('projet/class/project.class.php', PROJECT),
                                  ('societe/class/societe.class.php', CUSTOMER),
                                  ('custom/chinaplanetlogistics/class/chinaplanetlogistics.class.php', SHIPMENT),
                                  ('core/class/html.form.class.php', FORM)]:
            target = root / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_text(content)
        cases = ['valid', 'unscoped', 'external', 'anonymous', 'inactive', 'no-right',
                 'disabled', 'native-denied', 'private-project', 'foreign-project',
                 'foreign-customer', 'missing', 'mismatch', 'shipment', 'shipment-only', 'foreign-shipment',
                 'wrong-shipment-customer', 'wrong-shipment-project', 'no-logistics', 'missing-shipment', 'unsafe-return', 'choices-failure', 'adapter-enabled', 'adapter-unscoped', 'negative', 'post']
        for case in cases:
            result = subprocess.run([os.environ.get('PHP_BINARY', 'php'), str(module / 'index.php')],
                                    env={**os.environ, 'CPD_TEST_CASE': case}, text=True, capture_output=True)
            assert result.returncode == 0 and not result.stderr, (case, result.stderr)
            html = result.stdout
            if case in ['valid', 'unscoped', 'shipment', 'shipment-only', 'adapter-enabled', 'adapter-unscoped']:
                assert 'HEADER;' in html and 'FOOTER;' in html and 'STATUS:200' in html, case
                assert 'rel="noopener noreferrer"' in html, case
                if case == 'adapter-enabled':
                    assert 'id="cpd-editor-context"' in html and 'launch-context.mjs' in html, case
                else:
                    assert 'id="cpd-editor-context"' not in html, case
                if case in ['shipment', 'shipment-only']:
                    assert 'TEST-CONT' in html and '/erp/custom/chinaplanetlogistics/card.php?id=3' in html, case
                if case == 'valid':
                    assert 'CHECK:projet;' in html and 'CHECK:societe;' in html, case
                    assert '/erp/projet/card.php?id=9' in html and 'Synthetic customer' in html, case
            elif case == 'choices-failure':
                assert 'DocumentsChoicesUnavailable' in html and '<form' not in html and 'FOOTER;' in html, case
            elif case == 'post':
                assert 'STATUS:405' in html and 'HEADER;' not in html, case
            else:
                assert 'DENIED' in html and 'HEADER;' not in html and 'Synthetic customer' not in html, case
    return len(cases)

if __name__ == '__main__':
    source = (MODULE / 'index.php').read_text()
    count = run_suite(source)
    # Prove the harness detects bypassed native project membership checks.
    mutant = source.replace("if ($project->restrictedProjectArea($user, 'read') <= 0) accessforbidden();", '')
    assert mutant != source
    try:
        run_suite(mutant)
    except AssertionError as error:
        assert str(error) == 'private-project', str(error)
    else:
        raise AssertionError('project permission mutant survived')
    print(f'PASS: {count} entry-route cases; project permission mutation rejected')
