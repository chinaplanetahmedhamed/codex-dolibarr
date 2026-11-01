<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseNativeAccessStore.php';
final class OfficeExpenseStaffAccessDenied extends DomainException {}

/** Native identity/capability intersection with independent, default-deny branch grants. */
final class OfficeExpenseStaffAccess
{
    public const OFFICES=['egypt'=>['EGP','USD'],'china'=>['CNY','USD']];
    public const ACTIONS=['expenses'=>['read','create','update','delete'],'advances'=>['read','create','update','delete'],
        'treasury'=>['read','create','update','delete'],'receipts'=>['read','create','update','delete'],
        'categories'=>['read','create','update','delete'],'reports'=>['read'],
        'employees'=>['read','create','update','delete'],'payroll'=>['read','create','update','delete'],
        'settings'=>['read','update']];
    private $db;private $user;private $conf;private int $actor;private int $entity;private int $bootstrap;private OfficeExpenseNativeAccessStore $store;
    public function __construct($db,$user,$conf,int $bootstrap,?OfficeExpenseNativeAccessStore $store=null)
    {
        if(!class_exists('User',false)||!$user instanceof User||!is_object($conf)||!is_int($conf->entity??null)||$conf->entity<1||$bootstrap<1)throw new DomainException('Native staff context required.');
        $this->db=$db;$this->user=$user;$this->actor=(int)$user->id;$this->conf=$conf;$this->entity=$conf->entity;$this->bootstrap=$bootstrap;$this->store=$store??new OfficeExpenseNativeAccessStore($db);
        $this->identity();
    }
    public function usesContext($user,int $entity): bool {return $user===$this->user&&$entity===$this->entity&&(int)($user->id??0)===$this->actor;}
    private function identity(): User
    {
        if(($GLOBALS['db']??null)!==$this->db||($GLOBALS['conf']??null)!==$this->conf||(int)($this->user->id??0)!==$this->actor||($this->conf->entity??0)!==$this->entity||empty($this->conf->chinaplanetofficeexpenses->enabled)||empty($this->conf->expensereport->enabled))throw new DomainException('Staff context disabled.');
        // A fresh native object avoids session/direct/group-right caches after revocation.
        $native=$this->native($this->actor);$native->loadRights('chinaplanetofficeexpenses');return $native;
    }
    private function native(int $id): User
    {
        if($id<1)throw new DomainException('Staff identity unavailable.');
        $native=new User($this->db);
        // Native fetch-by-ID does not filter entity; inspect the persisted entity explicitly.
        // Single-company Dolibarr normalizes entity-1 admins to entity 0 after fetch.
        $row=(new OfficeExpenseNativeConnection($this->db))->one('SELECT entity FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.$id);
        if(!$row||!in_array((int)$row['entity'],[0,$this->entity],true)||$native->fetch($id,'','',0,$this->entity)!==1||((int)$native->entity!==$this->entity&&!((int)$native->entity===0&&!empty($native->admin)))||(int)$native->status!==1||!empty($native->socid)||$native->isNotIntoValidityDateRange())throw new DomainException('Staff identity unavailable.');
        return $native;
    }
    private function bootstrap(User $native): bool {return $this->actor===$this->bootstrap&&!empty($native->admin);}
    private function membership(string $office,User $native): ?array
    {
        if(!isset(self::OFFICES[$office]))throw new DomainException('Branch unavailable.');
        if($this->bootstrap($native))return ['grants'=>self::ACTIONS,'record_scope'=>'branch','active'=>true];
        $member=$this->store->find($this->entity,$this->actor,$office);
        if($member&&$member['active']){self::grants($member['grants']);if(!in_array($member['record_scope'],['own','branch'],true))throw new RuntimeException('Invalid stored scope.');return $member;}
        return null;
    }
    public function allowed(string $office,string $section,string $action): bool
    {
        $native=$this->identity();$member=$this->membership($office,$native);
        return $this->permits($native,$member,$section,$action);
    }
    private function permits(User $native,?array $member,string $section,string $action): bool
    {
        return isset(self::ACTIONS[$section])&&in_array($action,self::ACTIONS[$section],true)&&$member!==null
            &&in_array($action,$member['grants'][$section]??[],true)
            &&($this->bootstrap($native)||($native->hasRight('chinaplanetofficeexpenses','read')&&($action==='read'||$native->hasRight('chinaplanetofficeexpenses','draft'))));
    }
    public function assertAction(string $office,string $section,string $action): void {if(!$this->allowed($office,$section,$action))throw new OfficeExpenseStaffAccessDenied('Action unavailable in this scope.');}
    public function offices(): array
    {
        return array_intersect_key(self::OFFICES,$this->readScopes());
    }
    /** Fresh once per read operation; never retained in sessions or across requests. */
    public function readScopes(): array
    {
        $native=$this->identity();return $this->scopesFor($native);
    }
    private function scopesFor(User $native): array
    {
        $scopes=[];foreach(self::OFFICES as$office=>$currencies){$member=$this->membership($office,$native);foreach(array_keys(self::ACTIONS)as$section)if($this->permits($native,$member,$section,'read'))$scopes[$office][$section]=$member['record_scope'];}return $scopes;
    }
    public function recordScope(string $office,string $section): string
    {
        $native=$this->identity();$member=$this->membership($office,$native);if(!$this->permits($native,$member,$section,'read'))throw new OfficeExpenseStaffAccessDenied('Action unavailable in this scope.');return $member['record_scope'];
    }
    public function managedOffices(): array
    {
        $native=$this->identity();if($this->bootstrap($native))return self::OFFICES;
        if(!$native->hasRight('chinaplanetofficeexpenses','manageaccess'))throw new DomainException('Branch access administration is not authorized.');
        return array_intersect_key(self::OFFICES,$this->scopesFor($native));
    }
    public function directory(string $search=''): array
    {
        if(!$this->managedOffices())throw new DomainException('No managed branch.');
        if(strlen($search)>80||preg_match('/[\x00-\x1f]/',$search))throw new InvalidArgumentException('Invalid directory search.');
        $c=new OfficeExpenseNativeConnection($this->db);
        // Project safe fields only. Fetch below verifies expiry, identity and entity again.
        $rows=$c->rows('SELECT rowid,login,firstname,lastname FROM '.MAIN_DB_PREFIX.'user WHERE entity='.$this->entity.' AND statut=1 AND (fk_soc IS NULL OR fk_soc=0) ORDER BY login LIMIT 500');
        $out=[];foreach($rows as$row){if($search!==''&&stripos($row['login'].' '.$row['firstname'].' '.$row['lastname'],$search)===false)continue;try{$native=$this->native((int)$row['rowid']);}catch(DomainException$e){continue;}$out[]=['id'=>(int)$native->id,'login'=>(string)$native->login,'name'=>trim((string)$native->firstname.' '.(string)$native->lastname),'status'=>1];}return $out;
    }
    public function members(string $office): array
    {
        if(!isset($this->managedOffices()[$office]))throw new DomainException('Branch unavailable.');
        $rows=$this->store->members($this->entity,$office);foreach($rows as&$row){$native=new User($this->db);$row['login']='';$row['name']='';if($native->fetch($row['user_id'],'','',0,$this->entity)===1&&(int)$native->entity===$this->entity){$row['login']=(string)$native->login;$row['name']=trim((string)$native->firstname.' '.(string)$native->lastname);}}return $rows;
    }
    /** Safe native identities actually assigned to this branch; never the global staff directory. */
    public function branchStaff(string $office,string $section): array
    {
        $scope=$this->recordScope($office,$section);$rows=$this->store->members($this->entity,$office);$out=[];
        foreach($rows as$row){if(!$row['active']||($scope==='own'&&$row['user_id']!==$this->actor))continue;
            try{$native=$this->native($row['user_id']);}catch(DomainException $e){continue;}
            $out[]=['id'=>(int)$native->id,'login'=>(string)$native->login,'name'=>trim((string)$native->firstname.' '.(string)$native->lastname)];
        }return $out;
    }
    public function assign(int $target,string $office,array $grants,string $scope,int $expected): array
    {
        $grants=self::grants($grants);if(!in_array($scope,['own','branch'],true))throw new InvalidArgumentException('Invalid record scope.');
        $check=function()use($target,$office,$grants,$scope){$this->assertManage($target,$office);$this->native($target);foreach($grants as$section=>$actions){foreach($actions as$action)$this->assertAction($office,$section,$action);if($scope==='branch'&&$this->recordScope($office,$section)!=='branch')throw new DomainException('Cannot expand record visibility.');}};
        $check();return $this->store->replace($this->entity,$this->actor,$target,$office,$grants,$scope,$expected,true,$check);
    }
    public function revoke(int $target,string $office,int $expected): array
    {
        $check=fn()=>$this->assertManage($target,$office);$check();
        return $this->store->replace($this->entity,$this->actor,$target,$office,[],'own',$expected,false,$check);
    }
    public function mayManage(int $target,string $office): bool {try{$this->assertManage($target,$office);return true;}catch(DomainException $error){return false;}}
    private function assertManage(int $target,string $office): void
    {
        if($target<1||$target===$this->actor||$target===$this->bootstrap||!isset($this->managedOffices()[$office]))throw new DomainException('Membership administration unavailable.');
        $native=$this->identity();
        if(!$this->bootstrap($native)){
            // A delegate may only replace/revoke a target within their current ceiling too.
            $old=$this->store->find($this->entity,$target,$office);
            if($old&&$old['active'])foreach($old['grants']as$section=>$actions){foreach($actions as$action)$this->assertAction($office,$section,$action);if($old['record_scope']==='branch'&&$this->recordScope($office,$section)!=='branch')throw new DomainException('Existing membership exceeds delegation ceiling.');}
        }
    }
    public static function grants(array $grants): array
    {
        if(!$grants||array_diff(array_keys($grants),array_keys(self::ACTIONS)))throw new InvalidArgumentException('Unsupported permission section.');
        $out=[];foreach(self::ACTIONS as$section=>$supported){if(!array_key_exists($section,$grants))continue;$actions=$grants[$section];if(!is_array($actions)||!array_is_list($actions)||!$actions||array_filter($actions,fn($a)=>!is_string($a))||count(array_unique($actions,SORT_REGULAR))!==count($actions)||array_diff($actions,$supported)||!in_array('read',$actions,true))throw new InvalidArgumentException('Unsupported action or missing read grant.');$out[$section]=array_values(array_intersect($supported,$actions));}return $out;
    }
}
