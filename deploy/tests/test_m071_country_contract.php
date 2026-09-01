<?php

$root = dirname(__DIR__, 2);
require_once $root.'/custom/chinaplanetoperations/class/CatalogOrderV2Contract.class.php';

if (!CatalogOrderV2Contract::isIsoAlpha2('EG')) {
    fwrite(STDERR, "M071_COUNTRY=FAIL REASON=EG_REJECTED\n");
    exit(1);
}
if (CatalogOrderV2Contract::isIsoAlpha2('ZZ')) {
    fwrite(STDERR, "M071_COUNTRY=FAIL REASON=ZZ_ACCEPTED\n");
    exit(1);
}

$intake = file_get_contents($root.'/custom/chinaplanetoperations/class/PublicWebsiteRequestService.class.php');
if (strpos($intake, "UPPER(code)='") === false || strpos($intake, 'UPPER(code_iso)') !== false) {
    fwrite(STDERR, "M071_COUNTRY=FAIL REASON=DOLIBARR_ALPHA2_COLUMN_INVALID\n");
    exit(1);
}

echo "M071_COUNTRY=PASS ACCEPT=EG REJECT=ZZ DOLIBARR_COLUMN=code\n";
