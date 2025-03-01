<?php

namespace App\State\CashRegister;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\CashMovement;
use Doctrine\ORM\EntityManagerInterface;

readonly class CashShiftMovementsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        $cashShiftId = $uriVariables['id'];

        return $this->entityManager->getRepository(CashMovement::class)->findBy(['cashShift' => $cashShiftId]);
    }
}
