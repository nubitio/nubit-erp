<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Purchasing\Entity\Purchase;
use App\Purchasing\Repository\PurchaseRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'purchase_summary',
    description: 'Purchase (goods receipt) summary for a date range. Defaults to the last 7 days. '
    . 'Returns count and total amount.',
    method: 'purchaseSummary',
)]
#[AsTool(
    name: 'recent_purchases',
    description: 'Latest goods receipts with supplier, totals, and status.',
    method: 'recentPurchases',
)]
final readonly class PurchaseTools
{
    public function __construct(
        private PurchaseRepository $purchaseRepository,
    ) {}

    /**
     * @return array{from: string, to: string, totalPurchases: int, totalAmount: float}
     */
    public function purchaseSummary(?string $desde = null, ?string $hasta = null): array
    {
        $to = $this->parseDate($hasta) ?? new DateTimeImmutable('today');
        $from = $this->parseDate($desde) ?? $to->modify('-6 days');

        $qb = $this->purchaseRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id) AS totalPurchases', 'COALESCE(SUM(p.grandTotal), 0) AS totalAmount')
            ->andWhere('p.issuedAt >= :from')
            ->andWhere('p.issuedAt < :toExclusive')
            ->setParameter('from', $from->setTime(0, 0))
            ->setParameter('toExclusive', $to->modify('+1 day')->setTime(0, 0));

        /** @var array{totalPurchases: mixed, totalAmount: mixed} $row */
        $row = $qb->getQuery()->getSingleResult();

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'totalPurchases' => (int) $row['totalPurchases'],
            'totalAmount' => (float) $row['totalAmount'],
        ];
    }

    /**
     * @return list<array{id: string, status: string, total: string, currency: string, issued_at: string|null, supplier: string|null}>
     */
    public function recentPurchases(int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        /** @var list<Purchase> $purchases */
        $purchases = $this->purchaseRepository
            ->createQueryBuilder('p')
            ->orderBy('p.issuedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($purchases as $purchase) {
            $items[] = [
                'id' => (string) $purchase->getId(),
                'status' => $purchase->getStatus(),
                'total' => $purchase->getGrandTotal(),
                'currency' => $purchase->getCurrency(),
                'issued_at' => $purchase->getIssuedAt()?->format(DateTimeInterface::ATOM),
                'supplier' => $purchase->getSupplier()?->getBusinessName(),
            ];
        }

        return $items;
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed instanceof DateTimeImmutable ? $parsed : null;
    }
}
