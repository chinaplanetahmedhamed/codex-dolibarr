<?php
/** Read-only production prerequisites for the M083 staff-first workflow. */
if(PHP_SAPI!=='cli'){fwrite(STDERR,"M083_PREFLIGHT=FAIL REASON=CLI_ONLY\n");exit(1);}
define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);define('NOREQUIREAJAX',1);define('NOCSRFCHECK',1);
$root=rtrim(getenv('M083_DOL_DOCUMENT_ROOT')?:'/var/www/html','/');
if(!is_file($root.'/master.inc.php')){fwrite(STDERR,"M083_PREFLIGHT=FAIL REASON=DOLIBARR_ROOT_INVALID\n");exit(1);}
$_SERVER['DOCUMENT_ROOT']=$root;$_SERVER['SERVER_NAME']='erp.visiontrading.biz';$_SERVER['SERVER_PORT']='443';$_SERVER['HTTP_HOST']='erp.visiontrading.biz';$_SERVER['REQUEST_METHOD']='GET';chdir($root);require $root.'/master.inc.php';global$db,$conf;
$entity=(int)$conf->entity;
$one=function($sql,$reason)use($db){$res=$db->query($sql);$row=$res?$db->fetch_object($res):null;if(!$row){fwrite(STDERR,'M083_PREFLIGHT=FAIL REASON='.$reason."\n");exit(1);}return$row;};
foreach(array('chinaplanetoperations','chinaplanetcustomercenter','chinaplanetlogistics','propal','commande','facture','fournisseur','banque','projet')as$module){if(empty($conf->modules[$module])){fwrite(STDERR,'M083_PREFLIGHT=FAIL REASON=MODULE_DISABLED MODULE='.$module."\n");exit(1);}}
$staff=$one("SELECT COUNT(*) n FROM ".MAIN_DB_PREFIX."user WHERE entity=".$entity." AND statut=1 AND COALESCE(fk_soc,0)=0",'STAFF_QUERY_FAILED');
$suppliers=$one("SELECT COUNT(*) n FROM ".MAIN_DB_PREFIX."societe WHERE entity=".$entity." AND fournisseur=1 AND status=1",'SUPPLIER_QUERY_FAILED');
$banks=$one("SELECT COUNT(*) n,COUNT(DISTINCT UPPER(COALESCE(NULLIF(TRIM(currency_code),''),'".$db->escape(strtoupper((string)getDolCurrency()))."'))) currencies FROM ".MAIN_DB_PREFIX."bank_account WHERE entity=".$entity." AND clos=0",'BANK_QUERY_FAILED');
if((int)$staff->n<1){fwrite(STDERR,"M083_PREFLIGHT=FAIL REASON=ACTIVE_INTERNAL_STAFF_REQUIRED\n");exit(1);}
if((int)$suppliers->n<1){fwrite(STDERR,"M083_PREFLIGHT=FAIL REASON=ACTIVE_SUPPLIER_REQUIRED\n");exit(1);}
if((int)$banks->n<1){fwrite(STDERR,"M083_PREFLIGHT=FAIL REASON=OPEN_BANK_ACCOUNT_REQUIRED\n");exit(1);}
echo'M083_PREFLIGHT=PASS ENTITY='.$entity.' STAFF='.(int)$staff->n.' SUPPLIERS='.(int)$suppliers->n.' BANKS='.(int)$banks->n.' BANK_CURRENCIES='.(int)$banks->currencies.' BASE_CURRENCY='.strtoupper((string)getDolCurrency())." DATABASE_MUTATED=NO\n";
