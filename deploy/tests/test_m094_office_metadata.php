<?php
// Full OOXML containers with standard metadata, plus content-policy regressions.
define('MAIN_DB_PREFIX', 'llx_');
require dirname(__DIR__, 2).'/custom/chinaplanetoperations/class/InternalTaskAssetService.class.php';
$tmp = sys_get_temp_dir().'/m094-office-'.bin2hex(random_bytes(6));
mkdir($tmp, 0700);
$conf = (object) array('entity'=>1, 'chinaplanetoperations'=>(object)array('dir_output'=>$tmp));
$service = new InternalTaskAssetService(new stdClass(), new stdClass(), 1, $tmp);
$validate = new ReflectionMethod($service, 'validateAndNormalize');
$count = 0;
function checkOffice($ok, $message) {global $count; if (!$ok) throw new RuntimeException('FAIL '.$message); echo 'PASS '.(++$count).' '.$message."\n";}
function rejectOffice($fn, $reason) {try {$fn();} catch (RuntimeException $e) {checkOffice($e->getMessage() === $reason, $reason); return $e;} throw new RuntimeException('Expected rejection: '.$reason);}
function officeFixture($path, $extension, $relationship = '', $parts = array()) {
    $root = $extension === 'xlsx' ? 'xl/workbook.xml' : 'word/document.xml';
    $type = $extension === 'xlsx' ? 'spreadsheetml.sheet.main+xml' : 'wordprocessingml.document.main+xml';
    $xml = $extension === 'xlsx' ? '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>' : '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Task attachment</w:t></w:r></w:p></w:body></w:document>';
    $z = new ZipArchive(); $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $base = array(
        '[Content_Types].xml'=>'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/'.$root.'" ContentType="application/vnd.openxmlformats-officedocument.'.$type.'"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>',
        '_rels/.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="'.$root.'"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'.$relationship.'</Relationships>',
        $root=>$xml,
        'docProps/core.xml'=>'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:creator>Regression fixture</dc:creator></cp:coreProperties>',
        'docProps/app.xml'=>'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Microsoft Excel</Application></Properties>',
    );
    foreach (array_merge($base, $parts) as $name=>$value) $z->addFromString($name, $value);
    $z->close();
}
try {
    foreach (array('xlsx','docx') as $extension) {
        $p=$tmp.'/normal.'.$extension; officeFixture($p,$extension);
        $meta=$validate->invoke($service,$p,'normal.'.$extension);
        checkOffice($meta['type']==='document' && $meta['sha256']===hash_file('sha256',$p), 'standard '.$extension.' metadata accepted without changing file');
    }
    $p=$tmp.'/blocked.xlsx';
    foreach (array('package','oleObject','attachedTemplate') as $type) {
        officeFixture($p,'xlsx','<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/'.$type.'" Target="payload.xml"/>');
        rejectOffice(function()use($validate,$service,$p){$validate->invoke($service,$p,'blocked.xlsx');},'Embedded Office relationships are not allowed.');
    }
    officeFixture($p,'xlsx','<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="https://example.invalid/core.xml" TargetMode="External"/>');
    rejectOffice(function()use($validate,$service,$p){$validate->invoke($service,$p,'blocked.xlsx');},'External Office relationships are not allowed.');
    officeFixture($p,'xlsx','<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties/evil" Target="payload.xml"/>');
    rejectOffice(function()use($validate,$service,$p){$validate->invoke($service,$p,'blocked.xlsx');},'Embedded Office relationships are not allowed.');
    officeFixture($p,'xlsx','',array('docProps/core.xml'=>'<!DOCTYPE properties [<!ENTITY x SYSTEM "file:///etc/passwd">]><properties>&x;</properties>'));
    rejectOffice(function()use($validate,$service,$p){$validate->invoke($service,$p,'blocked.xlsx');},'Unsafe Office XML.');
    officeFixture($p,'xlsx','',array('docProps/core.xml'=>'<properties><broken></properties>'));
    rejectOffice(function()use($validate,$service,$p){$validate->invoke($service,$p,'blocked.xlsx');},'Invalid Office XML.');
    officeFixture($p,'xlsx','',array('xl/vbaProject.xml'=>'<payload/>'));
    rejectOffice(function()use($validate,$service,$p){$validate->invoke($service,$p,'blocked.xlsx');},'Active Office content is not allowed.');
    officeFixture($p,'xlsx','',array('../escape.xml'=>'<payload/>'));
    rejectOffice(function()use($validate,$service,$p){$validate->invoke($service,$p,'blocked.xlsx');},'Unsafe Office archive.');
    officeFixture($p,'xlsx','',array('xl/worksheets/sheet1.xml'=>'<worksheet>'.str_repeat('x',1000000).'</worksheet>'));
    rejectOffice(function()use($validate,$service,$p){$validate->invoke($service,$p,'blocked.xlsx');},'Unsafe Office archive.');
    checkOffice(InternalTaskFileValidator::errorTranslationKey(new RuntimeException('SQL password=/private/secret'))==='InternalTaskValidationError','unknown error details stay private');
    $keys=array('InternalTaskOfficeExternalLinks','InternalTaskOfficeEmbeddedContent','InternalTaskOfficeUnsupportedContent','InternalTaskOfficeInvalid','InternalTaskOfficeExpandedLimit','InternalTaskOfficeArchiveUnsafe','InternalTaskFileNameInvalid','InternalTaskFileQuota');
    $messages=array('External Office relationships are not allowed.','Embedded Office relationships are not allowed.','Unexpected Office package part.','Invalid Office XML.','Office archive is too large.','Unsafe Office archive.','Unsafe file name.','Task file quota exceeded.');
    foreach ($messages as $i=>$message) checkOffice(InternalTaskFileValidator::errorTranslationKey(new RuntimeException($message))===$keys[$i], 'allowlisted error category '.$keys[$i]);
    foreach (array('en_US','ar_SA','zh_CN') as $locale) {
        $translations=file_get_contents(dirname(__DIR__,2).'/custom/chinaplanetoperations/langs/'.$locale.'/chinaplanetoperations.lang');
        foreach ($keys as $key) checkOffice(preg_match('/^'.preg_quote($key,'/').'=(.+)$/m',$translations)===1,$locale.' '.$key.' translated');
    }
    checkOffice(InternalTaskAssetService::MAX_FILE_BYTES===200*1024*1024,'200 MB per-file limit retained');
    echo 'M094_OFFICE_METADATA=PASS ASSERTIONS='.$count."\n";
} finally {foreach(glob($tmp.'/*') as $p)unlink($p);rmdir($tmp);}
