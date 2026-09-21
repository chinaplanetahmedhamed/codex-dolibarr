<?php
class ActionsChinaplanetcustomercenter
{
    public $db;
    public $results = array();

    public function addMcpTools($parameters, &$object, &$action, $hookmanager)
    {
        require_once __DIR__.'/../../class/mcpcustomeradvance.class.php';
        $this->results = array('chinaplanetcustomercenter' => array(new CpccMcpCustomerAdvance($parameters['db'], $parameters['user'], $parameters['conf'])));
        return 0;
    }

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function main(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;
        require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/routeguard.lib.php';
        return cpcc_apply_external_route_guard($user, isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
    }

    public function index(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;
        require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/routeguard.lib.php';
        return cpcc_apply_external_route_guard($user, '/index.php');
    }

    public function setContentSecurityPolicy(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;
        require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/routeguard.lib.php';
        return cpcc_apply_external_route_guard($user, isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');
    }
}
