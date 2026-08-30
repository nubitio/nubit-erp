<?php

declare(strict_types=1);

namespace App\Integration\MessageHandler;

use App\Billing\Entity\TaxDocument;
use App\Billing\Sunat\Domain\BillSendUseCase;
use App\Integration\Event\IntegrationEventRecorder;
use App\Integration\Fiscal\DocumentInput;
use App\Integration\Fiscal\SunatOutcomeMapper;
use App\Integration\Fiscal\SyntheticVoucherFactory;
use App\Integration\Message\SendIntegrationDocumentMessage;
use App\Integration\Repository\IntegrationDocumentRepository;
use App\Organization\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ServiceException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Submits a queued integration document to SUNAT (through the existing
 * BillSendUseCase, handed a synthetic voucher), records the outcome on the
 * IntegrationDocument + the TaxDocument, and emits the terminal event that the
 * webhook layer delivers.
 *
 * A transport failure rethrows so Messenger retries; a SUNAT rejection is a
 * terminal state, not a retry.
 */
#[AsMessageHandler]
final readonly class SendIntegrationDocumentMessageHandler
{
    public function __construct(
        private IntegrationDocumentRepository $documents,
        private CompanyRepository $companies,
        private SyntheticVoucherFactory $voucherFactory,
        private SunatOutcomeMapper $outcomeMapper,
        private BillSendUseCase $billSend,
        private IntegrationEventRecorder $events,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $billingLogger,
    ) {}

    public function __invoke(SendIntegrationDocumentMessage $message): void
    {
        $document = $this->documents->find($message->integrationDocumentId);
        if (null === $document || $document->isTerminal()) {
            return;
        }

        $taxDocumentId = $document->getTaxDocumentId();
        if (null === $taxDocumentId) {
            throw new ServiceException('Integration document is not linked to a tax document.');
        }

        $input = DocumentInput::fromArray($document->getRequestPayload());

        $company = $this->companies->findOneBy([]);
        if (null === $company || null === $company->getId()) {
            // A tenant with no company is a provisioning gap, not this
            // document's fault — let the message retry.
            throw new ServiceException('The tenant has no company configured; cannot emit.');
        }

        $voucher = $this->voucherFactory->fromInput($input, (string) $company->getId());

        $document->markSent();
        $this->entityManager->flush();

        $this->billingLogger->info('integration.send.start', [
            'reference' => $document->getReference(),
            'external_id' => $document->getExternalId(),
        ]);

        try {
            $response = ($this->billSend)($taxDocumentId, $voucher);
        } catch (\Throwable $e) {
            $this->billingLogger->error('integration.send.transport_failure', [
                'reference' => $document->getReference(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $outcome = $this->outcomeMapper->from($response);

        if ($outcome->accepted) {
            $document->markAccepted($outcome->sunatStatus ?? '0', $outcome->cdrReference);
        } else {
            $document->markRejected($outcome->message ?? 'SUNAT rejected the document', $outcome->sunatStatus);
        }

        $taxDocument = $this->entityManager->find(TaxDocument::class, $taxDocumentId);
        $taxDocument?->setSunatStatus($outcome->sunatStatus)->setSunatMessage($outcome->message);

        $this->events->recordTerminal($document);

        $this->entityManager->flush();

        $this->billingLogger->info('integration.send.done', [
            'reference' => $document->getReference(),
            'status' => $document->getStatus(),
            'sunat_status' => $outcome->sunatStatus,
        ]);
    }
}
