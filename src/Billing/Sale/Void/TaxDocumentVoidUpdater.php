<?php

declare(strict_types=1);

namespace App\Billing\Sale\Void;

use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentEventRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TaxDocumentVoidUpdater
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaxDocumentEventRepository $taxDocumentEventRepository,
    ) {
    }

    public function applyVoidResult(TaxDocument $taxDocument, SunatVoidResponse $response, int $voidedStatus): void
    {
        $taxDocument->setVoidCorrelation($response->getCorrelation());
        $taxDocument->setVoidTicket($response->ticket);
        $taxDocument->setUpdatedAt(new DateTime());

        $this->entityManager->persist($taxDocument);

        $this->taxDocumentEventRepository->recordEvent(
            taxDocument: $taxDocument,
            eventType: 'sunat_void',
            statusCode: $response->ticket,
            statusMessage: $response->message ?? 'Solicitud de anulación',
            payload: ['voided' => $voidedStatus],
        );
    }
}
