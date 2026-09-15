<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/lib/main.lib.php';
require_once __DIR__.'/lib/ui.lib.php';
$language = cpcc_ui_language_parameter();
header('Location: '.dol_buildpath('/chinaplanetcustomercenter/board.php', 1).($language !== '' ? '?lang='.rawurlencode($language) : ''));
exit;
