<?php

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modChinaPlanetOperations extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->db = $db;
        $this->numero = 500500;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->family = 'projects';
        $this->module_position = 90;
        $this->description = 'Internal operations center, tasks, service workflows and controlled multilingual SOPs';
        $this->version = '2.5.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'fa-route';
        $this->dirs = array('/chinaplanetoperations/documents');
        $this->config_page_url = array('setup.php@chinaplanetoperations');
        $this->depends = array('modSociete', 'modProduct', 'modPropale', 'modChinaPlanetShippingPrices');
        $this->phpmin = array(7, 4);
        $this->need_dolibarr_version = array(23, 0);
        $this->module_parts = array('api' => 1);

        $this->rights = array();
        $this->rights_class = 'chinaplanetoperations';
        $definitions = array(
            array(500501, 'Read internal operations and SOPs', 'read'),
            array(500502, 'Create and edit operation SOP drafts', 'write'),
            array(500503, 'Approve translations and publish or archive SOPs', 'publish'),
            array(500504, 'Delete operation SOPs', 'delete'),
            array(500505, 'Manage operation SOP categories', 'category'),
            array(500506, 'Manage supported languages and translation settings', 'translation'),
            array(500507, 'Create and edit internal operations', 'operation_write'),
            array(500508, 'Manage internal tasks and Kanban', 'task_write'),
            array(500509, 'Manage operation documents and reviews', 'document_write'),
            array(500510, 'Prepare operation email drafts', 'email_write'),
            array(500511, 'Create public website intake requests', 'website_intake_create'),
            array(500512, 'Read public website intake requests', 'publicrequest_read'),
            array(500513, 'Update public website intake requests', 'publicrequest_update'),
            array(500514, 'Promote public website requests to operations', 'publicrequest_promote'),
            array(500515, 'Prepare draft quotations from public website requests', 'publicrequest_quote'),
            array(500516, 'Read own assigned internal tasks', 'task_read_own'),
            array(500517, 'Update own assigned internal tasks', 'task_update_own'),
            array(500518, 'Use secure internal task files', 'task_upload_own'),
            array(500519, 'Manage explicitly granted internal task teams', 'task_manage_team'),
            array(500520, 'Cancel, archive and restore scoped internal tasks', 'task_archive'),
            array(500521, 'Soft delete archived internal tasks', 'task_delete'),
            array(500522, 'Administer internal task board and team grants', 'task_admin'),
        );
        foreach ($definitions as $index => $definition) {
            $key = $index + 1;
            $this->rights[$key][0] = $definition[0];
            $this->rights[$key][1] = $definition[1];
            $this->rights[$key][3] = $definition[2] === 'read' ? 1 : 0;
            $this->rights[$key][4] = $definition[2];
        }

        $this->menu = array();
        $r = 0;
        $this->menu[$r++] = array(
            'fk_menu'=>'0', 'type'=>'top', 'titre'=>'OperationsCenter',
            'mainmenu'=>'chinaplanetoperations', 'leftmenu'=>'0',
            'url'=>'/custom/chinaplanetoperations/index.php',
            'langs'=>'chinaplanetoperations@chinaplanetoperations',
            'position'=>90, 'enabled'=>'$conf->chinaplanetoperations->enabled && empty($user->socid)',
            'perms'=>'!empty($user->admin) || $user->hasRight("chinaplanetoperations","read")',
            'user'=>0, 'picto'=>'fa-route',
        );
        $this->menu[$r++] = array(
            'fk_menu'=>'0', 'type'=>'top', 'titre'=>'SopLibrary',
            'mainmenu'=>'chinaplanetsops', 'leftmenu'=>'0',
            'url'=>'/custom/chinaplanetoperations/sop/index.php',
            'langs'=>'chinaplanetoperations@chinaplanetoperations',
            'position'=>91, 'enabled'=>'$conf->chinaplanetoperations->enabled && empty($user->socid)',
            'perms'=>'!empty($user->admin) || $user->hasRight("chinaplanetoperations","read")',
            'user'=>0, 'picto'=>'fa-book-open',
        );
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=chinaplanetoperations', 'type'=>'left',
            'titre'=>'OperationsDashboard', 'mainmenu'=>'chinaplanetoperations', 'leftmenu'=>'operations_dashboard',
            'url'=>'/custom/chinaplanetoperations/index.php',
            'langs'=>'chinaplanetoperations@chinaplanetoperations',
            'position'=>100, 'enabled'=>'$conf->chinaplanetoperations->enabled && empty($user->socid)',
            'perms'=>'!empty($user->admin) || $user->hasRight("chinaplanetoperations","read")', 'user'=>0,
        );
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=chinaplanetoperations', 'type'=>'left',
            'titre'=>'OperationsRegister', 'mainmenu'=>'chinaplanetoperations', 'leftmenu'=>'operations_register',
            'url'=>'/custom/chinaplanetoperations/operations/index.php',
            'langs'=>'chinaplanetoperations@chinaplanetoperations',
            'position'=>105, 'enabled'=>'$conf->chinaplanetoperations->enabled && empty($user->socid)',
            'perms'=>'!empty($user->admin) || $user->hasRight("chinaplanetoperations","read")', 'user'=>0,
        );
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=chinaplanetoperations', 'type'=>'left',
            'titre'=>'InternalTaskBoard', 'mainmenu'=>'chinaplanetoperations', 'leftmenu'=>'operations_tasks',
            'url'=>'/custom/chinaplanetoperations/tasks/board.php',
            'langs'=>'chinaplanetoperations@chinaplanetoperations',
            'position'=>106, 'enabled'=>'$conf->chinaplanetoperations->enabled && empty($user->socid)',
            'perms'=>'!empty($user->admin) || $user->hasRight("chinaplanetoperations","task_read_own") || $user->hasRight("chinaplanetoperations","task_manage_team") || $user->hasRight("chinaplanetoperations","task_admin")', 'user'=>0,
        );
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=chinaplanetoperations', 'type'=>'left',
            'titre'=>'InternalTaskTeamGrants', 'mainmenu'=>'chinaplanetoperations', 'leftmenu'=>'operations_task_grants',
            'url'=>'/custom/chinaplanetoperations/tasks/team_admin.php',
            'langs'=>'chinaplanetoperations@chinaplanetoperations',
            'position'=>107, 'enabled'=>'$conf->chinaplanetoperations->enabled && empty($user->socid)',
            'perms'=>'!empty($user->admin) || $user->hasRight("chinaplanetoperations","task_admin")', 'user'=>0,
        );
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=chinaplanetoperations', 'type'=>'left',
            'titre'=>'PublicWebsiteRequests', 'mainmenu'=>'chinaplanetoperations', 'leftmenu'=>'operations_public_requests',
            'url'=>'/custom/chinaplanetoperations/operations/public_requests.php',
            'langs'=>'chinaplanetoperations@chinaplanetoperations',
            'position'=>108, 'enabled'=>'$conf->chinaplanetoperations->enabled && empty($user->socid)',
            'perms'=>'!empty($user->admin) || $user->hasRight("chinaplanetoperations","publicrequest_read")', 'user'=>0,
        );
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=chinaplanetoperations', 'type'=>'left',
            'titre'=>'SopLibrary', 'mainmenu'=>'chinaplanetoperations', 'leftmenu'=>'operations_sop',
            'url'=>'/custom/chinaplanetoperations/sop/index.php',
            'langs'=>'chinaplanetoperations@chinaplanetoperations',
            'position'=>110, 'enabled'=>'$conf->chinaplanetoperations->enabled && empty($user->socid)',
            'perms'=>'!empty($user->admin) || $user->hasRight("chinaplanetoperations","read")', 'user'=>0,
        );
        $this->menu[$r++] = array(
            'fk_menu'=>'fk_mainmenu=chinaplanetoperations', 'type'=>'left',
            'titre'=>'SopCategories', 'mainmenu'=>'chinaplanetoperations', 'leftmenu'=>'operations_sop_categories',
            'url'=>'/custom/chinaplanetoperations/sop/categories.php',
            'langs'=>'chinaplanetoperations@chinaplanetoperations',
            'position'=>120, 'enabled'=>'$conf->chinaplanetoperations->enabled && empty($user->socid)',
            'perms'=>'!empty($user->admin) || $user->hasRight("chinaplanetoperations","category")', 'user'=>0,
        );
    }

    public function init($options = '')
    {
        require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/class/InternalTaskMigration.class.php';
        $migration = new InternalTaskMigration($this->db);
        foreach (InternalTaskMigration::rightCodes() as $rightId => $rightCode) {
            if ($migration->rightCollision($rightId)) {
                $this->error = 'M058 right ID collision: '.(int) $rightId;
                return -1;
            }
        }
        $result = $this->_load_tables('/chinaplanetoperations/sql/');
        if ($result <= 0) return $result;
        try {
            $migration->migrate();
        } catch (Throwable $e) {
            $this->error = 'Unable to install the internal task board: '.$e->getMessage();
            return -1;
        }
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        $defaults = array(
            'CHINAPLANETOPERATIONS_DEFAULT_CONTENT_LANG'=>'en_US',
            'CHINAPLANETOPERATIONS_REQUIRED_LANGS'=>'en_US,ar_SA,zh_CN',
        );
        foreach ($defaults as $key=>$value) {
            if (!isset($GLOBALS['conf']->global->$key)) dolibarr_set_const($this->db, $key, $value, 'chaine', 0, '', $GLOBALS['conf']->entity);
        }
        require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
        $extraFields = new ExtraFields($this->db);
        if ($extraFields->addExtraField('cp_woo_product_id', 'WooCommerce product ID', 'int', 500, '20', 'product', 0, 0, '', '', 1, '', '1', 'Authoritative mapping to the WordPress/WooCommerce product.', '', (int) $GLOBALS['conf']->entity, '', '1', 0, 0) < 0) return -1;
        if ($extraFields->addExtraField('cp_woo_variation_id', 'WooCommerce variation ID', 'int', 501, '20', 'product', 0, 0, '', '', 1, '', '1', 'Optional authoritative mapping to a WooCommerce variation.', '', (int) $GLOBALS['conf']->entity, '', '1', 0, 0) < 0) return -1;
        $this->seedCategories();
        try {
            require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetoperations/lib/standard_sops.lib.php';
            cpo_seed_standard_sops($this->db, (int)$GLOBALS['conf']->entity, !empty($GLOBALS['user']->id) ? (int)$GLOBALS['user']->id : 1);
        } catch (Throwable $e) {
            $this->error = 'Unable to install the standard SOP library: '.$e->getMessage();
            return -1;
        }
        return $this->_init(array(), $options);
    }

    private function seedCategories()
    {
        $categories = array(
            'customer_intake'=>array('Customer intake','استقبال العميل','客户接洽'),
            'order_management'=>array('Order management','إدارة الطلبات','订单管理'),
            'sourcing'=>array('Product sourcing','البحث عن المنتجات والموردين','产品采购'),
            'purchasing'=>array('Purchasing','المشتريات','采购执行'),
            'supplier_coordination'=>array('Supplier coordination','التنسيق مع المورد','供应商协调'),
            'factory_audit'=>array('Factory audit','تدقيق المصانع','工厂审核'),
            'quality_control'=>array('Product quality inspection','فحص جودة المنتجات','产品质量检验'),
            'shipping_inquiry'=>array('Shipping price inquiry','استعلام أسعار الشحن','运价询价'),
            'shipping_booking'=>array('Shipping booking','حجز الشحن','订舱操作'),
            'export_documents'=>array('Export documents','مستندات التصدير','出口单证'),
            'loading_warehouse'=>array('Loading and warehouse','التحميل والمستودعات','装柜与仓库'),
            'customs'=>array('Customs and country services','الجمارك وخدمات الدول','海关与目的国服务'),
            'customer_communication'=>array('Customer communication','التواصل مع العميل','客户沟通'),
            'exceptions_claims'=>array('Exceptions and claims','الاستثناءات والمطالبات','异常与索赔'),
            'internal_tasks'=>array('Internal daily tasks','المهام الداخلية اليومية','内部日常任务'),
        );
        $entity = (int) $GLOBALS['conf']->entity;
        $userId = !empty($GLOBALS['user']->id) ? (int) $GLOBALS['user']->id : 0;
        $position = 10;
        foreach ($categories as $code=>$labels) {
            $sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."cpo_sop_category(entity,code,position,status,fk_user_creat,date_creation) VALUES(".$entity.",'".$this->db->escape($code)."',".$position.",1,".$userId.",'".$this->db->idate(dol_now())."')";
            $this->db->query($sql);
            $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."cpo_sop_category WHERE entity=".$entity." AND code='".$this->db->escape($code)."'");
            $category = $res ? $this->db->fetch_object($res) : null;
            if ($category) {
                foreach (array('en_US'=>0,'ar_SA'=>1,'zh_CN'=>2) as $langCode=>$labelIndex) {
                    $this->db->query("INSERT IGNORE INTO ".MAIN_DB_PREFIX."cpo_sop_category_lang(entity,fk_category,lang_code,label) VALUES(".$entity.",".(int)$category->rowid.",'".$langCode."','".$this->db->escape($labels[$labelIndex])."')");
                }
            }
            $position += 10;
        }
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
