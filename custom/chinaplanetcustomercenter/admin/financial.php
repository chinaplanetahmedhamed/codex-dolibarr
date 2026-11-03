<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../lib/admin_shell.lib.php';
require_once __DIR__.'/../lib/financial.lib.php';
require_once __DIR__.'/../lib/portal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
if (empty($user->id) || !empty($user->socid) || (!$user->admin && !$user->hasRight('chinaplanetcustomercenter','write'))) accessforbidden();
if (!cpcc_financial_schema_ready($db)) accessforbidden('Financial tables are not installed. Re-enable the module or run the module upgrade first.');

function cpcc_admin_finance_date($value)
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value.' 12:00:00' : date('Y-m-d H:i:s');
}
function cpcc_admin_finance_project_ok($db,$socid,$projectid)
{
    if ($projectid<=0) return true;
    $r=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."projet WHERE rowid=".(int)$projectid." AND fk_soc=".(int)$socid);
    return $r && $db->num_rows($r)>0;
}
function cpcc_admin_finance_target($db,$entity,$socid,$projectid,$value)
{
    if ($value === '') return array('type'=>'','id'=>0);
    if (!preg_match('/^(operation|shipment|project|customer_invoice):([1-9][0-9]*)$/',$value,$match)) return null;
    $map=array('operation'=>array('cpo_operation','fk_project'),'shipment'=>array('chinaplanetlogistics','fk_project'),'project'=>array('projet','rowid'),'customer_invoice'=>array('facture','fk_projet'));
    $type=$match[1];$id=(int)$match[2];$def=$map[$type];
    $sql="SELECT rowid,".$def[1]." fk_project FROM ".MAIN_DB_PREFIX.$def[0]." WHERE entity=".(int)$entity." AND rowid=".$id." AND fk_soc=".(int)$socid." LIMIT 1";
    $res=$db->query($sql);$row=$res?$db->fetch_object($res):null;
    if(!$row||($projectid>0&&(int)$row->fk_project!==$projectid)) return null;
    return array('type'=>$type,'id'=>$id);
}
function cpcc_admin_insert_entry($db,$entity,$userid,$data)
{
    $values=array(
        (int)$entity,(int)$data['socid'],!empty($data['projectid'])?(int)$data['projectid']:'NULL',!empty($data['chargeid'])?(int)$data['chargeid']:'NULL',
        "'".$db->escape($data['date'])."'","'".$db->escape($data['type'])."'","'".$db->escape($data['direction'])."'","'".$db->escape($data['description'])."'",
        (float)$data['amount'],"'".$db->escape($data['currency'])."'",
        isset($data['allocated_amount'])&&$data['allocated_amount']!==null?(float)$data['allocated_amount']:'NULL',
        !empty($data['allocated_currency'])?"'".$db->escape($data['allocated_currency'])."'":'NULL',
        !empty($data['rate'])?(float)$data['rate']:'NULL',!empty($data['group'])?"'".$db->escape($data['group'])."'":'NULL',
        !empty($data['reference'])?"'".$db->escape($data['reference'])."'":'NULL',!empty($data['bank'])?"'".$db->escape($data['bank'])."'":'NULL',
        !empty($data['source_type'])?"'".$db->escape($data['source_type'])."'":'NULL',!empty($data['source_id'])?(int)$data['source_id']:'NULL',
        !empty($data['visible'])?1:0,(int)$userid,"'".$db->idate(dol_now())."'"
    );
    $sql="INSERT INTO ".MAIN_DB_PREFIX."cpcc_financial_entry(entity,fk_soc,fk_project,fk_charge,entry_date,entry_type,direction,description,amount,currency_code,allocated_amount,allocated_currency,exchange_rate,transfer_group,reference,bank_label,source_type,source_id,customer_visible,status,fk_user_creat,date_creation) VALUES (";
    $sql.=implode(',',array_slice($values,0,18)).','.$values[18].",1,".$values[19].','.$values[20].")";
    return $db->query($sql);
}

