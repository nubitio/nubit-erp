<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Catalog\Repository\VoucherTypeRepository;
use App\Efact\Domain\ActivatableInterface;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VoucherTypeRepository::class)]
#[ORM\Table(name: 'tipo_comprobante')]
#[ApiResource(filters: [DataGridFilter::class], mercure: true)]
class VoucherType implements ActivatableInterface
{
    #[Groups(['voucher_series:read'])]
    #[ORM\Id]
    #[ORM\Column(name: 'codigo', length: 2)]
    private string $code;

    #[Groups(['voucher_series:read'])]
    #[ApiProperty(description: 'voucher_type.name.label')]
    #[ORM\Column(name: 'descripcion', length: 255)]
    private string $name;

    #[Groups(['voucher_series:read'])]
    #[ApiProperty(description: 'voucher_type.is_active.label')]
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

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
