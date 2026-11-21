<?php
require_once __DIR__.'/InternalTaskAuthorizationService.class.php';

/** Standard Dolibarr login extension, with no core or stored landing-page changes. */
class ActionsChinaplanetoperations
{
    public $priority = 90;
    public $results = array();
    public $resprints = '';
    public $errors = array();
    public $error = '';
    public $warnings = array();
    private $db;
    public function __construct($db) { $this->db = $db; }
    /** Global native left-menu hook; append once without changing existing entries. */
    public function menuLeftMenuItems($parameters, &$object, &$action = null, $hookmanager = null)
    {
        global $conf,$user,$langs;
        $this->results=[];
        if(empty($conf->global->CPO_BOARDS_ENABLED)||empty($conf->global->CPO_BOARDS_SHORTCUT_ENABLED)||!(new InternalTaskAuthorizationService($this->db,$user,(int)$conf->entity))->canOpenBoard())return 0;
        foreach((array)$object as $item)if(strpos((string)($item['url']??''),'/chinaplanetoperations/tasks/boards.php')!==false)return 0;
        $langs->load('chinaplanetoperations@chinaplanetoperations');
        $this->results=['rowid'=>'cpo_boards_shortcut','fk_menu'=>0,'langs'=>'chinaplanetoperations@chinaplanetoperations','enabled'=>1,'type'=>'left','fk_mainmenu'=>'','fk_leftmenu'=>'','url'=>dol_buildpath('/chinaplanetoperations/tasks/boards.php',1).(strpos($langs->defaultlang,'ar')===0?'?lang=ar_SA':''),'titre'=>$langs->trans('CPOBoardsShortcut'),'perms'=>'1','target'=>'','mainmenu'=>$parameters['mainmenu']??'','leftmenu'=>'cpo_boards_shortcut','position'=>9990,'showtopmenuinframe'=>0,'level'=>0,'prefix'=>''];
        return 0;
    }
    public function getCalendarEvents($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $firstdaytoshow, $lastdaytoshow, $filtert, $status, $usergroup, $socid, $pid, $actioncode, $resourceid, $type, $search_categ_cus;
        $this->results = array();
        require_once __DIR__.'/InternalTaskAgendaService.class.php';
        try {
            $this->results['eventarray'] = (new InternalTaskAgendaService($this->db, $user, $conf->entity))->events($firstdaytoshow, $lastdaytoshow, array(
                'owner'=>$filtert, 'status'=>$status, 'group'=>$usergroup, 'socid'=>$socid, 'project'=>$pid,
                'actioncode'=>$actioncode, 'resourceid'=>$resourceid, 'type'=>$type, 'category'=>$search_categ_cus,
            ));
        } catch (Throwable $e) {
            $langs->load('chinaplanetoperations@chinaplanetoperations');
            setEventMessages($langs->trans('CPTAgendaUnavailable'), null, 'warnings');
            dol_syslog('CPO task calendar unavailable: '.get_class($e), LOG_ERR);
        }
        return 0;
    }

