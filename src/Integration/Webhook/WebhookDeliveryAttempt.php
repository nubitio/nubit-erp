<?php

declare(strict_types=1);

namespace App\Integration\Webhook;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Makes one HTTP attempt for a {@see WebhookDelivery} and records the result:
 * 2xx within the timeout is delivered; anything else schedules the next retry
 * from {@see RetrySchedule}, or dead-letters (and may auto-disable the
 * endpoint) once the schedule is exhausted.
 */
final readonly class WebhookDeliveryAttempt
{
    private const int TIMEOUT_SECONDS = 10;

    public function __construct(
        private HttpClientInterface $httpClient,
        private WebhookSigner $signer,
        private RetrySchedule $retrySchedule,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function attempt(WebhookDelivery $delivery): void
    {
        $endpoint = $delivery->getEndpoint();
        $body = json_encode($delivery->getPayload(), JSON_THROW_ON_ERROR);
        $now = new \DateTimeImmutable();

        try {
            $response = $this->httpClient->request('POST', $endpoint->getUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    WebhookSigner::HEADER => $this->signer->header($endpoint->getSecret(), $body),
                    'Nb-Event-Id' => $delivery->getEventId(),
                    'Nb-Event-Type' => $delivery->getEventType(),
                ],
                'body' => $body,
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            $status = $response->getStatusCode();
            $snippet = mb_substr($response->getContent(false), 0, 500);

            if ($status >= 200 && $status < 300) {
                $delivery->succeed($status, $snippet);
                $endpoint->recordSuccess();
            } else {
                $this->fail($delivery, $endpoint, $now, $status, $snippet);
            }
        } catch (HttpException $e) {
            $this->fail($delivery, $endpoint, $now, null, $e->getMessage());
        }

        $this->entityManager->flush();
    }

    private function fail(
        WebhookDelivery $delivery,
        WebhookEndpoint $endpoint,
        \DateTimeImmutable $now,
        ?int $status,
        ?string $detail,
    ): void {
        $completed = $delivery->getAttempt() + 1;
        $next = $this->retrySchedule->nextRetryAt($completed, $now);

        if (null === $next) {
            $delivery->deadLetter($status, $detail);
            $autoDisabled = $endpoint->recordFailure();
            $this->logger->warning('integration.webhook.dead_letter', [
                'event_id' => $delivery->getEventId(),
                'endpoint' => $endpoint->getUrl(),
                'auto_disabled' => $autoDisabled,
            ]);

            return;
        }

        $delivery->failAndRetryAt($next, $status, $detail);
        $this->logger->info('integration.webhook.retry_scheduled', [
            'event_id' => $delivery->getEventId(),
            'attempt' => $completed,
            'next_retry_at' => $next->format(\DATE_ATOM),
            'status' => $status,
        ]);
    }
}
