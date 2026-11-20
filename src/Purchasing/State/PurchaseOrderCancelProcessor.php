<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Purchasing\Entity\PurchaseOrder;
use DateTimeImmutable;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles DELETE /purchase-orders/{id} as a soft cancel — mirrors
 * App\Purchasing\State\PurchaseVoidProcessor's shape. A fully or partially
 * received order cannot be cancelled (goods have already moved); only
 * draft/sent orders can be.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final readonly class PurchaseOrderCancelProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
    ) {}

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof PurchaseOrder) {
            return null;
        }

        if (!in_array($data->getStatus(), [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_SENT], true)) {
            throw new UnprocessableEntityHttpException(
                'Solo se pueden cancelar órdenes de compra en estado "draft" o "sent".',
            );
        }

        $data->setStatus(PurchaseOrder::STATUS_CANCELLED);
        $data->setCancelledAt(new DateTimeImmutable());

        $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        return null;
    }
}
