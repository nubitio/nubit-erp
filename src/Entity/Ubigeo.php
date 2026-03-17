<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\UbigeoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Entity(repositoryClass: UbigeoRepository::class)]
#[ORM\Table(name: 'ubigeo')]
#[ApiResource(
    shortName: 'Ubigeo',
    normalizationContext: ['groups' => ['ubigeo:read']],
    filters: [DevExtremeFilter::class],
)]
class Ubigeo
{
    #[Groups(['ubigeo:read'])]
    #[ORM\Id]
    #[ORM\Column(name: 'codigo', length: 10)]
    private string $code;

    #[Groups(['ubigeo:read'])]
    #[ApiProperty(description: 'ubigeo.department.label')]
    #[ORM\Column(name: 'departamento', length: 255, nullable: true)]
    private ?string $department = null;

    #[Groups(['ubigeo:read'])]
    #[ApiProperty(description: 'ubigeo.province.label')]
    #[ORM\Column(name: 'provincia', length: 255, nullable: true)]
    private ?string $province = null;

    #[Groups(['ubigeo:read'])]
    #[ApiProperty(description: 'ubigeo.district.label')]
    #[ORM\Column(name: 'distrito', length: 255, nullable: true)]
    private ?string $district = null;

    #[Groups(['ubigeo:read', 'company:read'])]
    #[SerializedName('name')]
    public function getDisplayName(): string
    {
        $parts = array_filter([
            $this->department,
            $this->province,
            $this->district,
        ]);

        return sprintf('%s (%s)', implode(' / ', $parts), $this->code);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): static
    {
        $this->province = $province;

        return $this;
    }

    public function getDistrict(): ?string
    {
        return $this->district;
    }

    public function setDistrict(?string $district): static
    {
        $this->district = $district;

        return $this;
    }
}
