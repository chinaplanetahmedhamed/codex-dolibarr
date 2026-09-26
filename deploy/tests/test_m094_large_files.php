<?php
// Disk-backed fixtures prove limits without needing hundreds of MB of PHP memory.
define('MAIN_DB_PREFIX','llx_');
require dirname(__DIR__,2).'/custom/chinaplanetoperations/class/InternalTaskAssetService.class.php';
$tmp=sys_get_temp_dir().'/m094-large-'.bin2hex(random_bytes(4));mkdir($tmp,0700);
$conf=(object)array('entity'=>1,'chinaplanetoperations'=>(object)array('dir_output'=>$tmp));
$s=new InternalTaskAssetService(new stdClass(),new stdClass(),1,$tmp);$m=new ReflectionMethod($s,'validateAndNormalize');$m->setAccessible(true);
function check94($ok,$label){if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label."\n";}
function reject94($fn,$label){try{$fn();}catch(Throwable $e){echo 'PASS '.$label."\n";return;}throw new RuntimeException('FAIL '.$label);}
function fill94($f,$bytes,$pattern){while($bytes>0){$block=substr(str_repeat($pattern,65536),0,min($bytes,65536));fwrite($f,$block);$bytes-=strlen($block);}}
try {
    $max=InternalTaskAssetService::MAX_FILE_BYTES;
    $p=$tmp.'/large.txt';$f=fopen($p,'wb');fill94($f,$max,"Task attachment validation.\n");fclose($f);
    check94($m->invoke($s,$p,'large.txt')['size']===$max,'200 MB text accepted');
    file_put_contents($p,'x',FILE_APPEND);clearstatcache(true,$p);reject94(function()use($m,$s,$p){$m->invoke($s,$p,'large.txt');},'200 MB plus one byte rejected');unlink($p);
    $pdf=$tmp.'/large.pdf';$f=fopen($pdf,'wb');fwrite($f,"%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >> endobj\n4 0 obj << /Length 209714000 >>\nstream\n");fill94($f,209714000,"% synthetic padding\n");fwrite($f,"\nendstream\nendobj\n");$pos=ftell($f);$tail="xref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000061 00000 n \n0000000120 00000 n \n0000000210 00000 n \ntrailer << /Size 5 /Root 1 0 R >>\nstartxref\n".$pos."\n%%EOF\n";fill94($f,$max-ftell($f)-strlen($tail),' ');fwrite($f,$tail);fclose($f);
    check94($m->invoke($s,$pdf,'large.pdf')['size']===$max,'200 MB PDF accepted with bounded scanning');
    $f=fopen($pdf,'r+b');fseek($f,65530);fwrite($f,'/Open#41ction');fclose($f);reject94(function()use($m,$s,$pdf){$m->invoke($s,$pdf,'large.pdf');},'escaped active PDF name spanning scan boundary rejected');unlink($pdf);
    $xml=$tmp.'/sheet.xml';$f=fopen($xml,'wb');fwrite($f,'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');for($i=0;$i<500000;$i++)fwrite($f,'<row><c t="inlineStr"><is><t>'.str_repeat('sample ',20).'</t></is></c></row>');fwrite($f,'</sheetData></worksheet>');fclose($f);
    $xlsx=$tmp.'/large.xlsx';$z=new ZipArchive();$z->open($xlsx,ZipArchive::CREATE);$z->addFromString('[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');$z->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');$z->addFile($xml,'xl/worksheets/sheet1.xml');$z->setCompressionName('xl/worksheets/sheet1.xml',ZipArchive::CM_STORE);$z->close();
    check94($m->invoke($s,$xlsx,'large.xlsx')['size']>50*1024*1024,'large Office XML accepted without DOM memory expansion');
    $z=new ZipArchive();$z->open($xlsx);$z->addFromString('_rels/.rels','<!DOCTYPE x [<!ENTITY x SYSTEM "file:///etc/passwd">]><Relationships/>');$z->close();reject94(function()use($m,$s,$xlsx){$m->invoke($s,$xlsx,'large.xlsx');},'Office DTD rejected by streaming validation');
    check94(memory_get_peak_usage(true)<96*1024*1024,'validation peak PHP memory below 96 MB');
    echo 'M094_LARGE_FILES=PASS PEAK_BYTES='.memory_get_peak_usage(true)."\n";
} finally {foreach(glob($tmp.'/*') as $p)unlink($p);rmdir($tmp);}
