<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentEvent;
use App\Billing\Repository\TaxDocumentEventRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final readonly class TaxDocumentEventsController
{
    public function __construct(
        private TaxDocumentEventRepository $events,
    ) {}

    public function __invoke(TaxDocument $data): JsonResponse
    {
        // API Platform resolves the document with its branch filter and sales.view
        // before this controller runs. Event payloads remain internal.
        $events = $this->events->findBy(['taxDocument' => $data], ['createdAt' => 'DESC', 'id' => 'DESC'], 50);
        return new JsonResponse(array_map(static fn(TaxDocumentEvent $event): array => [
            'id' => $event->getId(),
            'eventType' => $event->getEventType(),
            'status' => $event->getStatus(),
            'code' => $event->getCode(),
            'message' => $event->getMessage(),
            'createdAt' => $event->getCreatedAt()->format(DATE_ATOM),
        ], $events));
    }
}
