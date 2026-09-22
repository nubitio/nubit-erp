<?php

declare(strict_types=1);

namespace App\Settings\Service;

use App\Efact\Enum\StockPolicy;
use App\Settings\Capability\SettingsCapabilitySync;
use App\Settings\Entity\TenantSettings;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Enum\PrintMethod;
use App\Settings\Enum\TaxRegime;
use App\Settings\Repository\TenantSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Primary access point for typed tenant settings.
 *
 * All application code should inject this service instead of the legacy
 * SettingsService. Every getter returns a properly-typed value with a
 * guaranteed default — no raw string parsing, no null checks in callers.
 */
readonly class TenantSettingsService
{
    public function __construct(
        private TenantSettingsRepository $repository,
        private EntityManagerInterface $em,
        private SettingsCapabilitySync $capabilitySync,
    ) {}

    public function get(): TenantSettings
    {
        return $this->repository->getOrCreate();
    }

    // ── Typed convenience accessors ───────────────────────────────────────────

    public function getTaxRegime(): TaxRegime
    {
        return $this->get()->getTaxRegime();
    }

    /**
     * Effective IGV rate used for sale/document calculations. NRUS taxpayers
     * never charge IGV, so this always returns 0 for an NRUS tenant regardless
     * of the configured rate — the raw configured value is still readable via
     * the TenantSettings entity/API so the admin's input isn't lost if they
     * switch back to GENERAL.
     */
    public function getIgv(): float
    {
        $settings = $this->get();
        if ($settings->getTaxRegime() === TaxRegime::NRUS) {
            return 0.0;
        }

        return $settings->getIgv();
    }

    public function getRc(): float
    {
        return $this->get()->getRc();
    }

    public function isPrintLogo(): bool
    {
        return $this->get()->isPrintLogo();
    }

    public function isRequireCashRegister(): bool
    {
        return $this->get()->isRequireCashRegister();
    }

    public function getPrintMethod(): PrintMethod
    {
        return $this->get()->getPrintMethod();
    }

    public function isAutoInvoice(): bool
    {
        return $this->get()->isAutoInvoice();
    }

    public function isCashRoundingEnabled(): bool
    {
        return $this->get()->isCashRoundingEnabled();
    }

    public function getDefaultCurrency(): DefaultCurrency
    {
        return $this->get()->getDefaultCurrency();
    }

    public function getNegativeStockPolicy(): StockPolicy
    {
        return $this->get()->getNegativeStockPolicy();
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public function save(TenantSettings $settings): void
    {
        $this->em->persist($settings);
        $this->capabilitySync->sync($settings);
        $this->em->flush();
    }
}
