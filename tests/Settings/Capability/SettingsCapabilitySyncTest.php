<?php

declare(strict_types=1);

namespace App\Tests\Settings\Capability;

use App\Inventory\Core\Enum\StockPolicy;
use App\Settings\Capability\SettingsCapabilitySync;
use App\Settings\Entity\TenantCapability;
use App\Settings\Entity\TenantSettings;
use App\Settings\Enum\CapabilitySource;
use App\Settings\Enum\PrintMethod;
use App\Settings\Repository\TenantCapabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SettingsCapabilitySyncTest extends TestCase
{
    public function testCreatesRowsForTheFourMappedKeysWhenNoneExist(): void
    {
        $persisted = [];
        $repository = $this->createMock(TenantCapabilityRepository::class);
        $repository->method('findByKey')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $settings = new TenantSettings();
        $settings
            ->setRequireCashRegister(true)
            ->setAutoInvoice(false)
            ->setNegativeStockPolicy(StockPolicy::ALLOW_NEGATIVE)
            ->setPrintMethod(PrintMethod::LOCAL_AGENT);

        new SettingsCapabilitySync($repository, $em)->sync($settings);

        $byKey = [];
        foreach ($persisted as $row) {
            static::assertInstanceOf(TenantCapability::class, $row);
            $byKey[$row->getCapabilityKey()] = $row;
        }

        static::assertSame(
            ['cash.sessions', 'billing.sunat', 'inventory.stock_tracking', 'hardware.printing'],
            array_keys($byKey),
        );
        static::assertSame(['requireOpenSession' => true], $byKey['cash.sessions']->getConfig());
        static::assertSame(['autoInvoice' => false], $byKey['billing.sunat']->getConfig());
        static::assertSame(
            ['negativeStockPolicy' => 'ALLOW_NEGATIVE'],
            $byKey['inventory.stock_tracking']->getConfig(),
        );
        static::assertSame(['method' => 'LOCAL_AGENT'], $byKey['hardware.printing']->getConfig());
        static::assertSame(CapabilitySource::Migration, $byKey['cash.sessions']->getSource());
        static::assertTrue($byKey['billing.sunat']->isEnabled(), 'billing.sunat is defaultEnabled');
    }

    public function testMergesIntoAnExistingRowWithoutTouchingOtherKeysOrSource(): void
    {
        $existing = new TenantCapability('cash.sessions');
        $existing->setEnabled(true)->setSource(CapabilitySource::Preset)->setConfig(['other' => 1]);

        $repository = $this->createMock(TenantCapabilityRepository::class);
        $repository->method('findByKey')->willReturnCallback(static fn(string $key): ?TenantCapability => $key
            === 'cash.sessions'
                ? $existing
                : null);

        $em = $this->createMock(EntityManagerInterface::class);

        $settings = new TenantSettings()->setRequireCashRegister(true);
        new SettingsCapabilitySync($repository, $em)->sync($settings);

        static::assertSame(['other' => 1, 'requireOpenSession' => true], $existing->getConfig());
        static::assertSame(CapabilitySource::Preset, $existing->getSource());
    }
}
