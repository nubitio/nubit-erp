<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Restaurant\Entity\RestaurantOrder;
use App\Restaurant\Repository\RestaurantOrderRepository;
use App\Restaurant\Repository\RestaurantTableRepository;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<CloseOrderInput, RestaurantOrder>
 */
final readonly class CloseOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantOrderRepository $orderRepository,
        private RestaurantTableRepository $tableRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param CloseOrderInput $data
     */
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

        if (!$order->isOpen() && $order->getSaleId() === $data->saleId) {
            return $order;
        }

        try {
            $order->close($data->saleId);
        } catch (DomainException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        $table = $this->tableRepository->find($order->getTableId());
        $table?->markFree();

        $this->entityManager->flush();

        return $order;
    }
}
