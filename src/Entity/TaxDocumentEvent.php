<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TaxDocumentEventRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxDocumentEventRepository::class)]
#[ORM\Table(name: 'tax_document_event')]
#[ORM\Index(name: 'IDX_TAX_DOCUMENT_EVENT_DOCUMENT', columns: ['tax_document_id'])]
class TaxDocumentEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TaxDocument::class)]
    #[ORM\JoinColumn(
        name: 'tax_document_id',
        referencedColumnName: 'tax_document_id',
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private TaxDocument $taxDocument;

    #[ORM\Column(name: 'event_type', type: Types::STRING, length: 40)]
    private string $eventType;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 30, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(name: 'code', type: Types::STRING, length: 50, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(name: 'message', type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'payload_json', type: Types::TEXT, nullable: true)]
    private ?string $payloadJson = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaxDocument(): TaxDocument
    {
        return $this->taxDocument;
    }

    public function setTaxDocument(TaxDocument $taxDocument): static
    {
        $this->taxDocument = $taxDocument;

        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getPayloadJson(): ?string
    {
        return $this->payloadJson;
    }

    public function setPayloadJson(?string $payloadJson): static
    {
        $this->payloadJson = $payloadJson;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
