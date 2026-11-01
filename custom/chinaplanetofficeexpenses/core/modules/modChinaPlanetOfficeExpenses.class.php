<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/** Standalone operational draft module; lifecycle never creates/deletes business data. */
class modChinaPlanetOfficeExpenses extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->numero=500600;
        $this->name='ChinaPlanetOfficeExpenses';
        $this->rights_class='chinaplanetofficeexpenses';
        $this->family='financial';
        $this->module_position=93;
        $this->description='Office Expenses operational drafts and private receipts; accounting posting disabled';
        $this->version='0.2.0';
        $this->const_name='MAIN_MODULE_CHINAPLANETOFFICEEXPENSES';
        $this->picto='fa-receipt';
        $this->depends=array('modExpenseReport');
        $this->requiredby=array();$this->conflictwith=array();
        $this->phpmin=array(8,2);$this->need_dolibarr_version=array(24,0);
        $this->langfiles=array('chinaplanetofficeexpenses@chinaplanetofficeexpenses');
        $this->module_parts=array();$this->dirs=array();$this->config_page_url=array();
        $this->rights=array(
            1=>array(0=>500601,1=>'Read and save own office expense drafts',3=>0,4=>'draft'),
            2=>array(0=>500602,1=>'Administer Office Expenses configuration',3=>0,4=>'admin'),
            3=>array(0=>500603,1=>'Read explicitly assigned Office Expenses branches',3=>0,4=>'read'),
            4=>array(0=>500604,1=>'Manage branch assignments within own delegation ceiling',3=>0,4=>'manageaccess'),
        );
        $this->menu=array();
        $this->menu[] = array(
            'fk_menu'=>'0', 'type'=>'top', 'titre'=>'OfficeExpenses',
            'mainmenu'=>'chinaplanetexpenses','leftmenu'=>'0',
            'url'=>'/custom/chinaplanetofficeexpenses/index.php',
            'langs'=>'chinaplanetofficeexpenses@chinaplanetofficeexpenses','position'=>93,
            'enabled'=>'$conf->chinaplanetofficeexpenses->enabled && empty($user->socid) && $conf->entity == 1',
            'perms'=>'($user->admin && $user->id == 1) || (getDolGlobalInt("CPO_OE_STAFF_ACCESS_ENABLED") === 1 && $user->hasRight("chinaplanetofficeexpenses", "read"))','user'=>0,'prefix'=>'fa-receipt',
        );
    }
    public function init($options=''){return $this->_init(array(),$options);}
    public function remove($options=''){return $this->_remove(array(),$options);}
}
