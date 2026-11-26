<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Accounting\Message\EconomicEvent;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Identity\Auth\OperationalBranchGuard;
use App\Identity\Entity\User;
use App\Purchasing\Entity\Purchase;
use App\Purchasing\Service\PurchaseOrderReceiptService;
use App\Purchasing\Service\PurchaseReceiptSequenceService;
use App\Purchasing\Service\PurchaseSupplierDocumentValidator;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class PurchaseProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private OperationalBranchGuard $branchGuard,
        private PurchaseInventoryService $purchaseInventoryService,
        private PurchaseReceiptSequenceService $purchaseReceiptSequenceService,
        private PurchaseSupplierDocumentValidator $purchaseSupplierDocumentValidator,
        private ActiveReferenceGuard $activeReferenceGuard,
        private PurchaseOrderReceiptService $purchaseOrderReceiptService,
        private ?MessageBusInterface $messageBus = null,
        private ?PurchasingBranchScope $purchasingScope = null,
    ) {}

    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isNewPurchase = $data instanceof Purchase && $data->getId() === null;

        // Prevent editing a voided purchase
        if ($data instanceof Purchase && !$isNewPurchase && $data->getStatus() === 'VOIDED') {
            throw new UnprocessableEntityHttpException('No se puede modificar una compra anulada.');
        }

        // Assign UUID v7 on new entity (when $data->getId() is null)
        if ($isNewPurchase) {
            $data->setId(Uuid::v7()->toRfc4122());
        }

        // Auto-populate fields that the form does not send
        if ($data instanceof Purchase) {
            $references = [];
            if ($data->getBranch() !== null || !$isNewPurchase) {
                $references['branch'] = $data->getBranch();
            }
            if ($data->getWarehouse() !== null) {
                $references['warehouse'] = $data->getWarehouse()->getBranch();
            }
            if ($data->getPurchaseOrder() !== null) {
                $references['purchaseOrder'] = $data->getPurchaseOrder()->getBranch();
            }
            $data->setBranch($this->branchGuard->requireBranch($references, $this->purchasingScope?->authorizedBranchId()));

            if ($isNewPurchase) {
                $this->purchaseSupplierDocumentValidator->assertRequiredForCreate($data);
                if ($data->getReceiptNo() === null) {
                    $data->setReceiptNo($this->purchaseReceiptSequenceService->next());
                }
                $this->activeReferenceGuard->assertActiveOnCreate($data->getSupplier(), 'supplier', 'El proveedor');
                $this->activeReferenceGuard->assertActiveOnCreate($data->getWarehouse(), 'warehouse', 'El almacén');
            }
            // 1. Set buyerUser from the authenticated user
            if ($data->getBuyerUser() === null) {
                $user = $this->security->getUser();
                if ($user instanceof User) {
                    $data->setBuyerUser($user);
                }
            }

            // 2. Default issuedAt to now if not provided
            if ($data->getIssuedAt() === null) {
                $data->setIssuedAt(new \DateTime());
            }

            // 4. Ensure each item's owning-side back-reference is set before Doctrine persist.
            //    API Platform may denormalise items directly into the collection without calling
            //    addItem(), so the ManyToOne foreign key would be left null.
            $lineNo = 1;
            foreach ($data->getItems() as $item) {
                if ($item->getPurchase() !== $data) {
                    $item->setPurchase($data);
                }
                if ($item->getLineNo() <= 0) {
                    $item->setLineNo($lineNo);
                }
                ++$lineNo;
            }
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        if ($isNewPurchase) {
            $this->purchaseInventoryService->recordPurchaseReceipt($data);
            if ($data->getPurchaseOrder() !== null) {
                $this->purchaseOrderReceiptService->applyReceipt($data);
            }
            $issued = $data->getIssuedAt() ?? new \DateTime();
            $this->messageBus?->dispatch(new EconomicEvent(
                source: EconomicEvent::SOURCE_PURCHASE,
                sourceId: (string) $data->getId(),
                kind: EconomicEvent::PURCHASE,
                occurredOn: $issued->format('Y-m-d'),
                description: 'Compra '
                . ($data->getReceiptNo() !== null ? (string) $data->getReceiptNo() : (string) $data->getId()),
                total: $data->getGrandTotal(),
                tax: $data->getTaxTotal(),
                currency: $data->getCurrency(),
            ));
        }

        return $result;
    }
}