$action=GETPOST('action','aZ09');
$selectedSocid=GETPOSTINT('socid');
$selectedProjectid=GETPOSTINT('projectid');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $socid=GETPOSTINT('socid'); $projectid=GETPOSTINT('projectid');
    if ($socid<=0 || !cpcc_admin_finance_project_ok($db,$socid,$projectid)) {
        setEventMessages('Customer or project link is invalid.',null,'errors');
    } elseif ($action==='addcharge') {
        $amount=price2num(GETPOST('amount','alphanohtml')); $currency=cpcc_financial_currency(GETPOST('currency','alpha'),'');
        $customerInvoiceId=GETPOSTINT('customer_invoice_id'); $sourceType=''; $sourceId=0;
        if($customerInvoiceId>0){
            $invoiceSql="SELECT rowid,fk_projet FROM ".MAIN_DB_PREFIX."facture WHERE rowid=".$customerInvoiceId." AND entity=".(int)$conf->entity." AND fk_soc=".$socid;
            $invoiceRes=$db->query($invoiceSql); $invoice=$invoiceRes?$db->fetch_object($invoiceRes):null;
            if(!$invoice||($projectid>0&&!empty($invoice->fk_projet)&&(int)$invoice->fk_projet!==$projectid)){
                setEventMessages('Customer invoice does not belong to this customer/project.',null,'errors');
                $amount=0;
            }else{$sourceType='customer_invoice';$sourceId=$customerInvoiceId;}
        }
        if ($amount<=0 || $currency==='') setEventMessages('Amount and currency are required.',null,'errors');
        else {
            $sql="INSERT INTO ".MAIN_DB_PREFIX."cpcc_project_charge(entity,fk_soc,fk_project,charge_date,category,label,supplier_label,amount,currency_code,source_type,source_id,reference,customer_visible,status,fk_user_creat,date_creation) VALUES (";
            $sql.=(int)$conf->entity.",".$socid.",".($projectid>0?$projectid:'NULL').",'".$db->escape(substr(cpcc_admin_finance_date(GETPOST('date','alpha')),0,10))."','".$db->escape(GETPOST('category','alpha'))."','".$db->escape(GETPOST('label','alphanohtml'))."',";
            $supplier=GETPOST('supplier_label','alphanohtml'); $reference=GETPOST('reference','alphanohtml');
            $sql.=($supplier!==''?"'".$db->escape($supplier)."'":'NULL').",".$amount.",'".$db->escape($currency)."',".($sourceType!==''?"'".$db->escape($sourceType)."'":'NULL').",".($sourceId>0?$sourceId:'NULL').",".($reference!==''?"'".$db->escape($reference)."'":'NULL').",1,1,".(int)$user->id.",'".$db->idate(dol_now())."')";
            if($db->query($sql)) setEventMessages('Customer commitment added.',null,'mesgs'); else setEventMessages($db->lasterror(),null,'errors');
        }
    } elseif ($action==='addentry') {
        $amount=price2num(GETPOST('amount','alphanohtml')); $currency=cpcc_financial_currency(GETPOST('currency','alpha'),''); $type=GETPOST('entry_type','alpha');
        $creditTypes=array('opening_balance'); $direction=in_array($type,$creditTypes,true)?'C':'D';
        $sourceType=''; $sourceId=0;
        $target=cpcc_admin_finance_target($db,(int)$conf->entity,$socid,$projectid,GETPOST('finance_target','alphanohtml'));
        if($target===null){setEventMessages('Financial target does not belong to this customer/project.',null,'errors');$amount=0;}
        elseif(!empty($target['id'])){$sourceType=$target['type'];$sourceId=$target['id'];}
        if(in_array($type,array('customer_receipt','invoice_allocation'),true)){
            setEventMessages('Customer receipts and invoice allocations are read automatically from Dolibarr. Do not enter them manually.',null,'errors');
            $amount=0;
        }
        $chargeid=GETPOSTINT('chargeid'); $allocated=null; $allocatedCurrency='';
        if($chargeid>0){
            $r=$db->query("SELECT currency_code FROM ".MAIN_DB_PREFIX."cpcc_project_charge WHERE rowid=".$chargeid." AND fk_soc=".$socid." AND status=1");
            $charge=$r?$db->fetch_object($r):null;
            if(!$charge){setEventMessages('Selected commitment is invalid.',null,'errors');$amount=0;}
            else{$allocated=price2num(GETPOST('allocated_amount','alphanohtml'));$allocatedCurrency=cpcc_financial_currency($charge->currency_code,'');}
        }
        if($amount<=0||$currency==='') setEventMessages('Amount and currency are required.',null,'errors');
        else {
            $ok=cpcc_admin_insert_entry($db,$conf->entity,$user->id,array('socid'=>$socid,'projectid'=>$projectid,'chargeid'=>$chargeid,'date'=>cpcc_admin_finance_date(GETPOST('date','alpha')),'type'=>$type,'direction'=>$direction,'description'=>GETPOST('description','alphanohtml'),'amount'=>$amount,'currency'=>$currency,'allocated_amount'=>$allocated,'allocated_currency'=>$allocatedCurrency,'rate'=>price2num(GETPOST('exchange_rate','alphanohtml')),'group'=>'','reference'=>GETPOST('reference','alphanohtml'),'bank'=>GETPOST('bank_label','alphanohtml'),'source_type'=>$sourceType,'source_id'=>$sourceId,'visible'=>GETPOSTINT('customer_visible')));
            if($ok) setEventMessages('Financial entry added.',null,'mesgs'); else setEventMessages($db->lasterror(),null,'errors');
        }
    } elseif ($action==='addfx') {
        $fromAmount=price2num(GETPOST('from_amount','alphanohtml'));$toAmount=price2num(GETPOST('to_amount','alphanohtml'));
        $fromCurrency=cpcc_financial_currency(GETPOST('from_currency','alpha'),'');$toCurrency=cpcc_financial_currency(GETPOST('to_currency','alpha'),'');
        if($fromAmount<=0||$toAmount<=0||$fromCurrency===''||$toCurrency===''||$fromCurrency===$toCurrency) setEventMessages('Enter two different currencies and positive amounts.',null,'errors');
        else {
            $group='FX-'.date('YmdHis').'-'.bin2hex(random_bytes(3));$date=cpcc_admin_finance_date(GETPOST('date','alpha'));$rate=$toAmount/$fromAmount;$description=GETPOST('description','alphanohtml');$reference=GETPOST('reference','alphanohtml');
            $db->begin();
            $common=array('socid'=>$socid,'projectid'=>$projectid,'chargeid'=>0,'date'=>$date,'description'=>$description?:'Currency conversion','allocated_amount'=>null,'allocated_currency'=>'','rate'=>$rate,'group'=>$group,'reference'=>$reference,'bank'=>'','source_type'=>'','source_id'=>0,'visible'=>1);
            $out=$common;$out['type']='fx_out';$out['direction']='D';$out['amount']=$fromAmount;$out['currency']=$fromCurrency;
            $in=$common;$in['type']='fx_in';$in['direction']='C';$in['amount']=$toAmount;$in['currency']=$toCurrency;
            if(cpcc_admin_insert_entry($db,$conf->entity,$user->id,$out)&&cpcc_admin_insert_entry($db,$conf->entity,$user->id,$in)){$db->commit();setEventMessages('Currency conversion recorded in both currencies.',null,'mesgs');}else{$db->rollback();setEventMessages($db->lasterror(),null,'errors');}
        }
    }
}

