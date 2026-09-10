<?php

declare(strict_types=1);

namespace App\Sales\Repository;

use App\Efact\Domain\Money;
use App\Sales\Domain\CustomerAccountEntryType;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Entity\CustomerPaymentApplication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerAccountEntry>
 */
class CustomerAccountEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerAccountEntry::class);
    }

    /**
     * The customer's balance right now: the running balance of their most recent
     * entry, or '0.00' if they have none yet. Ordered by id (append order), not
     * by occurred_on, so a back-dated entry never rewinds the balance.
     */
    public function currentBalance(int $customerId): string
    {
        /** @var array{runningBalance: string}|null $latest */
        $latest = $this
            ->createQueryBuilder('e')
            ->select('e.runningBalance')
            ->where('e.customerId = :customer')
            ->setParameter('customer', $customerId)
            ->orderBy('e.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $latest['runningBalance'] ?? '0.00';
    }

    /**
     * Advance the customer has left to spend: every ADVANCE received minus what
     * has already been applied to sales through `customer_payment_applications`
     * rows bound to those ADVANCE entries. Non-negative string (#382).
     */
    public function availableAdvance(int $customerId): string
    {
        $received = $this->sumCredit($customerId, CustomerAccountEntryType::ADVANCE);

        /** @var int|string|null $applied */
        $applied = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('COALESCE(SUM(a.appliedAmount), 0)')
            ->from(CustomerPaymentApplication::class, 'a')
            ->join('a.accountEntry', 'e')
            ->where('e.customerId = :customer')
            ->andWhere('e.entryType = :advance')
            ->setParameter('customer', $customerId)
            ->setParameter('advance', CustomerAccountEntryType::ADVANCE)
            ->getQuery()
            ->getSingleScalarResult();

        $available = Money::money($received)->minus(Money::money((string) ($applied ?? '0')));

        return Money::toString($available->isNegative() ? Money::money('0.00') : $available);
    }

    /**
     * Every entry for a customer up to and including $dateTo, in append order.
     * Feeds the statement (#384).
     *
     * @return list<CustomerAccountEntry>
     */
    public function timeline(int $customerId, \DateTimeImmutable $dateTo): array
    {
        /** @var list<CustomerAccountEntry> $rows */
        $rows = $this
            ->createQueryBuilder('e')
            ->where('e.customerId = :customer')
            ->andWhere('e.occurredOn <= :date_to')
            ->setParameter('customer', $customerId)
            ->setParameter('date_to', $dateTo->format('Y-m-d'))
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Whether a CHARGE entry already exists for a sale — the idempotency guard
     * for the credit-sale handler, which may see {@see \App\Sales\Event\SaleConfirmed}
     * more than once.
     */
    public function hasChargeForSale(string $saleId): bool
    {
        $count = (int) $this
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.saleId = :sale')
            ->andWhere('e.entryType = :type')
            ->setParameter('sale', $saleId)
            ->setParameter('type', CustomerAccountEntryType::CHARGE)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * The customer's ADVANCE entries that still have unallocated balance
     * (credit minus what has been applied through `customer_payment_applications`
     * rows bound to them), oldest first — the FIFO order an advance application
     * draws from (#382).
     *
     * @return list<array{entryId: int, remaining: string}>
     */
    public function advancesWithRemaining(int $customerId): array
    {
        /** @var list<array{entryId: int|string, credit: string, applied: int|string}> $rows */
        $rows = $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select('e.id AS entryId', 'e.credit AS credit', 'COALESCE(SUM(a.appliedAmount), 0) AS applied')
            ->from(CustomerAccountEntry::class, 'e')
            ->leftJoin(CustomerPaymentApplication::class, 'a', 'WITH', 'a.accountEntry = e')
            ->where('e.customerId = :customer')
            ->andWhere('e.entryType = :advance')
            ->setParameter('customer', $customerId)
            ->setParameter('advance', CustomerAccountEntryType::ADVANCE)
            ->groupBy('e.id, e.credit')
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $remaining = Money::money((string) $row['credit'])->minus(Money::money((string) $row['applied']));
            if ($remaining->isGreaterThan(0)) {
                $out[] = ['entryId' => (int) $row['entryId'], 'remaining' => Money::toString($remaining)];
            }
        }

        return $out;
    }

    private function sumCredit(int $customerId, CustomerAccountEntryType $entryType): string
    {
        /** @var int|string|null $sum */
        $sum = $this
            ->createQueryBuilder('e')
            ->select('COALESCE(SUM(e.credit), 0)')
            ->where('e.customerId = :customer')
            ->andWhere('e.entryType = :type')
            ->setParameter('customer', $customerId)
            ->setParameter('type', $entryType)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) ($sum ?? '0');
    }
}
