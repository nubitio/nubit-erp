<?php

namespace App\Operation\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Operation\Domain\Operation;
use App\Operation\Domain\OperationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class OperationRepository implements OperationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    /** {@inheritdoc} */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(string $id): ?Operation
    {
        /** @var ?OperationEntity $entity */
        $entity = $this->entityManager->find(OperationEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Operation::class);
    }

    public function insert(Operation $operation): Operation
    {
        $entity = DataMapper::map($operation, OperationEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Operation::class);
    }

    /**
     * @throws ServiceException
     */
    public function update(Operation $operation): Operation
    {
        $entity = $this->entityManager->find(OperationEntity::class, $operation->operacion_id);

        if (!$entity) {
            throw new ServiceException('La operación no existe.');
        }

        $entity = DataMapper::map($operation, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Operation::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(OperationEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
