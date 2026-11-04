<?php
declare(strict_types=1);
require_once __DIR__.'/../../../custom/chinaplanetofficeexpenses/class/OfficeExpenseNativeDraftBridge.php';

/** Real native objects, restricted to the disposable synthetic SQLite harness. NOT a production adapter. */
final class LocalNativeDraftWriter implements OfficeExpenseNativeDraftWriter
{
    private $db; private $user; private $conf; private $money; private $entity; private $currency;
    public function __construct($db,$user,$conf,OfficeExpenseMoney $money)
    {
        if (!defined('M119_SYNTHETIC_NATIVE') || M119_SYNTHETIC_NATIVE !== true || !defined('DOL_DATA_ROOT')
            || !is_file(DOL_DATA_ROOT.'/.m119-native-synthetic') || !isset($db->type) || $db->type !== 'sqlite3'
            || strpos((string)$db->database_name,'m119_office_') !== 0) throw new LogicException('Native writer is restricted to the synthetic local harness.');
        $file=$db->db->querySingle('PRAGMA database_list',true)['file'] ?? '';
        if (realpath(dirname($file)) !== realpath(DOL_DATA_ROOT)) throw new LogicException('Synthetic database is outside the owned test directory.');
        $this->db=$db;$this->user=$user;$this->conf=$conf;$this->money=$money;
        $this->entity=(int)$conf->entity;$this->currency=(string)$conf->currency;
    }

