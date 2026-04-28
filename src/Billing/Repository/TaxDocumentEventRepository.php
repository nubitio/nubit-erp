<?php

declare(strict_types=1);

namespace App\Billing\Repository;

use App\AI\Repository\SunatRejectionQueryInterface;
use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaxDocumentEvent>
 */
class TaxDocumentEventRepository extends ServiceEntityRepository implements SunatRejectionQueryInterface
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

    /**
     * @return list<TaxDocumentEvent>
     */
    public function findRejections(): array
    {
        /** @var list<TaxDocumentEvent> $result */
        $result = $this->createQueryBuilder('e')
            ->addSelect('d')
            ->join('e.taxDocument', 'd')
            ->where('e.code IS NOT NULL')
            ->andWhere('e.code != :ok')
            ->setParameter('ok', '0')
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
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
