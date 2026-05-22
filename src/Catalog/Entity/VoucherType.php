<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Efact\Doctrine\Filter\DataGridFilter;
use App\Catalog\Repository\VoucherTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VoucherTypeRepository::class)]
#[ORM\Table(name: 'tipo_comprobante')]
#[ApiResource(
    filters: [DataGridFilter::class],
    mercure: true,
)]
class VoucherType
{
    #[Groups(['voucher_series:read'])]
    #[ORM\Id]
    #[ORM\Column(name: 'codigo', length: 2)]
    private string $code;

    #[Groups(['voucher_series:read'])]
    #[ApiProperty(description: 'voucher_type.name.label')]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private string $name;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
