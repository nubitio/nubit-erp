<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\VoucherTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VoucherTypeRepository::class)]
#[ORM\Table(name: 'tipo_comprobante')]
#[ApiResource(
    filters: [DevExtremeFilter::class],
)]
class VoucherType
{
    #[Groups(['voucher_series:read'])]
    #[ORM\Id]
    #[ORM\Column(name: 'codigo', length: 2)]
    private ?string $code = null;

    #[Groups(['voucher_series:read'])]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private ?string $name = null;

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
