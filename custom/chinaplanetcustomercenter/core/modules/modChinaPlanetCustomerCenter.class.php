<?php
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modChinaPlanetCustomerCenter extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->db = $db;
        $this->numero = 500200;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->family = 'projects';
        $this->module_position = 98;
        $this->description = 'ChinaPlanet Customer Center';
        $this->version = '2.4.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'project';
        $this->dirs = array('/chinaplanetcustomercenter');
        $this->config_page_url = array('setup.php@chinaplanetcustomercenter');
        $this->depends = array('modSociete', 'modCommande', 'modFacture', 'modFournisseur', 'modBanque', 'modExpedition', 'modProjet');
        $this->requiredby = array();
        $this->phpmin = array(7, 2);
        $this->need_dolibarr_version = array(23, 0);
        $this->module_parts = array(
            'hooks' => array('main', 'index', 'ordercard', 'shippingcard', 'projectcard'),
            'triggers' => 1,
        );
        $this->rights = array();
        $this->rights_class = 'chinaplanetcustomercenter';

        $this->rights[1][0] = 500201;
        $this->rights[1][1] = 'Read customer center';
        $this->rights[1][3] = 1;
        $this->rights[1][4] = 'read';

        $this->rights[2][0] = 500202;
        $this->rights[2][1] = 'Create and edit records';
        $this->rights[2][3] = 0;
        $this->rights[2][4] = 'write';

        $this->rights[3][0] = 500203;
        $this->rights[3][1] = 'Delete records';
        $this->rights[3][3] = 0;
        $this->rights[3][4] = 'delete';

        $this->rights[4][0] = 500204;
        $this->rights[4][1] = 'Manage columns and onboarding';
        $this->rights[4][3] = 0;
        $this->rights[4][4] = 'admin';

        $this->rights[5][0] = 500205;
        $this->rights[5][1] = 'Read managed customer funds';
        $this->rights[5][3] = 0;
        $this->rights[5][4] = 'financial_read';

        $this->rights[6][0] = 500206;
        $this->rights[6][1] = 'Confirm and allocate managed customer funds';
        $this->rights[6][3] = 0;
        $this->rights[6][4] = 'financial_write';

        $this->menu = array();
        $r = 0;

        $this->menu[$r++] = array(
            'fk_menu' => '0',
            'type' => 'top',
            'titre' => 'Customer Center',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => '0',
            'url' => '/custom/chinaplanetcustomercenter/index.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 98,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","read")',
            'user' => 2,
            'picto' => 'project',
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Operations Center',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'operations_center',
            'url' => '/custom/chinaplanetcustomercenter/admin/operations.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 92,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","write") || !empty($user->admin)',
            'user' => 0,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Customer Finance',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'customer_finance',
            'url' => '/custom/chinaplanetcustomercenter/admin/financial_portfolio.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 95,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","write") || !empty($user->admin)',
            'user' => 0,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Managed Customer Funds',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'managed_customer_funds',
            'url' => '/custom/chinaplanetcustomercenter/admin/managed_funds.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 96,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","financial_read") || $user->hasRight("chinaplanetcustomercenter","financial_write") || !empty($user->admin)',
            'user' => 0,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Export Documents',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'export_documents',
            'url' => '/custom/chinaplanetcustomercenter/admin/export_document.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 97,
            'enabled' => '0',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","write") || !empty($user->admin)',
            'user' => 0,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Admin Dashboard',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'admin_dashboard',
            'url' => '/custom/chinaplanetcustomercenter/admin_dashboard.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 90,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled',
            'perms' => '$user->admin',
            'user' => 0,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Portal Home',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'portal_home',
            'url' => '/custom/chinaplanetcustomercenter/board.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 100,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","read")',
            'user' => 2,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Shipment Tracking',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'shipment_tracking',
            'url' => '/custom/chinaplanetlogistics/index.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 110,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","read")',
            'user' => 2,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Shipment List',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'shipment_list',
            'url' => '/custom/chinaplanetlogistics/list.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 120,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","read") || $user->hasRight("chinaplanetlogistics","read") || $user->admin',
            'user' => 2,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Shipment Documents',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'shipment_documents',
            'url' => '/custom/chinaplanetcustomercenter/documents.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 130,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","read")',
            'user' => 2,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'New Shipment',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'new_shipment',
            'url' => '/custom/chinaplanetlogistics/card.php?action=create',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 200,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","write") || $user->hasRight("chinaplanetlogistics","write") || $user->admin',
            'user' => 0,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'New Card',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'new_card',
            'url' => '/custom/chinaplanetcustomercenter/card.php?action=create',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 210,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","write")',
            'user' => 0,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Manage Columns',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'manage_columns',
            'url' => '/custom/chinaplanetcustomercenter/admin/columns.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 300,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","admin") || !empty($user->admin)',
            'user' => 0,
        );

        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetcustomercenter',
            'type' => 'left',
            'titre' => 'Customer Onboarding',
            'mainmenu' => 'chinaplanetcustomercenter',
            'leftmenu' => 'onboarding',
            'url' => '/custom/chinaplanetcustomercenter/admin/onboarding.php',
            'langs' => 'chinaplanetcustomercenter@chinaplanetcustomercenter',
            'position' => 400,
            'enabled' => '$conf->chinaplanetcustomercenter->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetcustomercenter","admin") || !empty($user->admin)',
            'user' => 0,
        );
    }

    public function init($options = '')
    {
        $result = $this->_load_tables('/chinaplanetcustomercenter/sql/');
        if ($result <= 0) {
            return $result;
        }

        $cpExists = $this->db->query("SHOW TABLES LIKE '".MAIN_DB_PREFIX."cpkanban_column'");
        if (!$cpExists || $this->db->num_rows($cpExists) === 0) {
            $result = $this->_load_tables('/chinaplanetcustomercenter/cpkanban/sql/');
            if ($result <= 0) {
                return $result;
            }
        }

        $logExists = $this->db->query("SHOW TABLES LIKE '".MAIN_DB_PREFIX."chinaplanetlogistics'");
        if (!$logExists || $this->db->num_rows($logExists) === 0) {
            $result = $this->_load_tables('/chinaplanetcustomercenter/chinaplanetlogistics/sql/');
            if ($result <= 0) {
                return $result;
            }
        }

        return $this->_init(array(), $options);
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
