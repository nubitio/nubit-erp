<?php

namespace App\Infrastructure\ProductLocal;

use App\Domain\ProductLocal\IProductLocalRepository;
use App\Domain\ProductLocal\ProductLocal;
use Doctrine\ORM\EntityManagerInterface;

class ProductLocalRepository implements IProductLocalRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function add(ProductLocal $productLocal): void
    {
        $entity = new ProductLocalEntity();
        $entity->fromDomain($productLocal);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function update(ProductLocal $productLocal): void
    {
        $entity = $this->entityManager->find(ProductLocalEntity::class, ['productId' => $productLocal->getProductId(), 'localId' => $productLocal->getLocalId()]);
        $entity->fromDomain($productLocal);
        $this->entityManager->flush();
    }

    public function delete(string $productId, string $localId): void
    {
        $entity = $this->entityManager->find(ProductLocalEntity::class, ['productId' => $productId, 'localId' => $localId]);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
