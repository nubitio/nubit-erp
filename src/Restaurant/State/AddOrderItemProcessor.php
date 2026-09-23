<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Repository\ProductRepository;
use App\Restaurant\Entity\RestaurantOrderItem;
use App\Restaurant\Repository\RestaurantOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<AddOrderItemInput, RestaurantOrderItem>
 */
final readonly class AddOrderItemProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantOrderRepository $orderRepository,
        private ProductRepository $productRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param AddOrderItemInput $data
     */
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): RestaurantOrderItem {
        $order = $this->orderRepository->find($data->orderId);
        if ($order === null) {
            throw new NotFoundHttpException('Comanda no encontrada.');
        }

        if (!$order->isOpen()) {
            throw new UnprocessableEntityHttpException('La mesa ya cerró su comanda.');
        }

        $product = $this->productRepository->find($data->productId);
        if ($product === null) {
            throw new NotFoundHttpException('Producto no encontrado.');
        }

        $item = new RestaurantOrderItem(
            order: $order,
            productId: $data->productId,
            productName: $product->getName(),
            unitPrice: $product->getSalePrice(),
            qty: $data->qty,
            notes: $data->notes,
        );
        $order->addItem($item);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }
}
