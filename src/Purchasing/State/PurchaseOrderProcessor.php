<?php

declare(strict_types=1);

namespace App\Purchasing\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Identity\Auth\OperationalBranchGuard;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use App\Purchasing\Entity\PurchaseOrder;
use App\Purchasing\Service\PurchaseOrderSequenceService;
use App\Purchasing\Service\PurchaseOrderService;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Standard write processor for PurchaseOrder (Post/Patch) — mirrors
 * App\Sales\State\SaleQuoteProcessor's shape. Unlike SaleQuoteProcessor,
 * this validates every incoming status change against the allowed-
 * transitions map (see PurchaseOrderService::ALLOWED_TRANSITIONS) —
 * closing the gap found in SaleQuoteProcessor, where Patch could write an
 * arbitrary status unguarded.
 *
 * @implements ProcessorInterface<PurchaseOrder, mixed>
 */
readonly class PurchaseOrderProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private PurchaseOrderService $purchaseOrderService,
        private PurchaseOrderSequenceService $purchaseOrderSequenceService,
        private ActiveReferenceGuard $activeReferenceGuard,
        private OperationalBranchGuard $branchGuard,
        private PurchasingBranchScope $purchasingScope,
    ) {}

    /**
     * @inheritdoc
     *
     * @param PurchaseOrder $data
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $references = [];
        if ($data->getBranch() !== null || $data->getId() !== null) {
            $references['branch'] = $data->getBranch();
        }
        if (($reference = $data->getWarehouse()) !== null) {
            $references['warehouse'] = $reference->getBranch();
        }
        $data->setBranch($this->branchGuard->requireBranch($references, $this->purchasingScope->authorizedBranchId()));

        if ($operation instanceof DeleteOperationInterface) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $isNew = $data->getId() === null;

        if ($isNew) {
            $requestedStatus = $data->getStatus();
            if (!in_array($requestedStatus, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_SENT], true)) {
                throw ValidationException::withErrors([
                    'status' => ['Una orden de compra nueva solo puede empezar en estado "draft" o "sent".'],
                ]);
            }

            $this->activeReferenceGuard->assertActiveOnCreate($data->getSupplier(), 'supplier', 'El proveedor');
            $data->setOrderNo($this->purchaseOrderSequenceService->next());
            $data->setBuyerUser($this->entityManager->getReference(User::class, $user->userId));
        } else {
            $requestedStatus = $data->getStatus();
            $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($data);
            $originalStatus = (string) ($originalData['status'] ?? $requestedStatus);

            if ($originalStatus !== $requestedStatus) {
                $data->setStatus($originalStatus);
                $this->purchaseOrderService->transitionTo($data, $requestedStatus);
            }
        }

        if ($data->getWriteItems() !== []) {
            $data->clearItems();
            foreach ($this->purchaseOrderService->buildItemsFromPayload($data->getWriteItems()) as $item) {
                $data->addItem($item);
            }
        }

        $this->purchaseOrderService->recomputeTotals($data);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
