<?php
define('NOLOGIN', 1); define('NOREQUIREMENU', 1); define('NOREQUIREHTML', 1); define('NOCSRFCHECK', 1);
$_SERVER['DOCUMENT_ROOT']='/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-23.0.3/htdocs';$_SERVER['SERVER_NAME']='127.0.0.1';$_SERVER['SERVER_PORT']='8088';
require $_SERVER['DOCUMENT_ROOT'].'/master.inc.php';
$polCases=array('深圳'=>'Shenzhen','蛇口'=>'Shekou','广州'=>'Guangzhou','南沙'=>'Guangzhou - Nansha','盐田'=>'Yantian','大铲湾'=>'Dachan Bay','厦门'=>'Xiamen','宁波'=>'Ningbo','上海'=>'Shanghai','青岛'=>'Qingdao');
foreach($polCases as $needle=>$pol){$sql="UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_rate SET pol='".$db->escape($pol)."' WHERE entity=".(int)$conf->entity." AND pol='UNKNOWN' AND region_label LIKE '%".$db->escape($needle)."%'";if(!$db->query($sql))throw new RuntimeException($db->lasterror());}
$sql="UPDATE ".MAIN_DB_PREFIX."chinaplanetsp_rate SET public_visible=CASE WHEN destination_country IS NOT NULL AND destination_country<>'' AND pol<>'UNKNOWN' AND shipping_line<>'UNKNOWN' THEN 1 ELSE 0 END WHERE entity=".(int)$conf->entity;
if(!$db->query($sql))throw new RuntimeException($db->lasterror());
print "Customer-visible rate quality boundary normalized.\n";
