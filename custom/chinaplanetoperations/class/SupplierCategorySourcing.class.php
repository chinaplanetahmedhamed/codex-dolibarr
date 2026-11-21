<?php
require_once __DIR__.'/SupplierProductCandidate.class.php';

/** Internal, live category-based sourcing. Native quotes remain the pricing authority. */
class SupplierCategorySourcing
{
    const PAGE_SIZE = 50;
    const MAX_MAPS = 200;
    private $db;
    private $entity;
    private $advanced;

    public function __construct($db, $entity, $advanced = false)
    {
        if (!is_int($entity) || $entity < 1) throw new InvalidArgumentException('Invalid entity');
        $this->db = $db;
        $this->entity = $entity;
        $this->advanced = (bool) $advanced;
    }

    public static function canManage($user, $entity, $advanced = false)
    {
        return SupplierProductCandidate::canRead($user, $entity, $advanced)
            && $user->hasRight('categorie', 'lire') && $user->hasRight('categorie', 'creer');
    }

    /** Only native same-entity product/supplier category metadata; never customer categories. */
    public function categories($user)
    {
        $this->authorize($user, true);
        return $this->categoryRows();
    }

    public function mappings($user)
    {
        $this->authorize($user, true);
        return $this->mapRows();
    }

    /** Save by immutable typed IDs, with optimistic versioning; never mutate memberships. */
    public function save($productCategory, $supplierCategory, $descendants, $enabled, $expectedVersion, $user)
    {
        $this->authorize($user, true);
        $this->id($productCategory); $this->id($supplierCategory);
        if (!is_bool($descendants) || !is_bool($enabled) || !is_int($expectedVersion) || $expectedVersion < 0
            || !empty($this->db->transaction_opened)) throw new InvalidArgumentException('Invalid mapping');
        if (!$this->db->begin()) throw new RuntimeException('Sourcing persistence failed');
        try {
            $this->requireCategory($productCategory, 0);
            $this->requireCategory($supplierCategory, 1);
            $where = 'entity='.$this->entity.' AND fk_product_category='.$productCategory.' AND fk_supplier_category='.$supplierCategory;
            $q = $this->query('SELECT rowid,version,include_descendants,status FROM '.MAIN_DB_PREFIX.'cpo_supplier_category_map WHERE '.$where);
            $old = $this->db->fetch_object($q);
            if ($old) {
                if ((int) $old->version !== $expectedVersion) throw new RuntimeException('Mapping changed; reload');
                if ((int) $old->include_descendants !== (int) $descendants || (int) $old->status !== (int) $enabled) {
                    $q = $this->query('UPDATE '.MAIN_DB_PREFIX.'cpo_supplier_category_map SET include_descendants='.(int) $descendants
                        .',status='.(int) $enabled.',version=version+1,fk_user_modif='.(int) $user->id
                        .",date_modification='".gmdate('Y-m-d H:i:s')."' WHERE ".$where.' AND version='.$expectedVersion);
                    if ($this->db->affected_rows($q) !== 1) throw new RuntimeException('Mapping changed; reload');
                }
            } else {
                if ($expectedVersion !== 0 || count($this->mapRows()) >= self::MAX_MAPS) throw new RuntimeException('Mapping unavailable');
                $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_supplier_category_map'
                    .' (entity,fk_product_category,fk_supplier_category,include_descendants,status,version,fk_user_creat,date_creation,fk_user_modif,date_modification) VALUES ('
                    .$this->entity.','.$productCategory.','.$supplierCategory.','.(int) $descendants.','.(int) $enabled.',1,'.(int) $user->id
                    .",'".gmdate('Y-m-d H:i:s')."',".(int) $user->id.",'".gmdate('Y-m-d H:i:s')."')");
            }
            if (!$this->db->commit()) throw new RuntimeException('Sourcing persistence failed');
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /** Paginated union, deduplicated by pair; adding/removing native membership takes effect immediately. */
    public function candidates($side, $id, $user, $page = 0)
    {
        $this->authorize($user);
        $this->id($id);
        if (!in_array($side, array('product','supplier'), true) || !is_int($page) || $page < 0 || $page > 100000) {
            throw new InvalidArgumentException('Invalid page');
        }
        $record = $side === 'product' ? 'product' : 'societe';
        $condition = $side === 'product' ? 'fk_product_type=0' : 'fournisseur=1';
        $q = $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.$record.' WHERE rowid='.$id.' AND entity='.$this->entity.' AND '.$condition);
        if ($this->db->num_rows($q) !== 1) throw new RuntimeException('Record unavailable');
        $filter = $side === 'product' ? 'fk_product='.$id : 'fk_supplier='.$id;
        $parts = array('SELECT fk_product,fk_supplier,1 AS has_direct,0 AS has_category,NULL AS category_map_id FROM '.MAIN_DB_PREFIX
            .'cpo_product_supplier_candidate WHERE entity='.$this->entity.' AND status=1 AND '.$filter);
        $maps = $this->mapRows();
        $categories = $maps ? $this->categoryRows() : array();
        foreach ($maps as $m) {
            if ((int) $m['status'] !== 1) continue;
            $ids = $this->productCategoryIds((int) $m['fk_product_category'], !empty($m['include_descendants']), $categories);
            if (!$ids) continue;
            $parts[] = 'SELECT cp.fk_product,cs.fk_soc AS fk_supplier,0 AS has_direct,1 AS has_category,'.(int) $m['rowid'].' AS category_map_id'
                .' FROM '.MAIN_DB_PREFIX.'categorie_product cp JOIN '.MAIN_DB_PREFIX.'categorie_fournisseur cs'
                .' ON cs.fk_categorie='.(int) $m['fk_supplier_category']
                .' JOIN '.MAIN_DB_PREFIX.'societe active_supplier ON active_supplier.rowid=cs.fk_soc'
                .' AND active_supplier.entity='.$this->entity.' AND active_supplier.fournisseur=1 AND active_supplier.status=1'
                .' WHERE cp.fk_categorie IN ('.implode(',', $ids).') AND '
                .($side === 'product' ? 'cp.fk_product='.$id : 'cs.fk_soc='.$id);
        }
        $pairs = '(SELECT fk_product,fk_supplier,MAX(has_direct) AS has_direct,MAX(has_category) AS has_category,MIN(category_map_id) AS category_map_id FROM ('
            .implode(' UNION ALL ', $parts).') sourcing_matches GROUP BY fk_product,fk_supplier) pairs';
        $from = ' FROM '.$pairs
            .' JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=pairs.fk_product AND p.entity='.$this->entity.' AND p.fk_product_type=0'
            .' JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=pairs.fk_supplier AND s.entity='.$this->entity.' AND s.fournisseur=1';
        $q = $this->query('SELECT COUNT(*) AS total'.$from);
        $total = (int) $this->db->fetch_object($q)->total;
        $page = min($page, max(0, (int) ceil($total / self::PAGE_SIZE) - 1));
        $q = $this->query('SELECT pairs.fk_product,pairs.fk_supplier,pairs.has_direct,pairs.has_category,p.ref,p.label,'
            .'s.nom AS supplier_name,s.status AS supplier_status,c.product_kind,c.evidence_url,'
            .'m.fk_product_category,m.fk_supplier_category,pc.label AS product_category_name,sc.label AS supplier_category_name,'
            .'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'product_fournisseur_price q WHERE q.entity='.$this->entity
            .' AND q.fk_product=pairs.fk_product AND q.fk_soc=pairs.fk_supplier AND q.unitprice IS NOT NULL) AS has_native_price'
            .$from.' LEFT JOIN '.MAIN_DB_PREFIX.'cpo_product_supplier_candidate c ON c.entity='.$this->entity
            .' AND c.status=1 AND c.fk_product=pairs.fk_product AND c.fk_supplier=pairs.fk_supplier'
            .' LEFT JOIN '.MAIN_DB_PREFIX.'cpo_supplier_category_map m ON m.rowid=pairs.category_map_id AND m.entity='.$this->entity.' AND m.status=1'
            .' LEFT JOIN '.MAIN_DB_PREFIX.'categorie pc ON pc.rowid=m.fk_product_category AND pc.entity='.$this->entity.' AND pc.type=0'
            .' LEFT JOIN '.MAIN_DB_PREFIX.'categorie sc ON sc.rowid=m.fk_supplier_category AND sc.entity='.$this->entity.' AND sc.type=1'
            .' ORDER BY '.($side === 'product' ? 's.nom,s.rowid' : 'p.ref,p.rowid')
            .' LIMIT '.self::PAGE_SIZE.' OFFSET '.($page * self::PAGE_SIZE));
        return array('rows'=>$this->all($q), 'total'=>$total, 'page'=>$page, 'page_size'=>self::PAGE_SIZE,
            'side'=>$side, 'id'=>$id, 'can_manage'=>self::canManage($user, $this->entity, $this->advanced));
    }