    public function eventOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;
        $this->resprints = '';
        if (!is_object($object) || $object->type !== 'cpo_task' || $object->id >= 0) return 0;
        $id = -(int)$object->id;
        $time = dol_print_date($object->datep, 'dayhour', 'tzuserrel');
        if ($object->datef !== $object->datep) $time .= ' – '.dol_print_date($object->datef, 'dayhour', 'tzuserrel');
        $sameDay = dol_print_date($object->datep, '%Y-%m-%d', 'tzuserrel') === dol_print_date($object->datef, '%Y-%m-%d', 'tzuserrel');
        $compactTime = $sameDay ? dol_print_date($object->datep, 'hour', 'tzuserrel') : $time;
        if ($sameDay && $object->datef !== $object->datep) $compactTime .= ' – '.dol_print_date($object->datef, 'hour', 'tzuserrel');
        if ($object->fulldayevent) $compactTime = $langs->trans('EventOnFullDay');
        $this->resprints = '<a class="cal_event_title cpo-agenda-task" href="'.dol_buildpath('/chinaplanetoperations/tasks/card.php', 1).'?id='.$id.'" title="'.dol_escape_htmltag($time.' · '.$object->label).'">';
        $this->resprints .= '<span class="cpo-agenda-status">'.dol_escape_htmltag($object->array_options['cpo_task_status']??'').'</span><bdi>'.dol_escape_htmltag($object->array_options['cpo_task_title']??$object->label).'</bdi></a><small class="cpo-agenda-time" dir="ltr">'.dol_escape_htmltag($compactTime).'</small>';
        return 1;
    }

    public function addCalendarChoice($parameters, &$object, &$action, $hookmanager)
    {
        $this->resprints = '';
        if (strpos((string)($parameters['context']??''), 'agenda') === false) return 0;
        $this->resprints = '<span hidden data-cpo-agenda-root="'.dol_escape_htmltag(DOL_URL_ROOT).'"></span><link rel="stylesheet" href="'.dol_buildpath('/chinaplanetoperations/css/task_agenda.css', 1).'"><script defer src="'.dol_buildpath('/chinaplanetoperations/js/task_agenda.js', 1).'"></script>';
        return 0;
    }

    public function formatEvent($parameters, &$object, &$action, $hookmanager)
    {
        // Per-user view inserts labels into its native tooltip HTML without escaping.
        if (is_object($object) && $object->type === 'cpo_task') $object->label = dol_escape_htmltag(html_entity_decode($object->label, ENT_QUOTES, 'UTF-8'));
        return 0;
    }
    public function afterLogin($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;
        // Dolibarr dispatches afterLogin before its normal loadRights call.
        $object->loadRights('chinaplanetoperations');
        $auth = new InternalTaskAuthorizationService($this->db, $object, (int) $conf->entity);
        if ($auth->isInternalActive() && !$auth->isAdministrator() && $auth->hasRight('task_read_own')) {
            if (!isset($object->conf) || !is_object($object->conf)) $object->conf = new stdClass();
            $object->conf->MAIN_LANDING_PAGE = '/custom/chinaplanetoperations/workspace.php?view=my&mainmenu=home';
        }
        return 0;
    }

    /** Native footer hook executes inside the current tab's content area. */
    public function llxFooter($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $conf, $user, $langs;
        $contexts = explode(':', $parameters['context'] ?? '');
        $side = in_array('pricesuppliercard', $contexts, true) ? 'product'
            : (in_array('thirdpartyconsumption', $contexts, true) ? 'supplier' : null);
        if (!$side) return 0;
        $this->resprints = '';
        require_once __DIR__.'/SupplierProductCandidate.class.php';
        require_once __DIR__.'/SupplierProductCandidateView.class.php';
        require_once __DIR__.'/SupplierCategorySourcing.class.php';
        $advanced = (bool) getDolGlobalString('MAIN_USE_ADVANCED_PERMS');
        if (!$side || !is_object($object) || empty($object->id) || (int) $object->entity !== (int) $conf->entity
            || !SupplierProductCandidate::canRead($user, (int) $conf->entity, $advanced)) return 0;
        if (($side === 'supplier' && empty($object->fournisseur))
            || ($side === 'product' && isset($object->type) && (int) $object->type !== 0)) return 0;
        $langs->load('supplier_sourcing@chinaplanetoperations');
        try {
            $service = new SupplierCategorySourcing($db, (int) $conf->entity, $advanced);
            $page = max(0, min(100000, (int) GETPOST('cpo_sourcing_page', 'int')));
            $result = $service->candidates($side, (int) $object->id, $user, $page);
            $this->resprints = SupplierProductCandidateView::render($result['rows'], $side, $langs, DOL_URL_ROOT, $result);
        } catch (Throwable $e) {
            // No SQL, record identifiers or internal error messages in the response.
            $this->resprints = '<p class="warning">'.htmlspecialchars($langs->trans('SourcingUnavailable'), ENT_QUOTES, 'UTF-8').'</p>';
        }
        return 0;
    }
}
