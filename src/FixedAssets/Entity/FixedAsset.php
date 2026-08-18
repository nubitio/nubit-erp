<?php

declare(strict_types=1);

namespace App\FixedAssets\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\AccountsPayable\Entity\SupplierBill;
use App\FixedAssets\Dto\FixedAssetDisposeDto;
use App\FixedAssets\Repository\FixedAssetRepository;
use App\FixedAssets\State\FixedAssetDisposeProcessor;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An owned asset (equipment, furniture) depreciated on a straight-line
 * basis. No `branch` relation on purpose — see the Fase 6 plan notes: the
 * global BranchFilter auto-scopes any entity with a `branch` association,
 * and a fixed asset doesn't need per-branch list scoping in this phase.
 */
#[ORM\Entity(repositoryClass: FixedAssetRepository::class)]
#[ORM\Table(name: 'fixed_asset')]
#[ORM\Index(name: 'idx_fixed_asset_status', columns: ['status'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(),
        new Get(),
        new Patch(),
        new Delete(),
        new Post(
            uriTemplate: '/fixed-assets/{id}/dispose',
            normalizationContext: ['groups' => ['fixed_asset:read']],
            // FixedAssetDisposeDto has no serialization groups of its own,
            // so this must override the class-level denormalizationContext
            // below — otherwise the 'fixed_asset:write' group filter strips
            // every property before the DTO constructor runs.
            denormalizationContext: [],
            input: FixedAssetDisposeDto::class,
            output: FixedAsset::class,
            processor: FixedAssetDisposeProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['fixed_asset:read']],
    denormalizationContext: ['groups' => ['fixed_asset:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class FixedAsset
{
    public const string ACTIVE = 'ACTIVE';

    public const string FULLY_DEPRECIATED = 'FULLY_DEPRECIATED';

    public const string DISPOSED = 'DISPOSED';

    #[Groups(['fixed_asset:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Assert\NotBlank]
    #[Groups(['fixed_asset:read', 'fixed_asset:write'])]
    #[ApiProperty(description: 'fixed_asset.description.label')]
    #[ORM\Column(name: 'description', length: 255)]
    private string $description;

    #[Assert\NotNull]
    #[Groups(['fixed_asset:read', 'fixed_asset:write'])]
    #[ApiProperty(description: 'fixed_asset.purchase_date.label')]
    #[ORM\Column(name: 'purchase_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $purchaseDate;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['fixed_asset:read', 'fixed_asset:write'])]
    #[ApiProperty(description: 'fixed_asset.cost.label')]
    #[ORM\Column(name: 'cost', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $cost;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['fixed_asset:read', 'fixed_asset:write'])]
    #[ApiProperty(description: 'fixed_asset.useful_life_months.label')]
    #[ORM\Column(name: 'useful_life_months', type: Types::INTEGER)]
    private int $usefulLifeMonths;

    #[Groups(['fixed_asset:read', 'fixed_asset:write'])]
    #[ApiProperty(description: 'fixed_asset.source_bill.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: SupplierBill::class)]
    #[ORM\JoinColumn(name: 'source_bill_id', referencedColumnName: 'id', nullable: true)]
    private ?SupplierBill $sourceBill = null;

    #[Groups(['fixed_asset:read'])]
    #[ApiProperty(description: 'fixed_asset.accumulated_depreciation.label')]
    #[ORM\Column(name: 'accumulated_depreciation', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $accumulatedDepreciation = '0.00';

    #[Groups(['fixed_asset:read'])]
    #[ApiProperty(description: 'fixed_asset.status.label')]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => self::ACTIVE])]
    private string $status = self::ACTIVE;

    #[Groups(['fixed_asset:read'])]
    #[ApiProperty(description: 'fixed_asset.disposed_at.label')]
    #[ORM\Column(name: 'disposed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $disposedAt = null;

    #[Groups(['fixed_asset:read'])]
    #[ApiProperty(description: 'fixed_asset.disposal_note.label')]
    #[ORM\Column(name: 'disposal_note', type: Types::TEXT, nullable: true)]
    private ?string $disposalNote = null;

    #[Groups(['fixed_asset:read', 'fixed_asset:write'])]
    #[ApiProperty(description: 'fixed_asset.note.label')]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[Groups(['fixed_asset:read'])]
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getPurchaseDate(): DateTimeImmutable
    {
        return $this->purchaseDate;
    }

    public function setPurchaseDate(DateTimeImmutable $purchaseDate): static
    {
        $this->purchaseDate = $purchaseDate;

        return $this;
    }

    public function getCost(): string
    {
        return $this->cost;
    }

    public function setCost(string $cost): static
    {
        $this->cost = $cost;

        return $this;
    }

    public function getUsefulLifeMonths(): int
    {
        return $this->usefulLifeMonths;
    }

    public function setUsefulLifeMonths(int $usefulLifeMonths): static
    {
        $this->usefulLifeMonths = $usefulLifeMonths;

        return $this;
    }

    public function getSourceBill(): ?SupplierBill
    {
        return $this->sourceBill;
    }

    public function setSourceBill(?SupplierBill $sourceBill): static
    {
        $this->sourceBill = $sourceBill;

        return $this;
    }

    public function getAccumulatedDepreciation(): string
    {
        return $this->accumulatedDepreciation;
    }

    public function setAccumulatedDepreciation(string $accumulatedDepreciation): static
    {
        $this->accumulatedDepreciation = $accumulatedDepreciation;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDisposedAt(): ?DateTimeImmutable
    {
        return $this->disposedAt;
    }

    public function setDisposedAt(?DateTimeImmutable $disposedAt): static
    {
        $this->disposedAt = $disposedAt;

        return $this;
    }

    public function getDisposalNote(): ?string
    {
        return $this->disposalNote;
    }

    public function setDisposalNote(?string $disposalNote): static
    {
        $this->disposalNote = $disposalNote;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
