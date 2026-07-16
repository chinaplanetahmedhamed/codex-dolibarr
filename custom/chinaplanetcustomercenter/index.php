<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
header('Location: '.dol_buildpath('/chinaplanetcustomercenter/board.php', 1));
exit;
