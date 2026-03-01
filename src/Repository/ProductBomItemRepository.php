<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductBomItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductBomItem>
 */
class ProductBomItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductBomItem::class);
    }

    /**
     * Return all BOM lines for a given parent product, ordered by sort_order.
     *
     * @return ProductBomItem[]
     */
    public function findByParent(string $parentProductId): array
    {
        return $this->createQueryBuilder('bom')
            ->join('bom.componentProduct', 'c')
            ->where('IDENTITY(bom.parentProduct) = :parentId')
            ->setParameter('parentId', $parentProductId)
            ->orderBy('bom.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
