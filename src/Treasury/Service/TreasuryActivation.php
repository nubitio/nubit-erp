<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Settings\Capability\CapabilityKey;
use App\Settings\Entity\TenantCapability;
use App\Settings\Enum\CapabilitySource;
use App\Settings\Repository\TenantCapabilityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Switches Treasury on or off for the tenant. Until now the only way was a row written by hand.
 *
 * Switching on also brings the accounts in line with what the tenant already has (registers, banks, suspense
 * accounts and the unambiguous default mappings), so the screens are usable straight away. Switching off stops
 * new payments from being recorded; nothing is deleted, and `treasury:backfill` records what was missed once it
 * is switched on again.
 */
final readonly class TreasuryActivation
{
    public function __construct(
        private TenantCapabilityRepository $capabilities,
        private EntityManagerInterface $entityManager,
        private TreasuryAccountSeeder $seeder,
    ) {}

    /** @return array{enabled: bool, created: list<string>, mapped: int} */
    public function set(bool $enabled): array
    {
        $row = $this->capabilities->findByKey(CapabilityKey::TreasuryAccounts->value);
        if ($row === null) {
            $row = new TenantCapability(CapabilityKey::TreasuryAccounts->value);
            $this->entityManager->persist($row);
        }
        $row->setEnabled($enabled)->setSource(CapabilitySource::Manual);
        $this->entityManager->flush();

        if (!$enabled) {
            return ['enabled' => false, 'created' => [], 'mapped' => 0];
        }

        return ['enabled' => true] + $this->seeder->sync();
    }
}
