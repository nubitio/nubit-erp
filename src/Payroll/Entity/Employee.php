<?php

declare(strict_types=1);

namespace App\Payroll\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Identity\Entity\User;
use App\Organization\Entity\Company;
use App\Payroll\Repository\EmployeeRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A worker on the payroll — distinct from User, which is only a system
 * login/access account. Decoupled (nullable ManyToOne, not an extension of
 * User) because not every employee needs system access and not every User
 * is an employee. No `branch` relation on purpose, same reasoning as
 * FixedAsset (Fase 6): scoping here is by Company (RUC, where AFP/ONP/
 * EsSalud contributions are actually computed), not by branch.
 */
#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ORM\Table(name: 'employee')]
#[ORM\UniqueConstraint(columns: ['company_id', 'dni'])]
#[ORM\Index(name: 'idx_employee_status', columns: ['status'])]
#[ApiResource(
    normalizationContext: ['groups' => ['employee:read']],
    denormalizationContext: ['groups' => ['employee:write']],
    filters: [DataGridFilter::class],
    mercure: true,
)]
class Employee
{
    public const string ACTIVE = 'ACTIVE';

    public const string TERMINATED = 'TERMINATED';

    public const string CONTRACT_INDEFINIDO = 'INDEFINIDO';

    public const string CONTRACT_PLAZO_FIJO = 'PLAZO_FIJO';

    public const string PENSION_AFP = 'AFP';

    public const string PENSION_ONP = 'ONP';

    #[Groups(['employee:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Assert\NotNull]
    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.company.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'empresa_id', nullable: false)]
    private Company $company;

    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.user.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'usuario_id', nullable: true)]
    private ?User $user = null;

    #[Assert\Regex(pattern: '/^\d{8}$/', message: 'El DNI debe tener 8 dígitos')]
    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.dni.label')]
    #[ORM\Column(name: 'dni', length: 8)]
    private string $dni;

    #[Assert\NotBlank]
    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.first_name.label')]
    #[ORM\Column(name: 'first_name', length: 255)]
    private string $firstName;

    #[Assert\NotBlank]
    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.last_name.label')]
    #[ORM\Column(name: 'last_name', length: 255)]
    private string $lastName;

    #[Assert\NotNull]
    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.hire_date.label')]
    #[ORM\Column(name: 'hire_date', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $hireDate;

    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.termination_date.label')]
    #[ORM\Column(name: 'termination_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $terminationDate = null;

    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.contract_type.label')]
    #[ORM\Column(name: 'contract_type', length: 20, options: ['default' => self::CONTRACT_INDEFINIDO])]
    private string $contractType = self::CONTRACT_INDEFINIDO;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.base_salary.label')]
    #[ORM\Column(name: 'base_salary', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $baseSalary;

    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.pension_scheme.label')]
    #[ORM\Column(name: 'pension_scheme', length: 10, options: ['default' => self::PENSION_ONP])]
    private string $pensionScheme = self::PENSION_ONP;

    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.afp_provider.label')]
    #[ORM\Column(name: 'afp_provider', length: 20, nullable: true)]
    private ?string $afpProvider = null;

    /**
     * Determines the bonificación extraordinaria rate on gratificaciones
     * (Ley 30334): 9% if covered by EsSalud, 6.75% if covered by an EPS
     * instead (the EPS substitutes part of the EsSalud contribution).
     */
    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.eps_affiliated.label')]
    #[ORM\Column(name: 'eps_affiliated', options: ['default' => false])]
    private bool $epsAffiliated = false;

    #[Groups(['employee:read', 'employee:write'])]
    #[ApiProperty(description: 'employee.status.label')]
    #[ORM\Column(name: 'status', length: 20, options: ['default' => self::ACTIVE])]
    private string $status = self::ACTIVE;

    #[Groups(['employee:read'])]
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

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getDni(): string
    {
        return $this->dni;
    }

    public function setDni(string $dni): static
    {
        $this->dni = $dni;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getHireDate(): DateTimeImmutable
    {
        return $this->hireDate;
    }

    public function setHireDate(DateTimeImmutable $hireDate): static
    {
        $this->hireDate = $hireDate;

        return $this;
    }

    public function getTerminationDate(): ?DateTimeImmutable
    {
        return $this->terminationDate;
    }

    public function setTerminationDate(?DateTimeImmutable $terminationDate): static
    {
        $this->terminationDate = $terminationDate;

        return $this;
    }

    public function getContractType(): string
    {
        return $this->contractType;
    }

    public function setContractType(string $contractType): static
    {
        $this->contractType = $contractType;

        return $this;
    }

    public function getBaseSalary(): string
    {
        return $this->baseSalary;
    }

    public function setBaseSalary(string $baseSalary): static
    {
        $this->baseSalary = $baseSalary;

        return $this;
    }

    public function getPensionScheme(): string
    {
        return $this->pensionScheme;
    }

    public function setPensionScheme(string $pensionScheme): static
    {
        $this->pensionScheme = $pensionScheme;

        return $this;
    }

    public function getAfpProvider(): ?string
    {
        return $this->afpProvider;
    }

    public function setAfpProvider(?string $afpProvider): static
    {
        $this->afpProvider = $afpProvider;

        return $this;
    }

    public function isEpsAffiliated(): bool
    {
        return $this->epsAffiliated;
    }

    public function setEpsAffiliated(bool $epsAffiliated): static
    {
        $this->epsAffiliated = $epsAffiliated;

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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
