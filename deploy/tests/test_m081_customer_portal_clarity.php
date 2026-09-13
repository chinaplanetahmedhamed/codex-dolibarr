<?php
if(PHP_SAPI!=='cli'){fwrite(STDERR,"M081=FAIL CLI_ONLY\n");exit(1);}
$root=dirname(__DIR__,2);
function m081Source($root,$path){$source=file_get_contents($root.'/'.$path);if($source===false)throw new RuntimeException('Missing '.$path);return $source;}
function m081Assert($condition,$label){if(!$condition){fwrite(STDERR,'M081=FAIL '.$label."\n");exit(1);}print 'PASS '.$label.PHP_EOL;}

$trace=m081Source($root,'custom/chinaplanetcustomercenter/lib/commercial_trace.lib.php');
m081Assert(strpos($trace,'AND p.fk_soc=".$socid."')!==false,'quotation list is customer scoped');
m081Assert(strpos($trace,'AND p.fk_statut>0')!==false,'draft quotations are excluded');
m081Assert(strpos($trace,"ee.sourcetype='propal' AND ee.targettype='commande'")!==false,'quotation to order uses native links');
m081Assert(strpos($trace,'AND f.fk_soc=".$socid."')!==false,'order invoice trace is customer scoped');
m081Assert(strpos($trace,'AND s.fk_soc=".$socid."')!==false&&strpos($trace,'AND s.customer_visible=1')!==false,'shipment trace is customer scoped and published only');

$quotations=m081Source($root,'custom/chinaplanetcustomercenter/quotations.php');
$quotation=m081Source($root,'custom/chinaplanetcustomercenter/quotation.php');
m081Assert(strpos($quotations,'cpcc_portal_customer_scope_id')!==false&&strpos($quotation,'cpcc_portal_customer_scope_id')!==false,'quotation routes derive authenticated customer scope');
m081Assert(strpos($quotation,'(int)$proposal->socid!==$socid')!==false,'quotation detail rejects foreign customer');
m081Assert(strpos($quotation,'$proposal->statut<=0')!==false,'quotation detail rejects external draft');
foreach(array('<form','action=','setSigned','setRefused','send') as $needle)m081Assert(stripos($quotation,$needle)===false,'quotation detail remains read only: '.$needle);
m081Assert(strpos($quotation,'note_private')===false,'quotation detail never renders private notes');

$financial=m081Source($root,'custom/chinaplanetlogistics/reports.php');
m081Assert(strpos($financial,'cpcc_financial_customer_invoices')!==false,'financial report uses shared original-currency projection');
m081Assert(strpos($financial,'foreach($totals as $code=>$values)')!==false,'financial totals render per currency');
m081Assert(strpos($financial,"SUM(total_ttc)")===false&&strpos($financial,'SUM(pf.amount)')===false,'financial report does not mix base-currency sums');
m081Assert(substr_count($financial,'dol_escape_htmltag($invoice[\'currency\'])')>=3,'invoice rows label original currency');

$documents=m081Source($root,'custom/chinaplanetcustomercenter/documents.php');
m081Assert(strpos($documents,'$socid > 0 ? cpcc_export_documents($db, $socid, 0, 0, $is_external)')!==false,'main Documents page loads external published commercial documents');
$reports=m081Source($root,'custom/chinaplanetcustomercenter/operation_reports.php');
m081Assert(strpos($reports,"css/documents.css")!==false&&strpos($reports,'cpcc-report-card')!==false,'operation reports use readable responsive cards');

$portal=m081Source($root,'custom/chinaplanetcustomercenter/lib/portal.lib.php');
$board=m081Source($root,'custom/chinaplanetcustomercenter/board.php');
m081Assert(strpos($portal,"'quotations' =>")!==false&&strpos($portal,'/chinaplanetcustomercenter/quotations.php')!==false,'quotation navigation is available');
m081Assert(strpos($board,'$quotationTotal')!==false&&strpos($board,'$quotationsUrl')!==false,'dashboard shows quotation count and link');

$order=m081Source($root,'custom/chinaplanetcustomercenter/order.php');
$project=m081Source($root,'custom/chinaplanetcustomercenter/project.php');
m081Assert(strpos($order,'cpcc_customer_order_trace')!==false&&strpos($order,'مسار الطلب / Order Journey')!==false,'order detail shows linked commercial journey');
m081Assert(strpos($project,'مسار المشروع / Project Journey')!==false&&strpos($project,'cpcc_customer_quotations')!==false,'project shows customer-safe journey and quotations');

echo "M081=PASS CUSTOMER_SCOPE=YES ORIGINAL_CURRENCIES=SEPARATE QUOTATIONS=READ_ONLY DOCUMENTS=VISIBLE REPORT_CARDS=RESPONSIVE TRACE=YES\n";
