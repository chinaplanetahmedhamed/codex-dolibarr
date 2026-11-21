<?php
/** Explicit additive installation; never run from a web read or save request. */
class InternalTaskExperienceMigration
{
    private $db;
    public function __construct($db) { $this->db = $db; }
    public function apply($entity)
    {
        $sql = file_get_contents(__DIR__.'/../sql/llx_cpo_task_details.sql');
        $sql = str_replace('CREATE TABLE llx_', 'CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX, $sql);
        if (!$this->db->query($sql)) throw new RuntimeException('Task details installation failed.');
        $check = $this->db->query('SELECT entity,fk_task,payload,create_hash,version,fk_user_creat,fk_user_modif,date_creation,tms FROM '.MAIN_DB_PREFIX.'cpo_task_details LIMIT 0');
        if (!$check) throw new RuntimeException('Task details schema mismatch.');
        $indexes = $this->db->query('SHOW INDEX FROM '.MAIN_DB_PREFIX.'cpo_task_details');
        $unique = array();
        while ($indexes && ($index = $this->db->fetch_object($indexes))) {
            if ($index->Key_name === 'uk_cpo_task_details' && (int) $index->Non_unique === 0) $unique[(int) $index->Seq_in_index] = $index->Column_name;
        }
        ksort($unique);
        if (array_values($unique) !== array('entity', 'fk_task')) throw new RuntimeException('Task details unique index mismatch.');
        require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
        $name = 'MAIN_MODULE_CHINAPLANETOPERATIONS_HOOKS';
        $existing = dolibarr_get_const($this->db, $name, (int) $entity);
        $contexts = array_filter(explode(':', (string) $existing));
        if (!in_array('login', $contexts, true)) {
            $contexts[] = 'login';
            if (dolibarr_set_const($this->db, $name, implode(':', $contexts), 'chaine', 0, '', (int) $entity) <= 0) throw new RuntimeException('Login hook registration failed.');
        }
    }
}
