<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Integration\Webhook\RetrySchedule;
use App\Integration\Webhook\WebhookDelivery;
use App\Integration\Webhook\WebhookDeliveryAttempt;
use App\Integration\Webhook\WebhookEndpoint;
use App\Integration\Webhook\WebhookSigner;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WebhookDeliveryAttemptTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<array-key, mixed>}> */
    private array $requests = [];

    private function attemptWith(MockHttpClient $client): WebhookDeliveryAttempt
    {
        return new WebhookDeliveryAttempt(
            $client,
            new WebhookSigner(),
            new RetrySchedule(),
            $this->createStub(EntityManagerInterface::class),
            new NullLogger(),
        );
    }

    private function delivery(WebhookEndpoint $endpoint): WebhookDelivery
    {
        return new WebhookDelivery($endpoint, 'evt-1', 'document.accepted', [
            'id' => 'evt_evt-1',
            'type' => 'document.accepted',
        ]);
    }

    public function testA2xxMarksDeliveredAndClearsEndpointFailures(): void
    {
        $endpoint = new WebhookEndpoint('https://receiver.test/hook', 'whsec_x', ['*']);
        $endpoint->recordFailure();
        $delivery = $this->delivery($endpoint);

        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{"ok":true}', ['http_code' => 200]);
        });

        $this->attemptWith($client)->attempt($delivery);

        self::assertSame(WebhookDelivery::STATUS_DELIVERED, $delivery->getStatus());
        self::assertSame(200, $delivery->getResponseStatus());
        self::assertNull($delivery->getNextRetryAt());
        self::assertSame(0, $endpoint->getConsecutiveFailures());

        $headers = (array) ($this->requests[0]['options']['normalized_headers'] ?? []);
        self::assertArrayHasKey('nb-signature', $headers);
        self::assertArrayHasKey('nb-event-id', $headers);
    }

    public function testA5xxSchedulesARetry(): void
    {
        $endpoint = new WebhookEndpoint('https://receiver.test/hook', 'whsec_x', ['*']);
        $delivery = $this->delivery($endpoint);

        $this->attemptWith(new MockHttpClient(new MockResponse('boom', ['http_code' => 500])))->attempt($delivery);

        self::assertSame(WebhookDelivery::STATUS_FAILED, $delivery->getStatus());
        self::assertSame(1, $delivery->getAttempt());
        self::assertNotNull($delivery->getNextRetryAt());
        self::assertSame(
            0,
            $endpoint->getConsecutiveFailures(),
            'only dead-letters count toward auto-disable, not retryable failures',
        );
    }

    public function testExhaustingTheScheduleDeadLettersAndCountsAgainstTheEndpoint(): void
    {
        $endpoint = new WebhookEndpoint('https://receiver.test/hook', 'whsec_x', ['*']);
        $delivery = $this->delivery($endpoint);

        // Drive the attempt counter to the end of the schedule (8 delays).
        for ($i = 0; $i < 8; ++$i) {
            $delivery->failAndRetryAt(new \DateTimeImmutable(), 500, 'x');
        }
        self::assertSame(8, $delivery->getAttempt());

        $this->attemptWith(new MockHttpClient(new MockResponse('still down', [
            'http_code' => 503,
        ])))->attempt($delivery);

        self::assertSame(WebhookDelivery::STATUS_DEAD, $delivery->getStatus());
        self::assertNull($delivery->getNextRetryAt());
        self::assertSame(1, $endpoint->getConsecutiveFailures());
    }

    public function testTransportErrorIsARetryableFailure(): void
    {
        $endpoint = new WebhookEndpoint('https://receiver.test/hook', 'whsec_x', ['*']);
        $delivery = $this->delivery($endpoint);

        $client = new MockHttpClient(static function (): MockResponse {
            return new MockResponse('', ['error' => 'Connection refused']);
        });

        $this->attemptWith($client)->attempt($delivery);

        self::assertSame(WebhookDelivery::STATUS_FAILED, $delivery->getStatus());
        self::assertNull($delivery->getResponseStatus());
        self::assertNotNull($delivery->getNextRetryAt());
        self::assertNotNull($delivery->getLastError());
    }
}