    private function productCategoryIds($root, $descendants, $categories)
    {
        $byId = array(); $children = array();
        foreach ($categories as $c) {
            if ((int) $c['type'] !== 0) continue;
            $byId[(int) $c['rowid']] = $c;
            $children[(int) $c['fk_parent']][] = (int) $c['rowid'];
        }
        if (!isset($byId[$root])) return array();
        $seen = array(); $pending = array($root);
        while ($pending) {
            $id = array_pop($pending);
            if (isset($seen[$id])) continue; // Corrupt cycles cannot loop or escape this entity/type.
            $seen[$id] = true;
            if ($descendants) foreach ($children[$id] ?? array() as $child) $pending[] = $child;
        }
        return array_keys($seen);
    }

    private function mapRows()
    {
        $q = $this->query('SELECT m.*,p.label AS product_category,s.label AS supplier_category FROM '.MAIN_DB_PREFIX.'cpo_supplier_category_map m'
            .' JOIN '.MAIN_DB_PREFIX.'categorie p ON p.rowid=m.fk_product_category AND p.entity=m.entity AND p.type=0'
            .' JOIN '.MAIN_DB_PREFIX.'categorie s ON s.rowid=m.fk_supplier_category AND s.entity=m.entity AND s.type=1'
            .' WHERE m.entity='.$this->entity.' ORDER BY m.rowid LIMIT '.(self::MAX_MAPS + 1));
        $rows = $this->all($q);
        if (count($rows) > self::MAX_MAPS) throw new RuntimeException('Mapping limit exceeded');
        return $rows;
    }

