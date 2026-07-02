<?php

declare(strict_types=1);

namespace App\Settings\Service;

use App\Inventory\Core\Enum\StockPolicy;
use App\Settings\Entity\TenantSettings;
use App\Settings\Enum\DefaultCurrency;
use App\Settings\Enum\PrintMethod;
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
    ) {}

    public function get(): TenantSettings
    {
        return $this->repository->getOrCreate();
    }

    // ── Typed convenience accessors ───────────────────────────────────────────

    public function getIgv(): float
    {
        return $this->get()->getIgv();
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
        $this->em->flush();
    }
}
