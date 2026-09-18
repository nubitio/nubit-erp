<?php

declare(strict_types=1);

namespace App\Settings\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Inventory\Core\Enum\StockPolicy;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Enum\PrintMethod;
use App\Settings\Enum\TaxRegime;
use App\Settings\Repository\TenantSettingsRepository;
use App\Settings\State\TenantSettingsPatchProcessor;
use App\Settings\State\TenantSettingsProvider;
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
        new Get(uriTemplate: '/tenant/settings', provider: TenantSettingsProvider::class),
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

    /** SUNAT tax regime. NRUS tenants can issue exonerated boletas, but not facturas. */
    #[ORM\Column(enumType: TaxRegime::class)]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private TaxRegime $taxRegime = TaxRegime::GENERAL;

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

    /**
     * When true, the POS sends the sale ticket to the printer automatically
     * after every checkout. When false, the operator prints on demand from the
     * "última venta" panel.
     */
    #[ORM\Column]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private bool $autoPrint = true;

    /** Apply Peru's customer-favourable S/ 0.10 rounding to cash-only POS sales. */
    #[ORM\Column]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private bool $cashRoundingEnabled = false;

    #[ORM\Column(enumType: DefaultCurrency::class)]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private DefaultCurrency $defaultCurrency = DefaultCurrency::PEN;

    /**
     * Default number of days between a recurring charge being generated and
     * its due date, used when a SubscriptionPlan does not override it.
     */
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 365)]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private int $defaultPaymentTermDays = 15;

    // ── Inventory ─────────────────────────────────────────────────────────────

    #[ORM\Column(enumType: StockPolicy::class)]
    #[Groups(['tenant_settings:read', 'tenant_settings:write'])]
    private StockPolicy $negativeStockPolicy = StockPolicy::STRICT;

    // ── Onboarding ────────────────────────────────────────────────────────────

    /**
     * Vertical (business type) key selected during onboarding, e.g. "retail" or
     * "pharmacy". Written by the VerticalPreset applier, not by tenant PATCH.
     * Topology/scale is NOT stored here; it is derived from tenant data.
     */
    #[ORM\Column(length: 64)]
    #[Groups(['tenant_settings:read'])]
    private string $vertical = 'retail';

    /**
     * Marks that the post-provisioning tenant onboarding wizard has been
     * completed. Used by the React OnboardingGate to decide whether to show
     * setup or the normal shell.
     */
    #[ORM\Column]
    #[Groups(['tenant_settings:read'])]
    private bool $onboardingCompleted = false;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaxRegime(): TaxRegime
    {
        return $this->taxRegime;
    }

    public function setTaxRegime(TaxRegime $taxRegime): static
    {
        $this->taxRegime = $taxRegime;
        return $this;
    }

    public function getIgv(): float
    {
        return $this->igv;
    }

    public function setIgv(float $igv): static
    {
        $this->igv = $igv;
        return $this;
    }

    public function getRc(): float
    {
        return $this->rc;
    }

    public function setRc(float $rc): static
    {
        $this->rc = $rc;
        return $this;
    }

    public function isPrintLogo(): bool
    {
        return $this->printLogo;
    }

    public function setPrintLogo(bool $printLogo): static
    {
        $this->printLogo = $printLogo;
        return $this;
    }

    public function isRequireCashRegister(): bool
    {
        return $this->requireCashRegister;
    }

    public function setRequireCashRegister(bool $requireCashRegister): static
    {
        $this->requireCashRegister = $requireCashRegister;
        return $this;
    }

    public function getPrintMethod(): PrintMethod
    {
        return $this->printMethod;
    }

    public function setPrintMethod(PrintMethod $printMethod): static
    {
        $this->printMethod = $printMethod;
        return $this;
    }

    public function isAutoInvoice(): bool
    {
        return $this->autoInvoice;
    }

    public function setAutoInvoice(bool $autoInvoice): static
    {
        $this->autoInvoice = $autoInvoice;
        return $this;
    }

    public function isAutoPrint(): bool
    {
        return $this->autoPrint;
    }

    public function setAutoPrint(bool $autoPrint): static
    {
        $this->autoPrint = $autoPrint;
        return $this;
    }

    public function isCashRoundingEnabled(): bool
    {
        return $this->cashRoundingEnabled;
    }

    public function setCashRoundingEnabled(bool $cashRoundingEnabled): static
    {
        $this->cashRoundingEnabled = $cashRoundingEnabled;
        return $this;
    }

    public function getDefaultCurrency(): DefaultCurrency
    {
        return $this->defaultCurrency;
    }

    public function setDefaultCurrency(DefaultCurrency $defaultCurrency): static
    {
        $this->defaultCurrency = $defaultCurrency;
        return $this;
    }

    public function getDefaultPaymentTermDays(): int
    {
        return $this->defaultPaymentTermDays;
    }

    public function setDefaultPaymentTermDays(int $defaultPaymentTermDays): static
    {
        $this->defaultPaymentTermDays = $defaultPaymentTermDays;
        return $this;
    }

    public function getNegativeStockPolicy(): StockPolicy
    {
        return $this->negativeStockPolicy;
    }

    public function setNegativeStockPolicy(StockPolicy $policy): static
    {
        $this->negativeStockPolicy = $policy;
        return $this;
    }

    public function getVertical(): string
    {
        return $this->vertical;
    }

    public function setVertical(string $vertical): static
    {
        $this->vertical = $vertical;
        return $this;
    }

    public function isOnboardingCompleted(): bool
    {
        return $this->onboardingCompleted;
    }

    public function setOnboardingCompleted(bool $onboardingCompleted): static
    {
        $this->onboardingCompleted = $onboardingCompleted;
        return $this;
    }
}
