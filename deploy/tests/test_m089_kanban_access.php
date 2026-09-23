<?php
// Isolated bootstrap test: no database, runtime credentials or hosted requests.
class M089AccessDenied extends RuntimeException {}
function accessforbidden() { throw new M089AccessDenied('denied'); }
class M089KanbanActor {
    public $id = 1;
    public $socid = 0;
    public $admin = 0;
    public $rights = array();
    public function hasRight($module, $right) { return in_array($module.':'.$right, $this->rights, true); }
}
$tmp = sys_get_temp_dir().'/m089-bootstrap-'.bin2hex(random_bytes(8));
mkdir($tmp, 0700);
file_put_contents($tmp.'/main.inc.php', '<?php return 1;');
$_SERVER['DOCUMENT_ROOT'] = $tmp;
$source = dirname(__DIR__, 2).'/custom/cpkanban/lib/main.lib.php';
$cases = array(
    array('ordinary worker', array('chinaplanetoperations:task_read_own', 'chinaplanetoperations:task_update_own'), 0, 0, 1, false),
    array('no permissions', array(), 0, 0, 1, false),
    array('anonymous', array('cpkanban:read'), 0, 0, 0, false),
    array('negative company is internal', array(), -1, 0, 1, false),
    array('kanban reader', array('cpkanban:read'), 0, 0, 1, true),
    array('kanban writer', array('cpkanban:write'), 0, 0, 1, true),
    array('customer-center reader', array('chinaplanetcustomercenter:read'), 0, 0, 1, true),
    array('customer-center writer', array('chinaplanetcustomercenter:write'), 0, 0, 1, true),
    array('administrator', array(), 0, 1, 1, true),
    array('external continues to company ownership guard', array(), 7, 0, 1, true),
);
try {
    foreach ($cases as $case) {
        list($label, $rights, $socid, $admin, $id, $expected) = $case;
        $user = new M089KanbanActor();
        $user->rights = $rights; $user->socid = $socid; $user->admin = $admin; $user->id = $id;
        $allowed = true;
        try { include $source; } catch (M089AccessDenied $e) { $allowed = false; }
        if ($allowed !== $expected) throw new RuntimeException('Failed: '.$label);
        echo 'PASS '.$label."\n";
    }
    foreach (array('board.php', 'card.php', 'asset.php', 'cover.php', 'ajax/move.php') as $endpoint) {
        $code = file_get_contents(dirname(__DIR__, 2).'/custom/cpkanban/'.$endpoint);
        if (strpos($code, 'lib/main.lib.php') === false) throw new RuntimeException('Missing shared guard: '.$endpoint);
    }
    echo "M089_KANBAN_ACCESS=PASS\n";
} finally {
    unlink($tmp.'/main.inc.php'); rmdir($tmp);
}
