<?php
require_once __DIR__.'/NewCatalogDiscovery.class.php';
require_once __DIR__.'/NewCatalogNativeScope.class.php';

/** M102: existing site credentials remain server-side. New native objects only.
 * There is deliberately no call to synchProduct/synchronizeProduct, update of an
 * existing Product, syncAll, category reconciliation, stock movement, or export.
 */
class NewCatalogNativeAdapter implements NewCatalogImportAdapter
{
    protected $db;
    protected $site;
    protected $user;
    private $remote;
    private $discovery;
    private $data=array();
    private $checked=false;
    private $diagnostic=array('stage'=>'TARGET','last_remote_status'=>null,'checks'=>array());
    public function getDiagnostic() { return $this->diagnostic; }

    public function __construct($db, $site, $user)
    {
        $this->db=$db; $this->site=$site; $this->user=$user;
    }

    /** Respect existing native rights, including Dolibarr's global admin entity0.
     * This never grants rights, changes user/entity context, or trusts a URL ID.
     */
    public static function allowsUser($user)
    {
        return !empty($user->statut) && empty($user->socid)
            && ((int)$user->entity===1 || ((int)$user->entity===0 && !empty($user->admin)))
            && $user->hasRight('ecommerceng','write') && $user->hasRight('produit','creer');
    }

    public static function prepareNewPrice($data)
    {
        if (is_array($data) && array_key_exists('price',$data) && $data['price']==='') {
            $data['price']=0;
            $data['price_requires_review']=true;
        }
        return $data;
    }

    public function assertTarget()
    {
        global $conf;
        if (!defined('DOL_VERSION') || DOL_VERSION!=='24.0.1' || (int)$conf->entity!==1
            || (int)$this->site->id!==1 || (int)$this->site->entity!==1
            || rtrim($this->site->webservice_address,'/')!=='https://cms.chinaplanet.org'
            || !self::allowsUser($this->user)
            || !empty($this->db->transaction_opened)
            || getDolGlobalString('ECOMMERCENG_WOOCOMMERCE_NO_VERIFY_SSL')
            || !empty($this->site->parameters['enable_product_plugin_wpml_support'])
            || ($this->site->parameters['product_variation_mode'] ?? '')==='all_to_one'
            || getDolGlobalString('ECOMMERCENG_SYNCHRO_PRODUCT_MODE_CUSTOM_1')
            || getDolGlobalString('PRODUIT_MULTIPRICES')
            || getDolGlobalString('ECOMMERCENG_DISABLED_PRODUCT_SYNCHRO_STOD')) {
            throw new NewCatalogImportFailure('TARGET_FAILED');
        }
        $pins=array(
            'CommonObject'=>'d24fc17e562553df9651274b6cba88b1a2604cbda1a2a5c324e8618183e25a16',
            'Categorie'=>'37c39c6d3de8ebd82aadf18cfe1add28bfd05b1d2281bb05180d2d458dfb3fcc',
            'eCommerceCategory'=>'c88720c4346d93377b095e3b644ea9feab98f832e54b1d533251ce60d55455f4',
            'Product'=>'772f09d416d3cbe4faf7d43f125fd6f80ef3db4821c231ab66065340c1a9b7d3',
            'eCommerceProduct'=>'5ca7a0373989ce7a4a06a83ca2df6528a0d7d9e068f88bb49df112829cc39aa7',
            'eCommerceRemoteAccess'=>'cc12cdf790a2a283a78daa687325f47116f672faeb2a22a04a5f214cb96bb987',
            'eCommerceRemoteAccessWoocommerce'=>'fbdaff2b74f4f85ba59dcc36db1aefedfa7cfba7edfecd1e5845596053728545',
            'eCommerceClientWooCommerceApi'=>'4905bf6f92d39b1c832319aac3b29c8e523c9ab63c25d124454fdf7d7369e4f9',
            'eCommerceClientApi'=>'7f5e4253fdee30a6039903680591cc83a213c85899d4ac8a91fe9eec08a0724d',
            'eCommerceSynchro'=>'2840ec62cfc78a58e9b3c3ef2325ac072fb642bd4fbc84c97d53e31cd1268364',
            'DoliDBMysqli'=>'fc6e1e6f194d6ce61380984207c95d8a3ed29ef463987bfead4079632707a064',
            'DoliDB'=>'fff88cb62729ee68784530c832ba73dc13d7103d16bebb0a41c2a4eaedb99c97',
        );
        foreach ($pins as $class=>$hash) {
            if (!class_exists($class) || hash_file('sha256',(new ReflectionClass($class))->getFileName())!==str_replace(' ','',$hash)) {
                throw new NewCatalogImportFailure('TARGET_FAILED');
            }
        }
        if (hash_file('sha256',(new ReflectionFunction('dol_syslog'))->getFileName())!=='3c6ac5fd3329a4295a7f854f3ef752e294e59031a9c047eb6ec40759d3c937a4') {
            throw new NewCatalogImportFailure('TARGET_FAILED');
        }
        // Native nested transactions only defer commit. All touched tables must
        // support rollback; never issue migrations or repair a missing schema.
        $tables=array('product','product_price','product_extrafields','ecommerce_product','categorie_product');
        if (getDolGlobalString('MAIN_PRODUCT_PERENTITY_SHARED')) $tables[]='product_perentity';
        foreach ($tables as $table) {
            $result=$this->query("SHOW TABLE STATUS WHERE Name='".$this->db->escape(MAIN_DB_PREFIX.$table)."'");
            $row=$this->db->fetch_object($result);
            if (!$row || strcasecmp($row->Engine ?? '', 'InnoDB')!==0) throw new NewCatalogImportFailure('SCHEMA_FAILED');
        }
        $this->requireUnique('product',array('entity','ref'));
        $this->requireUnique('ecommerce_product',array('fk_site','remote_id'));
        $this->requireUnique('ecommerce_product',array('fk_site','fk_product'));
        $this->checked=true;
    }

