<?php

/** Internal sourcing relationships, independent of quotations and purchase costs. */
class SupplierProductCandidate
{
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

    public static function canRead($user, $entity, $advanced = false)
    {
        return is_object($user) && isset($user->id, $user->statut) && (int) $user->id > 0
            && (int) $user->statut === 1 && empty($user->socid)
            && ((int) $user->entity === $entity || ((int) $user->entity === 0 && !empty($user->admin)))
            && $user->hasRight('product', 'read') && $user->hasRight('societe', 'lire')
            && (!$advanced || $user->hasRight('product', 'product_advance', 'read_supplier_prices'))
            && ($user->hasRight('fournisseur', 'lire') || $user->hasRight('supplier_order', 'read')
                || $user->hasRight('supplier_invoice', 'read'));
    }

    public function forProduct($productId, $user)
    {
        $this->authorize($user);
        $this->requireProduct($this->id($productId));
        return $this->rows('c.fk_product='.(int) $productId);
    }

    public function forSupplier($supplierId, $user)
    {
        $this->authorize($user);
        $this->requireSupplier($this->id($supplierId), false);
        return $this->rows('c.fk_supplier='.(int) $supplierId);
    }

    /** One bounded operator batch. No HTTP write endpoint or automatic matcher. */
    public function importPairs($pairs, $user, $batchKey)
    {
        $this->authorize($user);
        if (!$user->hasRight('produit', 'creer')) throw new RuntimeException('Access denied');
        if (!is_array($pairs) || count($pairs) < 1 || count($pairs) > 100
            || !is_string($batchKey) || !preg_match('/^[A-Z0-9_-]{1,64}$/D', $batchKey)
            || !empty($this->db->transaction_opened)) throw new InvalidArgumentException('Invalid batch');
        $seen = array();
        foreach ($pairs as $p) {
            if (!is_array($p) || !isset($p['product_id'], $p['supplier_id'], $p['kind'], $p['evidence_url'])) {
                throw new InvalidArgumentException('Invalid relationship');
            }
            $key = $this->id($p['product_id']).':'.$this->id($p['supplier_id']);
            if (isset($seen[$key]) || !in_array($p['kind'], array('pumps','engines','garden','sprayers','hoses'), true)
                || !self::validEvidence($p['evidence_url'])) throw new InvalidArgumentException('Invalid relationship');
            $seen[$key] = true;
        }
        if (!$this->db->begin()) throw new RuntimeException('Relationship persistence failed');
        $created = 0;
        $existing = 0;
        try {
            foreach ($pairs as $p) {
                $this->requireProduct($p['product_id']);
                $this->requireSupplier($p['supplier_id'], true);
                $where = 'entity='.$this->entity.' AND fk_product='.$p['product_id'].' AND fk_supplier='.$p['supplier_id'];
                $q = $this->query('SELECT rowid,product_kind,evidence_url,batch_key,status FROM '.MAIN_DB_PREFIX.'cpo_product_supplier_candidate WHERE '.$where);
                if ($this->db->num_rows($q) > 1) throw new RuntimeException('Relationship conflict');
                if ($old = $this->db->fetch_object($q)) {
                    if ($old->product_kind !== $p['kind'] || $old->evidence_url !== $p['evidence_url']
                        || $old->batch_key !== $batchKey || (int) $old->status !== 1) throw new RuntimeException('Relationship conflict');
                    $existing++;
                    continue;
                }
                $this->query('INSERT INTO '.MAIN_DB_PREFIX.'cpo_product_supplier_candidate'
                    .' (entity,fk_product,fk_supplier,product_kind,evidence_url,batch_key,fk_user_creat,date_creation,status) VALUES ('
                    .$this->entity.','.$p['product_id'].','.$p['supplier_id'].',\''.$this->db->escape($p['kind']).'\',\''
                    .$this->db->escape($p['evidence_url']).'\',\''.$this->db->escape($batchKey).'\','.(int) $user->id
                    .',\''.gmdate('Y-m-d H:i:s').'\',1)');
                $created++;
            }
            if (!$this->db->commit()) throw new RuntimeException('Relationship persistence failed');
            return array('created'=>$created, 'existing'=>$existing, 'total'=>count($pairs));
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public static function validEvidence($url)
    {
        if (!is_string($url) || strlen($url) > 512 || preg_match('/[\x00-\x20\x7f]/', $url)) return false;
        $p = parse_url($url);
        return is_array($p) && ($p['scheme'] ?? '') === 'https' && !empty($p['host'])
            && preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/Di', $p['host'])
            && !isset($p['user']) && !isset($p['pass']) && !isset($p['port']);
    }

    private function authorize($user)
    {
        if (!self::canRead($user, $this->entity, $this->advanced)) throw new RuntimeException('Access denied');
    }

    private function id($id)
    {
        if (!is_int($id) || $id < 1) throw new InvalidArgumentException('Invalid record');
        return $id;
    }

    private function requireProduct($id)
    {
        $q = $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'product WHERE rowid='.(int) $id
            .' AND entity='.$this->entity.' AND fk_product_type=0');
        if ($this->db->num_rows($q) !== 1) throw new RuntimeException('Record unavailable');
    }

    private function requireSupplier($id, $active)
    {
        $q = $this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE rowid='.(int) $id
            .' AND entity='.$this->entity.' AND fournisseur=1'.($active ? ' AND status=1' : ''));
        if ($this->db->num_rows($q) !== 1) throw new RuntimeException('Record unavailable');
    }

    private function rows($where)
    {
        $q = $this->query('SELECT c.rowid,c.fk_product,c.fk_supplier,c.product_kind,c.evidence_url,'
            .'p.ref,p.label,s.nom AS supplier_name,s.status AS supplier_status,'
            .'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'product_fournisseur_price q WHERE q.entity=c.entity'
            .' AND q.fk_product=c.fk_product AND q.fk_soc=c.fk_supplier AND q.unitprice IS NOT NULL) AS has_native_price'
            .' FROM '.MAIN_DB_PREFIX.'cpo_product_supplier_candidate c'
            .' JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=c.fk_product AND p.entity=c.entity AND p.fk_product_type=0'
            .' JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=c.fk_supplier AND s.entity=c.entity AND s.fournisseur=1'
            .' WHERE c.entity='.$this->entity.' AND c.status=1 AND '.$where.' ORDER BY s.nom,p.ref,c.rowid LIMIT 501');
        $rows = array();
        while ($r = $this->db->fetch_object($q)) $rows[] = (array) $r;
        if (count($rows) > 500) throw new RuntimeException('Relationship list limit exceeded');
        return $rows;
    }

    private function query($sql)
    {
        $q = $this->db->query($sql);
        if (!$q) throw new RuntimeException('Relationship persistence failed');
        return $q;
    }
}
