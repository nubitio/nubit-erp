<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Restaurant\Entity\RestaurantOrderItem;
use App\Restaurant\Enum\RestaurantOrderItemStatus;
use App\Restaurant\Repository\RestaurantOrderItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<MarkOrderItemReadyInput, RestaurantOrderItem>
 */
final readonly class MarkOrderItemReadyProcessor implements ProcessorInterface
{
    public function __construct(
        private RestaurantOrderItemRepository $itemRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): RestaurantOrderItem {
        $item = $this->itemRepository->find($uriVariables['id']);
        if ($item === null) {
            throw new NotFoundHttpException('Ítem de comanda no encontrado.');
        }

        if ($item->getStatus() !== RestaurantOrderItemStatus::Sent) {
            throw new UnprocessableEntityHttpException('El ítem todavía no está en cocina.');
        }

        $item->markReady();
        $this->entityManager->flush();

        return $item;
    }
}
