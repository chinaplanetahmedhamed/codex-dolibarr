<?php
/**
 * Module descriptor for China Planet Logistics
 * FIXED v1.2.3: Added dir_output + dirs so $conf->chinaplanetlogistics->dir_output
 *               is populated and document.php can serve files correctly.
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modChinaPlanetLogistics extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->name = "ChinaPlanetLogistics";
        $this->family = "projects";
        $this->module_position = 100;
        $this->description = "China Planet Logistics Control Tower - Phase 2 (Customer Portal)";
        $this->version = '1.2.3';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'ship';
        $this->hidden = true; // Hide legacy descriptor once the unified module is active

        // ── Data directories ─────────────────────────────────────────────
        // This populates $conf->chinaplanetlogistics->dir_output
        // which is REQUIRED by document.php to serve uploaded files.
        $this->dirs = array(
            "/chinaplanetlogistics",
        );

        // Config pages
        $this->config_page_url = array("setup.php@chinaplanetlogistics");

        // Dependencies
        $this->depends = array("modCommande", "modExpedition", "modProjet");
        $this->requiredby = array();
        $this->phpmin = array(7, 0);
        $this->need_dolibarr_version = array(14, 0);

        // Constants
        $this->const = array();

        // Module parts
        $this->module_parts = array(
            'hooks' => array('main', 'index', 'ordercard', 'shippingcard', 'projectcard'),
            'boxes'  => 1,
        );

        // Dashboard widget (box)
        $this->boxes = array(
            0 => array(
                'file'  => 'box_chinaplanetlogistics.php@chinaplanetlogistics',
                'note'  => 'Container status and countdown widget',
                'enabledbydefaulton' => 'Home',
            ),
        );

        // Tabs
        $this->tabs = array();

        // Permissions
        $this->rights = array();
        $this->rights_class = 'chinaplanetlogistics';

        $this->rights[1][0] = 500001;
        $this->rights[1][1] = 'Read shipments';
        $this->rights[1][3] = 1;
        $this->rights[1][4] = 'read';
        $this->rights[1][5] = 'external';

        $this->rights[2][0] = 500002;
        $this->rights[2][1] = 'Create/modify shipments';
        $this->rights[2][3] = 0;
        $this->rights[2][4] = 'write';

        $this->rights[3][0] = 500003;
        $this->rights[3][1] = 'Delete shipments';
        $this->rights[3][3] = 0;
        $this->rights[3][4] = 'delete';

        // Main Menu
        $this->menu = array();
        $r = 0;

        $this->menu[$r++] = array(
            'fk_menu'  => '0',
            'type'     => 'top',
            'titre'    => 'China Planet Logistics',
            'mainmenu' => 'chinaplanetlogistics',
            'leftmenu' => '0',
            'url'      => '/chinaplanetlogistics/index.php',
            'langs'    => 'chinaplanetlogistics@chinaplanetlogistics',
            'position' => 1000,
            'enabled'  => '0', // Legacy top menu hidden; functionality is exposed by Customer Center.
            'perms'    => '$user->hasRight("chinaplanetlogistics","read")',
            'user'     => 2,
            'picto'    => 'ship',
        );
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=chinaplanetlogistics',
            'type'     => 'left',
            'titre'    => 'Dashboard',
            'mainmenu' => 'chinaplanetlogistics',
            'leftmenu' => 'dashboard',
            'url'      => '/chinaplanetlogistics/index.php',
            'langs'    => 'chinaplanetlogistics@chinaplanetlogistics',
            'position' => 100,
            'enabled'  => '$conf->chinaplanetlogistics->enabled',
            'perms'    => '$user->hasRight("chinaplanetlogistics","read")',
            'user'     => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=chinaplanetlogistics',
            'type'     => 'left',
            'titre'    => 'Shipment List',
            'mainmenu' => 'chinaplanetlogistics',
            'leftmenu' => 'shipment_list',
            'url'      => '/chinaplanetlogistics/list.php',
            'langs'    => 'chinaplanetlogistics@chinaplanetlogistics',
            'position' => 200,
            'enabled'  => '$conf->chinaplanetlogistics->enabled',
            'perms'    => '$user->hasRight("chinaplanetlogistics","read")',
            'user'     => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=chinaplanetlogistics',
            'type'     => 'left',
            'titre'    => 'Financial Reports / التقارير المالية',
            'mainmenu' => 'chinaplanetlogistics',
            'leftmenu' => 'financial_reports',
            'url'      => '/chinaplanetcustomercenter/reports.php',
            'langs'    => 'chinaplanetlogistics@chinaplanetlogistics',
            'position' => 250,
            'enabled'  => '$conf->chinaplanetlogistics->enabled',
            'perms'    => '$user->hasRight("chinaplanetlogistics","read")',
            'user'     => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=chinaplanetlogistics',
            'type'     => 'left',
            'titre'    => 'Documents Management / إدارة المستندات',
            'mainmenu' => 'chinaplanetlogistics',
            'leftmenu' => 'documents_management',
            'url'      => '/chinaplanetcustomercenter/documents.php',
            'langs'    => 'chinaplanetlogistics@chinaplanetlogistics',
            'position' => 275,
            'enabled'  => '$conf->chinaplanetlogistics->enabled',
            'perms'    => '$user->hasRight("chinaplanetlogistics","read")',
            'user'     => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=chinaplanetlogistics',
            'type'     => 'left',
            'titre'    => 'New Shipment',
            'mainmenu' => 'chinaplanetlogistics',
            'leftmenu' => 'new_shipment',
            'url'      => '/chinaplanetlogistics/card.php?action=create',
            'langs'    => 'chinaplanetlogistics@chinaplanetlogistics',
            'position' => 300,
            'enabled'  => '$conf->chinaplanetlogistics->enabled && empty($user->socid)',
            'perms'    => '$user->hasRight("chinaplanetlogistics","write")',
            'user'     => 0,
        );
    }

    /**
     * Function called when module is enabled.
     * Creates the /chinaplanetlogistics directory under DOL_DATA_ROOT.
     */
    public function init($options = '')
    {
        $result = $this->_load_tables('/chinaplanetlogistics/sql/');
        if ($result <= 0) {
            return $result;
        }

        return $this->_init(array(), $options);
    }
}
?>
