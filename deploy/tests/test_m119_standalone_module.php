<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$module=$root.'/custom/chinaplanetofficeexpenses';
$descriptor=$module.'/core/modules/modChinaPlanetOfficeExpenses.class.php';
if(!is_file($descriptor)){fwrite(STDERR,"FAIL standalone Office Expenses module descriptor is missing\n");exit(1);}
$native=$argv[1]??'/Users/mac/Documents/dev/dolibarr/dolibarr-Dev/dolibarr-24.0.1/htdocs';
define('DOL_DOCUMENT_ROOT',$native);
require_once $descriptor;
require_once $module.'/class/OfficeExpenseModuleAccess.php';
$pass=0;$fail=0;
function stest($name,$f){global $pass,$fail;try{$f();$pass++;echo "PASS $name\n";}catch(Throwable $e){$fail++;fwrite(STDERR,"FAIL $name: ".$e->getMessage()."\n");}}
function seq($actual,$expected){if($actual!==$expected)throw new RuntimeException(var_export($actual,true).' differs from '.var_export($expected,true));}
stest('descriptor owns a unique namespace and depends only on native expense reports',function(){
    $m=new modChinaPlanetOfficeExpenses(null);
    seq($m->numero,500600);seq($m->rights_class,'chinaplanetofficeexpenses');seq($m->depends,array('modExpenseReport'));
    seq($m->menu[0]['url'],'/custom/chinaplanetofficeexpenses/index.php');
    seq(strpos($m->menu[0]['enabled'],'chinaplanetoperations'),false);
    foreach($m->rights as $right)seq($right[3],0);
    seq($m->dirs,array());
});
class LifecycleProbe extends modChinaPlanetOfficeExpenses {
    public $calls=array();
    protected function _init($sql,$options=''){$this->calls[]=array('init',$sql);return 1;}
    protected function _remove($sql,$options=''){$this->calls[]=array('remove',$sql);return 1;}
}
stest('enable and disable lifecycle passes no destructive or domain SQL',function(){
    $m=new LifecycleProbe(null);seq($m->init(),1);seq($m->remove(),1);seq($m->calls,array(array('init',array()),array('remove',array())));
});
function suser($own=false){return new class($own){public $id=7;public $admin=0;public $socid=0;public $status=1;private $own;public function __construct($own){$this->own=$own;}public function hasRight($module,$right){return $module==='expensereport'||($this->own&&$module==='chinaplanetofficeexpenses'&&$right==='draft');}};}
function sconf($enabled=true){return (object)array('entity'=>1,'chinaplanetofficeexpenses'=>(object)array('enabled'=>$enabled),'chinaplanetoperations'=>(object)array('enabled'=>false));}
stest('native expense rights alone do not authorize standalone access',function(){seq(OfficeExpenseModuleAccess::allows(suser(false),sconf()),false);seq(OfficeExpenseModuleAccess::allows(suser(true),sconf()),true);});
stest('disabled module guests external and inactive users fail closed',function(){
    $u=suser(true);seq(OfficeExpenseModuleAccess::allows($u,sconf(false)),false);
    foreach(array('id'=>0,'socid'=>99,'status'=>0)as$key=>$value){$bad=clone $u;$bad->$key=$value;seq(OfficeExpenseModuleAccess::allows($bad,sconf()),false);}
});
stest('native administrator remains independent of Operations module state',function(){$u=suser();$u->admin=1;seq(OfficeExpenseModuleAccess::allows($u,sconf()),true);});
stest('legacy class includes resolve once to the canonical implementation',function()use($root,$module){
    require_once $module.'/class/OfficeExpenseMoney.php';
    require_once $root.'/custom/chinaplanetoperations/office_expenses/class/OfficeExpenseMoney.php';
    seq((new ReflectionClass('OfficeExpenseMoney'))->getFileName(),$module.'/class/OfficeExpenseMoney.php');
    seq((new OfficeExpenseMoney())->amount('0.10','EGP'),'0.10');
});
stest('schema and private receipt configuration retain their original identities',function()use($root,$module){
    $legacy=$root.'/custom/chinaplanetoperations/office_expenses';
    foreach(array('manifest.json','001_drafts.sql','002_draft_audit.sql','003_schema_state.sql','receipts/manifest.json','receipts/001_receipts.sql','receipts/002_receipt_audit.sql')as$file)seq(hash_file('sha256',$module.'/schema/'.$file),hash_file('sha256',$legacy.'/schema/'.$file));
    require_once $module.'/class/OfficeExpensePilotConfiguration.php';seq(OfficeExpensePilotConfiguration::PATH,'/var/lib/chinaplanetos-office-expenses/pilot.json');
});
stest('old HTTP entry includes the canonical route without replay or independent application',function()use($root){
    $old=file_get_contents($root.'/custom/chinaplanetoperations/office_expenses/index.php');
    seq(strpos($old,'CPO_OFFICEEXPENSES_COMPATIBILITY_SHIM')!==false,true);
    seq(strpos($old,"require dirname(__DIR__,2).'/chinaplanetofficeexpenses/index.php'")!==false,true);
    seq(strpos($old,'OfficeExpenseEmployeeController'),false);
});
echo "$pass passed, $fail failed\n";exit($fail?1:0);
