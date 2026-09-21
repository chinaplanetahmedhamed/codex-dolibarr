<?php
define('CSRFCHECK_WITH_TOKEN', 1);
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../lib/admin_shell.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (!$user->admin) accessforbidden();

$action=GETPOST('action','alpha');
if($action==='save_usd_rates'){
    $rmbRate=price2num(GETPOST('usd_units_rmb','alphanohtml'));
    $egpRate=price2num(GETPOST('usd_units_egp','alphanohtml'));
    $rateDate=GETPOST('usd_rate_date','alpha');
    if($rmbRate<=0||$egpRate<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$rateDate)){
        setEventMessages('Enter positive RMB/EGP rates and a valid valuation date.',null,'errors');
    }else{
        $error=0;
        if(dolibarr_set_const($db,'CHINAPLANETCUSTOMERCENTER_USD_UNITS_RMB',$rmbRate,'chaine',0,'',$conf->entity)<0) $error++;
        if(dolibarr_set_const($db,'CHINAPLANETCUSTOMERCENTER_USD_UNITS_EGP',$egpRate,'chaine',0,'',$conf->entity)<0) $error++;
        if(dolibarr_set_const($db,'CHINAPLANETCUSTOMERCENTER_USD_RATE_DATE',$rateDate,'chaine',0,'',$conf->entity)<0) $error++;
        if(!$error){
            setEventMessages('USD balance valuation rates saved.',null,'mesgs');
            header('Location: '.$_SERVER['PHP_SELF']);
            exit;
        }
        setEventMessages('Unable to save USD valuation rates.',null,'errors');
    }
}

llxHeader('', 'ChinaPlanet Customer Center Setup', '', '', 0, 0, array(), array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
cpcc_admin_shell_open('dashboard','System Settings','Balance valuation rates and Customer Center administration links.','fa-cog');

print load_fiche_titre('ChinaPlanet Customer Center', '', 'title_setup');
print '<div class="info">';
print '<p>Unified portal, shipment tracking, and internal Kanban module.</p>';
print '<ul>';
print '<li><a href="'.dol_buildpath('/chinaplanetcustomercenter/board.php', 1).'">Portal board</a></li>';
print '<li><a href="'.dol_buildpath('/chinaplanetcustomercenter/index.php', 1).'">Shipment tracker</a></li>';
print '<li><a href="'.dol_buildpath('/chinaplanetcustomercenter/admin/financial.php', 1).'">Customer finance entry</a></li>';
print '</ul>';
print '</div>';

print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_usd_rates">';
print load_fiche_titre('Total Balance in USD — valuation rates', '', 'money-bill-alt');
print '<div class="info">These rates only display customer balances in USD. They do not change original currencies or entries.</div>';
print '<div class="div-table-responsive"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Currency</th><th>Valuation rate</th></tr>';
print '<tr><td>RMB</td><td>1 USD = <input required name="usd_units_rmb" value="'.dol_escape_htmltag(getDolGlobalString('CHINAPLANETCUSTOMERCENTER_USD_UNITS_RMB')).'"> RMB</td></tr>';
print '<tr><td>EGP</td><td>1 USD = <input required name="usd_units_egp" value="'.dol_escape_htmltag(getDolGlobalString('CHINAPLANETCUSTOMERCENTER_USD_UNITS_EGP')).'"> EGP</td></tr>';
print '<tr><td>Valuation date</td><td><input required type="date" name="usd_rate_date" value="'.dol_escape_htmltag(getDolGlobalString('CHINAPLANETCUSTOMERCENTER_USD_RATE_DATE')).'"></td></tr>';
print '</table></div><div class="center"><button class="button button-save" type="submit">Save valuation rates</button></div></form>';

cpcc_admin_shell_close();
llxFooter();
