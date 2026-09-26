<?php
/** Explicit hook registration; no task/schema changes, no writes from page reads. */
if (PHP_SAPI !== 'cli') exit(1);
$root = getenv('DOL_DOCUMENT_ROOT');
if (!$root || !is_file($root.'/master.inc.php')) { fwrite(STDERR, "Set DOL_DOCUMENT_ROOT to the target Dolibarr htdocs.\n"); exit(1); }
require $root.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
$entity = (int)(getenv('CPO_ENTITY') ?: 1);
if ($entity <= 0) throw new RuntimeException('A valid entity is required.');
$mode = $argv[1] ?? 'check';
if (!in_array($mode, array('check','apply','rollback'), true)) exit(1);
$name = 'MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS';
$contexts = array_values(array_filter(explode(':', (string)dolibarr_get_const($db, $name, $entity))));
if ($mode === 'apply') {
    if (!$db->query('SELECT entity,fk_task,payload FROM '.MAIN_DB_PREFIX.'cpo_task_details LIMIT 0')) throw new RuntimeException('Install task experience first.');
    if (!in_array('agenda', $contexts, true)) {
        $contexts[] = 'agenda';
        if (dolibarr_set_const($db, $name, implode(':', $contexts), 'chaine', 0, '', $entity) <= 0) throw new RuntimeException('Agenda hook registration failed.');
    }
} elseif ($mode === 'rollback') {
    $contexts = array_values(array_diff($contexts, array('agenda')));
    if (dolibarr_set_const($db, $name, implode(':', $contexts), 'chaine', 0, '', $entity) <= 0) throw new RuntimeException('Agenda hook removal failed.');
}
echo 'M091_AGENDA_HOOK='.(in_array('agenda', $contexts, true) ? 'ENABLED' : 'DISABLED')."\n";
$db->close();
