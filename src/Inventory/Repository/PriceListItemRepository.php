<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Inventory\Entity\PriceListItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PriceListItem>
 */
class PriceListItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceListItem::class);
    }

    /**
     * The special-price rows of a list, optionally limited to some products.
     *
     * @param list<string>|null $productIds
     *
     * @return array<string, PriceListItem> keyed by product id
     */
    public function byProduct(int $priceListId, ?array $productIds = null): array
    {
        $query = $this
            ->createQueryBuilder('i')
            ->addSelect('p')
            ->join('i.product', 'p')
            ->where('IDENTITY(i.priceList) = :list')
            ->setParameter('list', $priceListId);

        if ($productIds !== null) {
            $query->andWhere('p.id IN (:ids)')->setParameter('ids', $productIds, ArrayParameterType::STRING);
        }

        /** @var list<PriceListItem> $rows */
        $rows = $query->getQuery()->getResult();

        $items = [];
        foreach ($rows as $item) {
            $items[(string) $item->getProduct()->getId()] = $item;
        }

        return $items;
    }

    /**
     * Active products with what each one of them costs in a list, in name order.
     *
     * @return list<array{id: string, name: string, sku: ?string, code: ?string, basePrice: string, override: ?string}>
     */
    public function catalogueWithOverrides(int $priceListId): array
    {
        /** @var list<array{id: string, name: string, sku: ?string, code: ?string, basePrice: string, override: ?string}> */
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select(
                'p.id AS id',
                'p.name AS name',
                'p.sku AS sku',
                'p.code AS code',
                'p.salePrice AS basePrice',
                'i.price AS override',
            )
            ->from(\App\Catalog\Entity\Product::class, 'p')
            ->leftJoin(
                PriceListItem::class,
                'i',
                'WITH',
                'IDENTITY(i.product) = p.id AND IDENTITY(i.priceList) = :list',
            )
            ->where('p.isActive = true')
            ->orderBy('p.name', 'ASC')
            ->setParameter('list', $priceListId)
            ->getQuery()
            ->getArrayResult();
    }
}
