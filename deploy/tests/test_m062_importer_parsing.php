<?php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
if (!defined('MAIN_DB_PREFIX')) define('MAIN_DB_PREFIX', 'llx_');
$root=dirname(__DIR__,2);
$GLOBALS['conf']=(object)array('entity'=>1,'global'=>(object)array('CHINAPLANETSP_DEFAULT_POL'=>'Guangzhou'));
require_once $root.'/custom/chinaplanetshippingprices/class/ShippingPriceImporter.class.php';

function m062parseAssert($condition,$message){if(!$condition)throw new RuntimeException($message);}
class M062ParseDb{public function query($sql){return false;}public function fetch_object($res){return null;}}
class M062ParseCell{private $value;private $coordinate;public function __construct($value,$coordinate){$this->value=$value;$this->coordinate=$coordinate;}public function getValue(){return $this->value;}public function getCoordinate(){return $this->coordinate;}}
class M062ParseSheet{
    private $cells;private $title;
    public function __construct(array $cells,$title='M062'){$this->cells=$cells;$this->title=$title;}
    public function getHighestDataRow(){return 3;}
    public function getCellByColumnAndRow($column,$row){$key=$column.':'.$row;return new M062ParseCell($this->cells[$key]??null,chr(64+$column).$row);}
    public function getTitle(){return $this->title;}
}

$db=new M062ParseDb();$user=(object)array('id'=>1);$importer=new ShippingPriceImporter($db,$user,1);
$method=new ReflectionMethod($importer,'parseSheet');$method->setAccessible(true);
$meta=array('container_row'=>2,'origin_col'=>1,'pod_col'=>2,'route_col'=>3,'price_cols'=>array(4=>'20GP'),'notes_col'=>5,'effective_date'=>'2026-08-25','price_group'=>'tariff');
$sheet=new M062ParseSheet(array('1:3'=>'SHEKOU CMA','2:3'=>'SHEKOU-- SKIKDA, ANNABA','3:3'=>'direct','4:3'=>1500,'5:3'=>''));
$parsed=$method->invoke($importer,$sheet,$meta,1000,'USD');
m062parseAssert(count($parsed['rows'])===2,'Composite POD must create exactly two destination rows.');
m062parseAssert(array_column($parsed['rows'],'pod')===array('SKIKDA','ANNABA'),'Composite POD order/canonical names are wrong.');
foreach($parsed['rows']as$row){m062parseAssert($row['country']==='DZ'&&$row['pod_raw']==='SHEKOU-- SKIKDA, ANNABA'&&$row['port_match_method']==='composite'&&$row['public_visible']===1,'Composite row audit/country/visibility is wrong.');}
m062parseAssert(empty($parsed['warnings']),'Mapped composite produced a warning.');

$unsafe=new M062ParseSheet(array('1:3'=>'SHEKOU CMA','2:3'=>'ORANN','3:3'=>'direct','4:3'=>1500,'5:3'=>''),'Unsafe');
$parsedUnsafe=$method->invoke($importer,$unsafe,$meta,1000,'USD');
m062parseAssert(count($parsedUnsafe['rows'])===1&&$parsedUnsafe['rows'][0]['country']===''&&$parsedUnsafe['rows'][0]['public_visible']===0,'Unsafe short typo must remain non-public and unmapped.');
m062parseAssert(count($parsedUnsafe['warnings'])===1&&strpos($parsedUnsafe['warnings'][0],'Unmapped destination port/country: ORANN')===0,'Unsafe typo warning missing.');
$noPrice=new M062ParseSheet(array('1:3'=>'SHEKOU CMA','2:3'=>'ORAN','3:3'=>'direct','4:3'=>'-','5:3'=>''),'NoPrice');
$parsedNoPrice=$method->invoke($importer,$noPrice,$meta,1000,'USD');
$noPriceRows=array_values(array_filter($parsedNoPrice['ignored'],function($row){return$row['reason']==='no_usable_price';}));
m062parseAssert(count($parsedNoPrice['rows'])===0&&count($noPriceRows)===1&&$noPriceRows[0]['value']==='ORAN','Mapped POD without a usable price must retain the raw POD in ignored-row audit.');
print "OK M062 importer parsing composite=2 unsafe_warning=1 mapped_no_price=1\n";
