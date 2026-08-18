<?php

declare(strict_types=1);

namespace App\FixedAssets\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\FixedAssets\Repository\AssetDepreciationEntryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One month's depreciation posting for a FixedAsset — read-only via the
 * API, generated exclusively by FixedAssetService::generateDueEntries().
 * FixedAsset::$accumulatedDepreciation is the denormalized running total
 * shown in the UI; this table exists for audit trail.
 */
#[ORM\Entity(repositoryClass: AssetDepreciationEntryRepository::class)]
#[ORM\Table(name: 'asset_depreciation_entry')]
#[ORM\Index(name: 'idx_asset_depreciation_entry_fixed_asset', columns: ['fixed_asset_id'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['asset_depreciation_entry:read']],
    filters: [DataGridFilter::class],
)]
class AssetDepreciationEntry
{
    #[Groups(['asset_depreciation_entry:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['asset_depreciation_entry:read'])]
    #[ApiProperty(description: 'asset_depreciation_entry.fixed_asset.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: FixedAsset::class)]
    #[ORM\JoinColumn(name: 'fixed_asset_id', referencedColumnName: 'id', nullable: false)]
    private FixedAsset $fixedAsset;

    #[Groups(['asset_depreciation_entry:read'])]
    #[ApiProperty(description: 'asset_depreciation_entry.period.label')]
    #[ORM\Column(name: 'period', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $period;

    #[Groups(['asset_depreciation_entry:read'])]
    #[ApiProperty(description: 'asset_depreciation_entry.amount.label')]
    #[ORM\Column(name: 'amount', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount;

    #[Groups(['asset_depreciation_entry:read'])]
    #[ApiProperty(description: 'asset_depreciation_entry.accumulated_after.label')]
    #[ORM\Column(name: 'accumulated_after', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $accumulatedAfter;

    #[Groups(['asset_depreciation_entry:read'])]
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

    public function getFixedAsset(): FixedAsset
    {
        return $this->fixedAsset;
    }

    public function setFixedAsset(FixedAsset $fixedAsset): static
    {
        $this->fixedAsset = $fixedAsset;

        return $this;
    }

    public function getPeriod(): DateTimeImmutable
    {
        return $this->period;
    }

    public function setPeriod(DateTimeImmutable $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getAccumulatedAfter(): string
    {
        return $this->accumulatedAfter;
    }

    public function setAccumulatedAfter(string $accumulatedAfter): static
    {
        $this->accumulatedAfter = $accumulatedAfter;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
