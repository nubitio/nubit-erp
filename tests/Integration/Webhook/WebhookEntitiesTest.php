<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Integration\Event\EventType;
use App\Integration\Webhook\WebhookDelivery;
use App\Integration\Webhook\WebhookEndpoint;
use PHPUnit\Framework\TestCase;

final class WebhookEntitiesTest extends TestCase
{
    public function testSubscriptionMatchingHonoursPatterns(): void
    {
        $exact = new WebhookEndpoint('https://x', 's', ['document.accepted']);
        $prefix = new WebhookEndpoint('https://x', 's', ['document.*']);
        $wild = new WebhookEndpoint('https://x', 's', []);

        self::assertTrue($exact->wantsEvent(EventType::DocumentAccepted));
        self::assertFalse($exact->wantsEvent(EventType::DocumentRejected));
        self::assertTrue($prefix->wantsEvent(EventType::DocumentRejected));
        self::assertTrue($wild->wantsEvent(EventType::DocumentVoided), 'empty subscription defaults to *');
    }

    public function testEndpointAutoDisablesAfterTwentyConsecutiveFailures(): void
    {
        $endpoint = new WebhookEndpoint('https://x', 's', ['*']);

        for ($i = 1; $i <= 19; ++$i) {
            self::assertFalse($endpoint->recordFailure(), "failure $i should not disable yet");
        }
        self::assertTrue($endpoint->recordFailure(), 'the 20th failure disables');
        self::assertFalse($endpoint->isEnabled());
        self::assertStringContainsString('20 consecutive failures', (string) $endpoint->getDisabledReason());

        $endpoint->recordSuccess();
        // recordSuccess only resets the counter; re-enabling is explicit.
        self::assertFalse($endpoint->isEnabled());
        self::assertSame(0, $endpoint->getConsecutiveFailures());

        $endpoint->enable();
        self::assertTrue($endpoint->isEnabled());
    }

    public function testDeliveryAttemptCountingIsConsistent(): void
    {
        $endpoint = new WebhookEndpoint('https://x', 's', ['*']);
        $delivery = new WebhookDelivery($endpoint, 'evt-1', 'document.accepted', ['id' => 'evt_evt-1']);

        self::assertSame(0, $delivery->getAttempt());
        self::assertSame(WebhookDelivery::STATUS_PENDING, $delivery->getStatus());
        self::assertNotNull($delivery->getNextRetryAt());

        $delivery->failAndRetryAt(new \DateTimeImmutable('+1 minute'), 500, 'server error');
        self::assertSame(1, $delivery->getAttempt());
        self::assertSame(WebhookDelivery::STATUS_FAILED, $delivery->getStatus());

        $delivery->succeed(200, str_repeat('x', 5000));
        self::assertSame(2, $delivery->getAttempt());
        self::assertSame(WebhookDelivery::STATUS_DELIVERED, $delivery->getStatus());
        self::assertNull($delivery->getNextRetryAt());
        self::assertNotNull($delivery->getDeliveredAt());

        $delivery->requeue();
        self::assertSame(WebhookDelivery::STATUS_PENDING, $delivery->getStatus());
        self::assertNotNull($delivery->getNextRetryAt());
    }

    public function testDeadLetterIsTerminal(): void
    {
        $delivery = new WebhookDelivery(new WebhookEndpoint('https://x', 's', ['*']), 'evt-2', 'document.rejected', []);

        $delivery->deadLetter(410, 'gone');

        self::assertSame(WebhookDelivery::STATUS_DEAD, $delivery->getStatus());
        self::assertNull($delivery->getNextRetryAt());
        self::assertSame(1, $delivery->getAttempt());
    }
}
