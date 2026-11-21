<?php
declare(strict_types=1);

require_once __DIR__.'/CustomerAccountPolicy.class.php';
require_once __DIR__.'/CustomerAccountAmount.class.php';

/** Read-only projection of linked native purchase orders and bank-backed supplier payments. */
final class SupplierAccountService
{
    public function __construct(private $db, private int $entity, private string $baseCurrency)
    {
    }

    public static function allowed($user, CustomerAccountPolicy $policy): bool
    {
        return $policy->allowed('read') && (!empty($user->admin) || (
            ($user->hasRight('fournisseur', 'commande', 'lire') || $user->hasRight('supplier_order', 'lire'))
            && ($user->hasRight('fournisseur', 'facture', 'lire') || $user->hasRight('supplier_invoice', 'lire'))
            && $user->hasRight('banque', 'lire')
        ));
    }

    public function orders($user, int $customer, int $project, int $task, int $supplier, int $order = 0): array
    {
        $policy = new CustomerAccountPolicy($this->db, $user, $this->entity);
        if (!self::allowed($user, $policy)) throw new RuntimeException('Supplier account access denied.', 403);
        if ($customer) $policy->scope($customer, $project, $task);
        if (($project || $task) && !$customer) throw new RuntimeException('Customer context required.', 403);

        $p = MAIN_DB_PREFIX;
        $where = 'o.entity='.$this->entity.' AND s.fournisseur=1 AND '.$policy->projectSql();
        $where .= ' AND '.(new InternalTaskWorkspaceService($this->db, $user, $this->entity))->customerSql('s');
        if ($customer) $where .= ' AND p.fk_soc='.$customer;
        if ($project) $where .= ' AND p.rowid='.$project;
        if ($order) $where .= ' AND o.rowid='.$order;
        if ($supplier) $where .= ' AND o.fk_soc='.$supplier;
        if ($task) {
            // Card membership alone must never expose every supplier order in the project.
            $where .= " AND (EXISTS (SELECT 1 FROM {$p}cpo_project_task m
                JOIN {$p}projet_task nt ON nt.rowid=m.fk_native_task AND nt.entity=m.entity AND nt.fk_projet=p.rowid
                JOIN {$p}element_element l ON (
                    (l.sourcetype='project_task' AND l.fk_source=nt.rowid AND l.targettype='order_supplier' AND l.fk_target=o.rowid)
                    OR (l.targettype='project_task' AND l.fk_target=nt.rowid AND l.sourcetype='order_supplier' AND l.fk_source=o.rowid)
                ) WHERE m.entity=o.entity AND m.fk_project=p.rowid AND m.fk_task=$task)
                OR EXISTS (SELECT 1 FROM {$p}cpo_task t
                JOIN {$p}cpo_supplier_action a ON a.entity=t.entity AND a.fk_operation=t.fk_operation
                WHERE t.entity=o.entity AND t.rowid=$task AND a.action_type='supplier_order'
                    AND a.fk_supplier_order=o.rowid AND a.fk_supplier=o.fk_soc
                    AND a.fk_project=p.rowid AND a.fk_soc_customer=p.fk_soc))";
        }
        $orders = $this->rows("SELECT o.rowid,o.ref,o.fk_soc,o.fk_projet,o.fk_statut,
            o.multicurrency_code AS currency,o.total_ttc AS base_total,o.multicurrency_total_ttc AS foreign_total,
            s.nom AS supplier,p.ref AS project,c.nom AS customer,p.fk_soc AS customer_id
            FROM {$p}commande_fournisseur o
            JOIN {$p}societe s ON s.rowid=o.fk_soc AND s.entity=o.entity
            JOIN {$p}projet p ON p.rowid=o.fk_projet AND p.entity=o.entity
            JOIN {$p}societe c ON c.rowid=p.fk_soc AND c.entity=p.entity
            WHERE $where ORDER BY o.rowid DESC LIMIT 101");
        if (count($orders) > 100) throw new RuntimeException('Narrow the account to one supplier or project.', 422);
        foreach ($orders as &$order) {
            $order['currency'] = $this->currency($order['currency']);
            $order['total'] = $order['currency'] === $this->currency($this->baseCurrency)
                ? $order['base_total'] : $order['foreign_total'];
            $order += $this->position($order);
        }
        return $orders;
    }

    /** Totals are separate for each currency, and excess on one order never settles another. */
    public static function totalsByCurrency(array $orders): array
    {
        $totals = [];
        foreach ($orders as $order) {
            if (!$order['committed']) continue;
            $currency = $order['currency'];
            if (!isset($totals[$currency])) $totals[$currency] = ['ordered'=>'0', 'paid'=>'0', 'remaining'=>'0'];
            $total = &$totals[$currency];
            $total['ordered'] = $order['ordered'] === null || $total['ordered'] === null
                ? null : bcadd($total['ordered'], $order['ordered'], 0);
            $total['paid'] = bcadd($total['paid'], $order['paid'], 0);
            $total['remaining'] = $order['remaining'] === null || $total['remaining'] === null
                ? null : bcadd($total['remaining'], bccomp($order['remaining'], '0', 0) > 0 ? $order['remaining'] : '0', 0);
            unset($total);
        }
        ksort($totals);
        return $totals;
    }

    /** The input comes from the authorized native order query. Amounts are original-currency cents. */
    public function position(array $order): array
    {
        $p = MAIN_DB_PREFIX;
        $id = (int)$order['rowid'];
        $supplier = (int)$order['fk_soc'];
        $project = (int)$order['fk_projet'];
        $currency = $this->currency($order['currency']);
        $ordered = self::minor($order['total']);
        $committed = in_array((int)$order['fk_statut'], [1, 2, 3, 4, 5], true);
        $review = $ordered === null || !in_array($currency, ['USD', 'CNY', 'EGP'], true);
        $paid = '0';
        $payments = [];
        $correction_payments = [];
        // Normalize both native link directions and deduplicate repeated link records.
        $links = "SELECT fk_source AS order_id,fk_target AS invoice_id FROM {$p}element_element
            WHERE sourcetype='order_supplier' AND targettype='invoice_supplier'
            UNION SELECT fk_target,fk_source FROM {$p}element_element
            WHERE targettype='order_supplier' AND sourcetype='invoice_supplier'";
        $correctionFields = !empty($GLOBALS['conf']->global->CPO_SUPPLIER_CORRECTIONS_ENABLED)
            ? 'pay.tms AS payment_revision,pay.num_paiement AS payment_number,b.rowid AS bank_id,b.fk_account AS bank_account,'
            : '';
        $rows = $this->rows("SELECT $correctionFields f.rowid AS invoice_id,f.entity AS invoice_entity,f.fk_soc,f.fk_projet,
            f.fk_statut,f.type,f.multicurrency_code AS invoice_currency,f.ref AS invoice_ref,
            (SELECT COUNT(*) FROM ($links) other WHERE other.invoice_id=f.rowid) AS order_count,
            a.rowid AS allocation,a.amount,a.multicurrency_amount,a.multicurrency_code AS allocation_currency,
            pay.rowid AS payment_id,pay.ref AS payment_ref,pay.datep,ba.entity AS bank_entity,b.amount AS bank_amount
            FROM ($links) l
            LEFT JOIN {$p}facture_fourn f ON f.rowid=l.invoice_id
            LEFT JOIN {$p}paiementfourn_facturefourn a ON a.fk_facturefourn=f.rowid
            LEFT JOIN {$p}paiementfourn pay ON pay.rowid=a.fk_paiementfourn AND pay.entity=".$this->entity."
            LEFT JOIN {$p}bank b ON b.rowid=pay.fk_bank
            LEFT JOIN {$p}bank_account ba ON ba.rowid=b.fk_account
            WHERE l.order_id=$id ORDER BY pay.datep,pay.rowid,a.rowid");
        foreach ($rows as $row) {
            if ((int)$row['invoice_entity'] !== $this->entity || (int)$row['fk_soc'] !== $supplier
                || (int)$row['fk_projet'] !== $project || (int)$row['order_count'] !== 1) {
                $review = true;
                continue;
            }
            if ($row['allocation'] === null) continue;
            $invoiceCurrency = $this->currency($row['invoice_currency']);
            $foreign = $invoiceCurrency !== $this->currency($this->baseCurrency);
            $amount = self::minor($foreign ? $row['multicurrency_amount'] : $row['amount']);
            if (!in_array((int)$row['fk_statut'], [1, 2], true) || !in_array((int)$row['type'], [0, 3], true)
                || ($foreign && $this->currency($row['allocation_currency']) !== $invoiceCurrency)
                || (int)$row['bank_entity'] !== $this->entity || (float)$row['bank_amount'] >= 0
                || $amount === null || bccomp($amount, '0', 0) <= 0) {
                $review = true;
                continue;
            }
            $payment = ['invoice_id'=>(int)$row['invoice_id'], 'invoice_ref'=>$row['invoice_ref'],
                'payment_id'=>(int)$row['payment_id'], 'payment_ref'=>$row['payment_ref'],
                'date'=>$row['datep'], 'amount'=>$amount, 'advance'=>(int)$row['type'] === 3];
            if ($correctionFields && (int)$row['fk_statut'] === 1) {
                $correction_payments[] = $payment + ['currency'=>$invoiceCurrency,'account'=>(int)$row['bank_account'],
                    'reference'=>$row['payment_number'],'version'=>self::paymentVersion(
                        $row['payment_revision'],$row['datep'],$row['payment_number'],$row['allocation'],$row['amount'],$row['multicurrency_amount'],
                        $row['bank_id'],$row['bank_account'],$row['bank_amount'],$row['invoice_currency'],$row['allocation_currency']
                    )];
            }
            if ($invoiceCurrency !== $currency) {
                $review = true;
                continue;
            }
            $paid = bcadd($paid, $amount, 0);
            $payments[] = $payment;
        }
        $remaining = $committed && !$review ? bcsub($ordered, $paid, 0) : null;
        return compact('ordered', 'paid', 'payments', 'correction_payments', 'remaining', 'review', 'committed');
    }

    public static function paymentVersion(...$fields): string
    {
        return hash('sha256',json_encode(array_map('strval',$fields),JSON_THROW_ON_ERROR));
    }

    private function rows(string $sql): array
    {
        $result = $this->db->query($sql);
        if (!$result) throw new RuntimeException('Supplier account data unavailable.');
        $rows = [];
        while ($row = $this->db->fetch_object($result)) $rows[] = (array)$row;
        return $rows;
    }

    private function currency($code): string
    {
        $code = strtoupper(trim((string)$code)) ?: strtoupper($this->baseCurrency);
        return $code === 'RMB' ? 'CNY' : $code;
    }

    private static function minor($value): ?string
    {
        if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) return null;
        $decimal = number_format((float)$value, 2, '.', '');
        if ($decimal === '0.00') return '0';
        try {
            return CustomerAccountAmount::minor($decimal);
        } catch (InvalidArgumentException $error) {
            return null;
        }
    }
}
