<?php

namespace App\Product\Infrastructure;

use App\Category\Infrastructure\CategoryEntity;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Exception\ValidationException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Media\Infrastructure\MediaEntity;
use App\Product\Domain\Product;
use App\Product\Domain\ProductRepositoryInterface;
use App\Product\Domain\ProductStock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class ProductRepository implements ProductRepositoryInterface
{
    private const ERROR_MESSAGE = 'Ya existe un producto con el mismo nombre';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private DataLoader $dataLoader,
    ) {
    }

    /** {@inheritdoc} */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(string $id): ?Product
    {
        /** @var ?ProductEntity $entity */
        $entity = $this->entityManager->find(ProductEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Product::class);
    }

    /**
     * @throws ValidationException
     * @throws ORMException
     */
    public function insert(Product $product): Product
    {
        $entity = DataMapper::map($product, ProductEntity::class);
        $category = $this->entityManager->getReference(CategoryEntity::class, $product->categoria?->categoria_id);
        $entity->categoria = $category;
        if ($product->imagen?->id) {
            $image = $this->entityManager->getReference(MediaEntity::class, $product->imagen->id);
            $entity->imagen = $image;
        }

        $product = $this->entityManager->getRepository(ProductEntity::class)->findBy(['descripcion' => $entity->descripcion]);

        if ($product) {
            throw new ValidationException(self::ERROR_MESSAGE);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Product::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     */
    public function update(Product $product): Product
    {
        $entity = $this->entityManager->find(ProductEntity::class, $product->producto_id);

        if (!$entity) {
            throw new ServiceException('El producto no existe');
        }

        $entity = DataMapper::map($product, $entity);
        $category = $this->entityManager->getReference(CategoryEntity::class, $product->categoria?->categoria_id);

        if ($product->imagen?->id) {
            $image = $this->entityManager->getReference(MediaEntity::class, $product->imagen->id);
            $entity->imagen = $image;
        }

        $product = $this->entityManager->getRepository(ProductEntity::class)->findBy(['descripcion' => $entity->descripcion]);

        if ($product) {
            throw new ValidationException(self::ERROR_MESSAGE);
        }

        $entity->categoria = $category;
        $this->entityManager->flush();

        return DataMapper::map($entity, Product::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(ProductEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws Exception
     * @throws ServiceException
     */
    public function getStock(string $productId, string $warehouseId): ProductStock
    {
        /** @var false|array<string, string|float|int> $stock */
        $stock = $this->connection->fetchAssociative(
            'SELECT
                        p.producto_id AS id,
                        p.descripcion AS `name`,
                        SUM(IF(m.tipo_id = 1, IFNULL(md.cantidad, 0), IFNULL(md.cantidad, 0) * -1)) AS stock
                    FROM
                        producto p
                        INNER JOIN movimiento_detalle md ON p.producto_id = md.producto_id
                        INNER JOIN movimiento m ON md.movimiento_id = m.movimiento_id
                    WHERE
                        m.estado = 1
                        AND p.producto_id = :id
                        AND m.almacen_id = :warehouseId
                    GROUP BY
                        p.producto_id, p.descripcion',
            ['id' => $productId, 'warehouseId' => $warehouseId]);

        if (!$stock) {
            return new ProductStock($productId, '', 0);
        }

        return new ProductStock(
            strval($stock['id']),
            strval($stock['name']),
            floatval($stock['stock']),
        );
    }
}