    private function categoryRows()
    {
        $rows = $this->all($this->query('SELECT rowid,entity,type,label,fk_parent FROM '.MAIN_DB_PREFIX.'categorie'
            .' WHERE entity='.$this->entity.' AND type IN (0,1) ORDER BY type,label,rowid LIMIT 10001'));
        if (count($rows) > 10000) throw new RuntimeException('Category limit exceeded');
        return $rows;
    }

    private function requireCategory($id, $type)
    {
        $q = $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'categorie WHERE entity='.$this->entity.' AND type='.$type.' AND rowid='.$id);
        if ($this->db->num_rows($q) !== 1) throw new RuntimeException('Category unavailable');
    }

    private function authorize($user, $manage = false)
    {
        if (!SupplierProductCandidate::canRead($user, $this->entity, $this->advanced)
            || ($manage && !self::canManage($user, $this->entity, $this->advanced))) throw new RuntimeException('Access denied');
    }

    private function id($id)
    {
        if (!is_int($id) || $id < 1) throw new InvalidArgumentException('Invalid record');
    }

    private function all($q)
    {
        $rows = array();
        while ($r = $this->db->fetch_object($q)) $rows[] = (array) $r;
        return $rows;
    }

    private function query($sql)
    {
        $q = $this->db->query($sql);
        if (!$q) throw new RuntimeException('Sourcing persistence failed');
        return $q;
    }
}
