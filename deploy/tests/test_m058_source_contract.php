<?php
$root=getenv('M058_DOL_DOCUMENT_ROOT')?:dirname(__DIR__,2);$failed=array();
function m058check($ok,$label){global $failed;echo ($ok?'PASS ':'FAIL ').$label."\n";if(!$ok)$failed[]=$label;}
$descriptor=file_get_contents($root.'/custom/chinaplanetoperations/core/modules/modChinaPlanetOperations.class.php');
$auth=file_get_contents($root.'/custom/chinaplanetoperations/class/InternalTaskAuthorizationService.class.php');
$service=file_get_contents($root.'/custom/chinaplanetoperations/class/InternalTaskService.class.php');
$asset=file_get_contents($root.'/custom/chinaplanetoperations/class/InternalTaskAssetService.class.php');
$assetSql=file_get_contents($root.'/custom/chinaplanetoperations/sql/llx_cpo_task_asset.sql');
$migration=file_get_contents($root.'/custom/chinaplanetoperations/class/InternalTaskMigration.class.php');
$action=file_get_contents($root.'/custom/chinaplanetoperations/tasks/action.php');
$assetRoute=file_get_contents($root.'/custom/chinaplanetoperations/tasks/asset.php');
$operations=file_get_contents($root.'/custom/chinaplanetoperations/class/OperationsService.class.php');
m058check(strpos($descriptor,"'2.6.0'")!==false||strpos($descriptor,"'2.7.0'")!==false,'module version includes M059 2.6.0 or later');
foreach(range(500516,500522) as $id)m058check(strpos($descriptor,'array('.$id.',')!==false,'right '.$id.' is defined');
m058check(strpos($descriptor,'$definition[2] === \'read\' ? 1 : 0')!==false&&strpos($descriptor,"'task_read_own' ? 1")===false,'new rights default disabled');
m058check(strpos($auth,"cpo_task_team_manager")!==false&&strpos($auth,"usergroup_user")!==false,'authorization checks grants and active membership');
m058check(substr_count($auth,'u.entity=')>=2&&strpos($auth,'gu.entity=u.entity')!==false&&strpos($service,' AND u.entity=')!==false&&strpos($service,'validInternalUser')!==false,'authorization and user selectors require exact active entity');
m058check(strpos($auth,'fk_soc IS NULL')===false&&strpos($service,'fk_soc IS NULL')===false&&substr_count($auth,'COALESCE(')>=5&&substr_count($service,'COALESCE(')>=5,'internal user selectors accept Dolibarr null or zero third-party linkage');
m058check(strpos($auth,'(int) $this->actor->entity > 0')!==false,'zero-valued Dolibarr user object entity defers to exact database entity validation');
m058check(strpos($auth,'$rowEntity === 0 && (int) $row->admin === 1 && !empty($this->actor->admin)')!==false,'verified Dolibarr entity-zero super administrator may enter the active entity board');
m058check(strpos($auth,'if (!empty($this->actor->admin)) return true')===false,'Dolibarr admin does not bypass high-risk action rights');
m058check(strpos($service,'version=version+1')!==false&&strpos($service,' AND version=')!==false,'task mutations use optimistic CAS');
m058check(strpos($service,'if (!$this->db->begin())')!==false&&strpos($service,'if (!$this->db->commit())')!==false&&strpos($asset,'if(!$this->db->begin())')!==false&&strpos($asset,'if(!$this->db->commit())')!==false,'task and asset transactions fail closed');
m058check(strpos($action,"REQUEST_METHOD']!=='POST'")!==false&&strpos($action,"CSRFCHECK_WITH_TOKEN")!==false,'task mutations are POST and CSRF protected');
m058check(strpos($action,"cpo_m058_requests")!==false&&strpos($action,"request_id")!==false,'task creation has request-scoped duplicate protection');
m058check(strpos($assetRoute,"CSRFCHECK_WITH_TOKEN")!==false&&strpos($assetRoute,"Cache-Control: private, no-store")!==false&&strpos($assetRoute,"X-Content-Type-Options: nosniff")!==false,'asset routes enforce CSRF and safe stream headers');
m058check(strpos($asset,'imagecreatefrom')!==false&&strpos($asset,'25000000')!==false&&strpos($asset,'validateOoxml')!==false&&strpos($asset,'100')!==false,'asset service validates and normalizes allowed formats');
m058check(strpos($asset,'OpenAction')!==false&&strpos($asset,"TargetMode")!==false&&strpos($asset,'payload')===false&&strpos($asset,'Office package polyglot')!==false,'document validation rejects active relationships and package polyglots');
m058check(strpos($asset,'customer_after_approval')===false&&strpos($assetSql,'visibility')===false,'asset storage has no customer visibility state');
m058check(strpos($migration,'DROP TABLE')===false&&strpos($migration,'DROP COLUMN')===false&&strpos($migration,'CREATE TABLE IF NOT EXISTS')!==false,'migration is additive and idempotent');
m058check(strpos($migration,'idx_cpo_activity_task')!==false&&strpos($migration,'columnsMatch')!==false&&strpos($migration,'rightsReady')!==false,'runtime readiness requires exact activity schema indexes and disabled rights');
m058check(strpos($operations,'new InternalTaskService')!==false&&strpos($operations,'INSERT INTO ".MAIN_DB_PREFIX."cpo_task')===false,'legacy OperationsService delegates task writes');
m058check(strpos($operations,'a.fk_task IS NULL OR')!==false&&strpos($service,"t.date_archived IS NULL AND t.status NOT IN ('cancelled','deleted')")!==false,'alternate operation routes preserve task activity and archive boundaries');
$routes=array('tasks/board.php','tasks/card.php','tasks/action.php','tasks/asset.php','tasks/team_admin.php');foreach($routes as $route){$source=file_get_contents($root.'/custom/chinaplanetoperations/'.$route);m058check(strpos($source,'cpo_task_require')!==false,$route.' uses central task gate');}
$forbidden=array('/custom/cpkanban/','/custom/chinaplanetcustomercenter/');$releaseManifest=$root.'/deploy/releases/m060-runtime-files.txt';$diff=is_file($releaseManifest)?file_get_contents($releaseManifest):'';foreach($forbidden as $scope)m058check(strpos((string)$diff,ltrim($scope,'/'))===false,'no M058 changes under '.$scope);$mobileChanges=array_values(array_filter(preg_split('/\R/',trim((string)$diff)),function($path){return strpos($path,'mobile/')===0&&$path!=='mobile/chinaplanet-customer-app/docs/PORTAL_PARITY.md';}));m058check(!$mobileChanges,'no M058 mobile implementation change');
if($failed){fwrite(STDERR,'M058 source contract failed: '.implode(', ',$failed)."\n");exit(1);}echo "M058_SOURCE_CONTRACT=PASS\n";
