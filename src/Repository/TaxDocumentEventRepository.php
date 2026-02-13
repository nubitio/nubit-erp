<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TaxDocument;
use App\Entity\TaxDocumentEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxDocumentEvent>
 */
final class TaxDocumentEventRepository extends ServiceEntityRepository
{
    private ManagerRegistry $registry;

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
        parent::__construct($registry, TaxDocumentEvent::class);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function recordEvent(
        TaxDocument $taxDocument,
        string $eventType,
        ?string $statusCode,
        ?string $statusMessage,
        ?array $payload = null
    ): TaxDocumentEvent {
        $event = new TaxDocumentEvent();
        $event->setTaxDocument($taxDocument);
        $event->setEventType($eventType);
        $event->setStatus($this->resolveStatus($statusCode));
        $event->setCode($statusCode);
        $event->setMessage($statusMessage);
        $event->setPayloadJson($payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE));
        $event->setCreatedAt(new \DateTimeImmutable());

        $this->registry->getManager()->persist($event);

        return $event;
    }

    private function resolveStatus(?string $statusCode): string
    {
        $normalizedCode = strtolower(trim((string)$statusCode));
        if ($normalizedCode === '' || $normalizedCode === 'pendiente') {
            return 'pending';
        }
        if (in_array($normalizedCode, ['0', 'aceptado', 'accepted'], true)) {
            return 'accepted';
        }
        return 'error';
    }
}
