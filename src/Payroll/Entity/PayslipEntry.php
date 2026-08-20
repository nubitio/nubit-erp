<?php

declare(strict_types=1);

namespace App\Payroll\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Payroll\Repository\PayslipEntryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * One employee's line within a PayrollRun — read-only via the API,
 * generated exclusively by PayrollCalculationService and regenerated
 * (deleted + recreated) each time the run is recalculated while still
 * DRAFT. Never hand-edited, same as AssetDepreciationEntry (Fase 6).
 */
#[ORM\Entity(repositoryClass: PayslipEntryRepository::class)]
#[ORM\Table(name: 'payslip_entry')]
#[ORM\Index(name: 'idx_payslip_entry_payroll_run', columns: ['payroll_run_id'])]
#[ORM\Index(name: 'idx_payslip_entry_employee', columns: ['employee_id'])]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
    ],
    normalizationContext: ['groups' => ['payslip_entry:read']],
    filters: [DataGridFilter::class],
)]
class PayslipEntry
{
    #[Groups(['payslip_entry:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(name: 'id', type: Types::STRING, length: 36)]
    private ?string $id = null;

    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.payroll_run.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: PayrollRun::class)]
    #[ORM\JoinColumn(name: 'payroll_run_id', referencedColumnName: 'id', nullable: false)]
    private PayrollRun $payrollRun;

    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.employee.label', readableLink: true)]
    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id', nullable: false)]
    private Employee $employee;

    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.gross_salary.label')]
    #[ORM\Column(name: 'gross_salary', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $grossSalary;

    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.afp_mandatory_contribution.label')]
    #[ORM\Column(name: 'afp_mandatory_contribution', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $afpMandatoryContribution = '0.00';

    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.afp_insurance_premium.label')]
    #[ORM\Column(name: 'afp_insurance_premium', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $afpInsurancePremium = '0.00';

    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.afp_commission.label')]
    #[ORM\Column(name: 'afp_commission', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $afpCommission = '0.00';

    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.onp_amount.label')]
    #[ORM\Column(name: 'onp_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $onpAmount = '0.00';

    /** Employer-paid, informational — never subtracted from netPay. */
    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.essalud_employer_contribution.label')]
    #[ORM\Column(name: 'essalud_employer_contribution', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $essaludEmployerContribution = '0.00';

    /** Only set on GRATIFICATION_* runs (Ley 30334) — added on top of netPay, not deducted. */
    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.bonificacion_extraordinaria.label')]
    #[ORM\Column(name: 'bonificacion_extraordinaria', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $bonificacionExtraordinaria = '0.00';

    /** Only set on REGULAR runs — deducted from netPay, see PayrollCalculationService::calculateQuintaCategoria. */
    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.quinta_categoria_retention.label')]
    #[ORM\Column(name: 'quinta_categoria_retention', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $quintaCategoriaRetention = '0.00';

    /** Only set on LIQUIDACION runs — vacaciones truncas, added on top of netPay. */
    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.vacation_trunca_amount.label')]
    #[ORM\Column(name: 'vacation_trunca_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $vacationTruncaAmount = '0.00';

    /** Only set on LIQUIDACION runs — gratificación trunca INCLUDING bonificación extraordinaria, added on top of netPay. */
    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.gratificacion_trunca_amount.label')]
    #[ORM\Column(name: 'gratificacion_trunca_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $gratificacionTruncaAmount = '0.00';

    /** Only set on LIQUIDACION runs — CTS trunca, added on top of netPay. */
    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.cts_trunca_amount.label')]
    #[ORM\Column(name: 'cts_trunca_amount', type: Types::DECIMAL, precision: 12, scale: 2, options: [
        'default' => '0.00',
    ])]
    private string $ctsTruncaAmount = '0.00';

    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.net_pay.label')]
    #[ORM\Column(name: 'net_pay', type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $netPay;

    #[Groups(['payslip_entry:read'])]
    #[ApiProperty(description: 'payslip_entry.note.label')]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[Groups(['payslip_entry:read'])]
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

    public function getPayrollRun(): PayrollRun
    {
        return $this->payrollRun;
    }

    public function setPayrollRun(PayrollRun $payrollRun): static
    {
        $this->payrollRun = $payrollRun;

        return $this;
    }

    public function getEmployee(): Employee
    {
        return $this->employee;
    }

    public function setEmployee(Employee $employee): static
    {
        $this->employee = $employee;

        return $this;
    }

    public function getGrossSalary(): string
    {
        return $this->grossSalary;
    }

    public function setGrossSalary(string $grossSalary): static
    {
        $this->grossSalary = $grossSalary;

        return $this;
    }

    public function getAfpMandatoryContribution(): string
    {
        return $this->afpMandatoryContribution;
    }

    public function setAfpMandatoryContribution(string $afpMandatoryContribution): static
    {
        $this->afpMandatoryContribution = $afpMandatoryContribution;

        return $this;
    }

    public function getAfpInsurancePremium(): string
    {
        return $this->afpInsurancePremium;
    }

    public function setAfpInsurancePremium(string $afpInsurancePremium): static
    {
        $this->afpInsurancePremium = $afpInsurancePremium;

        return $this;
    }

    public function getAfpCommission(): string
    {
        return $this->afpCommission;
    }

    public function setAfpCommission(string $afpCommission): static
    {
        $this->afpCommission = $afpCommission;

        return $this;
    }

    public function getOnpAmount(): string
    {
        return $this->onpAmount;
    }

    public function setOnpAmount(string $onpAmount): static
    {
        $this->onpAmount = $onpAmount;

        return $this;
    }

    public function getEssaludEmployerContribution(): string
    {
        return $this->essaludEmployerContribution;
    }

    public function setEssaludEmployerContribution(string $essaludEmployerContribution): static
    {
        $this->essaludEmployerContribution = $essaludEmployerContribution;

        return $this;
    }

    public function getBonificacionExtraordinaria(): string
    {
        return $this->bonificacionExtraordinaria;
    }

    public function setBonificacionExtraordinaria(string $bonificacionExtraordinaria): static
    {
        $this->bonificacionExtraordinaria = $bonificacionExtraordinaria;

        return $this;
    }

    public function getQuintaCategoriaRetention(): string
    {
        return $this->quintaCategoriaRetention;
    }

    public function setQuintaCategoriaRetention(string $quintaCategoriaRetention): static
    {
        $this->quintaCategoriaRetention = $quintaCategoriaRetention;

        return $this;
    }

    public function getVacationTruncaAmount(): string
    {
        return $this->vacationTruncaAmount;
    }

    public function setVacationTruncaAmount(string $vacationTruncaAmount): static
    {
        $this->vacationTruncaAmount = $vacationTruncaAmount;

        return $this;
    }

    public function getGratificacionTruncaAmount(): string
    {
        return $this->gratificacionTruncaAmount;
    }

    public function setGratificacionTruncaAmount(string $gratificacionTruncaAmount): static
    {
        $this->gratificacionTruncaAmount = $gratificacionTruncaAmount;

        return $this;
    }

    public function getCtsTruncaAmount(): string
    {
        return $this->ctsTruncaAmount;
    }

    public function setCtsTruncaAmount(string $ctsTruncaAmount): static
    {
        $this->ctsTruncaAmount = $ctsTruncaAmount;

        return $this;
    }

    public function getNetPay(): string
    {
        return $this->netPay;
    }

    public function setNetPay(string $netPay): static
    {
        $this->netPay = $netPay;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
