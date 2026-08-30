<?php

declare(strict_types=1);

namespace App\Integration\Webhook;

use App\Integration\Event\EventType;
use App\Integration\Repository\IntegrationEventRepository;
use App\Integration\Repository\WebhookEndpointRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Drains the outbox: for every pending {@see \App\Integration\Event\IntegrationEvent},
 * creates one {@see WebhookDelivery} per enabled endpoint that subscribes to
 * its type, then marks the event dispatched. Runs on the current tenant
 * connection.
 */
final readonly class WebhookFanout
{
    public function __construct(
        private IntegrationEventRepository $events,
        private WebhookEndpointRepository $endpoints,
        private EntityManagerInterface $entityManager,
    ) {}

    public function run(int $limit = 200): int
    {
        $pending = $this->events->findPending($limit);
        if ([] === $pending) {
            return 0;
        }

        $endpoints = $this->endpoints->findEnabled();
        $deliveries = 0;

        foreach ($pending as $event) {
            $type = EventType::tryFrom($event->getType());
            if (null !== $type) {
                foreach ($endpoints as $endpoint) {
                    if (!$endpoint->wantsEvent($type)) {
                        continue;
                    }
                    $this->entityManager->persist(
                        new WebhookDelivery(
                            $endpoint,
                            (string) $event->getId(),
                            $event->getType(),
                            WebhookEnvelope::forEvent($event),
                        ),
                    );
                    ++$deliveries;
                }
            }

            $event->markDispatched();
        }

        $this->entityManager->flush();

        return $deliveries;
    }
}
