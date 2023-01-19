<?php

namespace App\Category\Infrastructure;

use App\Category\Domain\Category;
use App\Category\Domain\CategoryRepositoryInterface;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\EasyStore;
use App\Core\Infrastructure\DataLoader\Options;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

readonly class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader     $dataLoader,
    )
    {
    }

    public function getAll(Options $options): DataStoreInterface
    {
        $paginator = $this->dataLoader->load($options);

        $categories = [];

        foreach ($paginator->getIterator() as $category) {
            $categories[] = DataMapper::map($category, Category::class);
        }

        return new EasyStore($paginator->getTotalItems(), $categories);
    }

    public function findOneBy(array $filter): ?Category
    {
        /** @var ?CategoryEntity $categoryEntity */
        $entity = $this->entityManager->getRepository(CategoryEntity::class)->findOneBy($filter);

        if ($entity === null) {
            return null;
        }

        return DataMapper::map($entity, Category::class);
    }

    public function insert(Category $category): Category
    {
        $entity = DataMapper::map($category, CategoryEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Category::class);
    }

    public function update(Category $category): Category
    {
        $entity = $this->entityManager->find(CategoryEntity::class, $category->categoria_id);
        $entity = DataMapper::map($category, $entity);
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
