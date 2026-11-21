<?php
require_once __DIR__.'/CustomerAccountAmount.class.php';

/** Approval and public projection of existing Board cash entries. No cash is copied. */
class CustomerFinanceLink
{
    private $db;
    private $entity;
    private $currency;

    public function __construct($db, int $entity, string $currency)
    {
        $this->db = $db;
        $this->entity = $entity;
        $this->currency = strtoupper($currency);
    }

    public static function enabled($conf): bool
    {
        return !empty($conf->global->CPO_ACCOUNT_STATEMENTS_ENABLED) && !empty($conf->global->CPO_ACCOUNT_FINANCE_LINK_ENABLED);
    }

    private function table(): string { return MAIN_DB_PREFIX.'cpo_account_finance_link'; }
    private function quoted(string $text): string { return "'".$this->db->escape($text)."'"; }

    private function query(string $sql)
    {
        $result = $this->db->query($sql);
        if (!$result) throw new RuntimeException('Finance link data is unavailable.');
        return $result;
    }

    private function nativeReceiptMatches(string $source, int $customer, array $payload): bool
    {
        if (strpos($source, 'payment:') !== 0) return strpos($source, 'proof:') === 0;
        if ($payload['direction'] !== 'received') return false;
        $payment = (int)substr($source, 8);
        $validated = function_exists('getDolGlobalInt') && getDolGlobalInt('BILL_ADD_PAYMENT_VALIDATION');
        $status = $validated ? 'p.statut=1' : 'p.statut IN (0,1)';
        $prefix = MAIN_DB_PREFIX;
        $rows = $this->query("SELECT p.datep, f.fk_soc, f.entity, f.fk_statut, f.type, pf.amount,
            pf.multicurrency_code, pf.multicurrency_amount, f.multicurrency_code invoice_currency
            FROM {$prefix}paiement p
            JOIN {$prefix}bank b ON b.rowid=p.fk_bank AND b.amount>0
            JOIN {$prefix}bank_account ba ON ba.rowid=b.fk_account AND ba.entity=".$this->entity."
            JOIN {$prefix}paiement_facture pf ON pf.fk_paiement=p.rowid
            JOIN {$prefix}facture f ON f.rowid=pf.fk_facture
            WHERE p.entity=".$this->entity." AND p.rowid=".$payment." AND ".$status);
        $sum = '0';
        while ($row = $this->db->fetch_object($rows)) {
            $original = $row->multicurrency_code ?: $row->invoice_currency;
            $currency = $original ?: $this->currency;
            if ((int)$row->fk_soc !== $customer || (int)$row->entity !== $this->entity
                || (int)$row->fk_statut <= 0 || (int)$row->type === 2
                || $currency !== $payload['currency'] || substr($row->datep, 0, 10) !== $payload['date']) {
                return false;
            }
            $amount = $original ? (string)$row->multicurrency_amount : (string)$row->amount;
            if ($currency === $this->currency && bccomp($amount, '0', 8) === 0) $amount = (string)$row->amount;
            if (bccomp($amount, '0', 8) <= 0) return false;
            $sum = bcadd($sum, $amount, 8);
        }
        return bccomp($sum, $payload['amount'], 8) === 0;
    }

    /** Called inside the authorized Boards command transaction. */
    public function publish(int $customer, array $entry, string $source, int $actor): string
    {
        if (!preg_match('~^(payment:[1-9][0-9]{0,9}|proof:[A-Za-z0-9][A-Za-z0-9._/-]{2,120})$~D', $source)) {
            throw new RuntimeException('Enter a Dolibarr payment ID or a unique proof reference.', 422);
        }
        if ($entry['status'] !== 'posted' || !empty($entry['reversal_of'])) {
            throw new RuntimeException('Only an active posted movement can be reviewed.', 409);
        }
        $id = (int)$entry['rowid'];
        $source = strtolower($source);
        $payload = json_decode($entry['payload'], true, 512, JSON_THROW_ON_ERROR);
        if (!$this->nativeReceiptMatches($source, $customer, $payload)) {
            throw new RuntimeException('The payment must match this customer, date, currency and amount and have bank evidence.', 422);
        }

        $key = $this->quoted($source);
        $result = $this->query('SELECT fk_entry FROM '.$this->table().' WHERE entity='.$this->entity.' AND active_key='.$key.' FOR UPDATE');
        $existing = $this->db->fetch_object($result);
        if ($existing && (int)$existing->fk_entry !== $id) throw new RuntimeException('This payment or proof already belongs to a reviewed entry.', 409);

        $this->query('DELETE FROM '.$this->table().' WHERE entity='.$this->entity.' AND fk_soc='.$customer.' AND fk_entry='.$id);
        $this->query('INSERT INTO '.$this->table().'(entity,fk_soc,fk_entry,entry_version,source_key,active_key,fk_user,date_creation) VALUES ('
            .$this->entity.','.$customer.','.$id.','.(int)$entry['version'].','.$key.','.$key.','.$actor.','.$this->quoted(date('Y-m-d H:i:s')).')');
        return $source;
    }

    public function withdraw(int $customer, int $entry): void
    {
        $this->query('UPDATE '.$this->table().' SET active_key=NULL WHERE entity='.$this->entity.' AND fk_soc='.$customer.' AND fk_entry='.$entry);
    }

    public function reviewedVersions(int $customer): array
    {
        $result = $this->query('SELECT fk_entry,entry_version FROM '.$this->table().' WHERE entity='.$this->entity.' AND fk_soc='.$customer.' AND active_key=source_key');
        $versions = [];
        while ($row = $this->db->fetch_object($result)) $versions[(int)$row->fk_entry] = (int)$row->entry_version;
        return $versions;
    }

    /** Customer-safe rows. The caller resolves customer/project scope before rendering or exporting. */
    public function rows(int $customer, int $project = 0, ?CustomerAccountPolicy $staff = null): array
    {
        if ($customer <= 0 || $project < 0) return [];
        $prefix = MAIN_DB_PREFIX;
        $result = $this->query('SELECT e.rowid,e.payload,l.source_key,a.fk_project,a.fk_task,a.original_minor,a.base_minor,p.rowid owned_project,p.title project_title,p.ref project_ref'
            .' FROM '.$this->table().' l JOIN '.$prefix.'cpo_account_entry e ON e.entity=l.entity AND e.fk_soc=l.fk_soc AND e.rowid=l.fk_entry'
            .' JOIN '.$prefix.'cpo_account_allocation a ON a.entity=e.entity AND a.fk_entry=e.rowid AND a.batch=e.allocation_version'
            .' LEFT JOIN '.$prefix.'projet p ON p.entity=e.entity AND p.fk_soc=e.fk_soc AND p.rowid=a.fk_project'
            .' WHERE l.entity='.$this->entity.' AND l.fk_soc='.$customer." AND l.active_key=l.source_key AND e.status='posted'"
            .' AND e.version=l.entry_version AND e.reversal_of IS NULL'
            .' AND NOT EXISTS (SELECT 1 FROM '.$prefix."cpo_account_entry r WHERE r.entity=e.entity AND r.fk_soc=e.fk_soc AND r.reversal_of=e.rowid AND r.status='posted')"
            .' ORDER BY e.rowid,a.position');
        $grouped = [];
        while ($row = $this->db->fetch_object($result)) $grouped[(int)$row->rowid][] = $row;

        $out = [];
        $scopes = [];
        foreach ($grouped as $id => $lines) {
            $first = $lines[0];
            $payload = json_decode($first->payload, true, 512, JSON_THROW_ON_ERROR);
            $amount = $base = $full = $fullBase = '0';
            $projects = [];
            foreach ($lines as $line) {
                if ((int)$line->fk_project > 0 && empty($line->owned_project)) continue 2;
                $full = bcadd($full, (string)$line->original_minor, 0);
                $fullBase = bcadd($fullBase, (string)$line->base_minor, 0);
                if ($project > 0 && (int)$line->fk_project !== $project) continue;
                $projectId = (int)$line->fk_project;
                $taskId = (int)$line->fk_task;
                if ($staff && !isset($scopes[$projectId][$taskId])) {
                    $staff->scope($customer, $projectId, $taskId, true);
                    $scopes[$projectId][$taskId] = true;
                }
                if ($projectId > 0) $projects[$projectId] = $line->project_ref.' · '.$line->project_title;
                $amount = bcadd($amount, (string)$line->original_minor, 0);
                $base = bcadd($base, (string)$line->base_minor, 0);
            }
            if ($amount === '0' || $full !== CustomerAccountAmount::minor($payload['amount'])
                || bccomp($fullBase, $payload['base_minor'], 0) !== 0
                || !$this->nativeReceiptMatches($first->source_key, $customer, $payload)) continue;

            $out[] = [
                'id'=>$id, 'date'=>$payload['date'], 'direction'=>$payload['direction'],
                'kind'=>$payload['kind'] ?? 'movement', 'currency'=>$payload['currency'],
                'rate'=>$payload['rate'], 'rate_date'=>$payload['rate_date'],
                'original_minor'=>$amount, 'base_minor'=>$base, 'projects'=>$projects,
            ];
        }
        usort($out, static function ($a, $b) { return [$a['date'], $a['id']] <=> [$b['date'], $b['id']]; });
        return $out;
    }
}
