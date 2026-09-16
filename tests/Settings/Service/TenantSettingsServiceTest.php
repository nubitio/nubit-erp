<?php

declare(strict_types=1);

namespace App\Tests\Settings\Service;

use App\Settings\Capability\SettingsCapabilitySync;
use App\Settings\Entity\TenantSettings;
use App\Settings\Enum\TaxRegime;
use App\Settings\Repository\TenantCapabilityRepository;
use App\Settings\Repository\TenantSettingsRepository;
use App\Settings\Service\TenantSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class TenantSettingsServiceTest extends TestCase
{
    public function testGetIgvReturnsConfiguredRateForGeneralRegime(): void
    {
        $settings = new TenantSettings()
            ->setTaxRegime(TaxRegime::GENERAL)
            ->setIgv(18.0);
        $service = $this->makeService($settings);

        static::assertSame(18.0, $service->getIgv());
    }

    public function testGetIgvIsAlwaysZeroForNrusRegimeRegardlessOfConfiguredRate(): void
    {
        $settings = new TenantSettings()
            ->setTaxRegime(TaxRegime::NRUS)
            ->setIgv(18.0);
        $service = $this->makeService($settings);

        static::assertSame(0.0, $service->getIgv());
    }

    private function makeService(TenantSettings $settings): TenantSettingsService
    {
        /** @var TenantSettingsRepository&MockObject $repository */
        $repository = $this->createMock(TenantSettingsRepository::class);
        $repository->method('getOrCreate')->willReturn($settings);

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        // SettingsCapabilitySync is final and cannot be mocked; getIgv() never
        // calls sync(), so a real instance with unused mocked collaborators is fine.
        $capabilitySync = new SettingsCapabilitySync($this->createMock(TenantCapabilityRepository::class), $em);

        return new TenantSettingsService($repository, $em, $capabilitySync);
    }
}
