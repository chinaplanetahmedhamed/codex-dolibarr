<?php

/** Additive, idempotent M062 canonical port/alias and existing-rate migration. */
if (PHP_SAPI !== 'cli' || $argc !== 2 || !in_array($argv[1], array('dry-run','apply','check'), true)) {
    fwrite(STDERR, "M062_PORTS=FAIL REASON=INVALID_COMMAND\n"); exit(64);
}

define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOCSRFCHECK', 1);
$documentRoot = getenv('M062_DOL_DOCUMENT_ROOT');
if ($documentRoot === false || $documentRoot === '') $documentRoot = '/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';
$documentRoot = rtrim($documentRoot, '/');
if (!is_file($documentRoot.'/master.inc.php')) { fwrite(STDERR, "M062_PORTS=FAIL REASON=DOL_DOCUMENT_ROOT_INVALID\n"); exit(65); }
$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8088';
require $documentRoot.'/master.inc.php';
$candidateRoot = dirname(__DIR__, 2);
require_once $candidateRoot.'/custom/chinaplanetshippingprices/class/PortNameResolver.class.php';
global $db, $conf;

$GLOBALS['m062TransactionActive']=false;
function m062Fail($reason, $code = 1) { global $db;if(!empty($GLOBALS['m062TransactionActive'])&&is_object($db)){$db->rollback();$GLOBALS['m062TransactionActive']=false;}fwrite(STDERR, 'M062_PORTS=FAIL REASON='.$reason."\n"); exit($code); }
function m062Query($db, $sql, $reason) { $res = $db->query($sql); if (!$res) m062Fail($reason); return $res; }
function m062Count($db, $sql) { $res=m062Query($db,$sql,'COUNT_FAILED');$row=$db->fetch_object($res);return $row?(int)$row->total:0; }
function m062TableExists($db, $table) { return m062Count($db,"SELECT COUNT(*) total FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".$db->escape($table)."'")===1; }
function m062ColumnExists($db, $table, $column) { return m062Count($db,"SELECT COUNT(*) total FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='".$db->escape($table)."' AND column_name='".$db->escape($column)."'")===1; }
function m062IndexExists($db, $table, $index) { return m062Count($db,"SELECT COUNT(*) total FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='".$db->escape($table)."' AND index_name='".$db->escape($index)."'")>0; }

$entity = (int) $conf->entity;
$portTable = MAIN_DB_PREFIX.'chinaplanetsp_port';
$aliasTable = MAIN_DB_PREFIX.'chinaplanetsp_port_alias';
$rateTable = MAIN_DB_PREFIX.'chinaplanetsp_rate';
$batchTable = MAIN_DB_PREFIX.'chinaplanetsp_batch';
if ($entity <= 0 || !m062TableExists($db,$portTable) || !m062TableExists($db,$rateTable) || !m062TableExists($db,$batchTable)) m062Fail('BASE_SCHEMA_MISSING');

$inspect = function (PortNameResolver $resolver) use ($db,$entity,$rateTable) {
    $summary=array('unmapped_rows'=>0,'unmapped_values'=>0,'resolvable_rows'=>0,'resolvable_values'=>0,'remaining'=>array());
    $res=m062Query($db,"SELECT pod,COUNT(*) total FROM `".$rateTable."` WHERE entity=".$entity." GROUP BY pod ORDER BY total DESC,pod",'UNMAPPED_INSPECTION_FAILED');
    while($row=$db->fetch_object($res)){
        $match=$resolver->resolveList($row->pod,true);
        if($match['status']==='matched'){$summary['resolvable_rows']+=(int)$row->total;$summary['resolvable_values']++;}
        else{$summary['unmapped_rows']+=(int)$row->total;$summary['unmapped_values']++;$summary['remaining'][]=array('pod'=>$row->pod,'rows'=>(int)$row->total,'status'=>$match['status'],'reason'=>$match['reason']??'');}
    }
    return $summary;
};