    public function discover($cursor, $limit)
    {
        $this->requireChecked();
        if (!$this->discovery) {
            $this->discovery=new NewCatalogDiscovery(function ($filters) { return $this->get('products',$filters); });
        }
        return $this->discovery->discover($cursor,$limit);
    }

    public function classify($remoteId)
    {
        $this->requireChecked(); $this->assertId($remoteId);
        if ($this->linkedProduct($remoteId)!==null) return 'linked';
        $data=$this->productData($remoteId);
        if ((int)$data['fk_product_type']===1 && !$this->user->hasRight('service','creer')) throw new NewCatalogImportFailure('TARGET_FAILED');
        $ref=$this->reference($data);
        $result=$this->query("SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE ref='".$this->db->escape($ref)."' LIMIT 1");
        return $this->db->num_rows($result)>0 ? 'conflict' : 'new';
    }

    /** Read-only conversion/category preflight for an operator-selected ID. */
    public function previewNewProduct($remoteId)
    {
        $classification=$this->classify($remoteId);
        if ($classification!=='new') return array('classification'=>$classification);
        $this->categories($this->productData($remoteId));
        return array('classification'=>'new','native_data_valid'=>true,'category_mapping_ready'=>true,
            'price_requires_review'=>!empty($this->productData($remoteId)['price_requires_review']));
    }

