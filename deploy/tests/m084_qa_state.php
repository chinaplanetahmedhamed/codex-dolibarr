<?php
if(PHP_SAPI!=='cli')exit(1);
$qaRoot = realpath((string)getenv('M084_QA_ROOT'));
if (!$qaRoot || basename($qaRoot) !== 'm084-qa') exit(2);
include $qaRoot.'/htdocs/conf/conf.php';
if(!preg_match('/^m084_qa_[a-f0-9]{10}$/D',$dolibarr_main_db_name)||$dolibarr_main_url_root!=='http://127.0.0.1:8094')exit(2);
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli($dolibarr_main_db_host,$dolibarr_main_db_user,$dolibarr_main_db_pass,$dolibarr_main_db_name,(int)$dolibarr_main_db_port);
$mode=$argv[1]??'snapshot';
if($mode==='snapshot'){
 $out=[];foreach(['cpkanban_card','cpkanban_column','projet','facture','facturedet','paiement','paiement_facture','cpcc_financial_entry','cpcc_customer_receipt','cpcc_fund_allocation','chinaplanetlogistics'] as $t){$r=$db->query('SELECT * FROM llx_'.$t.' ORDER BY rowid');$rows=$r->fetch_all(MYSQLI_ASSOC);$out[$t]=['count'=>count($rows),'hash'=>hash('sha256',json_encode($rows))];}echo json_encode($out);exit;
}
if($mode==='sync_probe'){$db->query("UPDATE llx_projet SET description='M084 sync probe ".bin2hex(random_bytes(6))."' WHERE rowid=101 AND entity=1");echo 'SYNC_PROBE_READY';exit;}
if($mode==='hide_card_table'){$db->query('RENAME TABLE llx_cpkanban_card TO llx_m084_hidden_card');echo 'HIDDEN';exit;}
if($mode==='restore_card_table'){$db->query('RENAME TABLE llx_m084_hidden_card TO llx_cpkanban_card');echo 'RESTORED';exit;}
throw new RuntimeException('INVALID_MODE');
