<?php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
if (!defined('MAIN_DB_PREFIX')) define('MAIN_DB_PREFIX', 'llx_');
$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetshippingprices/class/PortNameResolver.class.php';

function m062Assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function m062Ports(PortNameResolver $resolver, $raw) {
    $resolved = $resolver->resolveList($raw, true);
    if ($resolved['status'] !== 'matched') return array();
    return array_map(function ($match) { return $match['port_name'].'|'.$match['country_code']; }, $resolved['matches']);
}

$resolver = new PortNameResolver(null, 1, 0.90);
$cases = array(
    'AIN SOKHNA'=>array('SOKHNA|EG'),
    'UMM AL QAWAIN'=>array('UMM AL QUWAIN|AE'),
    'SKIKDA'=>array('SKIKDA|DZ'), 'ORAN'=>array('ORAN|DZ'), 'KHOMS'=>array('AL KHUMS|LY'),
    'ORAN CONTAINER TERMINAL'=>array('ORAN|DZ'), 'BEJAIA'=>array('BEJAIA|DZ'),
    'AGADIR, Morocco'=>array('AGADIR|MA'), 'Nador'=>array('NADOR|MA'),
    'SHEKOU-- SKIKDA, ANNABA'=>array('SKIKDA|DZ','ANNABA|DZ'),
    'NANSHA-- SKIKDA, ANNABA'=>array('SKIKDA|DZ','ANNABA|DZ'),
    'Bejaia'=>array('BEJAIA|DZ'), 'Oran/CASABLANCA'=>array('ORAN|DZ','CASABLANCA|MA'),
    'DJEN DJEN'=>array('DJEN-DJEN|DZ'), 'BEJAIA/SKIKDA'=>array('BEJAIA|DZ','SKIKDA|DZ'),
    'ALGIERS （sea port，dry port到港之后随机）'=>array('ALGIERS|DZ'),
    'ORAN （sea port，dry port到港之后随机）'=>array('ORAN|DZ'),
    'ANNABA （sea port，dry port到港之后随机）'=>array('ANNABA|DZ'),
    'ALGER（dry port：EL HAMIZ & OUED BRICK两个港口随机）'=>array('ALGIERS|DZ'),
    'GHAZAOUET'=>array('GHAZAOUET|DZ'), 'MOSTAGANEM'=>array('MOSTAGANEM|DZ'), 'EL KHOMS'=>array('AL KHUMS|LY'),
    'SFAX & SOUSSE'=>array('SFAX|TN','SOUSSE|TN'), 'SUDAN'=>array('PORT SUDAN|SD'), 'JAZAN'=>array('JIZAN|SA'),
    'RADES'=>array('RADES|TN'), 'CONAKRY'=>array('CONAKRY|GN'), 'BIZERTE'=>array('BIZERTE|TN'),
    'SOUSSE'=>array('SOUSSE|TN'), 'MSIURATA'=>array('MISURATA|LY'), 'Rades,Tunis'=>array('RADES|TN','TUNIS|TN'),
    'Algiers'=>array('ALGIERS|DZ'), 'TUNIS / RADES'=>array('TUNIS|TN','RADES|TN'),
    'ALEXANDIRA OLD PORT （calling AICT）'=>array('ALEXANDRIA OLD PORT|EG'),
    'ALEXANDIRA OLD PORT （calling ACCHC）'=>array('ALEXANDRIA OLD PORT|EG'),
    'KHALIFA PORT, ABU DHABI'=>array('KHALIFA PORT|AE'), 'UMM北 (25.26#)'=>array('UMM QASR|IQ'),
    '南沙--PORT SAID WEST'=>array('PORT SAID WEST|EG'), '深圳--PORT SAID WEST'=>array('PORT SAID WEST|EG'),
    '虎门--PORT SAID WEST'=>array('PORT SAID WEST|EG'), 'HP-SZ-PORT SAID WEST'=>array('PORT SAID WEST|EG'),
    'Alger/Annaba'=>array('ALGIERS|DZ','ANNABA|DZ'), 'MSIURATA/Benghazi'=>array('MISURATA|LY','BENGHAZI|LY'),
    'BANDAR ABBAS'=>array('BANDAR ABBAS|IR'),
    'ORAN (SEA PORT) / CASABLANCA'=>array('ORAN|DZ','CASABLANCA|MA'),
    'KUWAIT(SHUAIBA)'=>array('SHUAIBA|KW'),
    'KUWAIT/SHUAIBA'=>array('KUWAIT|KW','SHUAIBA|KW'),
    'KUWAIT,SHUAIBA'=>array('KUWAIT|KW','SHUAIBA|KW'),
    'KUWAIT & SHUAIBA'=>array('KUWAIT|KW','SHUAIBA|KW'),
    'KUWAIT + SHUAIBA'=>array('KUWAIT|KW','SHUAIBA|KW'),
    'KUWAIT;SHUAIBA'=>array('KUWAIT|KW','SHUAIBA|KW'),
    'Alexandria（TMT）'=>array('ALEXANDRIA TMT|EG'),
    'Alexandria TMT'=>array('ALEXANDRIA TMT|EG'),
    'Alexandria Old Port'=>array('ALEXANDRIA OLD PORT|EG'),
    'Alexandria'=>array('ALEXANDRIA|EG'),
    'JEDDAH(REDSEA GATEWAY)'=>array('JEDDAH|SA'),
    'KHOR FAKKAN (ICD)'=>array('KHOR FAKKAN|AE'),
    'UMM QASR(北港，靠14 或15号码头)'=>array('UMM QASR|IQ'),
    'UMM QASR(南港)'=>array('UMM QASR|IQ'),
    'JEDDAH（SOUTH）'=>array('JEDDAH|SA'),
    'UMM QASR(北港)'=>array('UMM QASR|IQ'),
    'UMM QASR(北港，靠20号码头)'=>array('UMM QASR|IQ'),
    'UMM QASR(北港，靠27号码头)'=>array('UMM QASR|IQ'),
    'SHARJAH ICD'=>array('SHARJAH|AE'),
    'UMM QASR 北港20号码头'=>array('UMM QASR|IQ'),
    'JEDDAH(DPW/南港)'=>array('JEDDAH|SA'),
    'ISTANBUL(MARDAS)'=>array('ISTANBUL|TR'),
    'JEDDAH(REASEA GATEWAY)'=>array('JEDDAH|SA'),
    'UMM QASR北港（11号码头）'=>array('UMM QASR|IQ'),
);
foreach ($cases as $raw => $expected) m062Assert(m062Ports($resolver, $raw) === $expected, 'Unexpected mapping for '.$raw.': '.json_encode(m062Ports($resolver, $raw)));

