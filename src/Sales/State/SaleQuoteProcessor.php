<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Security\Security;
use App\Sales\Entity\SaleQuote;
use App\Sales\Service\SaleQuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Standard write processor for SaleQuote (Post/Patch/Delete) — the
 * simplified analog of App\Sales\State\SaleProcessor, without any of the
 * cash/inventory/SUNAT entanglement since quotes never touch those.
 *
 * @implements ProcessorInterface<SaleQuote, mixed>
 */
readonly class SaleQuoteProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     * @param ProcessorInterface<mixed, mixed> $removeProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private SaleQuoteService $saleQuoteService,
    ) {}

    /**
     * @inheritdoc
     *
     * @param SaleQuote $data
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($operation instanceof DeleteOperationInterface) {
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $isNew = $data->getId() === null;

        if ($isNew) {
            $data->setCreatedBy($user->userId);
            $data->setSellerUserId($user->userId);
        } else {
            $data->setUpdatedBy($user->userId);

            // Guard against a Patch silently writing an arbitrary status —
            // validate the transition against SaleQuoteService's allowed
            // transitions map, same as PurchaseOrderProcessor does.
            $requestedStatus = $data->getStatus();
            $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($data);
            $originalStatus = (string) ($originalData['status'] ?? $requestedStatus);

            if ($originalStatus !== $requestedStatus) {
                $data->setStatus($originalStatus);
                $this->saleQuoteService->transitionTo($data, $requestedStatus);
            }
        }

        if ($data->getWriteItems() !== []) {
            $data->clearItems();
            foreach ($this->saleQuoteService->buildItemsFromPayload($data->getWriteItems()) as $item) {
                $data->addItem($item);
            }
        }

        $this->saleQuoteService->recomputeTotals($data);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