$catalogOwnershipDrift = function () use ($db,$entity,$portTable,$aliasTable) {
    if(!m062TableExists($db,$aliasTable)) return 1;
    $expected=array();
    foreach(PortNameResolver::catalog() as $definition)foreach(array_merge(array($definition['port_name']),$definition['aliases']) as $alias){$normalized=PortNameResolver::normalize($alias);if($normalized!=='')$expected[$normalized]=array('port_name'=>$definition['port_name'],'country_code'=>$definition['country_code']);}
    $drift=0;
    foreach($expected as $normalized=>$owner){
        $sql="SELECT a.status,p.port_name,p.country_code,p.status port_status FROM `".$aliasTable."` a LEFT JOIN `".$portTable."` p ON p.rowid=a.fk_port AND p.entity=a.entity WHERE a.entity=".$entity." AND a.normalized_alias='".$db->escape($normalized)."' LIMIT 1";
        $res=m062Query($db,$sql,'CATALOG_ALIAS_AUDIT_FAILED');$row=$db->fetch_object($res);
        if(!$row||(int)$row->status!==1||(int)$row->port_status!==1||$row->port_name!==$owner['port_name']||$row->country_code!==$owner['country_code'])$drift++;
    }
    return $drift;
};

$legacyAliasConflicts = function () use ($db,$entity,$portTable) {
    $owners=array();$canonicalNames=array();
    foreach(PortNameResolver::catalog() as $definition){
        $canonicalNames[PortNameResolver::normalize($definition['port_name']).'|'.$definition['country_code']]=$definition['port_name'];
        foreach(array_merge(array($definition['port_name']),$definition['aliases']) as $alias){$normalized=PortNameResolver::normalize($alias);if($normalized!=='')$owners[$normalized]=array('port_name'=>$definition['port_name'],'country_code'=>$definition['country_code']);}
    }
    $ports=array();$res=m062Query($db,"SELECT rowid,port_name,country_code,aliases FROM `".$portTable."` WHERE entity=".$entity." AND status=1 ORDER BY rowid",'LEGACY_ALIAS_PREFLIGHT_READ_FAILED');
    while($port=$db->fetch_object($res))$ports[]=$port;
    $conflicts=array();
    foreach($ports as $port){
        $country=(string)$port->country_code;$portNormalized=PortNameResolver::normalize($port->port_name);$canonicalKey=$portNormalized.'|'.$country;$effectiveName=$canonicalNames[$canonicalKey]??$port->port_name;
        if(isset($owners[$portNormalized])&&($owners[$portNormalized]['port_name']!==$effectiveName||$owners[$portNormalized]['country_code']!==$country))continue;
        foreach(PortNameResolver::legacyAliasValues($port->port_name,$port->aliases)as$alias){
            $alias=trim($alias);$normalized=PortNameResolver::normalize($alias);if($normalized===''||!isset($owners[$normalized]))continue;$expected=$owners[$normalized];
            if($expected['port_name']===$effectiveName&&$expected['country_code']===$country)continue;
            $transition=PortNameResolver::catalogAliasOwnershipTransition($alias);$allowed=$transition&&$effectiveName===$transition['from_port']&&$country===$transition['from_country']&&$expected['port_name']===$transition['to_port']&&$expected['country_code']===$transition['to_country'];
            if(!$allowed)$conflicts[]=array('normalized_alias'=>$normalized,'legacy_port'=>$effectiveName,'legacy_country'=>$country,'catalog_port'=>$expected['port_name'],'catalog_country'=>$expected['country_code']);
        }
    }
    return $conflicts;
};

if ($argv[1] === 'dry-run') {
    // Read-only preflight runs before the alias table exists. This is the only
    // caller allowed to assess curated literal resolvability without a linked
    // alias row; apply/check and all runtime callers require database port IDs.
    $summary=$inspect(new PortNameResolver($db,$entity,null,true));
    $summary['legacy_alias_conflicts']=$legacyAliasConflicts();
    echo 'M062_PORTS_DRY_RUN='.json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
    exit(empty($summary['remaining'])&&empty($summary['legacy_alias_conflicts'])?0:2);
}

