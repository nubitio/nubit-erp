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
use Nubit\Platform\Exception\ValidationException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
            $this->assertCreatableStatus($data);
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
                $this->assertSendPermission($data, $originalStatus);
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

    /**
     * Creation stays on the transition graph: draft, or sent when the caller
     * also holds sale_quotes.send. converted/accepted/expired are not
     * client-writable here — conversion is the convert operation.
     */
    private function assertCreatableStatus(SaleQuote $quote): void
    {
        $status = $quote->getStatus();
        if ($status === SaleQuote::STATUS_DRAFT) {
            return;
        }

        if ($status === SaleQuote::STATUS_SENT) {
            $this->assertSendPermission($quote, SaleQuote::STATUS_DRAFT);

            return;
        }

        throw ValidationException::withErrors([
            'status' => ['Una cotización nueva solo puede empezar en borrador o enviada.'],
        ]);
    }

    private function assertSendPermission(SaleQuote $quote, string $revertStatus): void
    {
        if ($quote->getStatus() !== SaleQuote::STATUS_SENT || $this->security->hasPermission('sale_quotes.send')) {
            return;
        }

        $quote->setStatus($revertStatus);

        throw new AccessDeniedHttpException('No tienes permiso para enviar cotizaciones.');
    }
}
