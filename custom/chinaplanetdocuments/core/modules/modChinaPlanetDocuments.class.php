<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
class modChinaPlanetDocuments extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->db = $db;
        $this->numero = 500700; // M121 Documents allocation; Office keeps 500600.
        $this->name = 'ChinaPlanetDocuments';
        $this->family = 'projects';
        $this->description = 'Staff entry to the existing China Planet document editor';
        $this->version = '0.3.1';
        $this->const_name = 'MAIN_MODULE_CHINAPLANETDOCUMENTS';
        $this->picto = 'file';
        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(24, 0);
        $this->depends = array('modSociete', 'modProjet');
        $this->module_parts = array();
        $this->dirs = array();
        $this->langfiles = array('chinaplanetdocuments@chinaplanetdocuments');
        $this->rights_class = 'chinaplanetdocuments';
        $this->rights = array(array(500701, 'Read staff Documents workspace', 0, 0, 'workspace_read'));
        $this->menu = array(array(
            'fk_menu' => 0, 'type' => 'top', 'titre' => 'DocumentsWorkspace',
            'mainmenu' => 'chinaplanetdocuments', 'leftmenu' => '0',
            'url' => '/chinaplanetdocuments/index.php',
            'langs' => 'chinaplanetdocuments@chinaplanetdocuments', 'position' => 115,
            'enabled' => 'isModEnabled("chinaplanetdocuments")',
            'perms' => 'empty($user->socid) && (!empty($user->admin) || $user->hasRight("chinaplanetdocuments", "workspace_read"))',
            'user' => 0, 'picto' => 'file',
        ));
    }
    /** Read-only guard: no inferred reassignment/deletion of collided legacy grants. */
    private function permissionDefinitionsAreSafe()
    {
        global $conf;
        if (empty($conf->entity) || (int) $conf->entity <= 0) {
            $this->error = 'An active entity is required before Documents lifecycle changes';
            return false;
        }
        $sql = "SELECT id,entity,module,module_origin,perms,subperms FROM ".MAIN_DB_PREFIX."rights_def";
        $sql .= " WHERE id IN (500601,500701) OR LOWER(module)='chinaplanetdocuments' OR LOWER(module_origin)='chinaplanetdocuments'";
        $result = $this->db->query($sql);
        if (!$result) {
            $this->error = 'Documents rights inventory could not be verified; no lifecycle change applied';
            return false;
        }
        while ($right = $this->db->fetch_object($result)) {
            $module = strtolower((string) $right->module);
            $origin = strtolower((string) $right->module_origin);
            $owned = $module === 'chinaplanetdocuments' || $origin === 'chinaplanetdocuments';
            if ((int) $right->id === 500701) {
                if ($module !== 'chinaplanetdocuments' || ($origin !== '' && $origin !== 'chinaplanetdocuments')
                    || $right->perms !== 'workspace_read' || !empty($right->subperms)) {
                    $this->error = 'Documents permission 500701 is occupied by another definition; manual review required';
                    return false;
                }
            } elseif ($owned) {
                $this->error = 'Legacy Documents rights are ambiguous; preserve 500601 and review grants manually before lifecycle changes';
                return false;
            }
        }
        // Numeric-only orphan grants must not silently gain a new meaning.
        foreach (array('user_rights', 'usergroup_rights') as $table) {
            $sql = "SELECT r.fk_id FROM ".MAIN_DB_PREFIX.$table." r";
            $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."rights_def d ON d.id=r.fk_id AND d.entity=r.entity";
            $sql .= " WHERE r.fk_id=500701 AND d.id IS NULL";
            $result = $this->db->query($sql);
            if (!$result || $this->db->fetch_object($result)) {
                $this->error = 'Documents grant provenance could not be verified; manual review required';
                return false;
            }
        }
        return true;
    }
    public function init($options = '')
    {
        if (!$this->permissionDefinitionsAreSafe()) return -1;
        return $this->_init(array(), $options);
    }
    public function remove($options = '')
    {
        if (!$this->permissionDefinitionsAreSafe()) return -1;
        return $this->_remove(array(), $options);
    }
}
