<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Integration\Event\EventType;
use App\Integration\Event\IntegrationEvent;
use App\Integration\Repository\IntegrationEventRepository;
use App\Integration\Repository\WebhookEndpointRepository;
use App\Integration\Webhook\WebhookDelivery;
use App\Integration\Webhook\WebhookEndpoint;
use App\Integration\Webhook\WebhookFanout;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class WebhookFanoutTest extends TestCase
{
    public function testCreatesOneDeliveryPerSubscribedEndpointAndMarksEventsDispatched(): void
    {
        $accepted = new IntegrationEvent(EventType::DocumentAccepted, 'document', 'ext-1', ['reference' => 'efact-1']);
        $rejected = new IntegrationEvent(EventType::DocumentRejected, 'document', 'ext-2', ['reference' => 'efact-2']);

        $events = $this->createStub(IntegrationEventRepository::class);
        $events->method('claimPending')->willReturn([$accepted, $rejected]);

        $endpoints = $this->createStub(WebhookEndpointRepository::class);
        $endpoints
            ->method('findEnabled')
            ->willReturn([
                new WebhookEndpoint('https://a.test/hook', 's', ['document.accepted']),
                new WebhookEndpoint('https://b.test/hook', 's', ['document.*']),
            ]);

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $o) use (&$persisted): void {
            $persisted[] = $o;
        });

        $created = new WebhookFanout($events, $endpoints, $em)->run();

        // accepted -> both endpoints; rejected -> only the wildcard one
        self::assertSame(3, $created);
        self::assertCount(3, $persisted);
        self::assertContainsOnlyInstancesOf(WebhookDelivery::class, $persisted);
        self::assertSame(IntegrationEvent::STATUS_DISPATCHED, $accepted->getStatus());
        self::assertSame(IntegrationEvent::STATUS_DISPATCHED, $rejected->getStatus());
    }

    public function testNoPendingEventsIsANoOp(): void
    {
        $events = $this->createStub(IntegrationEventRepository::class);
        $events->method('claimPending')->willReturn([]);
        $endpoints = $this->createStub(WebhookEndpointRepository::class);

        self::assertSame(
            0,
            new WebhookFanout($events, $endpoints, $this->createStub(EntityManagerInterface::class))->run(),
        );
    }
}
