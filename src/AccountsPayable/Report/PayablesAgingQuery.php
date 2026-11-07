<?php

declare(strict_types=1);

namespace App\AccountsPayable\Report;

use App\AccountsPayable\Entity\SupplierBill;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Loads a branch's open supplier bills and delegates bucketing to
 * {@see PayablesAgingCalculator}. Balances are the current ones, so the cut-off
 * date can only look forward (projected maturities), never into the past.
 *
 * @phpstan-import-type BillRow from PayablesAgingCalculator
 */
final readonly class PayablesAgingQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PayablesAgingCalculator $calculator,
    ) {}

    /**
     * @return array{detail: list<array<string, string|int>>, suppliers: list<array<string, string|int>>, totals: list<array<string, string|int>>}
     */
    public function execute(string $branchId, string $asOf): array
    {
        /** @var list<SupplierBill> $bills */
        $bills = $this->entityManager
            ->createQueryBuilder()
            ->select('b', 's')
            ->from(SupplierBill::class, 'b')
            ->join('b.supplier', 's')
            ->where('IDENTITY(b.branch) = :branch')
            ->andWhere('b.balanceDue > 0')
            ->andWhere('b.status <> :void')
            ->setParameter('branch', $branchId)
            ->setParameter('void', SupplierBill::VOID)
            ->getQuery()
            ->getResult();

        $rows = [];
        foreach ($bills as $bill) {
            $supplier = $bill->getSupplier();
            $rows[] = [
                'supplierId' => (string) $supplier->getId(),
                'ruc' => $supplier->getRuc(),
                'supplier' => $supplier->getBusinessName(),
                'currency' => $bill->getCurrency(),
                'document' => $this->documentLabel($bill),
                'issueDate' => $bill->getIssueDate()->format('Y-m-d'),
                'dueDate' => $bill->getDueDate()->format('Y-m-d'),
                'balanceDue' => $bill->getBalanceDue(),
            ];
        }

        return $this->calculator->calculate($rows, $asOf);
    }

    private function documentLabel(SupplierBill $bill): string
    {
        $series = trim((string) $bill->getSeries());
        $number = trim((string) $bill->getNumber());
        $reference = implode('-', array_filter([$series, $number], static fn(string $part): bool => $part !== ''));

        return trim(sprintf('%s %s', (string) $bill->getDocumentType(), $reference));
    }
}
