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
        global $user, $conf, $langs;
        dol_syslog("MANUS DEBUG: User ID " . $user->id . " SocID " . $user->socid . " Script " . $_SERVER["PHP_SELF"]);

        // Only redirect external users (socid > 0) who are NOT already on our module pages
        if (empty($user->id)) return 0;
        if (empty($user->socid)) return 0; // Internal user, skip

        // External user detected
        $current_script = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';

        // Pages allowed for external users
        $allowed_paths = array(
            '/custom/cpkanban/',
            '/custom/chinaplanetlogistics/',
            '/compta/facture/card.php',
            '/compta/facture/list.php',
            '/commande/card.php',
            '/commande/list.php',
            '/projet/card.php',
            '/projet/list.php',
            '/projet/tasks/',
            '/user/card.php',
            '/user/passwordforgotten.php',
            '/index.php',
            '/public/',
            '/core/login/',
        );

        // Check if current page is allowed
        $is_allowed = false;
        foreach ($allowed_paths as $path) {
            if (strpos($current_script, $path) !== false) {
                $is_allowed = true;
                break;
            }
        }

        // If on the root index.php, redirect to the central Kanban board
        if ($current_script === '/index.php' || preg_match('#^/index\.php$#', $current_script)) {
            $portal_url = dol_buildpath('/custom/cpkanban/board.php', 1);
            header('Location: ' . $portal_url);
            exit;
        }

        return 0;
    }

    /**
     * Hook for the index page to redirect external users
     */
    public function index(&$parameters, &$object, &$action, $hookmanager)
    {
        global $user;

        if (!empty($user->socid)) {
            // External user on the default home page - redirect to central Kanban portal
            $portal_url = dol_buildpath('/custom/cpkanban/board.php', 1);
            header('Location: ' . $portal_url);
            exit;
        }

        return 0;
    }
}
