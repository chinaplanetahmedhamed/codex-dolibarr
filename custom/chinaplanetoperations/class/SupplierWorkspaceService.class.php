<?php
declare(strict_types=1);

require_once __DIR__.'/SupplierAccountService.class.php';

/** Supplier navigation over native third parties and purchase orders; no financial amounts here. */
final class SupplierWorkspaceService
{
    private InternalTaskWorkspaceService $scope;

    public function __construct(private $db, private $actor, private int $entity)
    {
        $this->scope = new InternalTaskWorkspaceService($db, $actor, $entity);
    }

    public static function enabled(): bool
    {
        return !empty($GLOBALS['conf']->global->CPO_SUPPLIER_WORKSPACES_ENABLED);
    }

    public function canBrowse(): bool
    {
        return self::enabled() && !empty($this->actor->id) && empty($this->actor->socid)
            && empty($this->actor->fk_soc)
            && (!isset($this->actor->statut) || (int)$this->actor->statut === 1)
            && $this->scope->customerSql('s') !== '1=0'
            && (!empty($this->actor->admin) || $this->actor->hasRight('fournisseur', 'commande', 'lire')
                || $this->actor->hasRight('supplier_order', 'lire'));
    }

    public function canEnable(): bool
    {
        return $this->canBrowse() && $this->scope->canCreate();
    }

    private function workspaceExistsSql(): string
    {
        return 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'cpo_supplier_workspace sw WHERE sw.entity=s.entity AND sw.fk_supplier=s.rowid)';
    }

    public function supplier(int $supplier): array
    {
        $this->requireBrowse();
        $workspace = $this->findWorkspace($supplier);
        if (!$workspace) throw new InternalTaskAccessException('Supplier access denied.');
        return $workspace;
    }

    public function findWorkspace(int $supplier): ?array
    {
        if (!$this->canBrowse()) return null;
        $p = MAIN_DB_PREFIX;
        $rows = $this->rows("SELECT s.rowid,s.nom FROM {$p}societe s WHERE s.fournisseur=1 AND s.rowid=$supplier AND ".$this->scope->customerSql('s')." AND ".$this->workspaceExistsSql());
        return $rows[0] ?? null;
    }

    public function suppliers(string $query, int $page = 1): array
    {
        $this->requireBrowse();
        return $this->searchSuppliers($query, $page, true);
    }

    public function supplierOptions(string $query): array
    {
        if (!$this->canEnable()) throw new InternalTaskAccessException('Supplier workspace activation denied.');
        return $this->searchSuppliers($query, 1, false);
    }