    public function createOrFind(array $draft,int $categoryId,string $taxPolicy): array
    {
        $this->authorize($draft);
        if ($draft['kind'] !== 'expense_claim' || $draft['status'] !== 'draft' || $draft['currency'] !== $this->currency) throw new DomainException('Foreign-currency native persistence is unproven; only base-currency expense claims are eligible.');
        if ($taxPolicy !== 'explicit_zero_tax') throw new DomainException('This synthetic native stage requires explicitly verified zero tax; no tax rate is assumed.');
        if (!preg_match('/^[a-f0-9]{32}$/D',$draft['id'])) throw new DomainException('Invalid immutable draft ID.');
        $amount=$this->money->amount($draft['amount'],$this->currency);
        $fee=$this->one('SELECT id FROM '.MAIN_DB_PREFIX.'c_type_fees WHERE id='.(int)$categoryId." AND active=1 AND code NOT IN ('EX_KME','EX_KMI')");
        if (!$fee) throw new DomainException('Choose an active non-mileage native expense category.');
        if ($this->db->transaction_opened !== 0) throw new LogicException('Native bridge must own the outer transaction.');
        $hash=hash('sha256',json_encode(array($draft,$categoryId,$taxPolicy),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $snapshot=json_encode($draft,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $where='entity='.$this->entity.' AND owner_id='.(int)$this->user->id." AND draft_id='".$this->db->escape($draft['id'])."'";
        if (!$this->db->begin()) throw new RuntimeException('Cannot begin native draft transaction.');
        try {
            $marker=$this->one('SELECT * FROM '.MAIN_DB_PREFIX.'oe_native_draft WHERE '.$where);
            if ($marker) {
                if (!hash_equals($marker->payload_hash,$hash) || $marker->snapshot_json !== $snapshot || $marker->status !== 'native_created' || (int)$marker->native_id <= 0) throw new DomainException('Native draft retry has conflicting or incomplete evidence.');
                $result=$this->verify((int)$marker->native_id,$draft,$categoryId);
            } else {
                $orphan=$this->one('SELECT rowid FROM '.MAIN_DB_PREFIX.'expensereport WHERE entity='.$this->entity." AND note_private LIKE 'CPO-OE-".$this->db->escape($draft['id'])."%'");
                if ($orphan) throw new DomainException('An unlinked native draft already exists; automatic duplication is blocked.');
                $this->query('INSERT INTO '.MAIN_DB_PREFIX.'oe_native_draft(entity,owner_id,draft_id,payload_hash,snapshot_json,status) VALUES('.$this->entity.','.(int)$this->user->id.",'".$this->db->escape($draft['id'])."','".$hash."','".$this->db->escape($snapshot)."','preparing')");
                $report=new ExpenseReport($this->db);
                $report->fk_user_author=(int)$this->user->id;
                $time=(new DateTimeImmutable($draft['occurred_on'].'T12:00:00'))->getTimestamp();
                $report->date_debut=$time;$report->date_fin=$time;
                $report->note_public='';$report->note_private='CPO-OE-'.$draft['id']."\n".$draft['source_ref']."\n".$draft['purpose'];
                // No native triggers are executed in the synthetic harness. No runtime setting is changed.
                if ($report->create($this->user,1) <= 0) throw new RuntimeException('Native expense draft creation failed: '.$report->error);
                $line=new ExpenseReportLine($this->db);
                $line->fk_expensereport=(int)$report->id;$line->fk_c_type_fees=$categoryId;$line->fk_project=0;
                $line->date=$time;$line->comments=$draft['purpose'];$line->qty='1';$line->value_unit=$amount;
                $line->vatrate='0';$line->vat_src_code='';$line->localtax1_tx='0';$line->localtax2_tx='0';$line->localtax1_type='0';$line->localtax2_type='0';
                $line->total_ht=$amount;$line->total_ttc=$amount;$line->total_tva='0';$line->total_localtax1='0';$line->total_localtax2='0';
                $line->fk_c_exp_tax_cat=0;$line->fk_ecm_files=0;$line->rule_warning_message='';
                if ($line->insert(1,true) <= 0 || $report->update_price(1) <= 0) throw new RuntimeException('Native expense line or total calculation failed.');
                $result=$this->verify((int)$report->id,$draft,$categoryId);
                $this->query('UPDATE '.MAIN_DB_PREFIX.'oe_native_draft SET native_id='.(int)$report->id.",status='native_created' WHERE ".$where." AND status='preparing'");
                $completed=$this->one('SELECT * FROM '.MAIN_DB_PREFIX.'oe_native_draft WHERE '.$where);
                if (!$completed || $completed->status !== 'native_created' || (int)$completed->native_id !== (int)$report->id
                    || !hash_equals($completed->payload_hash,$hash) || $completed->snapshot_json !== $snapshot) throw new RuntimeException('Native draft link was not persisted exactly.');
            }
            if ($this->db->transaction_opened !== 1 || !$this->db->commit()) throw new RuntimeException('Native draft commit failed.');
            return $result;
        } catch (Throwable $error) {
            while ($this->db->transaction_opened > 0) $this->db->rollback();
            throw $error;
        }
    }

    private function authorize(array $draft): void
    {
        if (($GLOBALS['conf'] ?? null) !== $this->conf || ($GLOBALS['user'] ?? null) !== $this->user
            || (int)$this->conf->entity !== $this->entity || (string)$this->conf->currency !== $this->currency
            || empty($this->conf->expensereport->enabled) || empty($this->user->id) || !empty($this->user->socid)
            || (int)($this->user->status ?? 0) !== 1
            || (method_exists($this->user,'isNotIntoValidityDateRange') && $this->user->isNotIntoValidityDateRange())
            || !$this->user->hasRight('expensereport','creer') || ($draft['entity'] ?? null) !== $this->entity || ($draft['owner_id'] ?? null) !== (int)$this->user->id) throw new DomainException('Native identity, entity, module or expense-report right mismatch.');
    }

    private function verify(int $id,array $draft,int $categoryId): array
    {
        $row=$this->one('SELECT rowid,ref,entity,fk_user_author,fk_user_creat,fk_statut,paid,total_ht,total_tva,total_ttc,localtax1,localtax2,note_private,date_debut,date_fin FROM '.MAIN_DB_PREFIX.'expensereport WHERE rowid='.$id.' AND entity='.$this->entity.' AND fk_user_author='.(int)$this->user->id);
        $note='CPO-OE-'.$draft['id']."\n".$draft['source_ref']."\n".$draft['purpose'];
        if (!$row || (int)$row->fk_user_creat !== (int)$this->user->id || (int)$row->fk_statut !== 0 || (int)$row->paid !== 0
            || (string)$row->note_private !== $note || substr((string)$row->date_debut,0,10) !== $draft['occurred_on']
            || substr((string)$row->date_fin,0,10) !== $draft['occurred_on']) throw new DomainException('Native record is no longer the owned unchanged unpaid draft.');
        foreach (array('total_ht'=>$draft['amount'],'total_ttc'=>$draft['amount'],'total_tva'=>'0','localtax1'=>'0','localtax2'=>'0') as $field=>$expected) $this->sameAmount($row->$field,$expected);
        $lines=$this->query('SELECT fk_c_type_fees,qty,value_unit,total_ht,total_tva,total_ttc,date,comments,tva_tx,localtax1_tx,localtax2_tx,localtax1_type,localtax2_type,total_localtax1,total_localtax2 FROM '.MAIN_DB_PREFIX.'expensereport_det WHERE fk_expensereport='.$id);
        if ($this->db->num_rows($lines) !== 1) throw new DomainException('Native expense detail count changed.');
        $line=$this->db->fetch_object($lines);
        if ((int)$line->fk_c_type_fees !== $categoryId || substr((string)$line->date,0,10) !== $draft['occurred_on'] || $line->comments !== $draft['purpose']) throw new DomainException('Native expense category, date or purpose changed.');
        foreach (array('qty'=>'1','value_unit'=>$draft['amount'],'total_ht'=>$draft['amount'],'total_ttc'=>$draft['amount'],'total_tva'=>'0',
            'tva_tx'=>'0','localtax1_tx'=>'0','localtax2_tx'=>'0','localtax1_type'=>'0','localtax2_type'=>'0','total_localtax1'=>'0','total_localtax2'=>'0') as $field=>$expected) $this->sameAmount($line->$field,$expected);
        return array('expense_report_id'=>$id,'ref'=>(string)$row->ref,'entity'=>$this->entity,'amount'=>$draft['amount'],'native_currency'=>$this->currency,'status'=>'draft','paid'=>false);
    }

    private function sameAmount($actual,string $expected): void
    {
        $actual=(string)$actual;
        if (!preg_match('/^[0-9]+(?:\.[0-9]+)?$/D',$actual) || bccomp($actual,$expected,8) !== 0) throw new DomainException('Native amount differs from the exact original; automatic continuation is blocked.');
    }
    private function query(string $sql) { $r=$this->db->query($sql); if (!$r) throw new RuntimeException('Synthetic native persistence failed: '.$this->db->lasterror()); return $r; }
    private function one(string $sql) { return $this->db->fetch_object($this->query($sql)); }
}
