<?php
$root = dirname(__DIR__, 2);
$workspace = file_get_contents($root.'/custom/chinaplanetoperations/workspace.php');
$edit = file_get_contents($root.'/custom/chinaplanetoperations/operations/edit.php');
$service = file_get_contents($root.'/custom/chinaplanetoperations/class/OperationsService.class.php');
$shell = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php');

$checks = array(
    'workspace requires authenticated operations read' => "cpo_require(\$user, 'read')",
    'employee tasks reuse scoped task service' => "listTasks(array('view'=>'my'))",
    'customer lookup is entity scoped' => 'WHERE s.entity=".$entity." AND s.status=1 AND s.client>0',
    'customer operation drill-down is scoped' => "search(array('socid'=>\$socid))",
    'native order field matches current schema' => 'SELECT rowid,ref,fk_statut,total_ht,date_commande',
    'prototype-only actions are called out' => 'الحوالات والتخصيص ودفع المورد وCargoX وDHL',
    'workspace never imports prototype assets' => 'daily-work-demo',
);
foreach ($checks as $label=>$needle) {
    $present = strpos($workspace, $needle) !== false;
    if ($label === 'workspace never imports prototype assets') $present = !$present;
    if (!$present) {fwrite(STDERR, "FAIL $label\n"); exit(1);}
}

$editChecks = array(
    'stable request identity'=>'name="request_hash"',
    'customer selector'=>'id="cpo-customer"',
    'project selector'=>'id="cpo-project"',
    'order selector'=>'id="cpo-order"',
    'shipment selector'=>'id="cpo-shipment"',
    'context filtering'=>'function filter(select,socid)',
    'owner list is entity exact'=>'WHERE entity=".(int) $conf->entity." AND statut=1 AND COALESCE(fk_soc,0)=0',
    'safe create readiness gate'=>'$createReady = $taskBoardReady && !empty($teams)',
    'disabled save until ready'=>"!\$createReady ? ' disabled' : ''",
    'guided create explicitly requires assignment'=>"'require_assignment'=>1",
    'existing operation team is derived'=>'SELECT fk_usergroup FROM ".MAIN_DB_PREFIX."cpo_task',
    'team selector remains on edit'=>'$selectedTeamId === (int) $team[\'id\']',
    'customer sales representative scope'=>"hasRight('societe', 'client', 'voir')",
    'private projects require global project read'=>"hasRight('projet', 'all', 'lire')",
    'restricted project link is preserved'=>"\$id > 0 && !\$canReadProjects ? (int) \$operation['fk_project']",
    'restricted order link is preserved'=>"\$id > 0 && !\$canReadOrders ? (int) \$operation['fk_commande']",
    'posted customer is authorization checked'=>'Customer is outside your authorized customer scope.',
    'posted project is authorization checked'=>'Project is outside your authorized project scope.',
    'posted order is authorization checked'=>'Order is outside your authorized order scope.',
    'posted shipment is authorization checked'=>'Shipment is outside your authorized customer scope.',
);
foreach ($editChecks as $label=>$needle) {
    if (strpos($edit, $needle) === false) {fwrite(STDERR, "FAIL $label\n"); exit(1);}
}

$serviceChecks = array(
    'operation request lock'=>'SELECT GET_LOCK(',
    'operation retry lookup fails closed'=>'Unable to check operation request identity.',
    'operation retry lookup'=>"action_code='operation_create_request'",
    'payload conflict'=>'Operation request payload changed.',
    'customer project validation'=>'Project and customer must match.',
    'customer itself is validated'=>'Customer must be an active customer in the current entity.',
    'customer order validation'=>'Order and customer must match.',
    'customer filter'=>"if (!empty(\$filters['socid']))",
    'active internal owner validation'=>'Operation owner must be an active internal user.',
    'primary team validation'=>'A valid primary team is required.',
    'team membership validation'=>'Operation owner must belong to the selected team.',
    'generated tasks retain direct owner'=>"'fk_user_assign'=>(int)(\$data['fk_user_assign']??0)",
);
foreach ($serviceChecks as $label=>$needle) {
    if (strpos($service, $needle) === false) {fwrite(STDERR, "FAIL $label\n"); exit(1);}
}

if (strpos($shell, "'workspace'=>array('/chinaplanetoperations/workspace.php'") === false) {
    fwrite(STDERR, "FAIL shared workspace navigation\n"); exit(1);
}
echo "M076_WORKSPACE_PHASE1_CONTRACT=PASS\n";
