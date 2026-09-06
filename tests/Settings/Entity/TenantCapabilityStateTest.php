<?php

declare(strict_types=1);

namespace App\Tests\Settings\Entity;

use App\Settings\Entity\TenantCapability;
use App\Settings\Entity\TenantProfile;
use App\Settings\Enum\CapabilitySource;
use PHPUnit\Framework\TestCase;

final class TenantCapabilityStateTest extends TestCase
{
    public function testTenantCapabilityDefaultsAndMutators(): void
    {
        $capability = new TenantCapability('inventory.lot_tracking');

        static::assertNull($capability->getId());
        static::assertSame('inventory.lot_tracking', $capability->getCapabilityKey());
        static::assertFalse($capability->isEnabled());
        static::assertNull($capability->getConfig());
        static::assertSame(CapabilitySource::Manual, $capability->getSource());
        static::assertNull($capability->getPresetKey());
        static::assertNull($capability->getPresetVersion());
        static::assertEquals($capability->getCreatedAt(), $capability->getUpdatedAt());

        $capability
            ->setEnabled(true)
            ->setConfig(['blockExpired' => true])
            ->setSource(CapabilitySource::Preset)
            ->setPreset('pharmacy', 2);

        static::assertTrue($capability->isEnabled());
        static::assertSame(['blockExpired' => true], $capability->getConfig());
        static::assertSame(CapabilitySource::Preset, $capability->getSource());
        static::assertSame('pharmacy', $capability->getPresetKey());
        static::assertSame(2, $capability->getPresetVersion());
        static::assertGreaterThanOrEqual($capability->getCreatedAt(), $capability->getUpdatedAt());
    }

    public function testTenantProfileRecordsAppliedPresetAndDedupesLockedFields(): void
    {
        $profile = new TenantProfile();

        static::assertNull($profile->getVerticalKey());
        static::assertNull($profile->getPresetVersion());
        static::assertNull($profile->getAppliedAt());
        static::assertSame([], $profile->getLockedFields());

        $profile->recordApplied('retail', 1);
        $profile->setLockedFields(['igv', 'igv', 'printMethod']);

        static::assertSame('retail', $profile->getVerticalKey());
        static::assertSame(1, $profile->getPresetVersion());
        static::assertNotNull($profile->getAppliedAt());
        static::assertSame(['igv', 'printMethod'], $profile->getLockedFields());
    }
}
