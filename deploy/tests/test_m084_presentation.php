<?php
/** No database writes. Proves date and locale regression contracts. */
require_once __DIR__.'/../../custom/chinaplanetcustomercenter/lib/portal.lib.php';
require_once __DIR__.'/../../custom/chinaplanetlogistics/lib/shipment_customer.lib.php';
function check084($value,$message){if(!$value)throw new RuntimeException($message);}
class M084DateDb {public function jdate($value){return strtotime($value.' UTC');}}
$db=new M084DateDb();
function dol_print_date($timestamp,$format){check084(is_int($timestamp),'raw date string reached core formatter');return gmdate($format==='day'?'Y-m-d':'Y-m-d H:i',$timestamp);}
check084(cpcc_portal_date('2026-10-05')==='2026-10-05','SQL date lost');
check084(cpcc_portal_date('2026-10-05 14:25:00','dayhour')==='2026-10-05 14:25','SQL datetime lost');
check084(cpcc_portal_date(strtotime('2026-10-05 UTC'))==='2026-10-05','timestamp lost');
foreach(array(null,'','0000-00-00','2026-02-30','wrong',0) as $value)check084(cpcc_portal_date($value)==='','invalid date rendered as a real date');
class M084Language {private $ar;public function __construct($ar){$this->ar=$ar;}public function trans($key){return array('FormatDateShort'=>$this->ar?'%d/%m/%Y':'%m/%d/%Y','DIRECTION'=>$this->ar?'rtl':'ltr','CPLShipmentDetails'=>$this->ar?'تفاصيل الشحنة':'Shipment details')[$key]??$key;}}
foreach(array(false,true) as $ar){$primary=new M084Language($ar);$proxy=new CplBilingualTranslate($primary,new M084Language(!$ar));check084($proxy->trans('FormatDateShort')===$primary->trans('FormatDateShort'),'date mask was concatenated');check084($proxy->trans('DIRECTION')===$primary->trans('DIRECTION'),'direction concatenated');check084(strpos($proxy->trans('CPLShipmentDetails'),' / ')!==false,'bilingual labels lost');}
echo "M084_PRESENTATION_PASS\n";