    public function createIfMissing($remoteId)
    {
        $this->requireChecked(); $this->assertId($remoteId);
        $data=$this->productData($remoteId);
        return NewCatalogNativeScope::call(function () use ($remoteId,$data) {
            if (!empty($this->db->transaction_opened) || !$this->db->begin()) throw new NewCatalogImportFailure('PERSISTENCE_NOT_CONFIRMED');
            try {
                if ($this->linkedProduct($remoteId)!==null) {
                    if (!$this->db->commit()) throw new NewCatalogImportFailure('PERSISTENCE_NOT_CONFIRMED');
                    return 'linked';
                }
                // Product::create does its own native uniqueness check and INSERT.
                // A concurrent SKU insertion fails creation; it cannot enter update.
                if ($this->classify($remoteId)!=='new') throw new NewCatalogImportFailure('NATIVE_PRODUCT_COLLISION');
                $categories=$this->categories($data);
                $product=$this->newProduct();
                $product->entity=1;
                $product->ref=$this->reference($data);
                $product->ref_ext=$this->site->name.'-'.$remoteId;
                $product->label=$data['label'];
                $product->description=$data['description'] ?? '';
                if (!empty($data['price_requires_review'])) {
                    $product->note_private='CMS price was not supplied. Zero is a catalogue placeholder. Product held not-for-sale; confirm the per-piece sale price before enabling sales or quotations.';
                    $product->note=$product->note_private;
                }
                $product->type=(int)$data['fk_product_type'];
                $product->finished=$data['finished'];
                $product->status=!empty($data['price_requires_review']) ? 0 : (int)($data['envente'] ?? 1);
                $product->status_buy=isset($data['enachat']) ? (int)$data['enachat'] : (int)(bool)getDolGlobalString('ECOMMERCENG_PRODUCT_IN_PURCHASE_WHEN_CREATED');
                $product->canvas=$data['canvas'] ?? '';
                $kind=$product->type===1 ? 'service' : 'product';
                foreach (array('buy','sell') as $direction) {
                    foreach (array(''=>'','_intra'=>'_intra','_export'=>'_export') as $suffix=>$unused) {
                        $key='accounting_'.$kind.'_'.($direction==='buy' ? 'buy' : 'sold').$suffix.'_account';
                        $field='accountancy_code_'.$direction.$suffix;
                        $product->$field=$this->site->parameters['default_account'][$key] ?? getDolGlobalString(strtoupper($key));
                    }
                }
                $product->url=$data['url'] ?? '';
                $product->barcode=-1;
                $product->stockable_product=(int)($data['extrafields']['ecommerceng_stockable_product'] ?? 1);
                foreach (array('weight','weight_units','width','width_units','height','height_units','length','length_units') as $field) {
                    if (isset($data[$field])) $product->$field=$data[$field];
                }
                // WooCommerce price remains per piece; no carton multiplication.
                $product->price_base_type=$this->site->ecommerce_price_type;
                if (!in_array($product->price_base_type,array('HT','TTC'),true)) throw new NewCatalogImportFailure('TARGET_FAILED');
                $product->tva_tx=$data['tax_rate'];
                if ($product->price_base_type==='TTC') $product->price_ttc=$data['price'];
                else $product->price=$data['price'];
                $defaults=(new eCommerceSynchro($this->db,$this->site))->getDefaultExtraFields('product',$this->site);
                $product->array_options=is_array($defaults) ? $defaults : array();
                foreach ($data['extrafields'] ?? array() as $key=>$value) $product->array_options['options_'.$key]=$value;
                $product->context['fromsyncofecommerceid']=1;
                $product->import_key='M102-CMS-site1';
                $id=$product->create($this->user,1);
                if ($id<=0) throw new NewCatalogImportFailure('PERSISTENCE_NOT_CONFIRMED');
                $this->linkNewProductCategories((int)$id,$categories);
                $link=$this->newLink();
                $link->fk_site=1; $link->fk_product=$id; $link->remote_id=$remoteId;
                $link->lang=$data['language'] ?? ''; $link->last_update=$data['last_update'];
                $link->other_data=$data['other_data'] ?? array();
                if ($link->create($this->user,1)<=0 || $this->linkedProduct($remoteId)!==(int)$id
                    || (int)$this->db->transaction_opened!==1) throw new NewCatalogImportFailure('PERSISTENCE_NOT_CONFIRMED');
                if (!$this->db->commit() || $this->linkedProduct($remoteId)!==(int)$id) throw new NewCatalogImportFailure('PERSISTENCE_NOT_CONFIRMED');
                return 'created';
            } catch (Throwable $e) {
                // Nested Dolibarr rollbacks only decrement a counter: unwind to
                // the outer level and issue the actual database rollback.
                while ((int)$this->db->transaction_opened>1) $this->db->rollback();
                if (!empty($this->db->transaction_opened)) $this->db->rollback();
                throw $e instanceof NewCatalogImportFailure ? $e : new NewCatalogImportFailure('PERSISTENCE_NOT_CONFIRMED');
            }
        });
    }

    protected function newProduct() { return new Product($this->db); }
    protected function newLink() { return new eCommerceProduct($this->db); }

