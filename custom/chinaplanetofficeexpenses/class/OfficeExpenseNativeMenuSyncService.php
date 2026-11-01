<?php
declare(strict_types=1);

final class OfficeExpenseNativeMenuSyncService
{
    private $db;
    private $user;
    private $conf;

    public function __construct($db, $user, $conf)
    {
        $this->db=$db;
        $this->user=$user;
        $this->conf=$conf;
    }

    public function isAuthorized(): bool
    {
        return !empty($this->user->admin)
            && (int)($this->user->id??0)===1
            && empty($this->user->socid)
            && (int)($this->conf->entity??0)===1
            && !empty($this->conf->chinaplanetofficeexpenses->enabled);
    }

    public function hasMenu(): bool
    {
        $this->requireAuthorization();
        $row=$this->findMenu();
        if($row===null)return false;
        $this->assertExpectedMenu($row);
        return true;
    }

    public function synchronize(): bool
    {
        $this->requireAuthorization();
        $existing=$this->findMenu();
        if($existing!==null){
            $this->assertExpectedMenu($existing);
            return false;
        }

        if(!class_exists('Menubase'))require_once DOL_DOCUMENT_ROOT.'/core/class/menubase.class.php';
        $menu=new Menubase($this->db);
        $menu->menu_handler='all';
        $menu->entity=1;
        $menu->module='chinaplanetofficeexpenses';
        $menu->type='top';
        $menu->mainmenu='chinaplanetexpenses';
        $menu->leftmenu='';
        $menu->fk_menu=0;
        $menu->position=93;
        $menu->url='/custom/chinaplanetofficeexpenses/index.php';
        $menu->target='';
        $menu->title='OfficeExpenses';
        $menu->prefix='fa-receipt';
        $menu->langs='chinaplanetofficeexpenses@chinaplanetofficeexpenses';
        $menu->perms='$user->admin && $user->id == 1';
        $menu->enabled='isModEnabled(\'chinaplanetofficeexpenses\') && !$user->socid && getDolEntity() == 1';
        $menu->user=0;

        $result=$menu->create($this->user);
        if($result>0)return true;
        if($result===0){
            $existing=$this->findMenu();
            if($existing!==null){
                $this->assertExpectedMenu($existing);
                return false;
            }
        }
        throw new RuntimeException('Office Expenses menu entry could not be created: '.(string)($menu->error??'unknown database error'));
    }

    private function findMenu()
    {
        $sql="SELECT rowid, menu_handler, entity, module, type, mainmenu, leftmenu, fk_menu, fk_mainmenu, fk_leftmenu, position, url, target, titre, prefix, langs, perms, enabled, usertype";
        $sql.=" FROM ".$this->db->prefix()."menu";
        $sql.=" WHERE module = '".$this->db->escape('chinaplanetofficeexpenses')."'";
        $sql.=" AND url = '".$this->db->escape('/custom/chinaplanetofficeexpenses/index.php')."'";
        $result=$this->db->query($sql);
        if(!$result)throw new RuntimeException('Office Expenses menu lookup failed.');
        $count=$this->db->num_rows($result);
        if($count>1)throw new RuntimeException('Multiple Office Expenses menu rows require manual review.');
        $row=$count===1?$this->db->fetch_object($result):null;
        $this->db->free($result);
        return $row;
    }

    private function assertExpectedMenu($row): void
    {
        $expected=array(
            'menu_handler'=>'all','entity'=>1,'module'=>'chinaplanetofficeexpenses','type'=>'top',
            'mainmenu'=>'chinaplanetexpenses','leftmenu'=>null,'fk_menu'=>0,
            'fk_mainmenu'=>null,'fk_leftmenu'=>null,'position'=>93,
            'url'=>'/custom/chinaplanetofficeexpenses/index.php','target'=>'',
            'titre'=>'OfficeExpenses','prefix'=>'fa-receipt',
            'langs'=>'chinaplanetofficeexpenses@chinaplanetofficeexpenses',
            'perms'=>'$user->admin && $user->id == 1',
            'enabled'=>'isModEnabled(\'chinaplanetofficeexpenses\') && !$user->socid && getDolEntity() == 1',
            'usertype'=>0,
        );
        foreach($expected as $field=>$value){
            $actual=$row->$field??null;
            // The reviewed standalone descriptor can include the dormant staff phase.
            // Accept only this exact opt-in expression; never adopt arbitrary menu permissions.
            $staffPerms='($user->admin && $user->id == 1) || (getDolGlobalInt("CPO_OE_STAFF_ACCESS_ENABLED") === 1 && $user->hasRight("chinaplanetofficeexpenses", "read"))';
            // Native module registration normalizes double quotes to single quotes.
            if($field==='perms'&&in_array($actual,[$staffPerms,str_replace('"',"'",$staffPerms)],true))continue;
            if($value===null?($actual!==null&&$actual!==''):(string)$actual!==(string)$value){
                throw new RuntimeException('Existing Office Expenses menu row differs from the approved entry.');
            }
        }
    }

    private function requireAuthorization(): void
    {
        if(!$this->isAuthorized())throw new RuntimeException('Office Expenses menu synchronization is restricted to administrator 1 in entity 1.');
    }
}
