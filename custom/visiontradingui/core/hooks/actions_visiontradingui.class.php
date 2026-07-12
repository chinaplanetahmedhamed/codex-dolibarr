<?php

class ActionsVisionTradingUI
{
    public $db;
    public $resprints = '';

    public function __construct($db)
    {
        $this->db = $db;
    }

    private function isEnabledForUser()
    {
        global $user;
        if (!getDolGlobalInt('VISIONTRADINGUI_ENABLE_DASHBOARD')) return false;
        if (empty($user) || empty($user->id)) return false;
        return !empty($user->admin) || $user->hasRight('visiontradingui', 'read');
    }

    public function addHtmlHeader(&$parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isEnabledForUser()) return 0;
        $nonce = function_exists('getNonce') ? getNonce() : '';
        $nonceAttr = $nonce !== '' ? ' nonce="'.dol_escape_htmltag($nonce).'"' : '';
        $base = dol_buildpath('/visiontradingui', 1);
        $this->resprints = '<link rel="stylesheet" href="'.$base.'/css/tokens.css">' . "\n"
            .'<link rel="stylesheet" href="'.$base.'/css/components.css">' . "\n"
            .'<link rel="stylesheet" href="'.$base.'/css/dashboard.css">' . "\n"
            .'<script'.$nonceAttr.' defer src="'.$base.'/js/dashboard.js"></script>' . "\n";
        return 0;
    }

    public function printMainArea(&$parameters, &$object, &$action, $hookmanager)
    {
        if (!$this->isEnabledForUser()) return 0;
        if (strtolower((string) getenv('VISIONTRADING_ENVIRONMENT')) !== 'staging') return 0;
        global $langs;
        $langs->loadLangs(array('visiontradingui@visiontradingui'));
        $this->resprints = '<div class="cpui-environment-banner" role="status">'
            .'<span aria-hidden="true">◆</span> '.dol_escape_htmltag($langs->trans('VisionTradingUIStagingBanner')).'</div>';
        return 0;
    }
}

