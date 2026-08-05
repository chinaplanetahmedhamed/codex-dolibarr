<?php

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modChinaPlanetShippingPrices extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->db = $db;
        $this->numero = 500400;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->family = 'products';
        $this->module_position = 99;
        $this->description = 'Versioned shipping rates, customer inquiry and internal operations SOP library';
        $this->version = '1.4.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'ship';
        $this->dirs = array('/chinaplanetshippingprices/imports');
        $this->config_page_url = array('setup.php@chinaplanetshippingprices');
        $this->depends = array('modSociete');
        $this->requiredby = array();
        $this->phpmin = array(7, 4);
        $this->need_dolibarr_version = array(23, 0);
        $this->module_parts = array('api' => 1);

        $this->rights = array();
        $this->rights_class = 'chinaplanetshippingprices';

        $this->rights[1][0] = 500401;
        $this->rights[1][1] = 'Search published shipping prices and view public history';
        $this->rights[1][3] = 1;
        $this->rights[1][4] = 'read';
        $this->rights[1][5] = 'external';

        $this->rights[2][0] = 500402;
        $this->rights[2][1] = 'View supplier cost, margin and internal notes';
        $this->rights[2][3] = 0;
        $this->rights[2][4] = 'viewcost';

        $this->rights[3][0] = 500403;
        $this->rights[3][1] = 'Import and publish shipping prices';
        $this->rights[3][3] = 0;
        $this->rights[3][4] = 'import';

        $this->rights[4][0] = 500404;
        $this->rights[4][1] = 'Manage shipping price rules, ports and settings';
        $this->rights[4][3] = 0;
        $this->rights[4][4] = 'admin';

        $this->rights[5][0] = 500405;
        $this->rights[5][1] = 'Use shipping price service through API integrations';
        $this->rights[5][3] = 0;
        $this->rights[5][4] = 'api';

        $this->rights[6][0] = 500406;
        $this->rights[6][1] = 'Read internal shipping operations SOPs';
        $this->rights[6][3] = 0;
        $this->rights[6][4] = 'sopread';

        $this->rights[7][0] = 500407;
        $this->rights[7][1] = 'Create and edit shipping operations SOP drafts';
        $this->rights[7][3] = 0;
        $this->rights[7][4] = 'sopwrite';

        $this->rights[8][0] = 500408;
        $this->rights[8][1] = 'Publish, archive and restore shipping operations SOPs';
        $this->rights[8][3] = 0;
        $this->rights[8][4] = 'soppublish';

        $this->rights[9][0] = 500409;
        $this->rights[9][1] = 'Delete shipping operations SOPs';
        $this->rights[9][3] = 0;
        $this->rights[9][4] = 'sopdelete';

        $this->rights[10][0] = 500410;
        $this->rights[10][1] = 'Manage shipping operations SOP categories';
        $this->rights[10][3] = 0;
        $this->rights[10][4] = 'sopcategory';

        $this->rights[11][0] = 500411;
        $this->rights[11][1] = 'Submit an authenticated customer shipping booking from a published price';
        $this->rights[11][3] = 1;
        $this->rights[11][4] = 'booking_submit';
        $this->rights[11][5] = 'external';

        $this->rights[12][0] = 500412;
        $this->rights[12][1] = 'Search public website FCL rates through a restricted server gateway';
        $this->rights[12][3] = 0;
        $this->rights[12][4] = 'website_rate_search';

        $this->menu = array();
        $r = 0;
        $this->menu[$r++] = array(
            'fk_menu' => '0', 'type' => 'top', 'titre' => 'ShippingPrices',
            'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => '0',
            'url' => '/custom/chinaplanetshippingprices/index.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 99, 'enabled' => '$conf->chinaplanetshippingprices->enabled',
            'perms' => '!empty($user->admin) || $user->hasRight("chinaplanetshippingprices","read") || (empty($user->socid) && $user->hasRight("chinaplanetshippingprices","sopread"))',
            'user' => 2, 'picto' => 'ship',
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetshippingprices', 'type' => 'left',
            'titre' => 'PriceSearch', 'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => 'price_search',
            'url' => '/custom/chinaplanetshippingprices/index.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 100, 'enabled' => '$conf->chinaplanetshippingprices->enabled',
            'perms' => '$user->hasRight("chinaplanetshippingprices","read")', 'user' => 2,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetshippingprices', 'type' => 'left',
            'titre' => 'ControlCenter', 'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => 'price_control',
            'url' => '/custom/chinaplanetshippingprices/admin/dashboard.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 190, 'enabled' => '$conf->chinaplanetshippingprices->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetshippingprices","import") || $user->hasRight("chinaplanetshippingprices","admin") || !empty($user->admin)', 'user' => 0,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetshippingprices', 'type' => 'left',
            'titre' => 'ImportRates', 'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => 'rate_import',
            'url' => '/custom/chinaplanetshippingprices/admin/import.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 200, 'enabled' => '$conf->chinaplanetshippingprices->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetshippingprices","import") || !empty($user->admin)', 'user' => 0,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetshippingprices', 'type' => 'left',
            'titre' => 'PricingRules', 'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => 'pricing_rules',
            'url' => '/custom/chinaplanetshippingprices/admin/rules.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 210, 'enabled' => '$conf->chinaplanetshippingprices->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetshippingprices","admin") || !empty($user->admin)', 'user' => 0,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetshippingprices', 'type' => 'left',
            'titre' => 'PriceAdjust', 'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => 'price_adjust',
            'url' => '/custom/chinaplanetshippingprices/admin/adjust.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 205, 'enabled' => '$conf->chinaplanetshippingprices->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetshippingprices","admin") || !empty($user->admin)', 'user' => 0,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetshippingprices', 'type' => 'left',
            'titre' => 'PortMapping', 'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => 'port_mapping',
            'url' => '/custom/chinaplanetshippingprices/admin/ports.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 220, 'enabled' => '$conf->chinaplanetshippingprices->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetshippingprices","admin") || !empty($user->admin)', 'user' => 0,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetshippingprices', 'type' => 'left',
            'titre' => 'DisabledPrices', 'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => 'disabled_prices',
            'url' => '/custom/chinaplanetshippingprices/admin/disabled.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 215, 'enabled' => '$conf->chinaplanetshippingprices->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetshippingprices","admin") || !empty($user->admin)', 'user' => 0,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetshippingprices', 'type' => 'left',
            'titre' => 'SopLibrary', 'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => 'sop_library',
            'url' => '/custom/chinaplanetshippingprices/sop/index.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 230, 'enabled' => '$conf->chinaplanetshippingprices->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetshippingprices","sopread") || !empty($user->admin)', 'user' => 0,
        );
        $this->menu[$r++] = array(
            'fk_menu' => 'fk_mainmenu=chinaplanetshippingprices', 'type' => 'left',
            'titre' => 'SopCategories', 'mainmenu' => 'chinaplanetshippingprices', 'leftmenu' => 'sop_categories',
            'url' => '/custom/chinaplanetshippingprices/sop/categories.php',
            'langs' => 'chinaplanetshippingprices@chinaplanetshippingprices',
            'position' => 231, 'enabled' => '$conf->chinaplanetshippingprices->enabled && empty($user->socid)',
            'perms' => '$user->hasRight("chinaplanetshippingprices","sopcategory") || !empty($user->admin)', 'user' => 0,
        );
    }

    public function init($options = '')
    {
        $result = $this->_load_tables('/chinaplanetshippingprices/sql/');
        if ($result <= 0) return $result;

        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        $defaults = array(
            'CHINAPLANETSP_IGNORE_BELOW' => '1000',
            'CHINAPLANETSP_MARGIN_20GP' => '200',
            'CHINAPLANETSP_MARGIN_40GP' => '400',
            'CHINAPLANETSP_MARGIN_40HQ' => '400',
            'CHINAPLANETSP_MARGIN_45HQ' => '400',
            'CHINAPLANETSP_CURRENCY' => 'USD',
            'CHINAPLANETSP_CUSTOMER_HISTORY' => '1',
            'CHINAPLANETSP_HISTORY_MONTHS' => '12',
            'CHINAPLANETSP_DEFAULT_POL' => 'Guangzhou',
            'CHINAPLANETSP_LEGACY_VALIDITY_DAYS' => '30',
            'CHINAPLANETSP_CUSTOMER_SHOW_CARRIER' => '1',
        );
        foreach ($defaults as $key => $value) {
            if (!isset($GLOBALS['conf']->global->$key)) {
                dolibarr_set_const($this->db, $key, $value, 'chaine', 0, '', $GLOBALS['conf']->entity);
            }
        }
        $this->seedSopCategories();
        return $this->_init(array(), $options);
    }

    private function seedSopCategories()
    {
        $categories = array(
            'shipping_inquiry' => array('Shipping price inquiry', 'استعلام أسعار الشحن'),
            'booking_confirmation' => array('Booking and carrier confirmation', 'الحجز وتأكيد الناقل'),
            'exw' => array('EXW shipment', 'شحنة EXW'),
            'fob' => array('FOB shipment', 'شحنة FOB'),
            'dangerous_goods' => array('Dangerous goods', 'البضائع الخطرة'),
            'battery_cargo' => array('Battery cargo', 'شحن البطاريات'),
            'special_cargo' => array('Oil, powder, liquid and chemical cargo', 'الزيوت والمساحيق والسوائل والمواد الكيميائية'),
            'copy_brand' => array('Copy-brand goods', 'البضائع ذات العلامات المقلدة'),
            'export_documents' => array('Export documents', 'مستندات التصدير'),
            'cargox_egypt' => array('CargoX for Egypt', 'CargoX لمصر'),
            'saber_saudi' => array('Saber for Saudi Arabia', 'سابر للسعودية'),
            'customs_clearance' => array('Customs clearance', 'التخليص الجمركي'),
            'loading_warehouse' => array('Loading and warehouse operations', 'عمليات التحميل والمستودع'),
            'supplier_coordination' => array('Supplier coordination', 'التنسيق مع المورد'),
            'customer_communication' => array('Customer communication', 'التواصل مع العميل'),
            'exceptions_claims' => array('Shipping exceptions and claims', 'استثناءات ومطالبات الشحن'),
        );
        $entity = (int) $GLOBALS['conf']->entity;
        $userId = !empty($GLOBALS['user']->id) ? (int) $GLOBALS['user']->id : 0;
        $position = 10;
        foreach ($categories as $code => $labels) {
            $sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."chinaplanetsp_sop_category(entity,code,label,label_ar,position,status,fk_user_creat,date_creation) VALUES(".$entity.",'".$this->db->escape($code)."','".$this->db->escape($labels[0])."','".$this->db->escape($labels[1])."',".$position.",1,".$userId.",'".$this->db->idate(dol_now())."')";
            $this->db->query($sql);
            $position += 10;
        }
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
