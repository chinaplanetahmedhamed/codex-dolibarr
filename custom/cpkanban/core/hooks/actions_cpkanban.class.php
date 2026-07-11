<?php
/**
 * China Planet Kanban - Hook Actions.
 * External customers should land in the Customer Center, not the noisy default dashboard.
 * This is a safety net; the preferred/stronger method is still MAIN_LANDING_PAGE set by onboarding.
 */
class ActionsCpKanban
{
    public $db;
    public $error = '';
    public $results = array();
    public $resprints;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function main(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;
        if (empty($user->id) || empty($user->socid) || !empty($user->admin)) return 0;
        $current = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
        if (preg_match('#/index\.php$#', $current)) {
            header('Location: /custom/chinaplanetcustomercenter/board.php');
            exit;
        }
        return 0;
    }

    public function index(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;
        if (!empty($user->id) && !empty($user->socid) && empty($user->admin)) {
            header('Location: /custom/chinaplanetcustomercenter/board.php');
            exit;
        }
        return 0;
    }
}
