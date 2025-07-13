<?php

namespace App\CreditNote\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\CreditNote\Domain\CreditNote;
use App\CreditNote\Domain\CreditNoteRepositoryInterface;
use App\Entity\Sale;
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

    #[\Override]
    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    #[\Override]
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
    #[\Override]
    public function insert(CreditNote $creditNote): CreditNote
    {
        $entity = DataMapper::map($creditNote, CreditNoteEntity::class);
        $entity->sale = $this->entityManager->getReference(Sale::class, $creditNote->sale?->getId());

        $entity->tipo_id = '07';
        $entity->serie = $this->creditNoteUtils->getCreditNoteSeries($creditNote->sale?->movimiento_id ?? '');
        $entity->correlativo = $this->creditNoteUtils->getCreditNoteNumber($entity->serie);
        $entity->doc_afectado = $entity->sale?->getInvoice() ?? '';
        $entity->total = $entity->sale?->getTotal() ?? 0;
        $entity->created_at = new \DateTimeImmutable();
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, CreditNote::class);
    }

    /**
     * @throws ServiceException
     * @throws ORMException
     */
    #[\Override]
    public function update(CreditNote $creditNote): CreditNote
    {
        $entity = $this->entityManager->find(CreditNoteEntity::class, $creditNote->nota_id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($creditNote, $entity);
        if ($creditNote->sale instanceof \App\Entity\Sale) {
            $entity->sale = $this->entityManager->getReference(Sale::class, $creditNote->sale->getId());
        }

        $this->entityManager->flush();

        return DataMapper::map($entity, CreditNote::class);
    }

    #[\Override]
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
