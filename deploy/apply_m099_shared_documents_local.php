<?php
/** Explicit local-only metadata registration; keeps the card UI flag unchanged and enables canonical storage. */
if(PHP_SAPI!=='cli'||getenv('M099_LOCAL_RUNTIME')!=='1')exit(1);
$config=getenv('M099_QA_CONFIG');if(!$config||!is_file($config))throw new RuntimeException('Local config required.');require $config;
if(!preg_match('/^m097_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name)||$dolibarr_main_db_host!=='127.0.0.1')throw new RuntimeException('Synthetic local only.');
$_SERVER['DOCUMENT_ROOT']=$dolibarr_main_document_root;$_SERVER['SERVER_NAME']='127.0.0.1';$_SERVER['HTTP_HOST']='127.0.0.1';require $dolibarr_main_document_root.'/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';require_once __DIR__.'/../custom/chinaplanetlogistics/class/ShipmentDocumentService.class.php';
$r=$db->query('SELECT DATABASE() n');if(!preg_match('/^m097_qa_[a-f0-9]{10}$/D',$db->fetch_object($r)->n))throw new RuntimeException('Database drift.');
(new ShipmentDocumentMigration($db))->apply();$u=new User($db);$u->fetch(1);$u->loadRights();if(empty($u->admin))throw new RuntimeException('Native admin required.');
ShipmentDocumentMigration::assertStorageRootsUnambiguous($db);$conf->global->CPO_SHIPMENT_CARDS_ENABLED=1;$svc=new ShipmentDocumentService($db,$u);$r=$db->query('SELECT rowid,ref FROM '.MAIN_DB_PREFIX.'chinaplanetlogistics WHERE entity='.(int)$conf->entity);$count=0;
while($s=$db->fetch_object($r)){foreach(cpcc_portal_shipment_doc_roots($s->rowid,$s->ref) as $root){if(!is_dir($root))continue;foreach(new DirectoryIterator($root) as $file){if(!$file->isFile()||$file->isLink()||preg_match('/(\\.meta|_preview.*\\.png)$/',$file->getFilename()))continue;$svc->registerExisting($s->rowid,basename($root).'/'.$file->getFilename());$count++;}}}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';if(dolibarr_set_const($db,'CPL_SHARED_DOCUMENTS_ENABLED','1','chaine',0,'',(int)$conf->entity)<=0)throw new RuntimeException('Canonical storage activation failed.');
echo json_encode(['status'=>'READY','registered_files'=>$count,'persisted_ui_flag_unchanged'=>true,'shared_storage_enabled'=>true])."\n";