$oldTransition=PortNameResolver::catalogAliasTransition('ALEXANDIRA OLD PORT');
$tmtTransition=PortNameResolver::catalogAliasTransition('Alexandria (TMT)');
m062Assert($oldTransition!==null&&$oldTransition['from_port']==='ALEXANDRIA'&&$oldTransition['to_port']==='ALEXANDRIA OLD PORT','Old Port catalog transition is not bounded correctly.');
m062Assert($tmtTransition!==null&&$tmtTransition['from_port']==='ALEXANDRIA'&&$tmtTransition['to_port']==='ALEXANDRIA TMT','TMT catalog transition is not bounded correctly.');
m062Assert(PortNameResolver::catalogAliasTransition('ALEXANDRIA')===null&&PortNameResolver::catalogAliasTransition('MANUAL ALIAS')===null,'Generic or manual aliases must not be transferable.');

$fuzzy = $resolver->resolveList('UMM AL QUWAI', true);
m062Assert($fuzzy['status'] === 'matched' && $fuzzy['matches'][0]['port_name'] === 'UMM AL QUWAIN' && $fuzzy['matches'][0]['method'] === 'fuzzy', 'Safe long-name fuzzy match failed.');
m062Assert($resolver->resolveList('ORANN', true)['status'] === 'unmapped', 'Short fuzzy input must fail closed.');
m062Assert($resolver->resolveList('ORAN/NOT A PORT', true)['status'] === 'unmapped', 'Partial composite must fail closed.');
m062Assert($resolver->resolveList('UNKNOWN--ORAN', true)['status'] === 'unmapped', 'Unknown origin prefix must not be stripped.');
m062Assert($resolver->resolveList('ORAN--SKIKDA', true)['status'] === 'unmapped', 'A destination port must not be accepted as an origin prefix.');
m062Assert($resolver->resolveList('PORT SAID (EAST)', true)['status'] === 'unmapped', 'Unknown terminal qualifier must not be stripped.');

