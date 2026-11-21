<?php
/**
 * China Planet Logistics - Hook Actions
 * Redirects external (customer) users from the default home page to the Kanban board.
 */
class ActionsChinaplanetlogistics
{
    public $db;
    public $error = "";

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Executed on every page (main hook). Redirect external users away from the
     * default Dolibarr home page to the customer Kanban board.
     */
    public function main(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;

        require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/routeguard.lib.php';
        return cpcc_apply_external_route_guard($user, isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
    }

    /**
     * Hook executed specifically on the index/home page.
     */
    public function index(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;

        require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/routeguard.lib.php';
        return cpcc_apply_external_route_guard($user, '/index.php');
    }
}
