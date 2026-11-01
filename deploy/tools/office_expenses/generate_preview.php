<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/LocalDraftStore.php';
require_once __DIR__.'/../../../custom/chinaplanetoperations/office_expenses/class/OfficeExpenseEmployeeController.php';
require_once __DIR__.'/../../../custom/chinaplanetoperations/office_expenses/class/OfficeExpenseEmployeePage.php';

if (($argc !== 2 && $argc !== 3) || ($argc === 3 && $argv[2] !== '--mobile-boundaries')) { fwrite(STDERR, "Usage: php generate_preview.php /absolute/output/directory [--mobile-boundaries]\n"); exit(1); }
$output=$argv[1];
if ($output === '' || $output[0] !== '/') throw new InvalidArgumentException('An absolute output directory is required.');
if (!is_dir($output) && !mkdir($output, 0700, true)) throw new RuntimeException('Cannot create preview directory.');
$user=new class {
    public $id=7; public $socid=0; public $status=1;
    public function hasRight($module, $right) { return $module === 'expensereport' && $right === 'creer'; }
};
$service=new OfficeExpenseDraftService(new LocalDraftStore(new PDO('sqlite::memory:')), new OfficeExpenseMoney(), $user, 1, array('egypt'=>array('EGP','USD'), 'china'=>array('CNY','USD')), 'EGP');
$examples=array(
    array('expense_claim','egypt','EGP','700.10','Office supplies / أدوات مكتبية'),
    array('expense_claim','china','CNY','180.29','Office transport / انتقالات المكتب'),
    array('advance','egypt','EGP','1000','Office advance / عهدة المكتب'),
    array('advance_return','egypt','EGP','300','Unused advance returned / رد باقي العهدة'),
);
if($argc===3){
    $examples[]=array('expense_claim','egypt','EGP','0.01','SYNTHETIC minimum / أصغر مبلغ');
    $examples[]=array('expense_claim','china','CNY','999999999999.99','SYNTHETIC maximum / أكبر مبلغ');
    $examples[]=array('expense_claim','egypt','USD','9876543210.12','SYNTHETIC long reference / '.str_repeat('تفاصيل المكتب ',10));
}
foreach ($examples as $index=>$example) {
    list($kind,$office,$currency,$amount,$purpose)=$example;
    $payload=array('kind'=>$kind,'office'=>$office,'currency'=>$currency,'amount'=>$amount,'purpose'=>$purpose,
        'occurred_on'=>'2026-10-31','source_ref'=>'SYNTHETIC-'.str_pad((string) ($index+1), 3, '0', STR_PAD_LEFT),
        'quote'=>$currency==='EGP'?array():array('rate'=>($argc===3?'1':'7.25'),'date'=>'2026-10-31','source'=>'SYNTHETIC illustrative quote'));
    if (in_array($kind,array('advance_return','reimbursement'),true)) $payload['settles_ref']='SYNTHETIC previous record';
    if ($kind === 'transfer') { $payload['from_account_ref']='SYNTHETIC source'; $payload['destination']=array('amount'=>'100','currency'=>'CNY','account_ref'=>'SYNTHETIC destination'); }
    $service->submit($payload, 'synthetic-preview-'.$index);
}
$renderer=new OfficeExpenseEmployeePage();
foreach(array('ar','en')as$language){
    $controller=new OfficeExpenseEmployeeController($service,'synthetic-preview','synthetic-preview',$language);
    foreach(array('expenses','overview','advances')as$view){
        $state=$controller->handle('GET',array('view'=>$view),array());$state['static_preview']=true;
        $file=$language.($view==='expenses'?'':'-'.$view).'.html';
        file_put_contents($output.'/'.$file,$renderer->render($state,$language,'synthetic-preview'));
        if($argc===3 && $view==='expenses'){
            $empty=$state;$empty['drafts']=array();$empty['filtered_drafts']=array();
            file_put_contents($output.'/'.$language.'-empty.html',$renderer->render($empty,$language,'synthetic-preview'));
        }
    }
}
$module=__DIR__.'/../../../custom/chinaplanetoperations/office_expenses';
foreach(array('employee.css','employee.js')as$file) copy($module.'/'.$file,$output.'/'.$file);
if(!is_dir($output.'/assets'))mkdir($output.'/assets',0700);
foreach(glob($module.'/assets/*')as$file)copy($file,$output.'/assets/'.basename($file));
echo 'Generated '.($argc===3?'eight':'six').' shared-renderer read-only Finens previews in '.$output."\n";
