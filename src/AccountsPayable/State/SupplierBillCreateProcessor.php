<?php

declare(strict_types=1);

namespace App\AccountsPayable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Accounting\Message\EconomicEvent;
use App\AccountsPayable\Entity\SupplierBill;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Persists a direct supplier bill and emits its economic fact. Bills linked to
 * a goods receipt are already posted by Purchasing and must not be accrued a
 * second time here.
 *
 * @implements ProcessorInterface<SupplierBill, SupplierBill>
 */
final readonly class SupplierBillCreateProcessor implements ProcessorInterface
{
    /** @param ProcessorInterface<mixed, mixed> $persistProcessor */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private ?MessageBusInterface $messageBus = null,
    ) {}

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use (
            $data,
            $operation,
            $uriVariables,
            $context,
        ): mixed {
            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

            if ($data->getPurchase() === null) {
                $this->messageBus?->dispatch(new EconomicEvent(
                    source: EconomicEvent::SOURCE_SUPPLIER_BILL,
                    sourceId: (string) $data->getId(),
                    kind: EconomicEvent::SUPPLIER_BILL,
                    occurredOn: $data->getIssueDate()->format('Y-m-d'),
                    description: 'Factura de proveedor ' . ($data->getNumber() ?? (string) $data->getId()),
                    total: $data->getAmount(),
                    currency: $data->getCurrency(),
                ));
            }

            return $result;
        });
    }
}
