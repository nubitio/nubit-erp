<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Catalog\Entity\VoucherType;
use App\Catalog\Repository\VoucherSeriesRepository;
use App\Efact\Domain\ActivatableInterface;
use App\Organization\Entity\Branch;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VoucherSeriesRepository::class)]
#[ORM\Table(name: 'local_comprobante')]
#[ApiResource(
    operations: [
        // Everyone signed in reads these (branch picker, receipts, series per sale); changing them is settings.
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('perm:settings.edit')"),
        new Put(security: "is_granted('perm:settings.edit')"),
        new Patch(security: "is_granted('perm:settings.edit')"),
        new Delete(security: "is_granted('perm:settings.edit')"),
    ],
    shortName: 'VoucherSerie',
    normalizationContext: ['groups' => ['voucher_series:read']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class VoucherSeries implements ActivatableInterface
{
    #[Groups(['voucher_series:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['voucher_series:read'])]
    #[ApiProperty(description: 'voucher_series.branch.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'local_id', referencedColumnName: 'local_id', nullable: false)]
    private Branch $branch;

    #[Groups(['voucher_series:read'])]
    #[ApiProperty(description: 'voucher_series.voucher_type.label')]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'tipo_comprobante_id', referencedColumnName: 'codigo', nullable: false)]
    private VoucherType $voucherType;

    #[Groups(['voucher_series:read'])]
    #[ApiProperty(description: 'voucher_series.series.label')]
    #[ORM\Column(name: 'series', length: 6)]
    private string $series;

    #[Groups(['voucher_series:read'])]
    #[ApiProperty(description: 'voucher_series.associated_series.label')]
    #[ORM\Column(name: 'serie_asociada', length: 6, nullable: true)]
    private ?string $associatedSeries = null;

    #[Groups(['voucher_series:read'])]
    #[ApiProperty(description: 'voucher_series.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBranch(): Branch
    {
        return $this->branch;
    }

    public function setBranch(Branch $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getVoucherType(): VoucherType
    {
        return $this->voucherType;
    }

    public function setVoucherType(VoucherType $voucherType): static
    {
        $this->voucherType = $voucherType;

        return $this;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function setSeries(string $series): static
    {
        $this->series = $series;

        return $this;
    }

    public function getAssociatedSeries(): ?string
    {
        return $this->associatedSeries;
    }

    public function setAssociatedSeries(?string $associated_series): static
    {
        $this->associatedSeries = $associated_series;

        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}
