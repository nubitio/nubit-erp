<?php

namespace App\Recipe\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Product\Infrastructure\ProductEntity;
use App\Recipe\Domain\Recipe;
use App\Recipe\Domain\RecipeRepositoryInterface;
use App\RecipeDetail\Infrastructure\RecipeDetailEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class RecipeRepository implements RecipeRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(int $id): ?Recipe
    {
        $entity = $this->entityManager->find(RecipeEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Recipe::class);
    }

    /**
     * @throws ORMException
     */
    public function insert(Recipe $recipe): Recipe
    {
        $entity = DataMapper::map($recipe, RecipeEntity::class);

        $entity->product = $this->entityManager->getReference(ProductEntity::class, $recipe->product?->producto_id);

        /** @var RecipeDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->recipe = $entity;
            $detail->material = $this->entityManager->getReference(ProductEntity::class, $detail->material?->producto_id);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Recipe::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     */
    public function update(Recipe $recipe): Recipe
    {
        $entity = $this->entityManager->find(RecipeEntity::class, $recipe->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($recipe, $entity);

        $entity->product = $this->entityManager->getReference(ProductEntity::class, $recipe->product?->producto_id);

        $this->deleteDetails($entity);
        /** @var RecipeDetailEntity $detail */
        foreach ($entity->details as $detail) {
            $detail->recipe = $entity;
            $detail->material = $this->entityManager->getReference(ProductEntity::class, $detail->material?->producto_id);
        }
        $this->entityManager->flush();

        return DataMapper::map($entity, Recipe::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(RecipeEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->deleteDetails($entity);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    private function deleteDetails(RecipeEntity $entity): void
    {
        $this->entityManager->createQuery('DELETE FROM App\RecipeDetail\Infrastructure\RecipeDetailEntity r WHERE r.recipe = :recipe')
            ->setParameter('recipe', $entity)
            ->execute();
    }
}