class M062ConflictResult { public $rows; public $index=0; public function __construct(array $rows){$this->rows=$rows;} }
class M062ConflictDb {
    public function query($sql){
        if(strpos($sql,'chinaplanetsp_port_alias')!==false)return false;
        if(strpos($sql,'chinaplanetsp_port')!==false)return new M062ConflictResult(array(
            (object)array('rowid'=>1,'port_name'=>'ALPHA','port_code'=>null,'country_code'=>'DZ','aliases'=>'CONFLICT'),
            (object)array('rowid'=>2,'port_name'=>'BETA','port_code'=>null,'country_code'=>'DZ','aliases'=>'CONFLICT'),
        ));
        return false;
    }
    public function fetch_object($result){return$result->rows[$result->index++]??false;}
}
$conflictResolver=new PortNameResolver(new M062ConflictDb(),1,0.90);
m062Assert($conflictResolver->resolveList('CONFLICT',true)['status']==='ambiguous','Same-country legacy alias conflict must fail closed.');

class M062MutableLiteralDb {
    public function query($sql){
        if(strpos($sql,'information_schema.tables')!==false)return new M062ConflictResult(array((object)array('total'=>1)));
        if(strpos($sql,'chinaplanetsp_port_alias')!==false)return new M062ConflictResult(array(
            (object)array('alias_name'=>'KUWAIT/SHUAIBA','normalized_alias'=>'KUWAIT SHUAIBA','rowid'=>99,'port_name'=>'SHUAIBA','port_code'=>'KWSAA','country_code'=>'KW'),
        ));
        if(strpos($sql,'chinaplanetsp_port')!==false)return new M062ConflictResult(array(
            (object)array('rowid'=>99,'port_name'=>'SHUAIBA','port_code'=>'KWSAA','country_code'=>'KW','aliases'=>'KUWAIT/SHUAIBA'),
        ));
        return false;
    }
    public function fetch_object($result){return$result->rows[$result->index++]??false;}
}
$mutableLiteralResolver=new PortNameResolver(new M062MutableLiteralDb(),1,0.90);
m062Assert(m062Ports($mutableLiteralResolver,'KUWAIT/SHUAIBA')===array('KUWAIT|KW','SHUAIBA|KW'),'Mutable database alias must not bypass top-level composite splitting.');

class M062LiteralFkDb {
    private $conflict;
    public function __construct($conflict=false){$this->conflict=$conflict;}
    public function query($sql){
        if(strpos($sql,'information_schema.tables')!==false)return new M062ConflictResult(array((object)array('total'=>1)));
        if(strpos($sql,'chinaplanetsp_port_alias')!==false)return new M062ConflictResult(array(
            (object)array('alias_name'=>'AGADIR, Morocco','normalized_alias'=>'AGADIR MOROCCO','rowid'=>$this->conflict?77:48,'port_name'=>$this->conflict?'ORAN':'AGADIR','port_code'=>$this->conflict?'DZORN':'MAAGA','country_code'=>$this->conflict?'DZ':'MA'),
            (object)array('alias_name'=>'KHALIFA PORT, ABU DHABI','normalized_alias'=>'KHALIFA PORT ABU DHABI','rowid'=>22,'port_name'=>'KHALIFA PORT','port_code'=>'AEKHL','country_code'=>'AE'),
        ));
        if(strpos($sql,'chinaplanetsp_port')!==false)return new M062ConflictResult(array(
            (object)array('rowid'=>48,'port_name'=>'AGADIR','port_code'=>'MAAGA','country_code'=>'MA','aliases'=>''),
            (object)array('rowid'=>22,'port_name'=>'KHALIFA PORT','port_code'=>'AEKHL','country_code'=>'AE','aliases'=>''),
        ));
        return false;
    }
    public function fetch_object($result){return$result->rows[$result->index++]??false;}
}
$literalFkResolver=new PortNameResolver(new M062LiteralFkDb(),1,0.90);
$agadirLiteral=$literalFkResolver->resolveList('AGADIR, Morocco',true);
$khalifaLiteral=$literalFkResolver->resolveList('KHALIFA PORT, ABU DHABI',true);
m062Assert($agadirLiteral['status']==='matched'&&(int)$agadirLiteral['matches'][0]['fk_port']===48&&$agadirLiteral['matches'][0]['port_code']==='MAAGA','AGADIR literal alias must retain its authoritative database port id.');
m062Assert($khalifaLiteral['status']==='matched'&&(int)$khalifaLiteral['matches'][0]['fk_port']===22&&$khalifaLiteral['matches'][0]['port_code']==='AEKHL','KHALIFA literal alias must retain its authoritative database port id.');
$literalConflict=(new PortNameResolver(new M062LiteralFkDb(true),1,0.90))->resolveList('AGADIR, Morocco',true);
m062Assert($literalConflict['status']==='ambiguous'&&$literalConflict['reason']==='literal_alias_owner_mismatch','A mutable literal owner mismatch must fail closed.');