llxHeader('', 'Customer Finance', '', '', 0, 0, array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/js/admin_financial.js')), array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/ux_fixes.css')));
cpcc_admin_shell_open('financial', 'Finance Allocation Center', 'Allocate funds and manage operational exceptions while Dolibarr remains the accounting source.', 'fa-wallet', array('socid'=>$selectedSocid,'projectid'=>$selectedProjectid));
print '<div class="info">Customer receipts, deposits and their consumption are read automatically from Dolibarr. Use this page only for opening balances, adjustments, operational expenses, currency conversions and commitments not created automatically by Dolibarr.</div>';
print '<p class="cpcc-statement-row"><a class="cpcc-statement-button" href="'.dol_buildpath('/chinaplanetcustomercenter/admin/financial_portfolio.php',1).'"><span class="fas fa-chart-pie"></span> Back to Financial Center</a></p>';
$customerOptions=array();$res=$db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom");if($res)while($o=$db->fetch_object($res))$customerOptions[(int)$o->rowid]=$o->nom;
$projectOptions=array();if($selectedSocid>0){$res=$db->query("SELECT rowid,ref,title FROM ".MAIN_DB_PREFIX."projet WHERE entity=".(int)$conf->entity." AND fk_soc=".$selectedSocid." ORDER BY ref DESC");if($res)while($o=$db->fetch_object($res))$projectOptions[(int)$o->rowid]=$o->ref.' — '.$o->title;}
$financeTargetOptions=array();if($selectedSocid>0){foreach(array('operation'=>array('cpo_operation','ref','fk_project'),'shipment'=>array('chinaplanetlogistics','ref','fk_project'),'project'=>array('projet','ref','rowid'),'customer_invoice'=>array('facture','ref','fk_projet')) as $type=>$def){$sql="SELECT rowid,".$def[1]." label FROM ".MAIN_DB_PREFIX.$def[0]." WHERE entity=".(int)$conf->entity." AND fk_soc=".$selectedSocid.($selectedProjectid>0?" AND ".$def[2]."=".$selectedProjectid:'')." ORDER BY rowid DESC";$res=$db->query($sql);if($res)while($o=$db->fetch_object($res))$financeTargetOptions[$type.':'.(int)$o->rowid]=ucfirst(str_replace('_',' ',$type)).' · '.$o->label;}}
$chargeOptions=array();if($selectedSocid>0&&cpcc_financial_schema_ready($db)){$sql="SELECT rowid,label,reference,currency_code,amount FROM ".MAIN_DB_PREFIX."cpcc_project_charge WHERE entity=".(int)$conf->entity." AND fk_soc=".$selectedSocid." AND status=1".($selectedProjectid>0?" AND fk_project=".$selectedProjectid:'')." ORDER BY rowid DESC";$res=$db->query($sql);if($res)while($o=$db->fetch_object($res))$chargeOptions[(int)$o->rowid]=$o->label.' — '.cpcc_portal_money($o->amount).' '.$o->currency_code.' — '.$o->reference;}
$invoiceOptions=array();if($selectedSocid>0){$sql="SELECT rowid,ref,multicurrency_code,multicurrency_total_ttc,total_ttc FROM ".MAIN_DB_PREFIX."facture WHERE entity=".(int)$conf->entity." AND fk_soc=".$selectedSocid.($selectedProjectid>0?" AND (fk_projet IS NULL OR fk_projet=".$selectedProjectid.")":'')." ORDER BY rowid DESC";$res=$db->query($sql);if($res)while($o=$db->fetch_object($res))$invoiceOptions[(int)$o->rowid]=$o->ref.' — '.cpcc_portal_money(!empty($o->multicurrency_code)?$o->multicurrency_total_ttc:$o->total_ttc).' '.cpcc_financial_currency($o->multicurrency_code,!empty($conf->currency)?$conf->currency:'USD');}
$today=date('Y-m-d');
?>
<form method="get" class="cpad-context-form"><div class="cpad-context-fields">
<label><span>Customer</span><?php echo Form::selectarray('socid',$customerOptions,$selectedSocid,'Search by customer name',0,0,'',0,0,0,'','minwidth300',1); ?></label>
<label><span>Project</span><?php echo Form::selectarray('projectid',$projectOptions,$selectedProjectid,'Choose a project or leave it unassigned',0,0,'',0,0,0,'','minwidth300',1); ?></label>
<button class="button" type="submit">Load</button>
</div></form><br>
<?php if($selectedSocid<=0){ ?><div class="info">Choose the customer first so entries are recorded against the correct account.</div><?php } ?>
<div class="fichecenter"><div class="fichehalfleft"><div class="underbanner clearboth"></div>
<form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="addentry"><input type="hidden" name="socid" value="<?php echo $selectedSocid; ?>"><input type="hidden" name="projectid" value="<?php echo $selectedProjectid; ?>">
<table class="border centpercent"><tr><td colspan="2"><b>Manual exception or expense</b></td></tr>
<tr><td>Customer / Project</td><td><b><?php echo dol_escape_htmltag(isset($customerOptions[$selectedSocid])?$customerOptions[$selectedSocid]:'—'); ?></b><br><span class="opacitymedium"><?php echo dol_escape_htmltag(isset($projectOptions[$selectedProjectid])?$projectOptions[$selectedProjectid]:'General customer wallet'); ?></span></td></tr>
<tr><td>Date</td><td><input required type="date" name="date" aria-label="Receipt date" value="<?php echo $today; ?>"></td></tr>
<tr><td>Type</td><td><select id="cpcc-entry-type" name="entry_type" aria-label="Financial entry type"><?php foreach(array('opening_balance','supplier_payment','shipping','commission','cargox','dhl','inspection','service','other','adjustment') as $type) print '<option value="'.$type.'">'.dol_escape_htmltag(cpcc_financial_type_label($type)).'</option>'; ?></select></td></tr>
<tr><td>Direction</td><td><output id="cpcc-direction-output" class="cpcc-direction">Credit (+)</output> <span class="opacitymedium">Automatically determined by entry type</span></td></tr>
<tr><td>Description</td><td><input required class="minwidth300" name="description" aria-label="Receipt or expense description"></td></tr><tr><td>Amount / Currency</td><td><input required name="amount" inputmode="decimal" aria-label="Receipt or expense amount"> <input required size="4" maxlength="3" name="currency" aria-label="Receipt or expense currency" placeholder="USD"></td></tr>
<tr><td>Expense target</td><td><?php echo Form::selectarray('finance_target',$financeTargetOptions,0,'General unbound expense',0,0,'aria-label="Exact operation, shipment, project or invoice target"',0,0,0,'','minwidth300',1); ?><br><span class="opacitymedium">Choose the exact target if this expense will settle allocated funds.</span></td></tr>
<tr><td>Commitment / Allocated</td><td><?php echo Form::selectarray('chargeid',$chargeOptions,0,'Not allocated',0,0,'aria-label="Project commitment"',0,0,0,'','minwidth300',1); ?> <input name="allocated_amount" inputmode="decimal" aria-label="Amount allocated to commitment" placeholder="Amount in the commitment currency"></td></tr>
<tr><td>Reference / Bank</td><td><input name="reference" aria-label="Payment reference"> <input name="bank_label" aria-label="Bank label"></td></tr><tr><td>Customer visible</td><td><input type="checkbox" name="customer_visible" value="1" aria-label="Visible to customer" checked></td></tr>
<tr><td></td><td><button class="button button-save" type="submit">Save entry</button></td></tr></table></form>
</div><div class="fichehalfright"><div class="underbanner clearboth"></div>
<form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="addfx"><input type="hidden" name="socid" value="<?php echo $selectedSocid; ?>"><input type="hidden" name="projectid" value="<?php echo $selectedProjectid; ?>">
<table class="border centpercent"><tr><td colspan="2"><b>Currency conversion</b></td></tr><tr><td>Customer / Project</td><td><?php echo dol_escape_htmltag(isset($customerOptions[$selectedSocid])?$customerOptions[$selectedSocid]:'—'); ?> / <?php echo dol_escape_htmltag(isset($projectOptions[$selectedProjectid])?$projectOptions[$selectedProjectid]:'General'); ?></td></tr>
<tr><td>Date</td><td><input required type="date" name="date" aria-label="Currency conversion date" value="<?php echo $today; ?>"></td></tr><tr><td>From</td><td><input required name="from_amount" inputmode="decimal" aria-label="Amount converted from"> <input required size="4" name="from_currency" aria-label="Source currency" placeholder="EGP"></td></tr>
<tr><td>To</td><td><input required name="to_amount" inputmode="decimal" aria-label="Amount converted to"> <input required size="4" name="to_currency" aria-label="Destination currency" placeholder="RMB"></td></tr><tr><td>Description / Reference</td><td><input name="description" aria-label="Currency conversion description"> <input name="reference" aria-label="Currency conversion reference"></td></tr>
<tr><td></td><td><button class="button button-save" type="submit">Record paired conversion</button></td></tr></table></form>
<br>
<form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="addcharge"><input type="hidden" name="socid" value="<?php echo $selectedSocid; ?>"><input type="hidden" name="projectid" value="<?php echo $selectedProjectid; ?>">
<table class="border centpercent"><tr><td colspan="2"><b>Project commitment</b></td></tr><tr><td>Customer / Project</td><td><?php echo dol_escape_htmltag(isset($customerOptions[$selectedSocid])?$customerOptions[$selectedSocid]:'—'); ?> / <?php echo dol_escape_htmltag(isset($projectOptions[$selectedProjectid])?$projectOptions[$selectedProjectid]:'—'); ?></td></tr>
<tr><td>Date / Category</td><td><input required type="date" name="date" aria-label="Commitment date" value="<?php echo $today; ?>"> <select name="category" aria-label="Commitment category"><?php foreach(array('product','commission','shipping','cargox','dhl','inspection','service','other') as $cat) print '<option>'.$cat.'</option>'; ?></select></td></tr>
<tr><td>Supplier-safe label</td><td><input name="supplier_label" aria-label="Supplier-safe label"></td></tr><tr><td>Label / Reference</td><td><input required name="label" aria-label="Commitment label"> <input name="reference" aria-label="Commitment reference"></td></tr><tr><td>Amount / Currency</td><td><input required name="amount" inputmode="decimal" aria-label="Commitment amount"> <input required size="4" name="currency" aria-label="Commitment currency"></td></tr>
<tr><td>Customer service invoice</td><td><?php echo Form::selectarray('customer_invoice_id',$invoiceOptions,0,'Not invoiced yet',0,0,'aria-label="Customer service invoice"',0,0,0,'','minwidth300',1); ?> <span class="opacitymedium">Linking the invoice prevents counting the service twice.</span></td></tr>
<tr><td></td><td><button class="button button-save" type="submit">Save commitment</button></td></tr></table></form>
</div></div>
<?php cpcc_admin_shell_close(); llxFooter();
