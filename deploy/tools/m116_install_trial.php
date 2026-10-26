<?php
/** CLI-only test installer. Never grants rights, enables feature or touches accounting. */
if(PHP_SAPI!=='cli'||getenv('M116_ISOLATED_TEST')!=='1'){fwrite(STDERR,"Refused: M116_ISOLATED_TEST=1 and CLI required.\n");exit(2);}
$root=$argv[1]??'';if(!is_file($root.'/main.inc.php')){fwrite(STDERR,"Provide isolated Dolibarr document root.\n");exit(2);}
if(!preg_match('/^m116_test_[a-z0-9_]+$/D',getenv('M116_EXPECTED_DATABASE')?:'')){fwrite(STDERR,"Expected database must start m116_test_.\n");exit(2);}
define('NOLOGIN',1);define('NOREQUIREMENU',1);define('NOREQUIREHTML',1);define('NOREQUIREAJAX',1);
require $root.'/main.inc.php';
if(($dolibarr_main_db_name??'')!==getenv('M116_EXPECTED_DATABASE')){fwrite(STDERR,"Refused: database identity mismatch.\n");exit(2);}
$sql=file_get_contents(__DIR__.'/../../custom/chinaplanetoperations/sql/llx_cpo_account.sql');
foreach(explode(';',$sql) as $statement){$statement=trim($statement);if($statement==='')continue;if(!$db->query(str_replace('llx_',MAIN_DB_PREFIX,$statement))){fwrite(STDERR,"Schema install stopped. DDL may be partially applied; inspect the test database.\n");exit(1);}}
echo "Installed M116 test tables only. Feature disabled and no rights granted.\n";$db->close();
