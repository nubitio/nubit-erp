<?php

declare(strict_types=1);

namespace App\Integration\Entity;

use App\Integration\Repository\IntegrationDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * One document submitted through the POST /api/v1/documents endpoint.
 *
 * It is the idempotency record (unique externalId), the resource the
 * GET /api/v1/documents/{reference} endpoint reads, and the local mirror of
 * the SUNAT outcome that the webhook layer turns into events.
 */
#[ORM\Entity(repositoryClass: IntegrationDocumentRepository::class)]
#[ORM\Table(name: 'integration_document')]
#[ORM\UniqueConstraint(name: 'uq_integration_document_external_id', columns: ['external_id'])]
#[ORM\UniqueConstraint(name: 'uq_integration_document_reference', columns: ['reference'])]
class IntegrationDocument
{
    public const string STATUS_QUEUED = 'queued';
    public const string STATUS_SENT = 'sent';
    public const string STATUS_ACCEPTED = 'accepted';
    public const string STATUS_REJECTED = 'rejected';
    public const string STATUS_VOIDED = 'voided';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column]
    private ?string $id = null;

    #[ORM\Column(length: 64)]
    private string $externalId;

    #[ORM\Column(length: 40)]
    private string $reference;

    #[ORM\Column(length: 64)]
    private string $requestHash;

    /**
     * The exact request body, kept so the async worker can rebuild the
     * synthetic voucher without a Sale to reconstruct one from.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $requestPayload;

    #[ORM\Column(length: 10)]
    private string $documentType;

    #[ORM\Column(length: 10)]
    private string $series;

    #[ORM\Column]
    private int $number;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_QUEUED;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $taxDocumentId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sunatStatus = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $cdrReference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $requestPayload
     */
    public function __construct(
        string $externalId,
        string $reference,
        string $requestHash,
        array $requestPayload,
        DocumentInputSummary $summary,
    ) {
        $this->externalId = $externalId;
        $this->reference = $reference;
        $this->requestHash = $requestHash;
        $this->requestPayload = $requestPayload;
        $this->documentType = $summary->documentType;
        $this->series = $summary->series;
        $this->number = $summary->number;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    /** @return array<string, mixed> */
    public function getRequestPayload(): array
    {
        return $this->requestPayload;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_REJECTED, self::STATUS_VOIDED], true);
    }

    public function getTaxDocumentId(): ?string
    {
        return $this->taxDocumentId;
    }

    public function getSunatStatus(): ?string
    {
        return $this->sunatStatus;
    }

    public function getCdrReference(): ?string
    {
        return $this->cdrReference;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function linkTaxDocument(string $taxDocumentId): void
    {
        $this->taxDocumentId = $taxDocumentId;
        $this->touch();
    }

    public function markSent(): void
    {
        if (self::STATUS_QUEUED === $this->status) {
            $this->status = self::STATUS_SENT;
            $this->touch();
        }
    }

    public function markAccepted(string $sunatStatus, ?string $cdrReference): void
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->sunatStatus = $sunatStatus;
        $this->cdrReference = $cdrReference;
        $this->error = null;
        $this->touch();
    }

    public function markRejected(string $reason, ?string $sunatStatus = null): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->sunatStatus = $sunatStatus;
        $this->error = $reason;
        $this->touch();
    }

    public function markVoided(?string $reason = null): void
    {
        $this->status = self::STATUS_VOIDED;
        $this->error = $reason;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
