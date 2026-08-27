<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\AccountsPayable\Entity\SupplierBill;
use App\AccountsPayable\Repository\SupplierBillRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'accounts_payable_aging',
    description: 'Open supplier bills grouped by aging bucket (current, 1-30, 31-60, 61+ days past due). '
    . 'Returns counts, amounts, and the most overdue bills.',
    method: 'accountsPayableAging',
)]
final readonly class AccountsPayableTools
{
    public function __construct(
        private SupplierBillRepository $supplierBillRepository,
    ) {}

    /**
     * @return array{
     *     buckets: array<string, array{count: int, amount: float}>,
     *     overdue: list<array{id: string, supplier: string, due_date: string, balance: string, status: string}>
     * }
     */
    public function accountsPayableAging(int $limit = 10): array
    {
        $limit = max(1, min(20, $limit));
        $today = new DateTimeImmutable('today');

        /** @var list<SupplierBill> $open */
        $open = $this->supplierBillRepository
            ->createQueryBuilder('b')
            ->andWhere('b.status IN (:open)')
            ->setParameter('open', [SupplierBill::PENDING, SupplierBill::PARTIAL])
            ->orderBy('b.dueDate', 'ASC')
            ->getQuery()
            ->getResult();

        $buckets = [
            'current' => ['count' => 0, 'amount' => 0.0],
            '1_30' => ['count' => 0, 'amount' => 0.0],
            '31_60' => ['count' => 0, 'amount' => 0.0],
            '61_plus' => ['count' => 0, 'amount' => 0.0],
        ];

        foreach ($open as $bill) {
            $days = (int) $bill->getDueDate()->diff($today)->format('%r%a');
            $amount = (float) $bill->getBalanceDue();
            $key = $days <= 0 ? 'current' : ($days <= 30 ? '1_30' : ($days <= 60 ? '31_60' : '61_plus'));
            $buckets[$key]['count']++;
            $buckets[$key]['amount'] += $amount;
        }

        $overdue = [];
        foreach (array_slice($open, 0, $limit) as $bill) {
            $overdue[] = [
                'id' => (string) $bill->getId(),
                'supplier' => $bill->getSupplier()->getBusinessName(),
                'due_date' => $bill->getDueDate()->format(DateTimeInterface::ATOM),
                'balance' => $bill->getBalanceDue(),
                'status' => $bill->getStatus(),
            ];
        }

        return [
            'buckets' => $buckets,
            'overdue' => $overdue,
        ];
    }
}