    private function categories($data)
    {
        if ((int)$this->site->fk_cat_product<1) throw new NewCatalogImportFailure('TARGET_FAILED');
        $mapping=new eCommerceCategory($this->db);
        $remoteIds=$data['categories'] ?? array();
        $ids=$remoteIds ? $mapping->getDolibarrCategoryFromRemoteIds($remoteIds,1) : array();
        if (!is_array($ids) || count($ids)!==count($data['categories'] ?? array())) throw new NewCatalogImportFailure('CATEGORY_MAPPING_MISSING');
        $ids[]=(int)$this->site->fk_cat_product;
        foreach ($ids as $id) {
            $result=$this->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'categorie WHERE rowid='.(int)$id.' AND entity=1 AND type=0');
            if ($this->db->num_rows($result)!==1) throw new NewCatalogImportFailure('TARGET_FAILED');
        }
        return array_values(array_unique(array_map('intval',$ids)));
    }

    /** Native Categorie::add_type always fires CATEGORY_MODIFY with no notrigger
     * argument. Use its verified association schema for this NEW product only,
     * so an inbound import cannot enqueue a reverse category export. No DELETE,
     * UPDATE, existing product association or category field change is allowed.
     */
    private function linkNewProductCategories($productId,$ids)
    {
        if (getDolGlobalString('CATEGORIE_RECURSIV_ADD')) {
            foreach ($ids as $initial) {
                $parent=(int)$initial;$seen=array();
                for ($depth=0;$parent>0;$depth++) {
                    if ($depth>20 || isset($seen[$parent])) throw new NewCatalogImportFailure('SCHEMA_FAILED');
                    $seen[$parent]=true;
                    $result=$this->query('SELECT fk_parent FROM '.MAIN_DB_PREFIX.'categorie WHERE rowid='.$parent.' AND entity=1 AND type=0');
                    $row=$this->db->fetch_object($result);
                    if (!$row) throw new NewCatalogImportFailure('SCHEMA_FAILED');
                    $parent=(int)$row->fk_parent;
                    if ($parent>0) $ids[]=$parent;
                }
            }
        }
        $ids=array_values(array_unique($ids));
        foreach ($ids as $id) {
            $this->query('INSERT INTO '.MAIN_DB_PREFIX.'categorie_product (fk_categorie,fk_product) VALUES ('.(int)$id.','.$productId.')');
        }
        $result=$this->query('SELECT fk_categorie FROM '.MAIN_DB_PREFIX.'categorie_product WHERE fk_product='.$productId);
        if ($this->db->num_rows($result)!==count($ids)) throw new NewCatalogImportFailure('PERSISTENCE_NOT_CONFIRMED');
    }

    private function linkedProduct($remoteId)
    {
        $result=$this->query('SELECT l.fk_product,p.entity FROM '.MAIN_DB_PREFIX.'ecommerce_product l LEFT JOIN '.MAIN_DB_PREFIX."product p ON p.rowid=l.fk_product WHERE l.fk_site=1 AND l.remote_id='".$this->db->escape($remoteId)."'");
        $count=$this->db->num_rows($result);
        if (!$count) return null;
        $row=$this->db->fetch_object($result);
        if ($count!==1 || !$row || (int)$row->entity!==1) throw new NewCatalogImportFailure('PERSISTENCE_NOT_CONFIRMED');
        return (int)$row->fk_product;
    }

    private function productData($remoteId)
    {
        if (!isset($this->data[$remoteId])) {
            $parts=explode('|',$remoteId);
            $this->diagnostic['stage']='PARENT_READ';
            $parent=$this->get('products/'.$parts[0]);
            $raw=count($parts)===2 ? $this->get('products/'.$parts[0].'/variations/'.$parts[1]) : $parent;
            if ((string)($parent['id'] ?? '')!==$parts[0] || ($parent['status'] ?? '')!=='publish'
                || (count($parts)===2 && ((string)($raw['id'] ?? '')!==$parts[1]
                    || !in_array((int)$parts[1],array_map('intval',$parent['variations'] ?? array()),true)))) throw new NewCatalogImportFailure('REMOTE_READ_FAILED');
            $this->diagnostic['stage']='CONVERSION';
            $data=NewCatalogNativeScope::call(function () use ($raw,$parent,$parts) {
                return $this->remote->convertProductDataIntoProcessedData($raw,count($parts)===2 ? $parent : null);
            });
            // WooCommerce uses an empty string for an unset catalogue price.
            // Dolibarr's native Product::create represents this as zero. Keep an
            // explicit review marker; never treat that placeholder as a quote.
            $data=self::prepareNewPrice($data);
            $this->diagnostic['stage']='CONVERSION_VALIDATION';
            $this->diagnostic['checks']=array(
                'converted'=>is_array($data),
                'remote_id_matches'=>is_array($data) && (string)($data['remote_id'] ?? '')===$remoteId,
                'label_present'=>isset($data['label']),
                'price_present'=>isset($data['price']),
                'price_numeric'=>isset($data['price']) && is_numeric($data['price']),
                'price_requires_review'=>!empty($data['price_requires_review']),
                'tax_numeric'=>isset($data['tax_rate']) && is_numeric($data['tax_rate']),
                'last_update_valid'=>isset($data['last_update']) && is_string($data['last_update']) && (bool)preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$data['last_update']),
                'components_empty'=>empty($data['components']),
                'parent_supported'=>count($parts)===2 || empty($data['has_variations'])
            );
            if (!is_array($data) || (string)($data['remote_id'] ?? '')!==$remoteId
                || !isset($data['label'],$data['price'],$data['tax_rate'],$data['last_update'])
                || !is_numeric($data['price']) || (float)$data['price']<0
                || !is_numeric($data['tax_rate']) || !is_string($data['last_update'])
                || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',$data['last_update'])
                || !empty($data['components'])
                || (count($parts)===1 && !empty($data['has_variations']))) throw new NewCatalogImportFailure('REMOTE_READ_FAILED');
            $this->data[$remoteId]=$data;
        }
        return $this->data[$remoteId];
    }

    private function get($path, $filters=array())
    {
        return NewCatalogNativeScope::call(function () use ($path,$filters) {
            if (!$this->remote) {
                $site=clone $this->site; $site->debug=0; $site->wordpress_debug=0; $site->timeout=20;
                $this->remote=new eCommerceRemoteAccessWoocommerce($this->db,$site);
                if (!$this->remote->connect()) throw new NewCatalogImportFailure('REMOTE_READ_FAILED');
            }
            $status=0;
            $result=$this->remote->client->sendToApi(eCommerceClientApi::METHOD_GET,$path,array(GuzzleHttp\RequestOptions::QUERY=>$filters),false,$status);
            $this->diagnostic['last_remote_status']=(int)$status;
            if (!is_array($result)) throw new NewCatalogImportFailure('REMOTE_READ_FAILED');
            return $result;
        });
    }

    private function requireUnique($table,$columns)
    {
        $result=$this->query('SHOW INDEX FROM '.MAIN_DB_PREFIX.$table);
        $indexes=array();$invalid=array();
        while ($row=$this->db->fetch_object($result)) {
            if ((int)$row->Non_unique!==0) continue;
            if (!empty($row->Sub_part)) $invalid[$row->Key_name]=true;
            $indexes[$row->Key_name][(int)$row->Seq_in_index]=$row->Column_name;
        }
        sort($columns);
        foreach ($indexes as $name=>$fields) {
            if (isset($invalid[$name])) continue;
            $fields=array_values($fields);sort($fields);
            if ($fields===$columns) return;
        }
        throw new NewCatalogImportFailure('SCHEMA_FAILED');
    }

    private function query($sql)
    {
        $result=$this->db->query($sql);
        if (!$result) throw new NewCatalogImportFailure('SCHEMA_FAILED');
        return $result;
    }
    private function reference($data)
    {
        $ref=dol_sanitizeFileName(dol_string_nospecial(trim($data['ref'] ?? '')));
        return $ref!=='' ? $ref : 'WOO-'.str_replace('|','-',$data['remote_id']);
    }
    private function assertId($id)
    {
        if (!is_string($id) || !preg_match('/^[1-9][0-9]*(\|[1-9][0-9]*)?$/D',$id)) throw new NewCatalogImportFailure('REMOTE_ID_INVALID');
    }
    private function requireChecked()
    {
        if (!$this->checked) throw new NewCatalogImportFailure('TARGET_FAILED');
    }
}
