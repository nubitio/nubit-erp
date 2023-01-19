<?php

namespace App\{{module}}\Infrastructure;

use App\{{module}}\Domain\{{module}}RepositoryInterface;
use App\{{module}}\Domain\{{module}};
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\EasyStore;
use App\Core\Infrastructure\DataLoader\Options;
use Doctrine\ORM\EntityManagerInterface;

readonly class {{module}}Repository implements {{module}}RepositoryInterface
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

        foreach ($paginator->getIterator() as ${{camelModule}}) {
            $data[] = DataMapper::map(${{camelModule}}, {{module}}::class);
        }

        return new EasyStore($paginator->getTotalItems(), $data);
    }

    public function getOne(string $id): ?{{module}}
    {
        /** @var ?{{module}}Entity $entity */
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
        $entity = DataMapper::map(${{camelModule}}, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, {{module}}::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find({{module}}Entity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}