<?php

namespace App\Product\Infrastructure;

use App\Category\Infrastructure\CategoryEntity;
use App\Core\Domain\Exception\ServiceException;
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

    public function insert(Product $product): Product
    {
        $entity = DataMapper::map($product, ProductEntity::class);
        $category = $this->entityManager->getReference(CategoryEntity::class, $product->categoria?->categoria_id);
        $entity->categoria = $category;
        if ($product->imagen?->id) {
            $image = $this->entityManager->getReference(MediaEntity::class, $product->imagen->id);
            $entity->imagen = $image;
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
     */
    public function getStock(string $productId): ProductStock
    {
        /** @var false|array<string, mixed> $stock */
        $stock = $this->connection->fetchAssociative(
            'select
                        md.producto_id as id,
                        p.descripcion as name,
                        sum(if(m.tipo_id = 1, md.cantidad, md.cantidad * -1)) as stock
                    from movimiento_detalle md
                        inner join producto p on md.producto_id = p.producto_id
                        inner join movimiento m on md.movimiento_id = m.movimiento_id
                    where md.producto_id = :id and m.estado = 1',
            ['id' => $productId]);

        return DataMapper::map($stock, ProductStock::class);
    }
}
