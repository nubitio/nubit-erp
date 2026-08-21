<?php

declare(strict_types=1);

namespace App\AI\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

#[ORM\Entity]
#[ORM\Table(name: 'assistant_proposed_action')]
#[ORM\Index(name: 'idx_assistant_action_user_status', columns: ['user_id', 'status'])]
class AssistantProposedAction
{
    public const string PENDING = 'pending';

    public const string CONFIRMED = 'confirmed';

    public const string CANCELLED = 'cancelled';

    public const string EXPIRED = 'expired';

    public const string FAILED = 'failed';

    public const string KIND_CREATE_CUSTOMER = 'create_customer';

    public const string KIND_CREATE_PRODUCT = 'create_product';

    public const string KIND_CREATE_SALE = 'create_sale';

    public const string KIND_REPLACE_REJECTED = 'replace_rejected';

    public const string KIND_FILL_ONBOARDING = 'fill_onboarding';

    public const string KIND_CREATE_CREDIT_NOTE = 'create_credit_note';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER)]
    private int $userId;

    #[ORM\Column(length: 64)]
    private string $kind;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::TEXT)]
    private string $summary;

    #[ORM\Column(length: 20)]
    private string $status = self::PENDING;

    #[ORM\Column(name: 'idempotency_key', length: 64, unique: true)]
    private string $idempotencyKey;

    #[ORM\Column(name: 'result_ref', length: 64, nullable: true)]
    private ?string $resultRef = null;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->expiresAt = $now->modify('+10 minutes');
        $this->idempotencyKey = bin2hex(random_bytes(16));
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): void
    {
        $this->kind = $kind;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): void
    {
        $this->summary = $summary;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getResultRef(): ?string
    {
        return $this->resultRef;
    }

    public function setResultRef(?string $resultRef): void
    {
        $this->resultRef = $resultRef;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new DateTimeImmutable();
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING && !$this->isExpired();
    }

    /**
     * @return array<string, mixed>
     */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'summary' => $this->summary,
            'payload' => $this->payload,
            'expiresAt' => $this->expiresAt->format(DATE_ATOM),
        ];
    }
}
