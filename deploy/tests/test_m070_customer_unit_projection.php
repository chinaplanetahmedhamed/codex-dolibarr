<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$root = dirname(__DIR__, 2);
$portal = file_get_contents($root.'/custom/chinaplanetcustomercenter/invoice.php');
$mobile = file_get_contents($root.'/custom/chinaplanetcustomercenter/lib/mobile_api_data.lib.php');

function m070UnitAssert($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (array('portal' => $portal, 'mobile' => $mobile) as $surface => $source) {
    m070UnitAssert(is_string($source), strtoupper($surface).'_SOURCE_UNREADABLE');
    m070UnitAssert(strpos($source, 'LEFT JOIN ".MAIN_DB_PREFIX."c_units u ON u.rowid=fd.fk_unit') !== false, strtoupper($surface).'_UNIT_LOOKUP_MISSING');
    m070UnitAssert(strpos($source, "COALESCE(NULLIF(TRIM(u.short_label),''),NULLIF(TRIM(u.label),''),NULLIF(TRIM(u.code),'')) AS unit_public") !== false, strtoupper($surface).'_SAFE_UNIT_PROJECTION_MISSING');
    m070UnitAssert(strpos($source, "'#'.(int)\$line->fk_unit") === false, strtoupper($surface).'_INTERNAL_UNIT_ID_EXPOSED');
}

m070UnitAssert(strpos($portal, 'dol_escape_htmltag($line->unit_public)') !== false, 'PORTAL_UNIT_NOT_ESCAPED');
m070UnitAssert(strpos($mobile, "'unit'=>trim((string)\$line->unit_public)!==''?(string)\$line->unit_public:null") !== false, 'MOBILE_UNIT_NOT_NULL_SAFE');

print "M070_CUSTOMER_UNIT_PROJECTION=PASS INTERNAL_ID=ABSENT FALLBACK=SHORT_LABEL_LABEL_CODE NULL_SAFE=YES\n";
