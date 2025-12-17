<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Assistant\ProductSearchResultDto;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return ProductSearchResultDto[]
     */
    public function searchByTerm(string $term, int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id', 'p.code', 'p.name', 'p.salePrice', 'p.stock', 'p.minStock')
            ->where('LOWER(p.name) LIKE :term OR LOWER(p.code) LIKE :term')
            ->setParameter('term', '%' . mb_strtolower($term) . '%')
            ->setMaxResults($limit)
            ->orderBy('p.name', 'ASC');

        return array_map(
            static fn (array $row): ProductSearchResultDto => new ProductSearchResultDto(
                (string) $row['id'],
                $row['code'],
                (string) $row['name'],
                (float) $row['salePrice'],
                null !== $row['stock'] ? (float) $row['stock'] : null,
                null !== $row['minStock'] ? (float) $row['minStock'] : null,
            ),
            $qb->getQuery()->getArrayResult()
        );
    }
}
