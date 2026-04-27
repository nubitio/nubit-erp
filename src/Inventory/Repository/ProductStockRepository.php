<?php

declare(strict_types=1);

namespace App\Inventory\Repository;

use App\Entity\Product;
use App\Inventory\Entity\ProductStock;
use App\Inventory\Entity\Warehouse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductStock>
 *
 * @method ProductStock|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductStock|null findOneBy(array<string, mixed> $criteria, array<string, mixed>|null $orderBy = null)
 * @method ProductStock[]    findAll()
 * @method ProductStock[]    findBy(array<string, mixed> $criteria, array<string, mixed>|null $orderBy = null, $limit = null, $offset = null)
 */
class ProductStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductStock::class);
    }

    /**
     * Find stock by product and warehouse
     */
    public function findByProductAndWarehouse(Product $product, Warehouse $warehouse): ?ProductStock
    {
        return $this->findOneBy([
            'product' => $product,
            'warehouse' => $warehouse,
        ]);
    }

    /**
     * Find all stock for a product across warehouses
     *
     * @return ProductStock[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->findBy(['product' => $product]);
    }

    /**
     * Find all stock for a warehouse
     *
     * @return ProductStock[]
     */
    public function findByWarehouse(Warehouse $warehouse): array
    {
        return $this->findBy(['warehouse' => $warehouse]);
    }

    /**
     * Find products below minimum stock threshold by warehouse
     *
     * @return ProductStock[]
     */
    public function findBelowMinimumStockByWarehouse(Warehouse $warehouse): array
    {
        return $this->createQueryBuilder('ps')
            ->where('ps.warehouse = :warehouse')
            ->andWhere('ps.stock <= ps.minStock')
            ->setParameter('warehouse', $warehouse)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all products below minimum stock threshold across all warehouses
     *
     * @return ProductStock[]
     */
    public function findAllBelowMinimumStock(): array
    {
        return $this->createQueryBuilder('ps')
            ->where('ps.stock <= ps.minStock')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total stock for a product across all warehouses
     */
    public function getTotalStockForProduct(Product $product): float
    {
        $result = $this->createQueryBuilder('ps')
            ->select('SUM(ps.stock) as totalStock')
            ->where('ps.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }
}