    public function enableSupplier($value): array
    {
        if (!$this->canEnable()) throw new InternalTaskAccessException('Supplier workspace activation denied.');
        $supplier = (is_int($value) || is_string($value))
            ? filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>2147483647]]) : false;
        if ($supplier === false) throw new RuntimeException('Select a valid supplier.');
        $p = MAIN_DB_PREFIX;
        $sql = "INSERT INTO {$p}cpo_supplier_workspace (entity,fk_supplier)
            SELECT s.entity,s.rowid FROM {$p}societe s WHERE s.rowid=$supplier AND s.fournisseur=1 AND "
            .$this->scope->customerSql('s').' ON DUPLICATE KEY UPDATE fk_supplier=VALUES(fk_supplier)';
        if (!$this->db->query($sql)) throw new RuntimeException('Unable to enable supplier workspace.');
        $this->supplier($supplier);
        return ['id'=>$supplier];
    }

    private function searchSuppliers(string $query, int $page, bool $enabled): array
    {
        $p = MAIN_DB_PREFIX;
        $query = trim(mb_substr($query, 0, 100));
        $filter = $query === '' ? '' : " AND s.nom LIKE '%".$this->db->escape(str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query))."%' ESCAPE '!'";
        $offset = (max(1, min($page, 10000)) - 1) * 50;
        return $this->rows("SELECT s.rowid,s.nom FROM {$p}societe s WHERE s.fournisseur=1 AND "
            .$this->scope->customerSql('s').' AND '.($enabled ? '' : 'NOT ').$this->workspaceExistsSql()."$filter ORDER BY s.nom,s.rowid LIMIT 51 OFFSET $offset");
    }

    public function orders(int $supplier, int $page = 1): array
    {
        $this->supplier($supplier);
        $p = MAIN_DB_PREFIX;
        $offset = (max(1, min($page, 10000)) - 1) * 50;
        $admin = !empty($this->actor->admin);
        $member = $admin ? '1=1' : "EXISTS (SELECT 1 FROM {$p}cpo_board_member bm WHERE bm.entity=m.entity
            AND bm.fk_board=m.fk_board AND bm.fk_user=".(int)$this->actor->id.')';
        // For staff, only existing boards they can access. Admins may create boards for linked orders.
        $visible = InternalTaskBoardLifecycle::visibleSql('m.entity', 'm.fk_board');
        $accessible = $admin ? '1=1' : "m.fk_board IS NOT NULL AND m.fk_supplier=o.fk_soc AND m.fk_project=o.fk_projet AND ($member) AND ($visible)";
        return $this->rows($this->orderSelect().", CASE WHEN m.fk_supplier=o.fk_soc AND m.fk_project=o.fk_projet THEN m.fk_board END AS board_id,
            CASE WHEN m.fk_board IS NOT NULL AND (m.fk_supplier<>o.fk_soc OR m.fk_project<>o.fk_projet) THEN 1 ELSE 0 END AS board_context_changed,
            CASE WHEN m.fk_board IS NULL AND o.fk_statut IN (2,3,4,5) THEN 1 ELSE 0 END AS can_create_board,
            CASE WHEN m.fk_board IS NOT NULL AND NOT ($visible) THEN 1 ELSE 0 END AS board_deleted"
            .$this->orderFrom()." LEFT JOIN {$p}cpo_supplier_board m ON m.entity=o.entity AND m.fk_supplier_order=o.rowid
            WHERE o.fk_soc=$supplier AND ($accessible) AND ".$this->orderScope()." ORDER BY o.rowid DESC LIMIT 51 OFFSET $offset");
    }

    /** Read only after the normal Board ACL has granted access. */
    public function context(int $board): ?array
    {
        if (!$this->canBrowse()) return null;
        $p = MAIN_DB_PREFIX;
        $rows = $this->rows($this->orderSelect().", CASE WHEN ".$this->workspaceExistsSql()." THEN 1 ELSE 0 END AS workspace_enabled".$this->orderFrom()." JOIN {$p}cpo_supplier_board m ON m.entity=o.entity AND m.fk_supplier_order=o.rowid
            WHERE m.fk_board=$board AND m.fk_supplier=o.fk_soc AND m.fk_project=o.fk_projet AND ".$this->orderScope());
        return $rows[0] ?? null;
    }

    public function mappingSql(string $entity, string $board): string
    {
        return 'EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX."cpo_supplier_board sb WHERE sb.entity=$entity AND sb.fk_board=$board)";
    }

    public function assertMove(int $source, int $target): void
    {
        if ($source === $target) return;
        $p = MAIN_DB_PREFIX;
        if ($this->rows("SELECT fk_board FROM {$p}cpo_supplier_board WHERE entity=".$this->entity." AND fk_board IN ($source,$target)")) {
            throw new InternalTaskAccessException('Lists stay within their supplier order board.');
        }
    }

    /** Called inside the existing Boards write transaction. Does not change the native order. */
    public function lockOrder(int $order): array
    {
        if (!$this->canEnable()) throw new InternalTaskAccessException('Supplier board creation denied.');
        $p = MAIN_DB_PREFIX;
        if (!$this->rows("SELECT o.rowid FROM {$p}commande_fournisseur o WHERE o.entity=".$this->entity." AND o.rowid=$order FOR UPDATE")) {
            throw new InternalTaskAccessException('Purchase order access denied.');
        }
        $orders = $this->rows($this->orderSelect().$this->orderFrom()." WHERE o.rowid=$order AND o.fk_statut IN (2,3,4,5) AND ".$this->orderScope()." AND ".$this->workspaceExistsSql());
        if (!$orders) {
            throw new InternalTaskAccessException('An approved purchase order linked to an accessible project is required.');
        }
        if ($this->rows("SELECT fk_board FROM {$p}cpo_supplier_board WHERE entity=".$this->entity." AND fk_supplier_order=$order")) {
            throw new InternalTaskConflictException('This purchase order already has a board.');
        }
        return $orders[0];
    }

    public function linkLockedOrder(array $order, int $board): void
    {
        $p = MAIN_DB_PREFIX;
        $id = (int)$order['rowid'];
        $supplier = (int)$order['supplier_id'];
        $project = (int)$order['project_id'];
        if (!$this->db->query("INSERT INTO {$p}cpo_supplier_board (entity,fk_supplier_order,fk_board,fk_supplier,fk_project) VALUES (".$this->entity.",$id,$board,$supplier,$project)")) {
            throw new RuntimeException('Unable to link supplier board.');
        }
    }

    private function orderSelect(): string
    {
        return 'SELECT o.rowid,o.ref,o.fk_soc AS supplier_id,s.nom AS supplier_name,p.rowid AS project_id,p.ref AS project_ref,
            p.title AS project_title,p.fk_soc AS customer_id,c.nom AS customer_name';
    }

    private function orderFrom(): string
    {
        $p = MAIN_DB_PREFIX;
        return " FROM {$p}commande_fournisseur o
            JOIN {$p}societe s ON s.entity=o.entity AND s.rowid=o.fk_soc
            JOIN {$p}projet p ON p.entity=o.entity AND p.rowid=o.fk_projet
            JOIN {$p}societe c ON c.entity=p.entity AND c.rowid=p.fk_soc";
    }

    private function orderScope(): string
    {
        return 'o.entity='.$this->entity.' AND s.fournisseur=1 AND '.$this->scope->customerSql('s').' AND '.$this->scope->projectSql('p');
    }

    private function requireBrowse(): void
    {
        if (!$this->canBrowse()) throw new InternalTaskAccessException('Supplier workspace access denied.');
    }

    private function rows(string $sql): array
    {
        $result = $this->db->query($sql);
        if (!$result) throw new RuntimeException('Supplier workspaces are unavailable.');
        $rows = [];
        while ($row = $this->db->fetch_object($result)) $rows[] = (array)$row;
        return $rows;
    }
}
