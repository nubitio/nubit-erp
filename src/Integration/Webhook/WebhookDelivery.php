<?php

declare(strict_types=1);

namespace App\Integration\Webhook;

use App\Integration\Repository\WebhookDeliveryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * One attempt (or retry chain) to deliver one event to one endpoint. The row
 * is the audit trail and the retry queue: a non-null `nextRetryAt` means it is
 * still owed an attempt.
 */
#[ORM\Entity(repositoryClass: WebhookDeliveryRepository::class)]
#[ORM\Table(name: 'integration_webhook_delivery')]
#[ORM\Index(name: 'idx_webhook_delivery_due', columns: ['status', 'next_retry_at'])]
#[ORM\Index(name: 'idx_webhook_delivery_event', columns: ['event_id'])]
#[ORM\UniqueConstraint(name: 'uq_webhook_delivery_event_endpoint', columns: ['event_id', 'endpoint_id'])]
class WebhookDelivery
{
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_DELIVERED = 'delivered';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_DEAD = 'dead';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: WebhookEndpoint::class)]
    #[ORM\JoinColumn(name: 'endpoint_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private WebhookEndpoint $endpoint;

    #[ORM\Column(length: 40)]
    private string $eventId;

    #[ORM\Column(length: 60)]
    private string $eventType;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column]
    private int $attempt = 0;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(nullable: true)]
    private ?int $responseStatus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $responseBody = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextRetryAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $claimUntil = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $claimToken = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(WebhookEndpoint $endpoint, string $eventId, string $eventType, array $payload)
    {
        $this->endpoint = $endpoint;
        $this->eventId = $eventId;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
        $this->nextRetryAt = $this->createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getEndpoint(): WebhookEndpoint
    {
        return $this->endpoint;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getResponseStatus(): ?int
    {
        return $this->responseStatus;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getNextRetryAt(): ?\DateTimeImmutable
    {
        return $this->nextRetryAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function succeed(int $responseStatus, ?string $body): void
    {
        ++$this->attempt;
        $this->status = self::STATUS_DELIVERED;
        $this->responseStatus = $responseStatus;
        $this->responseBody = self::truncate($body);
        $this->lastError = null;
        $this->nextRetryAt = null;
        $this->deliveredAt = new \DateTimeImmutable();
        $this->clearClaim();
    }

    public function failAndRetryAt(\DateTimeImmutable $next, ?int $responseStatus, ?string $bodyOrError): void
    {
        ++$this->attempt;
        $this->status = self::STATUS_FAILED;
        $this->responseStatus = $responseStatus;
        if (null !== $responseStatus) {
            $this->responseBody = self::truncate($bodyOrError);
        }
        $this->lastError = self::truncate($bodyOrError);
        $this->nextRetryAt = $next;
        $this->clearClaim();
    }

    public function deadLetter(?int $responseStatus, ?string $bodyOrError): void
    {
        ++$this->attempt;
        $this->status = self::STATUS_DEAD;
        $this->responseStatus = $responseStatus;
        $this->lastError = self::truncate($bodyOrError);
        $this->nextRetryAt = null;
        $this->clearClaim();
    }

    public function requeue(): void
    {
        $this->status = self::STATUS_PENDING;
        $this->nextRetryAt = new \DateTimeImmutable();
    }

    private function clearClaim(): void
    {
        $this->claimToken = null;
        $this->claimUntil = null;
    }

    private static function truncate(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return mb_substr($value, 0, 2000);
    }
}
