<?php

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/chinaplanetcustomercenter/lib/projectsync.lib.php';

class InterfaceChinaPlanetCustomerCenterCustomerProjectSync extends DolibarrTriggers
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = 'projects';
        $this->description = 'Sync Vision System projects into the customer portal Kanban.';
        $this->version = self::VERSIONS['experimental'];
        $this->picto = 'project';
    }

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (empty($conf->chinaplanetcustomercenter) || empty($conf->chinaplanetcustomercenter->enabled)) {
            return 0;
        }

        if (!($object instanceof Project)) {
            return 0;
        }

        $mode = '';
        switch ($action) {
            case 'PROJECT_CREATE':
                $mode = 'create';
                break;
            case 'PROJECT_MODIFY':
                $mode = 'modify';
                break;
            case 'PROJECT_VALIDATE':
                $mode = 'validate';
                break;
            case 'PROJECT_CLOSE':
                $mode = 'close';
                break;
            case 'PROJECT_DELETE':
                $mode = 'delete';
                break;
            default:
                return 0;
        }

        $sync = cpcc_project_sync_kanban_card($this->db, $object, $user, $mode);
        if (!empty($sync['message'])) {
            dol_syslog(__METHOD__.' '.$action.' project='.$sync['project_id'].' '.$sync['message'], LOG_WARNING);
        } elseif (!empty($sync['action'])) {
            dol_syslog(__METHOD__.' '.$action.' project='.$sync['project_id'].' '.$sync['action'].' card='.$sync['card_id'], LOG_INFO);
        }

        return 1;
    }
}
