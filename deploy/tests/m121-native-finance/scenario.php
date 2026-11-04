<?php
/** Actual unmodified Dolibarr 24 objects + DoliDB; fixture-owned configuration only. */
if (PHP_SAPI!=='cli'||count($argv)!==2) exit(64);
$s=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
$fixture=$s['fixture'];
if (!preg_match('~\A/tmp/m121-native-finance-[A-Za-z0-9_-]+\z~',$fixture)||is_link($fixture)||(fileperms($fixture)&0777)!==0700||ini_get('mysqli.default_socket')!==$fixture.'/mysql.sock') throw new RuntimeException('Private fixture identity required before connection');
$pdo=new PDO('mysql:unix_socket='.$fixture.'/mysql.sock;dbname='.$s['database'],'root','',array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
$meta=$pdo->query('SELECT @@skip_networking networking,@@socket socket,@@datadir datadir,DATABASE() db,VERSION() version,@@sql_mode sql_mode')->fetch(PDO::FETCH_ASSOC);
if ((int)$meta['networking']!==1||$meta['socket']!==$fixture.'/mysql.sock'||realpath($meta['datadir'])!==realpath($fixture.'/data')||$meta['db']!==$s['database']) throw new RuntimeException('Wrong native fixture server; no writes allowed');
if ((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0) throw new RuntimeException('Native fixture database must be empty');
require __DIR__.'/sql_import.php';
$tables=glob($s['core'].'/install/mysql/tables/*.sql'); $creates=array();$keys=array();
foreach ($tables as $file) { if (strpos(basename($file),'-')!==false||strpos(basename($file),'llx_')!==0) continue; $text=nf_import_sql(file_get_contents($file)); if (preg_match('/create\s+table\b/i',$text)) $creates[$file]=$text; else $keys[$file]=$text; }
$imported=0;
foreach ($creates as $file=>$sql) { try { $pdo->exec($sql); $imported++; } catch (Throwable $e) { throw new RuntimeException('Core create DDL failed: '.basename($file).' '.$e->getMessage()); } }
// Native installer order: tables, then keys, then dictionary rows.
foreach ($keys as $file=>$sql) {
    foreach (explode(';',$sql) as $statement) {
        if (trim($statement)==='') continue;
        try { $pdo->exec($statement); } catch (PDOException $e) {
            if (isset($e->errorInfo[1])&&(int)$e->errorInfo[1]===1061) continue;
            throw new RuntimeException('Core keys failed: '.basename($file).' '.$e->getMessage());
        }
    }
}
foreach (glob($s['core'].'/install/mysql/data/*.sql') as $file) {
    if (strpos(basename($file),'llx_accounting_account_')===0||strpos(basename($file),'-')!==false||strpos(basename($file),'llx_')!==0) continue;
    foreach (preg_split('/\r?\n/',nf_import_sql(file_get_contents($file))) as $row) {
        $row=trim(str_ireplace('__ENTITY__','1',$row)); if ($row==='') continue;
        try { $pdo->exec($row); } catch (PDOException $e) {
            // Installer tolerates duplicate dictionary records, never other errors.
            if (isset($e->errorInfo[1])&&(int)$e->errorInfo[1]===1062) continue;
            throw new RuntimeException('Core dictionary import failed: '.basename($file).' '.$e->getMessage());
        }
    }
}
foreach (array('cpcc_customer_receipt','cpcc_fund_allocation','cpcc_fund_event','cpcc_guided_receipt','cpcc_financial_entry','cpcc_project_charge') as $table) $pdo->exec(nf_import_sql(file_get_contents($s['schema_source'].'/custom/chinaplanetcustomercenter/sql/llx_'.$table.'.sql')));
foreach (array('cpo_operation','cpo_supplier_action') as $table) $pdo->exec(nf_import_sql(file_get_contents($s['schema_source'].'/custom/chinaplanetoperations/sql/llx_'.$table.'.sql')));
echo 'CHECKPOINT: imported '.$imported." native core table definitions and standard dictionaries/keys\n";
define('DOL_DOCUMENT_ROOT',$s['overlay']); define('DOL_DATA_ROOT',$fixture.'/documents'); define('DOL_URL_ROOT',''); define('DOL_VERSION','24.0.1'); define('MAIN_DB_PREFIX','llx_'); define('DOL_DOCUMENT_ROOT_ALT',$s['overlay'].'/custom');
date_default_timezone_set('UTC'); $_SERVER['DOCUMENT_ROOT']=DOL_DOCUMENT_ROOT; $_SERVER['QUERY_STRING']=''; $_SERVER['PHP_SELF']='/fixture.php'; $_SERVER['REMOTE_ADDR']='127.0.0.1';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/conf.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
$conf=new Conf(); $conf->entity=1; $conf->currency='USD'; $conf->global->MAIN_DISABLE_PDF_AUTOUPDATE=1; $conf->global->MAIN_MONNAIE='USD'; $conf->global->MAIN_LOGTOHTML=0; $conf->global->MAIN_LOGEVENTS=0;
$conf->global->MAIN_MAX_DECIMALS_TOT=2; $conf->global->MAIN_MAX_DECIMALS_UNIT=8; $conf->global->MAIN_MAX_DECIMALS_SHOWN=2;
$conf->db->type='mysqli'; $conf->db->character_set='utf8mb4'; $conf->db->dolibarr_main_db_collation='utf8mb4_unicode_ci';
foreach (array('bank','facture','fournisseur','societe') as $module) { $conf->modules[$module]=1; if (!isset($conf->$module)) $conf->$module=new stdClass(); $conf->$module->enabled=1; $conf->$module->dir_output=DOL_DATA_ROOT.'/'.$module; $conf->$module->multidir_output=array(1=>DOL_DATA_ROOT.'/'.$module); }
$conf->file->dol_document_root=array(DOL_DOCUMENT_ROOT); $conf->modules_parts['models']=array();
$langs=new Translate('', $conf); $langs->setDefaultLang('en_US'); $langs->load('main');
require_once DOL_DOCUMENT_ROOT.'/core/db/mysqli.class.php';
$db=new DoliDBMysqli('mysqli','localhost','root','',$s['database']);
if (!$db->ok) throw new RuntimeException('Native DoliDB fixture connection failed');
$hookmanager=new HookManager($db);
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
function nf_sql($sql) { global $db; $r=$db->query($sql); if (!$r) throw new RuntimeException('Synthetic fixture SQL failed: '.$db->lasterror()); return $r; }
function nf_assert($ok,$message) { if (!$ok) throw new RuntimeException($message); }
nf_sql("INSERT INTO llx_user(rowid,entity,login,lastname,admin,statut) VALUES(9,1,'synthetic-native','Synthetic actor',1,1)");
$user=new User($db); nf_assert($user->fetch(9)>0,'Synthetic native actor fetch');
nf_sql("INSERT INTO llx_societe(rowid,entity,nom,client,fournisseur,status) VALUES(7,1,'Synthetic customer',1,0,1),(88,1,'Synthetic supplier',0,1,1)");
$country=$db->fetch_object(nf_sql("SELECT rowid FROM llx_c_country WHERE code='US'")); nf_assert($country,'Native US country dictionary present');
$countryId=(int)$country->rowid;
$mysoc=new Societe($db); $mysoc->name='Synthetic fixture company'; $mysoc->country_code='US'; $mysoc->country_id=$countryId;
nf_sql("INSERT INTO llx_bank_account(rowid,entity,ref,label,courant,clos,currency_code,fk_pays) VALUES(11,1,'SYN-BANK','Synthetic USD bank',1,0,'USD',".$countryId.")");
nf_sql("INSERT INTO llx_facture(rowid,ref,entity,type,fk_soc,datef,fk_statut,paye,total_ht,total_ttc,multicurrency_code,multicurrency_tx,multicurrency_total_ht,multicurrency_total_ttc) VALUES(20,'SYN-DEP20',1,3,7,'2026-11-03',1,0,100,100,'USD',1,100,100)");
nf_sql("INSERT INTO llx_facture_fourn(rowid,ref,ref_supplier,entity,type,fk_soc,datef,fk_statut,paye,total_ht,total_ttc,multicurrency_code,multicurrency_tx,multicurrency_total_ht,multicurrency_total_ttc) VALUES(201,'SYN-SUP201','SYN-VENDOR',1,0,88,'2026-11-03',1,0,60,60,'USD',1,60,60)");
$mode=$db->fetch_object(nf_sql("SELECT id FROM llx_c_paiement WHERE code='VIR' LIMIT 1")); nf_assert($mode,'Native VIR payment dictionary present');
$third=new Societe($db); nf_assert($third->fetch(7)>0,'Native customer fetch');
$payment=new Paiement($db); $payment->datepaye=strtotime('2026-11-03 12:00:00'); $payment->paiementid=(int)$mode->id; $payment->num_payment='SYN-NATIVE-CUSTOMER'; $payment->note_private='Synthetic native fixture'; $payment->fk_account=11; $payment->amounts=array(20=>100); $payment->multicurrency_code=array(20=>'USD'); $payment->multicurrency_tx=array(20=>1);
$customerPayment=$payment->create($user,0,$third); nf_assert($customerPayment>0,'Actual Paiement.create failed: '.$payment->error);
$customerBank=$payment->addPaymentToBank($user,'payment','Synthetic actual customer payment',11,'',''); nf_assert($customerBank>0,'Actual customer bank posting failed: '.$payment->error);
echo "CHECKPOINT: actual native customer payment and bank line created\n";
$supplier=new Societe($db); nf_assert($supplier->fetch(88)>0,'Native supplier fetch');
$sp=new PaiementFourn($db); $sp->datepaye=strtotime('2026-11-03 12:00:00'); $sp->paiementid=(int)$mode->id; $sp->num_payment='SYN-NATIVE-SUPPLIER'; $sp->note_private='Synthetic native fixture'; $sp->fk_account=11; $sp->amounts=array(201=>40); $sp->multicurrency_code=array(201=>'USD'); $sp->multicurrency_tx=array(201=>1);
$supplierPayment=$sp->create($user,0,$supplier); nf_assert($supplierPayment>0,'Actual PaiementFourn.create failed: '.$sp->error);
$supplierBank=$sp->addPaymentToBank($user,'payment_supplier','Synthetic actual supplier payment',11,'',''); nf_assert($supplierBank>0,'Actual supplier bank posting failed: '.$sp->error);
echo "CHECKPOINT: actual native supplier payment and signed bank line created\n";
$rows=array(); foreach (array('paiement','paiement_facture','paiementfourn','paiementfourn_facturefourn','bank','bank_url') as $table) $rows[$table]=$pdo->query('SELECT * FROM llx_'.$table)->fetchAll(PDO::FETCH_ASSOC);
nf_assert(count($rows['paiement'])===1&&count($rows['paiement_facture'])===1,'Exactly one customer payment and invoice link');
nf_assert(count($rows['paiementfourn'])===1&&count($rows['paiementfourn_facturefourn'])===1,'Exactly one supplier payment and invoice link');
nf_assert(count($rows['bank'])===2&&count($rows['bank_url'])===4,'Exactly two bank lines and four source/company links');
nf_assert((int)$rows['paiement'][0]['fk_bank']===$customerBank&&(int)$rows['paiementfourn'][0]['fk_bank']===$supplierBank,'Native payment bank identities preserved');
$bankById=array(); foreach ($rows['bank'] as $row) $bankById[(int)$row['rowid']]=$row;
nf_assert(abs((float)$bankById[$customerBank]['amount']-100)<0.000001,'Customer cash is +100 USD');
nf_assert(abs((float)$bankById[$supplierBank]['amount']+40)<0.000001,'Supplier cash is -40 USD');
nf_assert($bankById[$customerBank]['amount_main_currency']===null&&$bankById[$supplierBank]['amount_main_currency']===null,'Base currency bank rows retain native null foreign-base fields');
nf_assert((int)$rows['paiement_facture'][0]['fk_facture']===20&&abs((float)$rows['paiement_facture'][0]['amount']-100)<0.000001,'Customer invoice allocation identity and amount');
nf_assert((int)$rows['paiementfourn_facturefourn'][0]['fk_facturefourn']===201&&abs((float)$rows['paiementfourn_facturefourn'][0]['amount']-40)<0.000001,'Supplier invoice allocation identity and amount');
$nativeLifecycle=require __DIR__.'/native_cfs_cases.php';
$nativeSnapshot=require __DIR__.'/native_snapshot_cases.php';
$result=array('native_snapshot'=>$nativeSnapshot,'native_lifecycle'=>$nativeLifecycle,'status'=>'native_cash_methods_passed','core_version'=>'24.0.1','engine'=>$meta['version'],'result_assertions'=>9,'scope'=>'Seeded invoice/company/user/bank-account context; actual unmodified core payment create and addPaymentToBank; closepaidinvoices=0; actual trigger dispatcher; PDF autoupdate disabled in fixture only','generated_ids'=>array('customer_payment'=>$customerPayment,'customer_bank'=>$customerBank,'supplier_payment'=>$supplierPayment,'supplier_bank'=>$supplierBank),'native_rows'=>$rows,'limits'=>array('No authenticated UI or full NativeAdvanceWorkflow/postPayment command acceptance','Not MariaDB or production acceptance'));
file_put_contents($s['output'].'/result.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)); chmod($s['output'].'/result.json',0600);
$db->close();
echo "PASS actual native financial methods\n";
