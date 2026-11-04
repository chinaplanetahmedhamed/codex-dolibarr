<?php
require __DIR__.'/sql_import.php';
$input="--Dictionary of package type\nCREATE TABLE example (note varchar(99) DEFAULT 'keep--literal'); -- valid inline\n";
$expected="CREATE TABLE example (note varchar(99) DEFAULT 'keep--literal'); -- valid inline\n";
if (nf_import_sql($input)!==$expected) throw new RuntimeException('Only complete native installer comment lines may be removed');
if (nf_import_sql("SELECT '--literal';\n")!=="SELECT '--literal';\n") throw new RuntimeException('SQL string changed');
if (nf_import_sql("--heading\r\nSELECT 1;\r\n-- final")!=="SELECT 1;\r\n") throw new RuntimeException('CRLF or final comment handling');
if (nf_import_sql("CREATE TABLE example (\n  --code_banque varchar(128), -- deprecated\n  id integer\n);\n")!=="CREATE TABLE example (\n  id integer\n);\n") throw new RuntimeException('Indented malformed native comments remain');
echo "PASS native SQL import: malformed dictionary comments removed; SQL strings/inline comments preserved\n";
