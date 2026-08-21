<?php

declare(strict_types=1);

namespace App\Accounting\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Accounting\Repository\JournalEntryRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: JournalEntryRepository::class)]
#[ORM\Table(name: 'journal_entry')]
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['journal_entry:read']],
    filters: [DataGridFilter::class],
    order: ['entryDate' => 'DESC', 'entryNo' => 'DESC'],
    security: "is_granted('perm:accounting.view')",
)]
class JournalEntry
{
    #[Groups(['journal_entry:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['journal_entry:read'])]
    #[ApiProperty(description: 'journal_entry.entry_no.label')]
    #[ORM\Column(name: 'entry_no', length: 32)]
    private string $entryNo = '';

    #[Groups(['journal_entry:read'])]
    #[ORM\Column(name: 'source', length: 32)]
    private string $source = '';

    #[Groups(['journal_entry:read'])]
    #[ORM\Column(name: 'source_id', length: 64)]
    private string $sourceId = '';

    #[Groups(['journal_entry:read'])]
    #[ApiProperty(description: 'journal_entry.entry_date.label')]
    #[ORM\Column(name: 'entry_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $entryDate;

    #[Groups(['journal_entry:read'])]
    #[ApiProperty(description: 'journal_entry.description.label')]
    #[ORM\Column(name: 'description', length: 255)]
    private string $description = '';

    #[Groups(['journal_entry:read'])]
    #[ORM\Column(name: 'currency', length: 3, options: ['default' => 'PEN'])]
    private string $currency = 'PEN';

    #[Groups(['journal_entry:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, JournalEntryLine> */
    #[Groups(['journal_entry:read'])]
    #[ORM\OneToMany(targetEntity: JournalEntryLine::class, mappedBy: 'entry', cascade: ['persist'])]
    #[ORM\OrderBy(['lineNo' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->entryDate = new DateTimeImmutable('today');
        $this->createdAt = new DateTimeImmutable();
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getEntryNo(): string
    {
        return $this->entryNo;
    }

    public function setEntryNo(string $entryNo): static
    {
        $this->entryNo = $entryNo;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function setSourceId(string $sourceId): static
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    public function getEntryDate(): DateTimeImmutable
    {
        return $this->entryDate;
    }

    public function setEntryDate(DateTimeImmutable $entryDate): static
    {
        $this->entryDate = $entryDate;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, JournalEntryLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(JournalEntryLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setEntry($this);
        }

        return $this;
    }
}
