<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseMoney.php';

interface OfficeExpenseDraftStore
{
    public function save(int $entity, int $owner, string $key, array $draft): array;
    public function listOwn(int $entity, int $owner): array;
    public function findOwn(int $entity, int $owner, string $id): ?array;
}

interface OfficeExpenseBranchDraftStore
{
    public function listBranch(int $entity,string $office,string $section,?int $owner): array;
    public function findBranch(int $entity,string $office,string $section,?int $owner,string $id): ?array;
}

/** Domain foundation only: no native writes, bootstrap, schema install or HTTP route. */
final class OfficeExpenseDraftService
{
    private $store; private $money; private $user; private $owner; private $entity; private $offices; private $base; private $access;
    private const ROUTES = array(
        'expense_claim'=>'expense_report_review', 'advance'=>'employee_advance_review',
        'advance_return'=>'employee_advance_settlement_review', 'transfer'=>'bank_transfer_review',
        'reimbursement'=>'expense_payment_review',
    );

    /** User, entity, office scope and base currency MUST come from trusted server context. */
    public function __construct(OfficeExpenseDraftStore $store, OfficeExpenseMoney $money, $user, int $entity, array $offices, string $base, ?OfficeExpenseStaffAccess $access=null)
    {
        if ($entity <= 0) throw new InvalidArgumentException('A valid native entity is required.');
        $money->amount('0', $base);
        foreach ($offices as $office=>$currencies) {
            if (!is_string($office) || !preg_match('/^[a-z][a-z0-9_-]{0,39}$/D', $office) || !is_array($currencies) || !$currencies) throw new InvalidArgumentException('Invalid trusted office scope.');
            foreach ($currencies as $currency) $money->amount('0', $currency);
        }
        $this->store=$store; $this->money=$money; $this->user=$user; $this->entity=$entity; $this->offices=$offices; $this->base=$base;
        $this->owner=is_object($user)?(int)($user->id??0):0;
        if($access!==null&&(!$access->usesContext($user,$entity)||!$store instanceof OfficeExpenseBranchDraftStore))throw new DomainException('Staff access binding differs.');
        $this->access=$access;
    }

