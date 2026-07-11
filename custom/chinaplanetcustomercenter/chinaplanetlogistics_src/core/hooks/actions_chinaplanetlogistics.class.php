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

        if (empty($user->id)) {
            return 0;
        }
        // Only external users (linked to a third party) are redirected.
        if (empty($user->socid)) {
            return 0;
        }

        $current_script = isset($_SERVER["PHP_SELF"]) ? $_SERVER["PHP_SELF"] : "";

        // Redirect only when on the default home/index page.
        if (preg_match('#/index\.php$#', $current_script)) {
            header("Location: /custom/chinaplanetcustomercenter/chinaplanetcustomercenter/board.php");
            exit;
        }

        return 0;
    }

    /**
     * Hook executed specifically on the index/home page.
     */
    public function index(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;

        if (!empty($user->socid)) {
            header("Location: /custom/chinaplanetcustomercenter/chinaplanetcustomercenter/board.php");
            exit;
        }

        return 0;
    }
}
