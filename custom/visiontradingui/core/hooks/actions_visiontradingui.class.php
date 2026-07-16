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

	private function isWhiteLabelEnabled()
	{
		return (bool) getDolGlobalInt('VISIONTRADINGUI_ENABLE_WHITE_LABEL');
	}

    public function addHtmlHeader(&$parameters, &$object, &$action, $hookmanager)
    {
		$loadDesignSystem = $this->isEnabledForUser();
		$loadWhiteLabel = $this->isWhiteLabelEnabled();
		if (!$loadDesignSystem && !$loadWhiteLabel) return 0;

        $nonce = function_exists('getNonce') ? getNonce() : '';
        $nonceAttr = $nonce !== '' ? ' nonce="'.dol_escape_htmltag($nonce).'"' : '';
        $base = dol_buildpath('/visiontradingui', 1);
		$prints = '';
		if ($loadDesignSystem) {
			$prints .= '<link rel="stylesheet" href="'.$base.'/css/tokens.css">' . "\n"
				.'<link rel="stylesheet" href="'.$base.'/css/components.css">' . "\n"
				.'<link rel="stylesheet" href="'.$base.'/css/dashboard.css">' . "\n"
				.'<script'.$nonceAttr.' defer src="'.$base.'/js/dashboard.js"></script>' . "\n";
		}
		if ($loadWhiteLabel) {
			$brandName = getDolGlobalString('VISIONTRADINGUI_BRAND_NAME', 'Vision System');
			$brandingVersion = @filemtime(DOL_DOCUMENT_ROOT.'/custom/visiontradingui/js/branding.js');
			$brandingCssVersion = @filemtime(DOL_DOCUMENT_ROOT.'/custom/visiontradingui/css/branding.css');
			$brandLogo = $base.'/img/vision-system-logo.svg';
			$brandMark = $base.'/img/vision-system-mark.svg';
			$prints .= '<meta name="vision-system-brand" content="'.dol_escape_htmltag($brandName).'">' . "\n"
				.'<meta name="vision-system-logo" content="'.dol_escape_htmltag($brandLogo).'">' . "\n"
				.'<meta name="vision-system-mark" content="'.dol_escape_htmltag($brandMark).'">' . "\n"
				.'<link rel="stylesheet" href="'.$base.'/css/branding.css?v='.(int) $brandingCssVersion.'">' . "\n"
				.'<script'.$nonceAttr.' defer src="'.$base.'/js/branding.js?v='.(int) $brandingVersion.'"></script>' . "\n";
		}
		$this->resprints = $prints;
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
