<?php

namespace App\RawDocument\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\RawDocument\Domain\RawDocument;
use App\RawDocument\Domain\RawDocumentRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class RawDocumentRepository implements RawDocumentRepositoryInterface
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

    public function getOne(int $id): RawDocument
    {
        $entity = $this->entityManager->find(RawDocumentEntity::class, $id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        return DataMapper::map($entity, RawDocument::class);
    }

    public function insert(RawDocument $rawDocument): RawDocument
    {
        $entity = DataMapper::map($rawDocument, RawDocumentEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, RawDocument::class);
    }

    public function update(RawDocument $rawDocument): RawDocument
    {
        $entity = $this->entityManager->find(RawDocumentEntity::class, $rawDocument->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($rawDocument, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, RawDocument::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(RawDocumentEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
