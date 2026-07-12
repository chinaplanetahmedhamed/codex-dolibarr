<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modVisionTradingUI extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->db = $db;
        $this->numero = 500300;
        $this->rights_class = 'visiontradingui';
        $this->family = 'interface';
        $this->module_position = 90;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Vision Trading role-based dashboard design system';
        $this->version = '0.1.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'palette';
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('visiontradingui@visiontradingui');
        $this->phpmin = array(7, 2);
        $this->need_dolibarr_version = array(23, 0);
        $this->module_parts = array(
            'hooks' => array('main'),
            'triggers' => 0,
        );
        $this->const = array(
            1 => array('VISIONTRADINGUI_ENABLE_DASHBOARD', 'chaine', '0', 'Feature flag for Vision Trading UI assets and components', 0, 'current', 0),
        );
        $this->rights = array();
        $this->rights[1][0] = 500301;
        $this->rights[1][1] = 'Use Vision Trading dashboard UI';
        $this->rights[1][3] = 0;
        $this->rights[1][4] = 'read';
        $this->menu = array();
        $this->tabs = array();
        $this->dirs = array();
        $this->config_page_url = array('setup.php@visiontradingui');
    }

    public function init($options = '')
    {
        return $this->_init(array(), $options);
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
