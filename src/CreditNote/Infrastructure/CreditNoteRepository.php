<?php

namespace App\CreditNote\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\CreditNote\Domain\CreditNote;
use App\CreditNote\Domain\CreditNoteRepositoryInterface;
use App\Sale\Infrastructure\SaleEntity;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

readonly class CreditNoteRepository implements CreditNoteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
        private CreditNoteUtils $creditNoteUtils,
    ) {
    }

    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }

    public function getOne(string $id): ?CreditNote
    {
        $entity = $this->entityManager->find(CreditNoteEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, CreditNote::class);
    }

    /**
     * @throws ServiceException
     * @throws Exception|ORMException
     */
    public function insert(CreditNote $creditNote): CreditNote
    {
        $entity = DataMapper::map($creditNote, CreditNoteEntity::class);
        $entity->sale = $this->entityManager->getReference(SaleEntity::class, $creditNote->sale?->movimiento_id);

        $entity->tipo_id = '07';
        $entity->serie = $this->creditNoteUtils->getCreditNoteSeries($creditNote->sale?->movimiento_id ?? '');
        $entity->correlativo = $this->creditNoteUtils->getCreditNoteNumber($entity->serie);
        $entity->doc_afectado = $entity->sale?->invoice ?? '';
        $entity->total = $entity->sale?->total ?? 0;
        $entity->created_at = new \DateTimeImmutable();
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, CreditNote::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     */
    public function update(CreditNote $creditNote): CreditNote
    {
        $entity = $this->entityManager->find(CreditNoteEntity::class, $creditNote->nota_id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($creditNote, $entity);
        $entity->sale = $this->entityManager->getReference(SaleEntity::class, $creditNote->sale?->movimiento_id);
        $this->entityManager->flush();

        return DataMapper::map($entity, CreditNote::class);
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
