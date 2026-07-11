<?php
/**
 * China Planet Logistics - Hook Actions
 * Redirects external users from core Dolibarr pages to the customer portal
 */
class ActionsChinaplanetlogistics
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * Constructor
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Overloading the main hook to redirect external users
     * Called at the beginning of every page
     *
     * @param array  $parameters Hook metadata
     * @param object $object     Current object
     * @param string $action     Current action
     * @param object $hookmanager Hook manager
     * @return int 0=OK, <0=error
     */
    public function main(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;
        require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/routeguard.lib.php';
        return cpcc_apply_external_route_guard($user, isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
    }

    /**
     * Hook for the index page to redirect external users
     */
    public function index(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;

        require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/routeguard.lib.php';
        return cpcc_apply_external_route_guard($user, '/index.php');
    }
}