class M062MissingLiteralAliasDb {
    private $aliasQueryFails;
    public function __construct($aliasQueryFails=false){$this->aliasQueryFails=$aliasQueryFails;}
    public function query($sql){
        if(strpos($sql,'information_schema.tables')!==false)return new M062ConflictResult(array((object)array('total'=>1)));
        if(strpos($sql,'chinaplanetsp_port_alias')!==false)return $this->aliasQueryFails?false:new M062ConflictResult(array());
        if(strpos($sql,'chinaplanetsp_port')!==false)return new M062ConflictResult(array(
            (object)array('rowid'=>48,'port_name'=>'AGADIR','port_code'=>'MAAGA','country_code'=>'MA','aliases'=>''),
        ));
        return false;
    }
    public function fetch_object($result){return$result->rows[$result->index++]??false;}
}
foreach(array(false,true) as $queryFails){
    $missingLiteral=(new PortNameResolver(new M062MissingLiteralAliasDb($queryFails),1,0.90))->resolveList('AGADIR, Morocco',true);
    m062Assert($missingLiteral['status']==='ambiguous'&&$missingLiteral['reason']==='literal_alias_owner_unavailable','A missing or unreadable active literal alias must fail closed.');
}

class M062PreMigrationLiteralDb extends M062MissingLiteralAliasDb {
    public function query($sql){
        if(strpos($sql,'information_schema.tables')!==false)return new M062ConflictResult(array((object)array('total'=>0)));
        return parent::query($sql);
    }
}
$preMigrationLiteral=(new PortNameResolver(new M062PreMigrationLiteralDb(),1,0.90,true))->resolveList('AGADIR, Morocco',true);
m062Assert($preMigrationLiteral['status']==='matched'&&(int)$preMigrationLiteral['matches'][0]['fk_port']===0,'Only explicit pre-migration dry-run mode may inspect a literal before alias-table creation.');

class M062AlexandriaDb {
    public function query($sql){
        if(strpos($sql,'information_schema.tables')!==false)return new M062ConflictResult(array((object)array('total'=>1)));
        if(strpos($sql,'chinaplanetsp_port_alias')!==false)return new M062ConflictResult(array(
            (object)array('alias_name'=>'ALEXANDRIA','normalized_alias'=>'ALEXANDRIA','rowid'=>10,'port_name'=>'ALEXANDRIA','port_code'=>'EGALY','country_code'=>'EG'),
            (object)array('alias_name'=>'ALEXANDRIA OLD PORT','normalized_alias'=>'ALEXANDRIA OLD PORT','rowid'=>11,'port_name'=>'ALEXANDRIA OLD PORT','port_code'=>'EGALY','country_code'=>'EG'),
            (object)array('alias_name'=>'ALEXANDIRA OLD PORT','normalized_alias'=>'ALEXANDIRA OLD PORT','rowid'=>11,'port_name'=>'ALEXANDRIA OLD PORT','port_code'=>'EGALY','country_code'=>'EG'),
            (object)array('alias_name'=>'ALEXANDRIA TMT','normalized_alias'=>'ALEXANDRIA TMT','rowid'=>12,'port_name'=>'ALEXANDRIA TMT','port_code'=>'EGALY','country_code'=>'EG'),
        ));
        if(strpos($sql,'chinaplanetsp_port')!==false)return new M062ConflictResult(array(
            (object)array('rowid'=>10,'port_name'=>'ALEXANDRIA','port_code'=>'EGALY','country_code'=>'EG','aliases'=>'ALEXANDIRA'),
            (object)array('rowid'=>11,'port_name'=>'ALEXANDRIA OLD PORT','port_code'=>'EGALY','country_code'=>'EG','aliases'=>'ALEXANDIRA OLD PORT'),
            (object)array('rowid'=>12,'port_name'=>'ALEXANDRIA TMT','port_code'=>'EGALY','country_code'=>'EG','aliases'=>'ALEXANDRIA (TMT)'),
        ));
        return false;
    }
    public function fetch_object($result){return$result->rows[$result->index++]??false;}
}
$alexandriaResolver=new PortNameResolver(new M062AlexandriaDb(),1,0.90);
$alexandriaGeneric=$alexandriaResolver->resolveList('ALEXANDRIA',true)['matches'][0];
$alexandriaOld=$alexandriaResolver->resolveList('ALEXANDIRA OLD PORT',true)['matches'][0];
$alexandriaTmt=$alexandriaResolver->resolveList('ALEXANDRIA (TMT)',true)['matches'][0];
m062Assert((int)$alexandriaGeneric['fk_port']===10&&(int)$alexandriaOld['fk_port']===11&&(int)$alexandriaTmt['fk_port']===12,'Alexandria pricing destinations must retain distinct database port IDs.');

