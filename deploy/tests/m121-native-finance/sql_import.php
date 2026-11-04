<?php
/** Fixture-only: mirror Dolibarr install/step2.php full-line comment removal.
 * Preserve inline comments and quoted strings; never rewrite installed schema/core.
 */
function nf_import_sql($sql) {
    return preg_replace('/^[\t ]*--[^\r\n]*(?:\r?\n|$)/m', '', $sql);
}
