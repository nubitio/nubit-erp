<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Sales\Entity\SaleQuote;
use App\Sales\Repository\SaleQuoteRepository;
use DateTimeInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'quote_search',
    description: 'Search recent sale quotes by customer name fragment or list the latest quotes. '
    . 'Returns status, totals, and validity dates.',
    method: 'quoteSearch',
)]
final readonly class QuoteTools
{
    public function __construct(
        private SaleQuoteRepository $saleQuoteRepository,
    ) {}

    /**
     * @return list<array{
     *     id: string,
     *     status: string,
     *     total: string,
     *     currency: string,
     *     issued_at: string,
     *     valid_until: string|null,
     *     customer: string|null
     * }>
     */
    public function quoteSearch(?string $term = null, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $qb = $this->saleQuoteRepository->createQueryBuilder('q')
            ->leftJoin('q.customer', 'c')
            ->orderBy('q.issuedAt', 'DESC')
            ->setMaxResults($limit);

        if ($term !== null && trim($term) !== '') {
            $qb->andWhere('c.businessName LIKE :term OR c.ruc LIKE :term')
                ->setParameter('term', '%' . trim($term) . '%');
        }

        /** @var list<SaleQuote> $quotes */
        $quotes = $qb->getQuery()->getResult();

        $items = [];
        foreach ($quotes as $quote) {
            $customer = $quote->getCustomer();
            $items[] = [
                'id' => (string) $quote->getId(),
                'status' => $quote->getStatus(),
                'total' => $quote->getGrandTotal(),
                'currency' => $quote->getCurrency(),
                'issued_at' => $quote->getIssuedAt()->format(DateTimeInterface::ATOM),
                'valid_until' => $quote->getValidUntil()?->format(DateTimeInterface::ATOM),
                'customer' => $customer?->getBusinessName(),
            ];
        }

        return $items;
    }
}
