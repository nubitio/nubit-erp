<?php

declare(strict_types=1);

namespace App\Settings\Capability;

use App\Settings\Entity\TenantCapability;
use App\Settings\Entity\TenantSettings;
use App\Settings\Enum\CapabilitySource;
use App\Settings\Repository\TenantCapabilityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Mirrors the four legacy TenantSettings flags that NB-7 moved onto capability
 * config so the resolver is the single read path. TenantSettings keeps the
 * columns for the compat window; every write goes through here as well.
 *
 *   requireCashRegister -> cash.sessions.requireOpenSession
 *   autoInvoice         -> billing.sunat.autoInvoice
 *   negativeStockPolicy -> inventory.stock_tracking.negativeStockPolicy
 *   printMethod         -> hardware.printing.method
 */
final readonly class SettingsCapabilitySync
{
    public function __construct(
        private TenantCapabilityRepository $capabilityRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function sync(TenantSettings $settings): void
    {
        $this->set(CapabilityKey::CashSessions, 'requireOpenSession', $settings->isRequireCashRegister());
        $this->set(CapabilityKey::BillingSunat, 'autoInvoice', $settings->isAutoInvoice());
        $this->set(
            CapabilityKey::InventoryStockTracking,
            'negativeStockPolicy',
            $settings->getNegativeStockPolicy()->value,
        );
        $this->set(CapabilityKey::HardwarePrinting, 'method', $settings->getPrintMethod()->value);
    }

    private function set(CapabilityKey $key, string $configKey, bool|string $value): void
    {
        $row = $this->capabilityRepository->findByKey($key->value);
        if ($row === null) {
            $definition = CapabilityCatalog::get($key->value);
            $row = new TenantCapability($key->value);
            $row->setEnabled(
                $definition !== null && $definition->defaultEnabled,
            )->setSource(CapabilitySource::Migration);
            $this->entityManager->persist($row);
        }

        $config = $row->getConfig() ?? [];
        $config[$configKey] = $value;
        $row->setConfig($config);
    }
}
