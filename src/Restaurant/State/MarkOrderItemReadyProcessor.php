<?php

declare(strict_types=1);

namespace App\Restaurant\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Restaurant\Entity\RestaurantOrderItem;
use App\Restaurant\Repository\RestaurantOrderItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        $item->markReady();
        $this->entityManager->flush();

        return $item;
    }
}
