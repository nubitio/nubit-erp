<?php

declare(strict_types=1);

namespace App\Payroll\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Payroll\Repository\AfpRateRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Editable reference table for AFP rates — deliberately data, not code
 * constants, because commission rates change periodically per AFP and
 * must be correctable without a redeploy. Seeded with rates gathered
 * during this phase's research; MUST be reviewed against the current
 * official rates by an accountant/payroll specialist before this module
 * is used for real payroll (see the Fase 7 plan notes).
 */
#[ORM\Entity(repositoryClass: AfpRateRepository::class)]
#[ORM\Table(name: 'afp_rate')]
#[ORM\Index(name: 'idx_afp_rate_provider', columns: ['afp_provider'])]
#[ApiResource(mercure: true)]
class AfpRate
{
    #[Groups(['Default'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Groups(['Default'])]
    #[ApiProperty(description: 'afp_rate.afp_provider.label')]
    #[ORM\Column(name: 'afp_provider', length: 20)]
    private string $afpProvider;

    #[Assert\NotNull]
    #[Groups(['Default'])]
    #[ApiProperty(description: 'afp_rate.mandatory_contribution_rate.label')]
    #[ORM\Column(name: 'mandatory_contribution_rate', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $mandatoryContributionRate;

    #[Assert\NotNull]
    #[Groups(['Default'])]
    #[ApiProperty(description: 'afp_rate.insurance_premium_rate.label')]
    #[ORM\Column(name: 'insurance_premium_rate', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $insurancePremiumRate;

    #[Assert\NotNull]
    #[Groups(['Default'])]
    #[ApiProperty(description: 'afp_rate.commission_rate.label')]
    #[ORM\Column(name: 'commission_rate', type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $commissionRate;

    #[Assert\NotNull]
    #[Groups(['Default'])]
    #[ApiProperty(description: 'afp_rate.effective_from.label')]
    #[ORM\Column(name: 'effective_from', type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $effectiveFrom;

    #[Groups(['Default'])]
    #[ApiProperty(description: 'afp_rate.note.label')]
    #[ORM\Column(name: 'note', type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAfpProvider(): string
    {
        return $this->afpProvider;
    }

    public function setAfpProvider(string $afpProvider): static
    {
        $this->afpProvider = $afpProvider;

        return $this;
    }

    public function getMandatoryContributionRate(): string
    {
        return $this->mandatoryContributionRate;
    }

    public function setMandatoryContributionRate(string $mandatoryContributionRate): static
    {
        $this->mandatoryContributionRate = $mandatoryContributionRate;

        return $this;
    }

    public function getInsurancePremiumRate(): string
    {
        return $this->insurancePremiumRate;
    }

    public function setInsurancePremiumRate(string $insurancePremiumRate): static
    {
        $this->insurancePremiumRate = $insurancePremiumRate;

        return $this;
    }

    public function getCommissionRate(): string
    {
        return $this->commissionRate;
    }

    public function setCommissionRate(string $commissionRate): static
    {
        $this->commissionRate = $commissionRate;

        return $this;
    }

    public function getEffectiveFrom(): DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(DateTimeImmutable $effectiveFrom): static
    {
        $this->effectiveFrom = $effectiveFrom;

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
}
