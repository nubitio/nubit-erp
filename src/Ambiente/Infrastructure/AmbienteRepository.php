<?php

namespace App\Ambiente\Infrastructure;

use App\Ambiente\Domain\Ambiente;
use App\Ambiente\Domain\AmbienteRepositoryInterface;
use App\Branch\Infrastructure\BranchEntity;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class AmbienteRepository implements AmbienteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(int $id): ?Ambiente
    {
        $entity = $this->entityManager->find(AmbienteEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Ambiente::class);
    }

    public function insert(Ambiente $ambiente): Ambiente
    {
        $entity = DataMapper::map($ambiente, AmbienteEntity::class);
        $branch = $this->entityManager->getReference(BranchEntity::class, $ambiente->local?->local_id);
        $entity->local = $branch;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Ambiente::class);
    }

    public function update(Ambiente $ambiente): Ambiente
    {
        $entity = $this->entityManager->find(AmbienteEntity::class, $ambiente->ambiente_id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($ambiente, $entity);
        $branch = $this->entityManager->getReference(BranchEntity::class, $ambiente->local?->local_id);
        $entity->local = $branch;
        $this->entityManager->flush();

        return DataMapper::map($entity, Ambiente::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(AmbienteEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
