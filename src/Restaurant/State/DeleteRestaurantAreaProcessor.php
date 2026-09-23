<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Restaurant\Entity\RestaurantArea;
use App\Restaurant\Repository\RestaurantTableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteRestaurantAreaProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantTableRepository $tableRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof RestaurantArea) {
            return;
        }

        $hasTables = $this->tableRepository->count(['area' => $data]) > 0;
        if ($hasTables) {
            throw new UnprocessableEntityHttpException('No se puede eliminar un ambiente que todavía tiene mesas.');
        }

        $this->entityManager->remove($data);
        $this->entityManager->flush();
    }
}
