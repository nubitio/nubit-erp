<?php

namespace App\Category\Infrastructure;

use App\Category\Domain\Category;
use App\Category\Domain\CategoryRepositoryInterface;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Paginator;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader             $dataLoader,
    )
    {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        $paginator = $this->dataLoader->load($options);

        $data = [];

        foreach ($paginator->getIterator() as $category) {
            $data[] = DataMapper::map($category, Category::class);
        }

        return new Paginator($data, $paginator);
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

    public function update(Category $category): Category
    {
        $entity = $this->entityManager->find(CategoryEntity::class, $category->categoria_id);
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
