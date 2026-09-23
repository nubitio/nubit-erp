<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Restaurant\Entity\RestaurantTable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteRestaurantTableProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof RestaurantTable) {
            return;
        }

        if (!$data->isFree()) {
            throw new UnprocessableEntityHttpException('No se puede eliminar una mesa con una comanda abierta.');
        }

        $this->entityManager->remove($data);
        $this->entityManager->flush();
    }
}