$owners = array();
foreach (PortNameResolver::catalog() as $definition) {
    foreach (array_merge(array($definition['port_name']), $definition['aliases']) as $alias) {
        $normalized = PortNameResolver::normalize($alias);
        $owner = $definition['port_name'].'|'.$definition['country_code'];
        m062Assert(!isset($owners[$normalized]) || $owners[$normalized] === $owner, 'Conflicting built-in alias '.$normalized);
        $owners[$normalized] = $owner;
    }
}

$aliasSchema = file_get_contents($root.'/custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_port_alias.sql');
m062Assert(strpos($aliasSchema, 'UNIQUE KEY uk_chinaplanetsp_port_alias (entity, normalized_alias)') !== false, 'Normalized alias uniqueness missing.');
$rateSchema = file_get_contents($root.'/custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_rate.sql');
foreach (array('pod_raw','fk_destination_port','port_match_method','port_match_score') as $column) m062Assert(strpos($rateSchema, $column) !== false, 'Rate audit column missing '.$column);
$importer = file_get_contents($root.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php');
m062Assert(strpos($importer, 'resolveList($podRaw, true)') !== false && strpos($importer, "'pod_raw'=>\$podRaw") !== false, 'Importer resolver/raw POD integration missing.');
$migration = file_get_contents($root.'/deploy/migrations/m062_port_normalization.php');
foreach (array("array('dry-run','apply','check')", 'new PortNameResolver($db,$entity,null,true)', 'GET_LOCK(', 'normalized_alias', 'NOT EXISTS(SELECT 1', 'resolved_warnings_removed', 'ACTIVE_ALIAS_CONFLICT', 'LEGACY_ALIAS_CONFLICT') as $needle) m062Assert(strpos($migration, $needle) !== false, 'Migration safety boundary missing '.$needle);
m062Assert(strpos($migration,'catalogAliasTransition')!==false&&strpos($migration,"alias_source==='builtin'")!==false&&strpos($migration,'RAW_RATE_CORRECTION_FAILED')!==false&&strpos($migration,'catalog_alias_ownership_drift')!==false&&strpos($migration,"summary['raw_rate_drift']")!==false,'Alexandria ownership correction must be bounded, raw-source driven and visible to check mode.');
m062Assert(strpos($migration,'INSERT IGNORE INTO `".$aliasTable')===false,'Legacy alias conflicts must not be silently ignored.');
m062Assert(substr_count($migration, "ELSE source.public_visible END")===1&&strpos($migration, "ELSE public_visible END,pod=")!==false, 'Migration must preserve existing visibility and reveal only newly mapped rows.');
$admin = file_get_contents($root.'/custom/chinaplanetshippingprices/admin/ports.php');
m062Assert(strpos($admin, 'PortNameResolver::normalize') !== false && strpos($admin, 'Alias already belongs to') !== false, 'Admin alias collision guard missing.');
m062Assert(strpos($admin, 'portBelongsToEntity') !== false && strpos($admin, 'Port mapping was not found in this entity.') !== false, 'Admin port mutations must reject cross-entity identifiers.');
m062Assert(strpos($admin, "define('CSRFCHECK_WITH_TOKEN',1)") !== false && strpos($admin, 'FOR UPDATE') !== false && strpos($admin, 'ON DUPLICATE KEY UPDATE fk_port=VALUES(fk_port)') === false, 'Admin aliases require POST/CSRF and atomic no-steal ownership.');
m062Assert(strpos($admin, '"DELETE FROM ".MAIN_DB_PREFIX."chinaplanetsp_port') === false && strpos($admin, 'Mapping archived.') !== false, 'Port mapping must archive instead of delete.');
$manual=file_get_contents($root.'/custom/chinaplanetshippingprices/admin/adjust.php');
m062Assert(strpos($manual,'new PortNameResolver')!==false&&strpos($manual,'resolveList($podRaw,true)')!==false&&strpos($manual,'Selected country does not match the canonical port country')!==false,'Manual rates must use canonical port resolution and reject country mismatch.');
m062Assert(strpos($manual,'pod_raw=')!==false&&strpos($manual,'fk_destination_port=')!==false&&strpos($manual,'port_match_method=')!==false&&strpos($manual,'ON DUPLICATE KEY UPDATE country_code=VALUES(country_code)')===false,'Manual rates must retain match evidence and never rewrite canonical port countries.');

print 'OK M062 port normalization cases='.count($cases).' aliases='.count($owners)."\n";
