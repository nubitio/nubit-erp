<?php

declare(strict_types=1);

namespace App\Organization\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Organization\Repository\CompanyRepository;
use Doctrine\ORM\Mapping as ORM;
use Nubit\AdminBundle\Media\Entity\Media;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\Table(name: 'empresa')]
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
    normalizationContext: ['groups' => ['company:read']],
    filters: [DataGridFilter::class],
    mercure: true,
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
    #[ApiProperty(description: 'company.ruc.label')]
    #[ORM\Column(length: 11)]
    private string $ruc;

    #[Assert\NotBlank(message: 'Indica la razón social.')]
    #[Assert\Length(max: 255, maxMessage: 'La razón social no puede superar {{ limit }} caracteres.')]
    #[Groups([
        'company:read',
        'branch:read',
    ])]
    #[ApiProperty(description: 'company.business_name.label')]
    #[ORM\Column(name: 'razon_social', length: 255)]
    private string $businessName;

    #[Assert\Length(max: 255, maxMessage: 'El nombre comercial no puede superar {{ limit }} caracteres.')]
    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.trade_name.label')]
    #[ORM\Column(name: 'nombre_comercial', length: 255, nullable: true)]
    private ?string $tradeName = null;

    #[Assert\NotBlank(message: 'Indica el domicilio fiscal.')]
    #[Assert\Length(max: 500, maxMessage: 'El domicilio fiscal no puede superar {{ limit }} caracteres.')]
    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.fiscal_address.label')]
    #[ORM\Column(name: 'domicilio_fiscal', type: 'text')]
    private string $fiscalAddress;

    #[Assert\Regex(pattern: '/^\d{6,10}$/', message: 'El teléfono debe tener entre 6 y 10 dígitos.')]
    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.phone.label')]
    #[ORM\Column(name: 'telefono', length: 10, nullable: true)]
    private ?string $phone = null;

    #[Groups(['company:read'])]
    #[ApiProperty(readableLink: true, description: 'company.logo.label')]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(name: 'logo', referencedColumnName: 'id', nullable: true)]
    private ?Media $logo = null;

    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.ubigeo_code.label')]
    #[ORM\Column(name: 'ubigeo', length: 255, nullable: true)]
    private ?string $ubigeoCode = null;

    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.department.label')]
    #[ORM\Column(name: 'departamento', length: 255, nullable: true)]
    private ?string $department = null;

    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.province.label')]
    #[ORM\Column(name: 'provincia', length: 255, nullable: true)]
    private ?string $province = null;

    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.district.label')]
    #[ORM\Column(name: 'distrito', length: 255, nullable: true)]
    private ?string $district = null;

    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.bank_name.label')]
    #[ORM\Column(name: 'bank_name', length: 255, nullable: true)]
    private ?string $bankName = null;

    #[Assert\Regex(pattern: '/^\d{10,20}$/', message: 'La cuenta bancaria debe tener entre 10 y 20 dígitos.')]
    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.bank_account.label')]
    #[ORM\Column(name: 'bank_account', length: 50, nullable: true)]
    private ?string $bankAccount = null;

    #[Assert\Regex(pattern: '/^\d{20}$/', message: 'El CCI debe tener 20 dígitos.')]
    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.bank_cci.label')]
    #[ORM\Column(name: 'bank_cci', length: 50, nullable: true)]
    private ?string $bankCci = null;

    #[Assert\Regex(pattern: '/^\d{11}$/', message: 'La cuenta de detracciones debe tener 11 dígitos.')]
    #[Groups(['company:read'])]
    #[ApiProperty(description: 'company.detraccion_account.label')]
    #[ORM\Column(name: 'detraccion_account', length: 50, nullable: true)]
    private ?string $detraccionAccount = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRuc(): string
    {
        return $this->ruc;
    }

    public function setRuc(string $ruc): static
    {
        $this->ruc = $ruc;

        return $this;
    }

    public function getBusinessName(): string
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

    public function getFiscalAddress(): string
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

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): static
    {
        $this->bankName = $bankName;

        return $this;
    }

    public function getBankAccount(): ?string
    {
        return $this->bankAccount;
    }

    public function setBankAccount(?string $bankAccount): static
    {
        $this->bankAccount = $bankAccount;

        return $this;
    }

    public function getBankCci(): ?string
    {
        return $this->bankCci;
    }

    public function setBankCci(?string $bankCci): static
    {
        $this->bankCci = $bankCci;

        return $this;
    }

    public function getDetraccionAccount(): ?string
    {
        return $this->detraccionAccount;
    }

    public function setDetraccionAccount(?string $detraccionAccount): static
    {
        $this->detraccionAccount = $detraccionAccount;

        return $this;
    }
}
