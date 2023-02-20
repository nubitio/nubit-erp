<?php

namespace App\Product\Infrastructure;

use App\Category\Infrastructure\CategoryEntity;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Media\Infrastructure\MediaEntity;
use App\Product\Domain\Product;
use App\Product\Domain\ProductRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

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
        $category = $this->entityManager->getReference(CategoryEntity::class, $product->categoria->categoria_id);
        $entity->categoria = $category;
        $image = $this->entityManager->getReference(MediaEntity::class, $product->imagen->id);
        $entity->imagen = $image;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Product::class);
    }

    public function update(Product $product): Product
    {
        $entity = $this->entityManager->find(ProductEntity::class, $product->producto_id);
        $entity = DataMapper::map($product, $entity);
        $category = $this->entityManager->getReference(CategoryEntity::class, $product->categoria->categoria_id);
        $image = $this->entityManager->getReference(MediaEntity::class, $product->imagen->id);
        $entity->imagen = $image;
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