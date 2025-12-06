<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Core\Doctrine\Filter\DevExtremeFilter;
use App\Repository\CompanyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'empresa')]
#[ApiResource(
    normalizationContext: ['groups' => ['company:read']],
    filters: [DevExtremeFilter::class],
)]
class Company
{
    #[Groups([
        'company:read',
        'branch:read',
    ])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'empresa_id')]
    private ?string $id = null;

    #[Assert\Regex(pattern: '/^\d{11}$/', message: 'El RUC debe tener 11 dígitos')]
    #[Groups(['company:read'])]
    #[ORM\Column(length: 11)]
    private ?string $ruc = null;

    #[Groups([
        'company:read',
        'branch:read',
    ])]
    #[ORM\Column(name: 'razon_social', length: 255)]
    private ?string $businessName = null;

    #[Groups(['company:read'])]
    #[ORM\Column(name: 'nombre_comercial', length: 255, nullable: true)]
    private ?string $tradeName = null;

    #[Assert\NotBlank]
    #[Groups(['company:read'])]
    #[ORM\Column(name: 'domicilio_fiscal', length: 255)]
    private ?string $fiscalAddress = null;

    #[Groups(['company:read'])]
    #[ORM\Column(name: 'telefono', length: 10, nullable: true)]
    private ?string $phone = null;

    #[Groups(['company:read'])]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: 'logo', referencedColumnName: 'id', nullable: true)]
    private ?Media $logo = null;

    #[Groups(['company:read'])]
    #[ORM\Column(name: 'ubigeo', length: 255, nullable: true)]
    private ?string $ubigeoCode = null;

    #[Groups(['company:read'])]
    #[ORM\Column(name: 'departamento', length: 255, nullable: true)]
    private ?string $department = null;

    #[Groups(['company:read'])]
    #[ORM\Column(name: 'provincia', length: 255, nullable: true)]
    private ?string $province = null;

    #[Groups(['company:read'])]
    #[ORM\Column(name: 'distrito', length: 255, nullable: true)]
    private ?string $district = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRuc(): ?string
    {
        return $this->ruc;
    }

    public function setRuc(string $ruc): static
    {
        $this->ruc = $ruc;

        return $this;
    }

    public function getBusinessName(): ?string
    {
        return $this->businessName;
    }

    public function setBusinessName(string $businessName): static
    {
        $this->businessName = $businessName;

        return $this;
    }

    public function getTradeName(): ?string
    {
        return $this->tradeName;
    }

    public function setTradeName(?string $tradeName): static
    {
        $this->tradeName = $tradeName;

        return $this;
    }

    public function getFiscalAddress(): ?string
    {
        return $this->fiscalAddress;
    }

    public function setFiscalAddress(string $fiscalAddress): static
    {
        $this->fiscalAddress = $fiscalAddress;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getLogo(): ?Media
    {
        return $this->logo;
    }

    public function setLogo(?Media $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getUbigeoCode(): ?string
    {
        return $this->ubigeoCode;
    }

    public function setUbigeoCode(?string $ubigeoCode): static
    {
        $this->ubigeoCode = $ubigeoCode;

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
