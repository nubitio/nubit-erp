<?php

namespace App\Category\Infrastructure;

use App\Category\Domain\Category;
use App\Category\Domain\CategoryRepositoryInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CategoryRepository implements CategoryRepositoryInterface
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

    public function getOne(string $id): ?Category
    {
        /** @var ?CategoryEntity $entity */
        $entity = $this->entityManager->getRepository(CategoryEntity::class)->find($id);

        if ($entity === null) {
            return null;
        }

        return DataMapper::map($entity, Category::class);
    }

    public function insert(Category $category): Category
    {
        $entity = DataMapper::map($category, CategoryEntity::class);
        if ($category->parent !== null) {
            $parent = $this->entityManager->getReference(CategoryEntity::class, $category->parent->categoria_id);
            $entity->parent = $parent;
        }
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Category::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     */
    public function update(Category $category): Category
    {
        $entity = $this->entityManager->find(CategoryEntity::class, $category->categoria_id);

        if ($entity === null) {
            throw new ServiceException('La categoría no existe.');
        }

        $entity = DataMapper::map($category, $entity);
        if ($category->parent !== null) {
            $parent = $this->entityManager->getReference(CategoryEntity::class, $category->parent->categoria_id);
            $entity->parent = $parent;
        } else {
            $entity->parent = null;
        }
        $this->entityManager->flush();

        return DataMapper::map($entity, Category::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CategoryEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
