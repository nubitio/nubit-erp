<?php

declare(strict_types=1);

namespace App\Integration\Event;

use App\Integration\Entity\IntegrationDocument;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Writes outbox rows. Only `persist()`s — the caller owns the flush, so the
 * event lands in the same transaction as the state change it describes.
 */
final readonly class IntegrationEventRecorder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Record the event for a document that has just reached a terminal SUNAT
     * state. No-op if the status has no mapped event type.
     */
    public function recordTerminal(IntegrationDocument $document): ?IntegrationEvent
    {
        $type = EventType::forDocumentStatus($document->getStatus());
        if (null === $type) {
            return null;
        }

        $event = new IntegrationEvent($type, 'document', $document->getExternalId(), self::documentData($document));
        $this->entityManager->persist($event);

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    public static function documentData(IntegrationDocument $document): array
    {
        return [
            'external_id' => $document->getExternalId(),
            'reference' => $document->getReference(),
            'status' => $document->getStatus(),
            'sunat_status' => $document->getSunatStatus(),
            'series' => $document->getSeries(),
            'number' => $document->getNumber(),
            'document_type' => $document->getDocumentType(),
            'cdr_reference' => $document->getCdrReference(),
            'error' => $document->getError(),
        ];
    }
}
