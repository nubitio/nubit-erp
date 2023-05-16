<?php

namespace App\Product\Infrastructure;

use App\Category\Infrastructure\CategoryEntity;
use App\Core\Domain\Exception\InsertEntityException;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Media\Infrastructure\MediaEntity;
use App\Product\Domain\Product;
use App\Product\Domain\ProductRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

    /** @inheritdoc */
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
     * @throws ServiceException
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
            throw new InsertEntityException('Ya existe un producto con el mismo nombre');
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
            throw new InsertEntityException('Ya existe un producto con el mismo nombre');
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
}