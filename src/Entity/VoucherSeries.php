<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\VoucherSeriesRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VoucherSeriesRepository::class)]
#[ORM\Table(name: 'local_comprobante')]
#[ApiResource(
    shortName: 'VoucherSerie',
    normalizationContext: ['groups' => ['voucher_series:read']],
    filters: [DevExtremeFilter::class],
)]
class VoucherSeries
{
    #[Groups(['voucher_series:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['voucher_series:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'local_id', referencedColumnName: 'local_id', nullable: false)]
    private Branch $branch;

    #[Groups(['voucher_series:read'])]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'tipo_comprobante_id', referencedColumnName: 'codigo', nullable: false)]
    private VoucherType $voucherType;

    #[Groups(['voucher_series:read'])]
    #[ORM\Column(name: 'serie', length: 6)]
    private string $series;

    #[Groups(['voucher_series:read'])]
    #[ORM\Column(name: 'serie_asociada', length: 6, nullable: true)]
    private ?string $associatedSeries = null;

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
}
