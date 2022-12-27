<?php

namespace App\CreditNote\Infrastructure;

use App\CreditNote\Domain\CreditNote;
use App\CreditNote\Domain\CreditNoteRepositoryInterface;
use App\Infrastructure\Common\DataGridLoader;
use App\Util\DataMapper;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class CreditNoteRepository implements CreditNoteRepositoryInterface
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_nota', 'created_at', 'DESC', filter: $filter);
    }

    public function insert(CreditNote $creditNote): void
    {
        $entity = DataMapper::mapTo($creditNote, CreditNoteEntity::class);
        $entity->created_at = new \DateTime();
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(CreditNote $creditNote): void
    {
        $entity = $this->entityManager->find(CreditNoteEntity::class, $creditNote->nota_id);
        $entity = DataMapper::map($creditNote, $entity);
        $entity->updated_at = new \DateTime();
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CreditNoteEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}