$schemaReady = function () use ($db,$aliasTable,$rateTable) {
    foreach(array('pod_raw','fk_destination_port','port_match_method','port_match_score') as $column) if(!m062ColumnExists($db,$rateTable,$column)) return false;
    return m062TableExists($db,$aliasTable) && m062IndexExists($db,$aliasTable,'uk_chinaplanetsp_port_alias') && m062IndexExists($db,$rateTable,'idx_chinaplanetsp_rate_destination_port');
};

$rawRateDrift = function (PortNameResolver $resolver) use ($db,$entity,$rateTable) {
    $drift=0;$res=m062Query($db,"SELECT pod,pod_raw,destination_country,fk_destination_port FROM `".$rateTable."` WHERE entity=".$entity." AND pod_raw IS NOT NULL AND pod_raw<>'' AND UPPER(pod_raw) LIKE '%ALEXAND%'",'RAW_RATE_AUDIT_FAILED');
    while($row=$db->fetch_object($res)){$resolution=$resolver->resolveList($row->pod_raw,true);$transition=PortNameResolver::exactCatalogAliasTransition($resolution);if(!$transition)continue;$match=$resolution['matches'][0];$targetId=$match['port_name']===$transition['to_port']&&$match['country_code']===$transition['to_country']?(int)($match['fk_port']??0):0;if($row->pod!==$transition['to_port']||$row->destination_country!==$transition['to_country']||$targetId<=0||(int)$row->fk_destination_port!==$targetId)$drift++;}
    return $drift;
};

