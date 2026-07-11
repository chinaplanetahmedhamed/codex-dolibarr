<?php
/**
 * China Planet Kanban - Module Descriptor
 * Customer Project Kanban portal inside Dolibarr 23+
 * Companion to the chinaplanetlogistics (shipment tracking) module.
 */
class modCpKanban extends DolibarrModules
{
    /**
     * Constructor
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 500100; // Unique module id (logistics module uses 500000)
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->family = "projects";
        $this->module_position = 101;
        $this->description = "China Planet - Customer Project Kanban Portal";
        $this->version = '2.1.0'; // Customer Center redesign + onboarding + hidden-column folding
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'project';

        // Config page
        $this->config_page_url = array("setup.php@cpkanban");

        // Dependencies
        $this->depends = array("modSociete");
        $this->requiredby = array();
        $this->phpmin = array(7, 2);
        $this->need_dolibarr_version = array(23, 0);

        $this->const = array();

        // Module parts: register the AJAX/redirect hook on main + the board pages
        $this->module_parts = array(
            'hooks' => array('main', 'index'),
            'triggers' => 0,
        );

        $this->tabs = array();

        // ---------- Permissions (Dolibarr 23 uses $user->hasRight('cpkanban','read') etc.) ----------
        $this->rights = array();
        $this->rights_class = 'cpkanban';

        $this->rights[1][0] = 500101;
        $this->rights[1][1] = 'Read Kanban cards';
        $this->rights[1][3] = 1;        // enabled by default
        $this->rights[1][4] = 'read';

        $this->rights[2][0] = 500102;
        $this->rights[2][1] = 'Create/modify Kanban cards';
        $this->rights[2][3] = 0;
        $this->rights[2][4] = 'write';

        $this->rights[3][0] = 500103;
        $this->rights[3][1] = 'Delete Kanban cards';
        $this->rights[3][3] = 0;
        $this->rights[3][4] = 'delete';

        $this->rights[4][0] = 500104;
        $this->rights[4][1] = 'Manage columns and settings';
        $this->rights[4][3] = 0;
        $this->rights[4][4] = 'admin';

        // ---------- Menus ----------
        $this->menu = array();
        $r = 0;

        // Top menu
        $this->menu[$r++] = array(
            'fk_menu'  => '0',
            'type'     => 'top',
            'titre'    => 'Customer Projects',
            'mainmenu' => 'cpkanban',
            'leftmenu' => '0',
            'url'      => '/custom/cpkanban/board.php',
            'langs'    => 'cpkanban@cpkanban',
            'position' => 1001,
            'enabled'  => '$conf->cpkanban->enabled',
            'perms'    => '$user->hasRight("cpkanban","read")',
            'picto'    => 'project',
        );

        // Left: Board
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=cpkanban',
            'type'     => 'left',
            'titre'    => 'Project Board',
            'mainmenu' => 'cpkanban',
            'leftmenu' => 'cpkanban_board',
            'url'      => '/custom/cpkanban/board.php',
            'langs'    => 'cpkanban@cpkanban',
            'position' => 100,
            'enabled'  => '$conf->cpkanban->enabled',
            'perms'    => '$user->hasRight("cpkanban","read")',
        );

        // Left: New card (internal staff only)
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=cpkanban',
            'type'     => 'left',
            'titre'    => 'New Card',
            'mainmenu' => 'cpkanban',
            'leftmenu' => 'cpkanban_new',
            'url'      => '/custom/cpkanban/card.php?action=create',
            'langs'    => 'cpkanban@cpkanban',
            'position' => 200,
            'enabled'  => '$conf->cpkanban->enabled && empty($user->socid)',
            'perms'    => '$user->hasRight("cpkanban","write")',
        );

        // Left: Manage columns (admin)
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=cpkanban',
            'type'     => 'left',
            'titre'    => 'Manage Columns',
            'mainmenu' => 'cpkanban',
            'leftmenu' => 'cpkanban_columns',
            'url'      => '/custom/cpkanban/admin/columns.php',
            'langs'    => 'cpkanban@cpkanban',
            'position' => 300,
            'enabled'  => '$conf->cpkanban->enabled && empty($user->socid)',
            'perms'    => '$user->hasRight("cpkanban","admin")',
        );

        // Left: Customer onboarding helper (admin)
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=cpkanban',
            'type'     => 'left',
            'titre'    => 'Customer Onboarding',
            'mainmenu' => 'cpkanban',
            'leftmenu' => 'cpkanban_onboarding',
            'url'      => '/custom/cpkanban/admin/onboarding.php',
            'langs'    => 'cpkanban@cpkanban',
            'position' => 400,
            'enabled'  => '$conf->cpkanban->enabled && empty($user->socid)',
            'perms'    => '$user->hasRight("cpkanban","admin")',
        );
    }

    /**
     * Called when module is enabled. Creates tables and seeds default columns.
     * @param string $options Options
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        $result = $this->_load_tables('/cpkanban/sql/');

        // Seed default columns only if the column table is empty
        $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."cpkanban_column";
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            if ($obj && (int) $obj->nb === 0) {
                $this->_load_tables('/cpkanban/sql/', 'data_columns.sql');
            }
        }

        return $this->_init(array(), $options);
    }

    /**
     * Called when module is disabled.
     * @param string $options Options
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
