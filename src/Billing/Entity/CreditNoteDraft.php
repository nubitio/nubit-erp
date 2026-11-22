<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use App\Billing\Repository\CreditNoteDraftRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;

/**
 * An unfinished credit note. It reserves no correlative and moves no stock: that
 * only happens when the user issues the real note.
 */
#[ORM\Entity(repositoryClass: CreditNoteDraftRepository::class)]
#[ORM\Table(name: 'billing_credit_note_draft')]
#[ORM\Index(name: 'IDX_BILLING_CN_DRAFT_OWNER', columns: ['branch_id', 'user_id', 'updated_at'])]
class CreditNoteDraft
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * @param list<array{lineNumber: int, quantity: string, restock: bool}> $lines
     */
    public function __construct(
        #[ORM\Column(name: 'branch_id', type: Types::STRING, length: 36)]
        private string $branchId,
        #[ORM\Column(name: 'user_id', type: Types::INTEGER)]
        private int $userId,
        #[ORM\Column(name: 'related_tax_document_id', type: Types::STRING, length: 36)]
        private string $relatedTaxDocumentId,
        #[ORM\Column(name: 'reason_code', type: Types::STRING, length: 2, nullable: true)]
        private ?string $reasonCode,
        #[ORM\Column(name: 'reason_description', type: Types::STRING, length: 250, nullable: true)]
        private ?string $reasonDescription,
        #[ORM\Column(name: 'line_items', type: Types::JSON)]
        private array $lines,
    ) {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    /**
     * @param list<array{lineNumber: int, quantity: string, restock: bool}> $lines
     */
    public function replaceContent(
        string $relatedTaxDocumentId,
        ?string $reasonCode,
        ?string $reasonDescription,
        array $lines,
    ): void {
        $this->relatedTaxDocumentId = $relatedTaxDocumentId;
        $this->reasonCode = $reasonCode;
        $this->reasonDescription = $reasonDescription;
        $this->lines = $lines;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getBranchId(): string
    {
        return $this->branchId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getRelatedTaxDocumentId(): string
    {
        return $this->relatedTaxDocumentId;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public function getReasonDescription(): ?string
    {
        return $this->reasonDescription;
    }

    /**
     * @return list<array{lineNumber: int, quantity: string, restock: bool}>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
