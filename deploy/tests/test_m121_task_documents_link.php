<?php
/** Database-free navigation tests; native task/reference authorization is separate. */
require_once __DIR__.'/../../custom/chinaplanetoperations/class/InternalTaskContextService.class.php';
define('DOL_URL_ROOT','/erp');
$enabled=true;
function isModEnabled($name){global $enabled;return $name==='chinaplanetdocuments'&&$enabled;}
class LinkActor121 {public $id=8,$socid=0,$fk_soc=0,$statut=1,$admin=0,$allowed=true,$documentsRight='workspace_read';function hasRight(...$args){return $this->allowed&&$args===['chinaplanetdocuments',$this->documentsRight];}}
class LinkLang121 {private $labels=[];function load($name){$lang=in_array('--ar',$GLOBALS['argv'],true)?'ar_SA':'en_US';foreach(file(__DIR__.'/../../custom/chinaplanetdocuments/langs/'.$lang.'/chinaplanetdocuments.lang') as $line){$pair=explode('=',trim($line),2);if(count($pair)===2)$this->labels[$pair[0]]=$pair[1];}}function trans($name){return $this->labels[$name]??$name;}}
$langs=new LinkLang121;
$r=new ReflectionClass(InternalTaskContextService::class);$svc=$r->newInstanceWithoutConstructor();$actor=new LinkActor121;$r->getProperty('actor')->setValue($svc,$actor);$method=$r->getMethod('documentsWorkspace');
$context=['restricted'=>false,'customer'=>['id'=>7],'project'=>['id'=>11],'shipment'=>['id'=>17]];
$n=0;function linkCheck121($ok,$message){global $n;if(!$ok)throw new RuntimeException($message);$n++;}
if(in_array('--fixture',$argv,true)){echo json_encode($method->invoke($svc,$context),JSON_UNESCAPED_UNICODE);exit;}
$url=$method->invoke($svc,$context);linkCheck121($url['url']==='/erp/custom/chinaplanetdocuments/index.php?socid=7&projectid=11&shipmentid=17&returnto=shipment','canonical scoped shipment link');
linkCheck121($url['label']!=='DocumentsWorkspace','native language label');
$c=$context;$c['shipment']=null;linkCheck121(str_ends_with($method->invoke($svc,$c)['url'],'projectid=11&returnto=project'),'project return');
$c['project']=null;linkCheck121(str_ends_with($method->invoke($svc,$c)['url'],'socid=7&returnto=customer'),'customer return');
$enabled=false;linkCheck121($method->invoke($svc,$context)===null,'disabled module');$enabled=true;
$actor->allowed=false;linkCheck121($method->invoke($svc,$context)===null,'missing Documents right');$actor->allowed=true;
$actor->documentsRight='read';linkCheck121($method->invoke($svc,$context)===null,'legacy collided read right cannot authorize Documents link');$actor->documentsRight='workspace_read';
foreach(['socid','fk_soc'] as $field){$actor->$field=7;linkCheck121($method->invoke($svc,$context)===null,'external actor');$actor->$field=0;}
$actor->statut=0;linkCheck121($method->invoke($svc,$context)===null,'inactive actor');$actor->statut=1;
$c=$context;$c['restricted']=true;linkCheck121($method->invoke($svc,$c)===null,'restricted references');
$c=$context;$c['customer']=null;linkCheck121($method->invoke($svc,$c)===null,'absent customer');
$source=file_get_contents(__DIR__.'/../../custom/chinaplanetoperations/class/InternalTaskContextService.class.php');linkCheck121(strpos($source,"if(\$includeDocuments)\$out['documentsWorkspace']")!==false,'detail-only projection');
echo "M121_TASK_DOCUMENTS_LINK=PASS ASSERTIONS=$n\n";
