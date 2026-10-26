<?php
$root=__DIR__.'/../../../custom/chinaplanetoperations';foreach(['/js/customer_account.js','/css/customer_account.css'] as $p)if(!file_exists($root.$p)){fwrite(STDERR,"FAIL contextual account UI missing\n");exit(1);}echo "PASS contextual UI assets exist\n";
