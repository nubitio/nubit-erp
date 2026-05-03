<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Inventory\Core\Enum\StockPolicy;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Enum\PrintMethod;
use App\Settings\Repository\TenantSettingsRepository;
use App\Settings\State\TenantSettingsProvider;
use App\Settings\State\TenantSettingsPatchProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Single-row tenant configuration editable by the tenant administrator.
 *
 * One row is created per tenant DB during onboarding. Access is via the
 * singleton endpoints GET /api/tenant/settings and PATCH /api/tenant/settings
 * — there is no collection endpoint and no row ID in the URL.
 */
#[ORM\Entity(repositoryClass: TenantSettingsRepository::class)]
#[ORM\Table(name: 'tenant_settings')]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/tenant/settings',
            provider: TenantSettingsProvider::class,
        ),
        new Patch(
            uriTemplate: '/tenant/settings',
            provider: TenantSettingsProvider::class,
            processor: TenantSettingsPatchProcessor::class,
            inputFormats: ['json' => ['application/merge-patch+json', 'application/json']],
        ),
    ],
    normalizationContext: ['groups' => ['tenant_settings:read']],
    denormalizationContext: ['groups' => ['tenant_settings:write']],
)]
class TenantSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ── General ──────────────────────────────────────────────────────────────

    #[ORM\Column(type: 'float')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private float $igv = 18.0;

    #[ORM\Column(type: 'float')]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private float $rc = 0.0;

    #[ORM\Column]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private bool $printLogo = true;

    #[ORM\Column]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private bool $requireCashRegister = true;

    #[ORM\Column(enumType: PrintMethod::class)]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private PrintMethod $printMethod = PrintMethod::WEBUSB;

    // ── Sales ─────────────────────────────────────────────────────────────────

    #[ORM\Column]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private bool $autoInvoice = true;

    #[ORM\Column(enumType: DefaultCurrency::class)]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private DefaultCurrency $defaultCurrency = DefaultCurrency::PEN;

    // ── Inventory ─────────────────────────────────────────────────────────────

    #[ORM\Column(enumType: StockPolicy::class)]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private StockPolicy $negativeStockPolicy = StockPolicy::STRICT;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getIgv(): float { return $this->igv; }
    public function setIgv(float $igv): static { $this->igv = $igv; return $this; }

    public function getRc(): float { return $this->rc; }
    public function setRc(float $rc): static { $this->rc = $rc; return $this; }

    public function isPrintLogo(): bool { return $this->printLogo; }
    public function setPrintLogo(bool $printLogo): static { $this->printLogo = $printLogo; return $this; }

    public function isRequireCashRegister(): bool { return $this->requireCashRegister; }
    public function setRequireCashRegister(bool $requireCashRegister): static
    {
        $this->requireCashRegister = $requireCashRegister;
        return $this;
    }

    public function getPrintMethod(): PrintMethod { return $this->printMethod; }
    public function setPrintMethod(PrintMethod $printMethod): static
    {
        $this->printMethod = $printMethod;
        return $this;
    }

    public function isAutoInvoice(): bool { return $this->autoInvoice; }
    public function setAutoInvoice(bool $autoInvoice): static
    {
        $this->autoInvoice = $autoInvoice;
        return $this;
    }

    public function getDefaultCurrency(): DefaultCurrency { return $this->defaultCurrency; }
    public function setDefaultCurrency(DefaultCurrency $defaultCurrency): static
    {
        $this->defaultCurrency = $defaultCurrency;
        return $this;
    }

    public function getNegativeStockPolicy(): StockPolicy { return $this->negativeStockPolicy; }
    public function setNegativeStockPolicy(StockPolicy $policy): static
    {
        $this->negativeStockPolicy = $policy;
        return $this;
    }
}
