<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Restaurant\Entity\RestaurantOrder;
use App\Restaurant\Repository\RestaurantOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<SendToKitchenInput, RestaurantOrder>
 */
final readonly class SendToKitchenProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantOrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): RestaurantOrder {
        $order = $this->orderRepository->find($uriVariables['id']);
        if ($order === null) {
            throw new NotFoundHttpException('Comanda no encontrada.');
        }

        $order->sendPendingItemsToKitchen();
        $this->entityManager->flush();

        return $order;
    }
}
