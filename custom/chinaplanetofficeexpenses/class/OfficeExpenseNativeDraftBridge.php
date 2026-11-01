<?php
declare(strict_types=1);
require_once __DIR__.'/OfficeExpenseDraftService.php';

interface OfficeExpenseNativeDraftWriter
{
    public function createOrFind(array $draft, int $categoryId, string $taxPolicy): array;
}

/** Reload immutable scoped intent; never accept native write payloads from the browser. */
final class OfficeExpenseNativeDraftBridge
{
    private $drafts; private $writer;
    public function __construct(OfficeExpenseDraftService $drafts, OfficeExpenseNativeDraftWriter $writer)
    {
        $this->drafts=$drafts; $this->writer=$writer;
    }

    public function createDraft(string $id, int $categoryId, string $taxPolicy): array
    {
        $draft=$this->drafts->getOwn($id);
        if ($draft['kind'] !== 'expense_claim' || $draft['status'] !== 'draft') throw new DomainException('Only expense-claim drafts can become expense reports.');
        return $this->writer->createOrFind($draft, $categoryId, $taxPolicy);
    }
}
