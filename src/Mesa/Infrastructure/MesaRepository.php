<?php

namespace App\Mesa\Infrastructure;

use App\Ambiente\Infrastructure\AmbienteEntity;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Mesa\Domain\Mesa;
use App\Mesa\Domain\MesaRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class MesaRepository implements MesaRepositoryInterface
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

    public function getOne(int $id): ?Mesa
    {
        $entity = $this->entityManager->find(MesaEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Mesa::class);
    }

    public function insert(Mesa $mesa): Mesa
    {
        $entity = DataMapper::map($mesa, MesaEntity::class);
        $table = $this->entityManager->getReference(AmbienteEntity::class, $mesa->ambiente?->ambiente_id);
        $entity->ambiente = $table;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Mesa::class);
    }

    public function update(Mesa $mesa): Mesa
    {
        $entity = $this->entityManager->find(MesaEntity::class, $mesa->mesa_id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($mesa, $entity);
        $table = $this->entityManager->getReference(AmbienteEntity::class, $mesa->ambiente?->ambiente_id);
        $entity->ambiente = $table;
        $this->entityManager->flush();

        return DataMapper::map($entity, Mesa::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(MesaEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
