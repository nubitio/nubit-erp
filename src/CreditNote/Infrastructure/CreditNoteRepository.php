<?php

namespace App\CreditNote\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\CreditNote\Domain\CreditNote;
use App\CreditNote\Domain\CreditNoteRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class CreditNoteRepository implements CreditNoteRepositoryInterface
{
    private EntityManagerInterface $entityManager;
    private DataGridLoader $dataGridLoader;

    public function __construct(Connection $connection, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->dataGridLoader = new DataGridLoader($connection);
    }

    /** {@inheritdoc} */
    public function getAll(?array $filter = null): array
    {
        return $this->dataGridLoader->load('vw_nota', 'created_at', 'DESC', filter: $filter);
    }

    public function insert(CreditNote $creditNote): void
    {
        $entity = DataMapper::map($creditNote, CreditNoteEntity::class);
        $entity->created_at = new \DateTime();
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * @throws ServiceException
     */
    public function update(CreditNote $creditNote): void
    {
        $entity = $this->entityManager->find(CreditNoteEntity::class, $creditNote->nota_id);

        if (!$entity) {
            throw new ServiceException('La nota de crédito no existe.');
        }

        $entity = DataMapper::map($creditNote, $entity);
        $entity->updated_at = new \DateTime();
        $this->entityManager->flush();
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(CreditNoteEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
