<?php
require_once __DIR__.'/InternalTaskAuthorizationService.class.php';

/** Standard Dolibarr login extension, with no core or stored landing-page changes. */
class ActionsChinaplanetoperations
{
    public $priority = 90;
    public $results = array();
    public $resprints = '';
    public $errors = array();
    private $db;
    public function __construct($db) { $this->db = $db; }
    public function afterLogin($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;
        // Dolibarr dispatches afterLogin before its normal loadRights call.
        $object->loadRights('chinaplanetoperations');
        $auth = new InternalTaskAuthorizationService($this->db, $object, (int) $conf->entity);
        if ($auth->isInternalActive() && !$auth->isAdministrator() && $auth->hasRight('task_read_own')) {
            if (!isset($object->conf) || !is_object($object->conf)) $object->conf = new stdClass();
            $object->conf->MAIN_LANDING_PAGE = '/custom/chinaplanetoperations/workspace.php?view=my&mainmenu=home';
        }
        return 0;
    }
}