if ($argv[1] === 'check') {
    if(!$schemaReady()){echo "M062_PORTS_CHECK=MIGRATION_REQUIRED REASON=SCHEMA\n";exit(2);}
    $duplicates=m062Count($db,"SELECT COUNT(*) total FROM (SELECT normalized_alias FROM `".$aliasTable."` WHERE entity=".$entity." AND status=1 GROUP BY normalized_alias HAVING COUNT(*)>1) duplicate_aliases");
    $checkResolver=new PortNameResolver($db,$entity);$summary=$inspect($checkResolver);$summary['catalog_alias_ownership_drift']=$catalogOwnershipDrift();$summary['raw_rate_drift']=$rawRateDrift($checkResolver);
    if($duplicates>0 || !empty($summary['remaining']) || $summary['unmapped_rows']>0 || $summary['catalog_alias_ownership_drift']>0 || $summary['raw_rate_drift']>0){echo 'M062_PORTS_CHECK=MIGRATION_REQUIRED REASON=DATA '.json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";exit(2);}
    echo "M062_PORTS_CHECK=PASS DUPLICATE_ALIASES=0 UNMAPPED_ROWS=0\n";exit(0);
}

$lockName='chinaplanetos:m062:entity:'.$entity;
$preflightLegacyConflicts=$legacyAliasConflicts();
if(!empty($preflightLegacyConflicts)){fwrite(STDERR,'M062_PORTS_LEGACY_ALIAS_CONFLICTS='.json_encode($preflightLegacyConflicts,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");m062Fail('LEGACY_ALIAS_CONFLICT_PREFLIGHT');}
$lockRes=m062Query($db,"SELECT GET_LOCK('".$db->escape($lockName)."',0) acquired",'LOCK_CHECK_FAILED');
$lock=$db->fetch_object($lockRes);if(!$lock||(int)$lock->acquired!==1)m062Fail('MIGRATION_LOCKED');
$releaseLock=function()use($db,$lockName){$db->query("SELECT RELEASE_LOCK('".$db->escape($lockName)."')");};

try {
    if(!m062TableExists($db,$aliasTable)){
        $schema=file_get_contents($candidateRoot.'/custom/chinaplanetshippingprices/sql/llx_chinaplanetsp_port_alias.sql');
        $schema=preg_replace('/^CREATE TABLE llx_/','CREATE TABLE '.MAIN_DB_PREFIX,$schema);
        m062Query($db,$schema,'ALIAS_TABLE_CREATE_FAILED');
    }
    $columns=array(
        'pod_raw'=>'varchar(160) NULL AFTER `pod`',
        'fk_destination_port'=>'bigint NULL AFTER `pod_raw`',
        'port_match_method'=>"varchar(16) NOT NULL DEFAULT 'legacy' AFTER `fk_destination_port`",
        'port_match_score'=>'decimal(5,4) NULL AFTER `port_match_method`',
    );
    foreach($columns as $column=>$definition) if(!m062ColumnExists($db,$rateTable,$column)) m062Query($db,"ALTER TABLE `".$rateTable."` ADD COLUMN `".$column."` ".$definition,'RATE_COLUMN_ADD_FAILED');
    if(!m062IndexExists($db,$rateTable,'idx_chinaplanetsp_rate_destination_port')) m062Query($db,"ALTER TABLE `".$rateTable."` ADD INDEX `idx_chinaplanetsp_rate_destination_port` (`entity`,`fk_destination_port`,`status`)",'RATE_INDEX_ADD_FAILED');
    if($db->begin()<=0)m062Fail('TRANSACTION_BEGIN_FAILED');$GLOBALS['m062TransactionActive']=true;

    $now=$db->idate(dol_now());$userId=!empty($GLOBALS['user']->id)?(int)$GLOBALS['user']->id:1;$seededPorts=0;$seededAliases=0;$transferredBuiltinAliases=0;$canonicalPortIds=array();
    foreach(PortNameResolver::catalog() as $definition){
        $res=m062Query($db,"SELECT rowid FROM `".$portTable."` WHERE entity=".$entity." AND UPPER(TRIM(port_name))='".$db->escape(mb_strtoupper($definition['port_name'],'UTF-8'))."' ORDER BY status DESC,rowid LIMIT 1",'PORT_LOOKUP_FAILED');
        $port=$db->fetch_object($res);
        if($port){$portId=(int)$port->rowid;m062Query($db,"UPDATE `".$portTable."` SET port_name='".$db->escape($definition['port_name'])."',port_code=".($definition['port_code']?"'".$db->escape($definition['port_code'])."'":'NULL').",country_code='".$db->escape($definition['country_code'])."',status=1,fk_user_modif=".$userId." WHERE rowid=".$portId." AND entity=".$entity,'PORT_UPDATE_FAILED');}
        else{m062Query($db,"INSERT INTO `".$portTable."`(entity,port_name,port_code,country_code,aliases,status,fk_user_creat,date_creation) VALUES(".$entity.",'".$db->escape($definition['port_name'])."',".($definition['port_code']?"'".$db->escape($definition['port_code'])."'":'NULL').",'".$db->escape($definition['country_code'])."','',1,".$userId.",'".$now."')",'PORT_INSERT_FAILED');$portId=(int)$db->last_insert_id($portTable);$seededPorts++;}
        $canonicalPortIds[$definition['country_code'].'|'.$definition['port_name']]=$portId;
        foreach(array_merge(array($definition['port_name']),$definition['aliases']) as $alias){
            $normalized=PortNameResolver::normalize($alias);if($normalized==='')continue;
            $existing=m062Query($db,"SELECT a.rowid,a.fk_port,a.status,a.alias_source,p.port_name owner_port_name,p.country_code owner_country_code FROM `".$aliasTable."` a LEFT JOIN `".$portTable."` p ON p.rowid=a.fk_port AND p.entity=a.entity WHERE a.entity=".$entity." AND a.normalized_alias='".$db->escape($normalized)."' FOR UPDATE",'ALIAS_SEED_LOOKUP_FAILED');$existingAlias=$db->fetch_object($existing);
            if($existingAlias){
                if($existingAlias->alias_source!=='builtin')m062Fail('CATALOG_ALIAS_PROVENANCE_CONFLICT');
                if((int)$existingAlias->status!==1)m062Fail('CATALOG_ALIAS_INACTIVE_CONFLICT');
                if((int)$existingAlias->fk_port!==$portId){$transition=PortNameResolver::catalogAliasOwnershipTransition($alias);$allowed=$transition&&$existingAlias->owner_port_name===$transition['from_port']&&$existingAlias->owner_country_code===$transition['from_country']&&$definition['port_name']===$transition['to_port']&&$definition['country_code']===$transition['to_country'];if(!$allowed)m062Fail('ACTIVE_ALIAS_CONFLICT');$transferredBuiltinAliases++;}
            }
            if($existingAlias)$sql="UPDATE `".$aliasTable."` SET fk_port=".$portId.",alias_name='".$db->escape($alias)."',alias_source='builtin',status=1,fk_user_modif=".$userId." WHERE entity=".$entity." AND rowid=".(int)$existingAlias->rowid." AND fk_port=".(int)$existingAlias->fk_port." AND status=1 AND alias_source='builtin'";
            else $sql="INSERT INTO `".$aliasTable."`(entity,fk_port,alias_name,normalized_alias,alias_source,status,fk_user_creat,date_creation) VALUES(".$entity.",".$portId.",'".$db->escape($alias)."','".$db->escape($normalized)."','builtin',1,".$userId.",'".$now."')";
            m062Query($db,$sql,'ALIAS_SEED_FAILED');$seededAliases++;
            $verified=m062Query($db,"SELECT a.fk_port,a.status,a.alias_source,p.port_name,p.country_code FROM `".$aliasTable."` a INNER JOIN `".$portTable."` p ON p.rowid=a.fk_port AND p.entity=a.entity WHERE a.entity=".$entity." AND a.rowid=".($existingAlias?(int)$existingAlias->rowid:(int)$db->last_insert_id($aliasTable))." FOR UPDATE",'ALIAS_SEED_VERIFY_FAILED');$verifiedAlias=$db->fetch_object($verified);if(!$verifiedAlias||(int)$verifiedAlias->fk_port!==$portId||(int)$verifiedAlias->status!==1||$verifiedAlias->alias_source!=='builtin'||$verifiedAlias->port_name!==$definition['port_name']||$verifiedAlias->country_code!==$definition['country_code'])m062Fail('ALIAS_SEED_VERIFY_FAILED');
        }
    }

    $ports=m062Query($db,"SELECT rowid,port_name FROM `".$portTable."` WHERE entity=".$entity." AND status=1",'PORT_DEDUPE_READ_FAILED');$archivedPorts=0;
    while($port=$db->fetch_object($ports)){$normalized=PortNameResolver::normalize($port->port_name);$res=m062Query($db,"SELECT fk_port FROM `".$aliasTable."` WHERE entity=".$entity." AND normalized_alias='".$db->escape($normalized)."' AND status=1",'PORT_DEDUPE_ALIAS_FAILED');$alias=$db->fetch_object($res);if($alias&&(int)$alias->fk_port!==(int)$port->rowid){m062Query($db,"UPDATE `".$portTable."` SET status=0,fk_user_modif=".$userId." WHERE entity=".$entity." AND rowid=".(int)$port->rowid,'DUPLICATE_PORT_ARCHIVE_FAILED');$archivedPorts++;}}

    $legacy=m062Query($db,"SELECT rowid,port_name,aliases FROM `".$portTable."` WHERE entity=".$entity." AND status=1 ORDER BY rowid",'LEGACY_PORT_READ_FAILED');
    while($port=$db->fetch_object($legacy))foreach(PortNameResolver::legacyAliasValues($port->port_name,$port->aliases)as$alias){
        $alias=trim($alias);$normalized=PortNameResolver::normalize($alias);if($normalized==='')continue;
        $existing=m062Query($db,"SELECT a.rowid,a.fk_port,a.status,a.alias_source,p.port_name owner_port_name,p.country_code owner_country_code FROM `".$aliasTable."` a LEFT JOIN `".$portTable."` p ON p.rowid=a.fk_port AND p.entity=a.entity WHERE a.entity=".$entity." AND a.normalized_alias='".$db->escape($normalized)."' FOR UPDATE",'LEGACY_ALIAS_LOOKUP_FAILED');$existingAlias=$db->fetch_object($existing);
        if($existingAlias&&(int)$existingAlias->status===1&&(int)$existingAlias->fk_port!==(int)$port->rowid){$transition=PortNameResolver::catalogAliasOwnershipTransition($alias);$expectedLegacy=$transition&&$port->port_name===$transition['from_port']&&$existingAlias->alias_source==='builtin'&&$existingAlias->owner_port_name===$transition['to_port']&&$existingAlias->owner_country_code===$transition['to_country'];if($expectedLegacy)continue;m062Fail('LEGACY_ALIAS_CONFLICT');}
        if(!$existingAlias)m062Query($db,"INSERT INTO `".$aliasTable."`(entity,fk_port,alias_name,normalized_alias,alias_source,status,fk_user_creat,date_creation) VALUES(".$entity.",".(int)$port->rowid.",'".$db->escape($alias)."','".$db->escape($normalized)."','legacy',1,".$userId.",'".$now."')",'LEGACY_ALIAS_IMPORT_FAILED');
        elseif((int)$existingAlias->status!==1)m062Query($db,"UPDATE `".$aliasTable."` SET fk_port=".(int)$port->rowid.",alias_name='".$db->escape($alias)."',alias_source='legacy',status=1,fk_user_modif=".$userId." WHERE entity=".$entity." AND rowid=".(int)$existingAlias->rowid,'LEGACY_ALIAS_RESTORE_FAILED');
    }

    $resolver=new PortNameResolver($db,$entity);$mappedRows=0;$clonedRows=0;$mappedValues=0;
    $pods=m062Query($db,"SELECT pod,COUNT(*) total FROM `".$rateTable."` WHERE entity=".$entity." GROUP BY pod ORDER BY pod",'RATE_POD_READ_FAILED');
    while($podRow=$db->fetch_object($pods)){
        $resolution=$resolver->resolveList($podRow->pod,true);if($resolution['status']!=='matched'||empty($resolution['matches']))continue;
        $matches=$resolution['matches'];$rows=m062Query($db,"SELECT rowid FROM `".$rateTable."` WHERE entity=".$entity." AND pod='".$db->escape($podRow->pod)."' ORDER BY rowid",'RATE_ROW_READ_FAILED');$rowIds=array();while($rate=$db->fetch_object($rows))$rowIds[]=(int)$rate->rowid;
        foreach($rowIds as $rateId){
            for($index=1;$index<count($matches);$index++){
                $match=$matches[$index];$guard="duplicate.entity=source.entity AND duplicate.fk_batch=source.fk_batch AND duplicate.source_row=source.source_row AND COALESCE(duplicate.source_cell,'')=COALESCE(source.source_cell,'') AND duplicate.pod='".$db->escape($match['port_name'])."' AND duplicate.container_type=source.container_type AND duplicate.shipping_line=source.shipping_line AND duplicate.cost_price=source.cost_price";
                $insert="INSERT INTO `".$rateTable."`(entity,fk_batch,source_sheet,source_row,source_cell,region_label,pol,destination_country,pod,pod_raw,fk_destination_port,port_match_method,port_match_score,shipping_line,service_type,route_detail,container_type,cost_price,sale_price,currency_code,valid_from,valid_to,source_notes_raw,source_notes,notes_en,translation_status,translation_source_hash,translation_provider,translation_model,translation_error,fk_user_translation_review,date_translation_review,public_notes,internal_notes,public_visible,is_current,status,fk_user_creat,date_creation)";
                $insert.=" SELECT source.entity,source.fk_batch,source.source_sheet,source.source_row,source.source_cell,source.region_label,source.pol,'".$db->escape($match['country_code'])."','".$db->escape($match['port_name'])."',COALESCE(NULLIF(source.pod_raw,''),source.pod),".((int)($match['fk_port']??0)>0?(int)$match['fk_port']:'NULL').",'composite',1.0000,source.shipping_line,source.service_type,source.route_detail,source.container_type,source.cost_price,source.sale_price,source.currency_code,source.valid_from,source.valid_to,source.source_notes_raw,source.source_notes,source.notes_en,source.translation_status,source.translation_source_hash,source.translation_provider,source.translation_model,source.translation_error,source.fk_user_translation_review,source.date_translation_review,source.public_notes,source.internal_notes,CASE WHEN (source.destination_country IS NULL OR source.destination_country='') AND source.pol<>'UNKNOWN' AND source.shipping_line<>'UNKNOWN' THEN 1 ELSE source.public_visible END,source.is_current,source.status,source.fk_user_creat,source.date_creation FROM `".$rateTable."` source WHERE source.rowid=".$rateId." AND source.entity=".$entity." AND NOT EXISTS(SELECT 1 FROM `".$rateTable."` duplicate WHERE ".$guard.")";
                $insertResult=m062Query($db,$insert,'COMPOSITE_RATE_CLONE_FAILED');$clonedRows+=(int)$db->affected_rows($insertResult);
            }
            $first=$matches[0];$method=count($matches)>1?'composite':($first['method']??$resolution['method']??'exact');$score=(float)($first['score']??$resolution['score']??1);
            $update="UPDATE `".$rateTable."` SET pod_raw=COALESCE(NULLIF(pod_raw,''),pod),public_visible=CASE WHEN (destination_country IS NULL OR destination_country='') AND pol<>'UNKNOWN' AND shipping_line<>'UNKNOWN' THEN 1 ELSE public_visible END,pod='".$db->escape($first['port_name'])."',destination_country='".$db->escape($first['country_code'])."',fk_destination_port=".((int)($first['fk_port']??0)>0?(int)$first['fk_port']:'NULL').",port_match_method='".$db->escape($method)."',port_match_score=".number_format($score,4,'.','')." WHERE entity=".$entity." AND rowid=".$rateId;
            m062Query($db,$update,'RATE_NORMALIZE_FAILED');$mappedRows++;
        }
        $mappedValues++;
    }

    // Re-evaluate preserved source values after a catalog ownership change.
    // A plain ALEXANDRIA source stays generic; only one-port raw resolutions
    // can correct a stored row to Old Port or TMT.
    $rawCorrectedRows=0;$rawRates=m062Query($db,"SELECT rowid,pod,pod_raw,destination_country,fk_destination_port FROM `".$rateTable."` WHERE entity=".$entity." AND pod_raw IS NOT NULL AND pod_raw<>'' AND UPPER(pod_raw) LIKE '%ALEXAND%' ORDER BY rowid FOR UPDATE",'RAW_RATE_CORRECTION_READ_FAILED');
    while($rate=$db->fetch_object($rawRates)){$resolution=$resolver->resolveList($rate->pod_raw,true);$transition=PortNameResolver::exactCatalogAliasTransition($resolution);if(!$transition)continue;$match=$resolution['matches'][0];$targetId=(int)($canonicalPortIds[$transition['to_country'].'|'.$transition['to_port']]??0);$fromId=(int)($canonicalPortIds[$transition['from_country'].'|'.$transition['from_port']]??0);if($targetId<=0||$fromId<=0||$match['port_name']!==$transition['to_port']||$match['country_code']!==$transition['to_country']||(int)($match['fk_port']??0)!==$targetId)m062Fail('RAW_RATE_CORRECTION_PORT_MISSING');if($rate->pod===$transition['to_port']&&$rate->destination_country===$transition['to_country']&&(int)$rate->fk_destination_port===$targetId)continue;if($rate->pod!==$transition['from_port']||$rate->destination_country!==$transition['from_country']||(int)$rate->fk_destination_port!==$fromId)m062Fail('RAW_RATE_CORRECTION_PRESTATE_INVALID');$method=$match['method']??$resolution['method']??'exact';$score=(float)($match['score']??$resolution['score']??1);m062Query($db,"UPDATE `".$rateTable."` SET pod='".$db->escape($transition['to_port'])."',destination_country='".$db->escape($transition['to_country'])."',fk_destination_port=".$targetId.",port_match_method='".$db->escape($method)."',port_match_score=".number_format($score,4,'.','')." WHERE entity=".$entity." AND rowid=".(int)$rate->rowid." AND pod='".$db->escape($transition['from_port'])."' AND destination_country='".$db->escape($transition['from_country'])."' AND fk_destination_port=".$fromId,'RAW_RATE_CORRECTION_FAILED');$rawCorrectedRows++;}

    $compositeRaw=m062Query($db,"SELECT DISTINCT pod_raw FROM `".$rateTable."` WHERE entity=".$entity." AND pod_raw IS NOT NULL AND pod_raw<>''",'COMPOSITE_AUDIT_READ_FAILED');$compositeAuditRows=0;
    while($raw=$db->fetch_object($compositeRaw)){$resolution=$resolver->resolveList($raw->pod_raw,true);if($resolution['status']!=='matched'||count($resolution['matches'])<2)continue;$ports=array();foreach($resolution['matches']as$match)$ports[]="'".$db->escape($match['port_name'])."'";$audit=m062Query($db,"UPDATE `".$rateTable."` SET port_match_method='composite',port_match_score=1.0000 WHERE entity=".$entity." AND pod_raw='".$db->escape($raw->pod_raw)."' AND pod IN (".implode(',',$ports).")",'COMPOSITE_AUDIT_UPDATE_FAILED');$compositeAuditRows+=(int)$db->affected_rows($audit);}

    $warningsRemoved=0;$batches=m062Query($db,"SELECT rowid,report_json FROM `".$batchTable."` WHERE entity=".$entity,'BATCH_WARNING_READ_FAILED');
    while($batch=$db->fetch_object($batches)){$report=json_decode((string)$batch->report_json,true);if(!is_array($report)||!isset($report['warnings'])||!is_array($report['warnings']))continue;$kept=array();foreach($report['warnings']as$warning){if(preg_match('/^(?:Unmapped destination country|Unmapped destination port\/country|Ambiguous destination port):\s*(.+)$/u',(string)$warning,$match)&&$resolver->resolveList($match[1],true)['status']==='matched'){$warningsRemoved++;continue;}$kept[]=$warning;}$report['warnings']=$kept;$json=json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);m062Query($db,"UPDATE `".$batchTable."` SET warning_count=".count($kept).",report_json='".$db->escape($json)."',accepted_rows=(SELECT COUNT(*) FROM `".$rateTable."` rate WHERE rate.entity=".$entity." AND rate.fk_batch=".(int)$batch->rowid.") WHERE entity=".$entity." AND rowid=".(int)$batch->rowid,'BATCH_WARNING_UPDATE_FAILED');}

    $verifyResolver=new PortNameResolver($db,$entity);$summary=$inspect($verifyResolver);$duplicateAliases=m062Count($db,"SELECT COUNT(*) total FROM (SELECT normalized_alias FROM `".$aliasTable."` WHERE entity=".$entity." AND status=1 GROUP BY normalized_alias HAVING COUNT(*)>1) duplicates");$ownershipDrift=$catalogOwnershipDrift();$remainingRawDrift=$rawRateDrift($verifyResolver);
    if(!$schemaReady()||$duplicateAliases!==0||$summary['unmapped_rows']!==0||$ownershipDrift!==0||$remainingRawDrift!==0)m062Fail('POST_APPLY_VERIFY_FAILED');
    if($db->commit()<=0)m062Fail('TRANSACTION_COMMIT_FAILED');$GLOBALS['m062TransactionActive']=false;
    $releaseLock();
    echo 'M062_PORTS_APPLY=PASS '.json_encode(array('seeded_ports'=>$seededPorts,'seeded_alias_operations'=>$seededAliases,'transferred_builtin_aliases'=>$transferredBuiltinAliases,'archived_duplicate_ports'=>$archivedPorts,'normalized_rows'=>$mappedRows,'raw_corrected_rows'=>$rawCorrectedRows,'cloned_rows'=>$clonedRows,'normalized_values'=>$mappedValues,'composite_audit_rows'=>$compositeAuditRows,'resolved_warnings_removed'=>$warningsRemoved,'remaining'=>$summary['remaining']),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
} catch (Throwable $exception) {
    if(!empty($GLOBALS['m062TransactionActive'])){$db->rollback();$GLOBALS['m062TransactionActive']=false;}$releaseLock();m062Fail('MIGRATION_FAILED');
}
