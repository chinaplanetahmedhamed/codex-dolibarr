<?php
if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../lib/admin_shell.lib.php';
require_once __DIR__.'/../lib/financial.lib.php';
require_once __DIR__.'/../lib/export.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

if (empty($user->id) || !empty($user->socid) || (!$user->admin && !$user->hasRight('chinaplanetcustomercenter','write'))) accessforbidden();
if (!cpcc_export_schema_ready($db)) accessforbidden('Export document tables are not installed. Re-enable the module once.');
$form = new Form($db);
$socid = GETPOSTINT('socid');
$projectid = GETPOSTINT('projectid');
$shipmentid = GETPOSTINT('shipmentid');
$documentid = GETPOSTINT('documentid');
$action = GETPOST('action','aZ09');

function cpcc_admin_export_scope_ok($db,$socid,$projectid,$shipmentid=0)
{
    if($socid<=0||$projectid<=0)return false;
    $sql="SELECT p.rowid FROM ".MAIN_DB_PREFIX."projet p WHERE p.rowid=".(int)$projectid." AND p.fk_soc=".(int)$socid;
    $res=$db->query($sql);if(!$res||$db->num_rows($res)===0)return false;
    if($shipmentid>0){$res=$db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE rowid=".(int)$shipmentid." AND fk_soc=".(int)$socid." AND fk_project=".(int)$projectid);if(!$res||$db->num_rows($res)===0)return false;}
    return true;
}

if($_SERVER['REQUEST_METHOD']==='POST'&&$action==='create_document'){
    if(!cpcc_admin_export_scope_ok($db,$socid,$projectid,$shipmentid)){setEventMessages('Customer, project or shipment link is invalid.',null,'errors');}
    else{
        $ref=trim(GETPOST('ref','alphanohtml'));$type=GETPOST('document_type','alpha');$date=GETPOST('document_date','alpha');$currency=cpcc_financial_currency(GETPOST('currency_code','alpha'),'');
        if($ref===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!in_array($type,array('commercial_invoice','packing_list','bill_of_lading','cargox','certificate_origin','inspection','other'),true)){setEventMessages('Reference, date and document type are required.',null,'errors');}
        else{
            $sql="INSERT INTO ".MAIN_DB_PREFIX."cpcc_export_document(entity,fk_soc,fk_project,fk_shipment,document_type,ref,document_date,currency_code,total_amount,shipper_name,consignee_name,port_loading,port_destination,incoterm,notes_public,customer_visible,status,fk_user_creat,date_creation) VALUES(".(int)$conf->entity.",".$socid.",".$projectid.",".($shipmentid>0?$shipmentid:'NULL').",'".$db->escape($type)."','".$db->escape($ref)."','".$db->escape($date)."',".($currency!==''?"'".$db->escape($currency)."'":'NULL').",0,'".$db->escape(GETPOST('shipper_name','alphanohtml'))."','".$db->escape(GETPOST('consignee_name','alphanohtml'))."','".$db->escape(GETPOST('port_loading','alphanohtml'))."','".$db->escape(GETPOST('port_destination','alphanohtml'))."','".$db->escape(GETPOST('incoterm','alphanohtml'))."','".$db->escape(GETPOST('notes_public','restricthtml'))."',".(GETPOSTINT('customer_visible')?1:0).",1,".(int)$user->id.",'".$db->idate(dol_now())."')";
            if($db->query($sql)){$documentid=(int)$db->last_insert_id(MAIN_DB_PREFIX.'cpcc_export_document');setEventMessages('Export document created. Add its lines below.',null,'mesgs');}else setEventMessages($db->lasterror(),null,'errors');
        }
    }
}
if($_SERVER['REQUEST_METHOD']==='POST'&&$action==='add_line'){
    $document=cpcc_export_fetch($db,$documentid,$socid,false);$qty=price2num(GETPOST('quantity','alphanohtml'));$unitPrice=price2num(GETPOST('unit_price','alphanohtml'));
    if(!$document||$qty<=0||trim(GETPOST('description','alphanohtml'))===''){setEventMessages('Document, description and quantity are required.',null,'errors');}
    else{
        $position=count($document->lines)+1;$total=$qty*$unitPrice;
        $sql="INSERT INTO ".MAIN_DB_PREFIX."cpcc_export_document_line(fk_document,position,description,hs_code,quantity,unit,unit_price,total_amount,cartons,net_weight,gross_weight,country_origin,date_creation) VALUES(".$documentid.",".$position.",'".$db->escape(GETPOST('description','alphanohtml'))."','".$db->escape(GETPOST('hs_code','alphanohtml'))."',".$qty.",'".$db->escape(GETPOST('unit','alphanohtml'))."',".$unitPrice.",".$total.",".(float)price2num(GETPOST('cartons','alphanohtml')).",".(float)price2num(GETPOST('net_weight','alphanohtml')).",".(float)price2num(GETPOST('gross_weight','alphanohtml')).",'".$db->escape(GETPOST('country_origin','alphanohtml'))."','".$db->idate(dol_now())."')";
        if($db->query($sql)){$db->query("UPDATE ".MAIN_DB_PREFIX."cpcc_export_document SET total_amount=(SELECT COALESCE(SUM(total_amount),0) FROM ".MAIN_DB_PREFIX."cpcc_export_document_line WHERE fk_document=".$documentid.") WHERE rowid=".$documentid);setEventMessages('Document line added.',null,'mesgs');}else setEventMessages($db->lasterror(),null,'errors');
    }
}

$customerOptions=array();$res=$db->query("SELECT rowid,nom FROM ".MAIN_DB_PREFIX."societe WHERE entity=".(int)$conf->entity." AND status=1 AND client IN (1,3) ORDER BY nom");if($res)while($o=$db->fetch_object($res))$customerOptions[(int)$o->rowid]=$o->nom;
$projectOptions=array();if($socid>0){$res=$db->query("SELECT rowid,ref,title FROM ".MAIN_DB_PREFIX."projet WHERE fk_soc=".$socid." ORDER BY ref");if($res)while($o=$db->fetch_object($res))$projectOptions[(int)$o->rowid]=$o->ref.' — '.$o->title;}
$shipmentOptions=array();if($projectid>0){$res=$db->query("SELECT rowid,ref,container_no FROM ".MAIN_DB_PREFIX."chinaplanetlogistics WHERE fk_soc=".$socid." AND fk_project=".$projectid." ORDER BY rowid DESC");if($res)while($o=$db->fetch_object($res))$shipmentOptions[(int)$o->rowid]=$o->ref.(!empty($o->container_no)?' — '.$o->container_no:'');}
$selectedDocument=$documentid>0&&$socid>0?cpcc_export_fetch($db,$documentid,$socid,false):null;

llxHeader('', 'Export Documents', '', '', 0, 0, array(), array(cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/admin_dashboard.css'),cpcc_admin_shell_asset('/custom/chinaplanetcustomercenter/css/export.css')));
cpcc_admin_shell_open('export', 'Export Documents', 'Create commercial invoices, packing lists and other export documents.', 'fa-file-export', array('socid'=>$socid,'projectid'=>$projectid));
?>
<form method="get" class="fichecenter"><table class="border centpercent"><tr><td>Customer</td><td><?php echo Form::selectarray('socid',$customerOptions,$socid,'Select customer',0,0,'',0,0,0,'','minwidth300',1); ?></td><td>Project</td><td><?php echo Form::selectarray('projectid',$projectOptions,$projectid,'Select project',0,0,'',0,0,0,'','minwidth300',1); ?></td><td><button class="button" type="submit">Load</button></td></tr></table></form>
<?php if($socid>0&&$projectid>0){ ?>
<form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="create_document"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="projectid" value="<?php echo $projectid; ?>">
<table class="border centpercent"><tr><td colspan="4"><b>Create Export Document</b></td></tr><tr><td>Type</td><td><select name="document_type"><?php foreach(array('commercial_invoice','packing_list','bill_of_lading','cargox','certificate_origin','inspection','other') as $type)echo '<option value="'.$type.'">'.dol_escape_htmltag(cpcc_export_type_label($type)).'</option>'; ?></select></td><td>Reference / Date</td><td><input required name="ref"> <input required type="date" name="document_date" value="<?php echo date('Y-m-d'); ?>"></td></tr>
<tr><td>Shipment</td><td><?php echo Form::selectarray('shipmentid',$shipmentOptions,$shipmentid,'No shipment',0,0,'',0,0,0,'','minwidth250',1); ?></td><td>Currency / Incoterm</td><td><input size="4" name="currency_code" value="USD"> <input name="incoterm" value="FOB"></td></tr>
<tr><td>Shipper</td><td><input class="minwidth300" name="shipper_name" value="Vision for Import and Export Limited"></td><td>Consignee</td><td><input class="minwidth300" name="consignee_name" value="<?php echo dol_escape_htmltag(isset($customerOptions[$socid])?$customerOptions[$socid]:''); ?>"></td></tr>
<tr><td>Ports</td><td colspan="3"><input name="port_loading" placeholder="Loading port"> <input name="port_destination" placeholder="Destination"></td></tr><tr><td>Public notes</td><td colspan="3"><textarea class="quatrevingtpercent" name="notes_public"></textarea></td></tr><tr><td>Customer visible</td><td><input type="checkbox" name="customer_visible" value="1" checked></td><td></td><td><button class="button button-save" type="submit">Create document</button></td></tr></table></form>
<?php }
if($selectedDocument){ ?>
<br><form method="post"><input type="hidden" name="token" value="<?php echo newToken(); ?>"><input type="hidden" name="action" value="add_line"><input type="hidden" name="socid" value="<?php echo $socid; ?>"><input type="hidden" name="projectid" value="<?php echo $projectid; ?>"><input type="hidden" name="documentid" value="<?php echo $documentid; ?>">
<table class="border centpercent"><tr><td colspan="4"><b>Add line to <?php echo dol_escape_htmltag($selectedDocument->ref); ?></b></td></tr><tr><td>Description</td><td><input required class="minwidth300" name="description"></td><td>HS code / Origin</td><td><input name="hs_code"> <input name="country_origin" value="China"></td></tr><tr><td>Quantity / Unit</td><td><input required name="quantity"> <input name="unit" value="PCS"></td><td>Unit price</td><td><input name="unit_price" value="0"></td></tr><tr><td>Cartons</td><td><input name="cartons"></td><td>Net / Gross KG</td><td><input name="net_weight"> <input name="gross_weight"></td></tr><tr><td></td><td colspan="3"><button class="button button-save" type="submit">Add line</button> <a class="button" target="_blank" href="<?php echo dol_buildpath('/chinaplanetcustomercenter/export_document.php',1).'?id='.$documentid.'&socid='.$socid; ?>">Preview</a></td></tr></table></form>
<?php } ?>
<?php cpcc_admin_shell_close(); llxFooter();
