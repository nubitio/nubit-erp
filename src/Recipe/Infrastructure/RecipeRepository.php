<?php

namespace App\Recipe\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Product\Infrastructure\ProductEntity;
use App\Recipe\Domain\RecipeRepositoryInterface;
use App\Recipe\Domain\Recipe;
use App\SaleDetail\Infrastructure\SaleDetailEntity;
use App\Warehouse\Infrastructure\WarehouseEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class RecipeRepository implements RecipeRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(int $id): ?Recipe
    {
        $entity = $this->entityManager->find(RecipeEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Recipe::class);
    }

    public function insert(Recipe $recipe): Recipe
    {
        $entity = DataMapper::map($recipe, RecipeEntity::class);
        $producto = $this->entityManager->getReference(ProductEntity::class, $recipe->producto?->producto_id);
        $entity->producto = $producto;
        /** @var SaleDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->product = $entity;
            $detail->product = $this->entityManager->getReference(ProductEntity::class, $detail->producto?->producto_id);
            $subtotal += $detail->precio_total;

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Recipe::class);
    }

    public function update(Recipe $recipe): Recipe
    {
        $entity = $this->entityManager->find(RecipeEntity::class, $recipe->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($recipe, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Recipe::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(RecipeEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}