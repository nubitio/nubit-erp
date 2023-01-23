<?php

namespace App\Product\Infrastructure;

use App\Category\Infrastructure\CategoryEntity;
use App\Product\Domain\ProductRepositoryInterface;
use App\Product\Domain\Product;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\EasyStore;
use App\Core\Infrastructure\DataLoader\Options;
use Doctrine\ORM\EntityManagerInterface;

readonly class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    )
    {
    }

    public function getAll(Options $options): DataStoreInterface
    {
        $paginator = $this->dataLoader->load($options);

        $data = [];

        foreach ($paginator->getIterator() as $product) {
            $data[] = DataMapper::map($product, Product::class);
        }

        return new EasyStore($paginator->getTotalItems(), $data);
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
        $category = $this->entityManager->getReference(CategoryEntity::class, $product->categoria->categoria_id);
        $entity->categoria = $category;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Product::class);
    }

    public function update(Product $product): Product
    {
        $entity = $this->entityManager->find(ProductEntity::class, $product->producto_id);
        $entity = DataMapper::map($product, $entity);
        $category = $this->entityManager->getReference(CategoryEntity::class, $product->categoria->categoria_id);
        $entity->categoria = $category;
        $this->entityManager->flush();

        return DataMapper::map($entity, Product::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(ProductEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}