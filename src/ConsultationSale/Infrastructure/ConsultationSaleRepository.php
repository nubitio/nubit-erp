<?php

namespace App\ConsultationSale\Infrastructure;

use App\Consultation\Infrastructure\ConsultationEntity;
use App\ConsultationSale\Domain\ConsultationSale;
use App\ConsultationSale\Domain\ConsultationSaleRepositoryInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Sale\Infrastructure\SaleEntity;
use Doctrine\ORM\EntityManagerInterface;

readonly class ConsultationSaleRepository implements ConsultationSaleRepositoryInterface
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

    public function getOne(int $id): ?ConsultationSale
    {
        $entity = $this->entityManager->find(ConsultationSaleEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, ConsultationSale::class);
    }

    public function insert(ConsultationSale $consultationSale): ConsultationSale
    {
        $entity = DataMapper::map($consultationSale, ConsultationSaleEntity::class);
        $entity->consultation = $this->entityManager->getReference(ConsultationEntity::class, $consultationSale->consultation?->id);
        $entity->sale = $this->entityManager->getReference(SaleEntity::class, $consultationSale->sale?->movimiento_id);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, ConsultationSale::class);
    }

    public function update(ConsultationSale $consultationSale): ConsultationSale
    {
        $entity = $this->entityManager->find(ConsultationSaleEntity::class, $consultationSale->id);

        if (!$entity) {
            throw new ServiceException('Entity not found');
        }

        $entity = DataMapper::map($consultationSale, $entity);
        $entity->consultation = $this->entityManager->getReference(ConsultationEntity::class, $consultationSale->consultation?->id);
        $entity->sale = $this->entityManager->getReference(SaleEntity::class, $consultationSale->sale?->movimiento_id);
        $this->entityManager->flush();

        return DataMapper::map($entity, ConsultationSale::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(ConsultationSaleEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
