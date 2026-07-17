<?php
define('CSRFCHECK_WITH_TOKEN', 1);
require $_SERVER['DOCUMENT_ROOT'].'/main.inc.php';
require_once __DIR__.'/../lib/main.lib.php';
require_once __DIR__.'/../class/ShippingSopService.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/admin_shell.lib.php';
chinaplanetsp_require_sop($user, 'sopwrite');
$service = new ShippingSopService($db);
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$fields = array('ref','title','title_ar','fk_category','country_code','pol','pod','shipping_line','service_type','container_type','incoterm','cargo_type','document_codes','applicability','required_information','procedure_steps','required_documents','warnings','escalation_instructions','internal_notes','responsible_department','effective_date','change_summary');
$data = array(); foreach($fields as $field) $data[$field] = GETPOST($field, in_array($field,array('applicability','required_information','procedure_steps','required_documents','warnings','escalation_instructions','internal_notes'),true)?'restricthtml':'alphanohtml');
if ($action === 'save') {
    try {
        if ($id > 0) $service->saveDraft($id, $data, (int)$user->id); else $id = $service->create($data, (int)$user->id);
        setEventMessages('Draft saved. Active procedures remain unchanged until this revision is published.', null, 'mesgs');
        header('Location: view.php?id='.$id); exit;
    } catch (Throwable $e) { setEventMessages($e->getMessage(), null, 'errors'); }
}
$sop = $id > 0 ? $service->get($id, true) : null;
if ($id > 0 && !$sop) accessforbidden();
if ($sop && $action !== 'save') $data = $sop;
$categories = $service->getCategories(true);
$listValue = function($field) use($data) { return implode("\n", ShippingSopService::decodeList($data[$field] ?? '')); };
$value = function($field,$default='') use($data) { return dol_escape_htmltag(isset($data[$field]) && $data[$field] !== '' ? $data[$field] : $default); };
$morecss=array(cpcc_admin_shell_asset('/custom/chinaplanetshippingprices/css/shippingprices.css'));
llxHeader('', $id ? 'Edit SOP' : 'New SOP', '', '', 0, 0, array(), $morecss);
cpcc_admin_shell_open('shippingprices', $id ? 'Edit SOP draft / تعديل المسودة' : 'New SOP / إجراء جديد', 'Use one clear action per line so a new employee can follow it', 'fa-clipboard-list');
print '<main class="chinaplanetsp-sop-page"><div class="chinaplanetsp-admin-toolbar"><a class="button" href="'.($id?'view.php?id='.$id:'index.php').'">Back</a></div><section class="chinaplanetsp-rule-editor"><form method="post"><input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="'.$id.'"><div class="chinaplanetsp-sop-form-grid">';
print '<label><span>Reference number *</span><input name="ref" required maxlength="64" value="'.$value('ref').'"'.($id?' readonly':'').' placeholder="SOP-SHP-001"></label><label class="wide"><span>Title *</span><input name="title" required maxlength="255" value="'.$value('title').'"></label><label class="wide"><span>Arabic title</span><input name="title_ar" dir="rtl" maxlength="255" value="'.$value('title_ar').'"></label>';
print '<label><span>Category</span><select name="fk_category"><option value="">Uncategorized</option>'; foreach($categories as$c) print '<option value="'.(int)$c['rowid'].'"'.((int)($data['fk_category']??0)===(int)$c['rowid']?' selected':'').'>'.dol_escape_htmltag($c['label']).((int)$c['status']?'':' (inactive)').'</option>'; print '</select></label>';
$simple = function($name,$label,$placeholder='All') use($value) { print '<label><span>'.$label.'</span><input name="'.$name.'" value="'.$value($name).'" placeholder="'.$placeholder.'"></label>'; };
$simple('country_code','Country (2-letter code)'); $simple('pol','POL'); $simple('pod','POD'); $simple('shipping_line','Shipping line');
print '<label><span>Service type</span><select name="service_type">'; foreach(array('all'=>'All','direct'=>'Direct','transit'=>'Transit') as$k=>$v) print '<option value="'.$k.'"'.(($data['service_type']??'all')===$k?' selected':'').'>'.$v.'</option>'; print '</select></label>';
$simple('container_type','Container type'); $simple('incoterm','Incoterm'); $simple('cargo_type','Cargo type'); $simple('document_codes','Document/service codes','cargox, saber'); $simple('responsible_department','Responsible department','Shipping Operations');
print '<label><span>Effective date</span><input type="date" name="effective_date" value="'.$value('effective_date').'"></label><label class="wide"><span>When this SOP applies</span><textarea name="applicability" rows="3">'.$value('applicability').'</textarea></label>';
$area = function($name,$label,$help,$rows=6) use($value,$listValue) { print '<label class="full"><span>'.$label.'</span><small>'.$help.'</small><textarea name="'.$name.'" rows="'.$rows.'">'.dol_escape_htmltag($listValue($name)).'</textarea></label>'; };
$area('required_information','Required information','One item per line.'); $area('procedure_steps','Numbered procedure steps','One action per line; numbering is added automatically.',10); $area('required_documents','Required documents','One document per line.'); $area('warnings','Important warnings','One warning per line. These appear prominently.');
print '<label class="full"><span>Escalation instructions</span><textarea name="escalation_instructions" rows="5">'.$value('escalation_instructions').'</textarea></label><label class="full"><span>Internal notes</span><textarea name="internal_notes" rows="5">'.$value('internal_notes').'</textarea></label><label class="full"><span>Change summary</span><input name="change_summary" maxlength="512" value="'.$value('change_summary').'" placeholder="What changed in this revision?"></label>';
print '</div><div class="chinaplanetsp-rule-actions"><button class="button button-save">Save draft</button><a class="button" href="'.($id?'view.php?id='.$id:'index.php').'">Cancel</a></div></form></section></main>';
cpcc_admin_shell_close(); llxFooter(); $db->close();
