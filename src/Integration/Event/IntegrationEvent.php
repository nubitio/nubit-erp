<?php

declare(strict_types=1);

namespace App\Integration\Event;

use App\Integration\Repository\IntegrationEventRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * Transactional outbox row. Written in the same transaction as the domain
 * transition it describes, then drained by the webhook dispatcher — so a crash
 * between "document accepted" and "webhook sent" loses nothing.
 */
#[ORM\Entity(repositoryClass: IntegrationEventRepository::class)]
#[ORM\Table(name: 'integration_event')]
#[ORM\Index(name: 'idx_integration_event_status', columns: ['status', 'occurred_at'])]
class IntegrationEvent
{
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_DISPATCHED = 'dispatched';
    public const string STATUS_FAILED = 'failed';
    public const string STATUS_PROCESSING = 'processing';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column]
    private ?string $id = null;

    #[ORM\Column(length: 60)]
    private string $type;

    #[ORM\Column(length: 30)]
    private string $resourceType;

    #[ORM\Column(length: 64)]
    private string $resourceId;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dispatchedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $claimUntil = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $claimToken = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(EventType $type, string $resourceType, string $resourceId, array $payload)
    {
        $this->type = $type->value;
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->payload = $payload;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getDispatchedAt(): ?\DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function getClaimToken(): ?string
    {
        return $this->claimToken;
    }

    public function hasActiveClaim(\DateTimeImmutable $now): bool
    {
        return null !== $this->claimToken && null !== $this->claimUntil && $this->claimUntil >= $now;
    }

    public function markDispatched(): void
    {
        $this->status = self::STATUS_DISPATCHED;
        $this->dispatchedAt = new \DateTimeImmutable();
        $this->claimUntil = null;
        $this->claimToken = null;
    }

    public function markFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->claimUntil = null;
        $this->claimToken = null;
    }
}
