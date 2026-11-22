<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Identity\Entity\User;
use App\Sales\Domain\PriceSource;
use App\Sales\Entity\SaleItemRecord;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Sale lines of offline sales that were accepted at a price other than the one in force
 * when they reached the server ({@see PriceSource::OFFLINE_DIFF}), for someone to review.
 * Newest first. Nothing here changes a sale: the ticket was already given to the customer.
 */
final readonly class OfflinePriceReview
{
    public const int MAX_LINES = 500;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return list<array{
     *   lineId: int, saleId: string, document: string, issuedAt: ?string, seller: ?string, product: string,
     *   quantity: string, chargedPrice: string, expectedPrice: string, difference: string,
     *   differencePercent: ?string, impact: string
     * }>
     */
    public function lines(int $limit = self::MAX_LINES): array
    {
        /** @var list<array{lineId: int, saleId: string, series: ?string, number: ?string, issuedAt: ?\DateTimeInterface, seller: ?string, product: string, quantity: string, charged: string, expected: ?string}> $rows */
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select(
                'i.id AS lineId',
                's.id AS saleId',
                's.series AS series',
                's.number AS number',
                's.issuedAt AS issuedAt',
                'u.username AS seller',
                'i.nameSnapshot AS product',
                'i.qty AS quantity',
                'i.unitPrice AS charged',
                'i.listPriceSnapshot AS expected',
            )
            ->from(SaleItemRecord::class, 'i')
            ->join('i.saleRecord', 's')
            ->leftJoin(User::class, 'u', 'WITH', 'u.id = s.sellerUserId')
            ->where('i.priceSource = :source')
            ->setParameter('source', PriceSource::OFFLINE_DIFF->value)
            ->orderBy('s.issuedAt', 'DESC')
            ->addOrderBy('i.id', 'DESC')
            ->setMaxResults(min(self::MAX_LINES, max(1, $limit)))
            ->getQuery()
            ->getArrayResult();

        return array_map(self::present(...), $rows);
    }

    /**
     * @param array{lineId: int, saleId: string, series: ?string, number: ?string, issuedAt: ?\DateTimeInterface, seller: ?string, product: string, quantity: string, charged: string, expected: ?string} $row
     *
     * @return array{
     *   lineId: int, saleId: string, document: string, issuedAt: ?string, seller: ?string, product: string,
     *   quantity: string, chargedPrice: string, expectedPrice: string, difference: string,
     *   differencePercent: ?string, impact: string
     * }
     */
    public static function present(array $row): array
    {
        $charged = BigDecimal::of($row['charged']);
        $expected = BigDecimal::of($row['expected'] ?? $row['charged']);
        $difference = $charged->minus($expected);

        return [
            'lineId' => $row['lineId'],
            'saleId' => $row['saleId'],
            'document' => trim(($row['series'] ?? '') . '-' . ($row['number'] ?? ''), '-'),
            'issuedAt' => $row['issuedAt']?->format(\DATE_ATOM),
            'seller' => $row['seller'],
            'product' => $row['product'],
            'quantity' => $row['quantity'],
            'chargedPrice' => (string) $charged->toScale(2, RoundingMode::HALF_UP),
            'expectedPrice' => (string) $expected->toScale(2, RoundingMode::HALF_UP),
            'difference' => (string) $difference->toScale(2, RoundingMode::HALF_UP),
            'differencePercent' => $expected->isZero()
                ? null
                : (string) $difference->multipliedBy(100)->dividedBy($expected, 1, RoundingMode::HALF_UP),
            // What the business gained (+) or gave away (-) on the line, against the current price.
            'impact' => (string) $difference->multipliedBy($row['quantity'])->toScale(2, RoundingMode::HALF_UP),
        ];
    }
}