    public function submit(array $payload, $key): array
    {
        $this->authorize();
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $key)) throw new InvalidArgumentException('A stable submission key of 8–80 characters is required.');
        $allowed = array('kind','office','amount','currency','occurred_on','source_ref','purpose','quote','settles_ref','from_account_ref','destination');
        if (array_diff(array_keys($payload), $allowed)) throw new InvalidArgumentException('Unexpected draft fields; identity and state are server-controlled.');
        $office=$payload['office'] ?? null; $kind=$payload['kind'] ?? null; $currency=$payload['currency'] ?? null;
        if (!is_string($office) || !array_key_exists($office, $this->offices)) throw new DomainException('Office is outside the authorized scope.');
        if (!is_string($kind) || !isset(self::ROUTES[$kind])) throw new InvalidArgumentException('Unsupported office movement.');
        if($this->access!==null)$this->access->assertAction($office,self::section($kind),'create');
        if (!is_string($currency) || !in_array($currency, $this->offices[$office], true)) throw new InvalidArgumentException('Currency is not configured for this office.');
        $amount=$this->money->amount($payload['amount'] ?? null, $currency);
        if (bccomp($amount, '0', 4) <= 0) throw new InvalidArgumentException('Amount must be greater than zero.');
        OfficeExpenseMoney::date($payload['occurred_on'] ?? null);
        OfficeExpenseMoney::text($payload['source_ref'] ?? null, 160);
        OfficeExpenseMoney::text($payload['purpose'] ?? null, 1000);
        $quote=$payload['quote'] ?? array();
        if (!is_array($quote) || array_diff(array_keys($quote), array('rate','date','source'))) throw new InvalidArgumentException('Invalid quote fields.');
        $draft=array('entity'=>$this->entity,'owner_id'=>$this->owner,'office'=>$office,'kind'=>$kind,
            'amount'=>$amount,'currency'=>$currency,'occurred_on'=>$payload['occurred_on'],
            'source_ref'=>trim($payload['source_ref']),'purpose'=>trim($payload['purpose']),
            'conversion'=>$this->money->convert($amount, $currency, $this->base, $quote),
            'status'=>'draft','accounting_route'=>self::ROUTES[$kind],'native_record'=>null);
        if (in_array($kind, array('advance_return','reimbursement'), true)) {
            OfficeExpenseMoney::text($payload['settles_ref'] ?? null, 160);
            // Reference retained for later native verification; never treated as a validated link.
            $draft['settles_ref']=trim($payload['settles_ref']);
        } elseif (isset($payload['settles_ref'])) {
            throw new InvalidArgumentException('Settlement reference is not applicable to this movement.');
        }
        if ($kind === 'transfer') {
            OfficeExpenseMoney::text($payload['from_account_ref'] ?? null, 160);
            $destination=$payload['destination'] ?? null;
            if (!is_array($destination) || array_diff(array_keys($destination), array('amount','currency','account_ref'))) throw new InvalidArgumentException('Both transfer legs are required.');
            OfficeExpenseMoney::text($destination['account_ref'] ?? null, 160);
            if (trim($destination['account_ref']) === trim($payload['from_account_ref'])) throw new InvalidArgumentException('Transfer accounts must differ.');
            if (!is_string($destination['currency'] ?? null)) throw new InvalidArgumentException('Destination currency is required.');
            $destinationAmount=$this->money->amount($destination['amount'] ?? null, $destination['currency']);
            if (bccomp($destinationAmount, '0', 4) <= 0) throw new InvalidArgumentException('Destination amount must be greater than zero.');
            $draft['from_account_ref']=trim($payload['from_account_ref']);
            $draft['destination']=array('amount'=>$destinationAmount,'currency'=>$destination['currency'],'account_ref'=>trim($destination['account_ref']));
        } elseif (isset($payload['from_account_ref']) || isset($payload['destination'])) {
            throw new InvalidArgumentException('Transfer fields are not applicable to this movement.');
        }
        return $this->store->save($this->entity, $this->owner, $key, $draft);
    }

    public function listOwn(): array
    {
        $this->authorize();
        return array_values(array_filter($this->store->listOwn($this->entity, $this->owner), function ($draft) {
            return array_key_exists($draft['office'], $this->offices)&&$this->canRead($draft);
        }));
    }

    public function getOwn(string $id): array
    {
        $this->authorize();
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new DomainException('Draft is unavailable in the current scope.');
        $draft=$this->store->findOwn($this->entity, $this->owner, $id);
        if (!$draft || !array_key_exists($draft['office'], $this->offices)||!$this->canRead($draft)) throw new DomainException('Draft is unavailable in the current scope.');
        return $draft;
    }

    /** Explicit branch read API. Legacy own-record methods retain their ownership boundary. */
    public function listReadable(): array
    {
        if($this->access===null)return $this->listOwn();$rows=[];
        foreach($this->readScopes()as$office=>$sections)foreach($sections as$section=>$scope){
            $owner=$scope==='own'?$this->owner:null;
            foreach($this->store->listBranch($this->entity,$office,$section,$owner)as$draft)$rows[]=$draft;
        }
        return $rows;
    }
    public function getReadable(string $id): array
    {
        if($this->access===null)return $this->getOwn($id);
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new DomainException('Draft is unavailable in the current scope.');
        foreach($this->readScopes()as$office=>$sections)foreach($sections as$section=>$scope){
            $owner=$scope==='own'?$this->owner:null;
            $draft=$this->store->findBranch($this->entity,$office,$section,$owner,$id);if($draft!==null)return $draft;
        }
        throw new DomainException('Draft is unavailable in the current scope.');
    }
    public function usesStaffAccess(): bool {return $this->access!==null;}
    public function canCreate(string $office,string $kind): bool {if($this->access!==null)return isset($this->offices[$office])&&$this->access->allowed($office,self::section($kind),'create');$this->authorize();return true;}
    public function assertAttach(string $id): array { $draft=$this->getOwn($id);if(!$this->canCreate($draft['office'],$draft['kind']))throw new DomainException('Receipt attachment unavailable.');return $draft; }
    private static function section(string $kind): string {if($kind==='expense_claim')return 'expenses';if(in_array($kind,['advance','advance_return'],true))return 'advances';throw new DomainException('Movement unavailable for branch access.');}
    private function canRead(array $draft): bool {return $this->access===null||$this->access->allowed($draft['office'],self::section($draft['kind']),'read');}
    private function readScopes(): array
    {
        if(!$this->access->usesContext($this->user,$this->entity))throw new OfficeExpenseStaffAccessDenied('Staff context differs.');
        $scopes=array_intersect_key($this->access->readScopes(),$this->offices);if(!$scopes)throw new OfficeExpenseStaffAccessDenied('No authorized staff branch.');return $scopes;
    }
    public function officeOptions(): array {if($this->access!==null)return array_intersect_key($this->offices,$this->readScopes());$this->authorize();return $this->offices;}
    public function baseCurrency(): string { $this->authorize(); return $this->base; }
    public function usesNativeContext($user,int $entity): bool { return $this->user === $user && $this->entity === $entity && is_object($user) && $this->owner>0 && (int)($user->id??0)===$this->owner; }

    public function post(array $draft): void
    {
        throw new LogicException('Native financial posting is disabled in the local foundation.');
    }

    private function authorize(): void
    {
        if($this->access!==null){$this->readScopes();return;}
        if (!is_object($this->user) || empty($this->user->id) || (int) $this->user->id <= 0 || !empty($this->user->socid)
            || (int)$this->user->id!==$this->owner
            || (int)($this->user->status ?? 0) !== 1
            || (method_exists($this->user,'isNotIntoValidityDateRange') && $this->user->isNotIntoValidityDateRange())
            || !method_exists($this->user, 'hasRight') || !$this->user->hasRight('expensereport','creer')) {
            throw new DomainException('An enabled valid internal native user with expense-report creation rights is required.');
        }
    }
}
