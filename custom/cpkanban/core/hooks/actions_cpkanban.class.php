<?php
/**
 * China Planet Kanban - Hook Actions.
 * External customers should land in the Customer Center, not the noisy default dashboard.
 * Includes auto-provisioning for MAIN_LANDING_PAGE.
 */
class ActionsCpKanban
{
    public \$db;
    public \$error = '';
    public \$results = array();
    public \$resprints;

    public function __construct(\$db)
    {
        \$this->db = \$db;
    }

    /**
     * Auto-set landing page for external users if not already set correctly.
     */
    private function _autoProvisionLandingPage(\$user)
    {
        if (empty(\$user->id) || empty(\$user->socid) || !empty(\$user->admin)) return;

        \$target = '/custom/cpkanban/board.php';
        
        // Check if already set correctly in user session or params
        if (isset(\$user->conf->MAIN_LANDING_PAGE) && \$user->conf->MAIN_LANDING_PAGE === \$target) {
            return;
        }

        // Force update in database
        \$sql = "INSERT INTO ".MAIN_DB_PREFIX."user_param (fk_user, entity, param, value) ";
        \$sql.= "VALUES (".((int) \$user->id).", ".((int) \$user->entity).", 'MAIN_LANDING_PAGE', '".\$this->db->escape(\$target)."') ";
        \$sql.= "ON DUPLICATE KEY UPDATE value = '".\$this->db->escape(\$target)."'";
        
        \$this->db->query(\$sql);
        
        // Update current session object to avoid redundant queries
        \$user->conf->MAIN_LANDING_PAGE = \$target;
    }

    /**
     * Hook on index (Dolibarr Home)
     */
    public function index(\$parameters, &\$object, &\$action, \$hookmanager)
    {
        global \$user;
        
        // Only for external users who are not admins and have cpkanban read permission
        if (!empty(\$user->id) && !empty(\$user->socid) && empty(\$user->admin) && \$user->hasRight('cpkanban', 'read')) {
            \$this->_autoProvisionLandingPage(\$user);
            header('Location: '.DOL_URL_ROOT.'/custom/cpkanban/board.php');
            exit;
        }
        return 0;
    }

    /**
     * Hook on main (Every page)
     */
    public function main(\$parameters, &\$object, &\$action, \$hookmanager)
    {
        global \$user;
        
        if (empty(\$user->id) || empty(\$user->socid) || !empty(\$user->admin) || !\$user->hasRight('cpkanban', 'read')) {
            return 0;
        }

        \$current = isset(\$_SERVER['PHP_SELF']) ? \$_SERVER['PHP_SELF'] : '';
        
        // If external user tries to access index.php directly
        if (preg_match('#/index\.php$#', \$current)) {
            \$this->_autoProvisionLandingPage(\$user);
            header('Location: '.DOL_URL_ROOT.'/custom/cpkanban/board.php');
            exit;
        }
        return 0;
    }
}
