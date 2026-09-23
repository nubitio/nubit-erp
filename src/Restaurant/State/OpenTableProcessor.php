<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Restaurant\Entity\RestaurantOrder;
use App\Restaurant\Repository\RestaurantOrderRepository;
use App\Restaurant\Repository\RestaurantTableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<OpenTableInput, RestaurantOrder>
 */
final readonly class OpenTableProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantTableRepository $tableRepository,
        private RestaurantOrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param OpenTableInput $data
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): RestaurantOrder {
        $table = $this->tableRepository->find($data->tableId);
        if ($table === null) {
            throw new NotFoundHttpException('Mesa no encontrada.');
        }

        $existingOpenOrder = $this->orderRepository->findOpenForTable($data->tableId);
        if ($existingOpenOrder !== null) {
            return $existingOpenOrder;
        }

        if (!$table->isFree()) {
            throw new UnprocessableEntityHttpException('La mesa no está libre.');
        }

        $table->markOccupied();
        $order = new RestaurantOrder($data->tableId);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }
}
