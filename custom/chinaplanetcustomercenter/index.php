<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require '../../main.inc.php';
header('Location: '.dol_buildpath('/chinaplanetcustomercenter/board.php', 1));
exit;
