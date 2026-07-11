<?php
/**
 * Module descriptor for China Planet Logistics
 * FIXED v1.2.3: Added dir_output + dirs so $conf->chinaplanetcustomercenter->dir_output
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

        // ── Data directories ─────────────────────────────────────────────
        // This populates $conf->chinaplanetcustomercenter->dir_output
        // which is REQUIRED by document.php to serve uploaded files.
        $this->dirs = array(
            "/chinaplanetcustomercenter",
        );

        // Config pages
        $this->config_page_url = array("setup.php@chinaplanetcustomercenter");

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
                'file'  => 'box_chinaplanetcustomercenter.php@chinaplanetcustomercenter',
                'note'  => 'Container status and countdown widget',
                'enabledbydefaulton' => 'Home',
            ),
        );

        // Tabs
        $this->tabs = array();

        // Permissions
        $this->rights = array();
        $this->rights_class = 'chinaplanetcustomercenter';

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
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => '0',
            'url'      => '/chinaplanetcustomercenter/index.php',
            'langs'    => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 1000,
            'enabled'  => '$conf->chinaplanetcustomercenter->enabled',
            'perms'    => '$user->hasRight("chinaplanetcustomercenter","read")',
            'user'     => 2,
            'picto'    => 'ship',
        );
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=chinaplanetcustomercenter',
            'type'     => 'left',
            'titre'    => 'Dashboard',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'dashboard',
            'url'      => '/chinaplanetcustomercenter/index.php',
            'langs'    => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 100,
            'enabled'  => '$conf->chinaplanetcustomercenter->enabled',
            'perms'    => '$user->hasRight("chinaplanetcustomercenter","read")',
            'user'     => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=chinaplanetcustomercenter',
            'type'     => 'left',
            'titre'    => 'Shipment List',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'shipment_list',
            'url'      => '/chinaplanetcustomercenter/list.php',
            'langs'    => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 200,
            'enabled'  => '$conf->chinaplanetcustomercenter->enabled',
            'perms'    => '$user->hasRight("chinaplanetcustomercenter","read")',
            'user'     => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=chinaplanetcustomercenter',
            'type'     => 'left',
            'titre'    => 'Financial Reports / التقارير المالية',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'financial_reports',
            'url'      => '/chinaplanetcustomercenter/reports.php',
            'langs'    => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 250,
            'enabled'  => '$conf->chinaplanetcustomercenter->enabled',
            'perms'    => '$user->hasRight("chinaplanetcustomercenter","read")',
            'user'     => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=chinaplanetcustomercenter',
            'type'     => 'left',
            'titre'    => 'New Shipment',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'new_shipment',
            'url'      => '/chinaplanetcustomercenter/card.php?action=create',
            'langs'    => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 300,
            'enabled'  => '$conf->chinaplanetcustomercenter->enabled && empty($user->socid)',
            'perms'    => '$user->hasRight("chinaplanetcustomercenter","write")',
            'user'     => 0,
        );
    }

    /**
     * Function called when module is enabled.
     * Creates the /chinaplanetcustomercenter directory under DOL_DATA_ROOT.
     */
    public function init($options = '')
    {
        $result = $this->_load_tables('/chinaplanetcustomercenter/sql/');
        if ($result <= 0) {
            return $result;
        }

        return $this->_init(array(), $options);
    }
}
?>
