<?php

namespace App\{{module}}\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\{{module}}\Domain\{{module}}RepositoryInterface;
use App\{{module}}\Domain\{{module}};
use Doctrine\ORM\EntityManagerInterface;

readonly class {{module}}Repository implements {{module}}RepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne({{entityIdParam}}): ?{{module}}
    {
        $entity = $this->entityManager->find({{module}}Entity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, {{module}}::class);
    }

    public function insert({{module}} ${{camelModule}}): {{module}}
    {
        $entity = DataMapper::map(${{camelModule}}, {{module}}Entity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, {{module}}::class);
    }

    public function update({{module}} ${{camelModule}}): {{module}}
    {
        $entity = $this->entityManager->find({{module}}Entity::class, ${{camelModule}}->{{entityId}});

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map(${{camelModule}}, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, {{module}}::class);
    }

    public function delete({{entityIdParam}}): void
    {
        $entity = $this->entityManager->find({{module}}Entity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
