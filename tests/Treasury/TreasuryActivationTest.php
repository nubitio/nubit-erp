<?php

declare(strict_types=1);

namespace App\Tests\Treasury;

use App\Settings\Entity\TenantCapability;
use App\Settings\Enum\CapabilitySource;
use App\Settings\Repository\TenantCapabilityRepository;
use App\Treasury\Domain\AccountKind;
use App\Treasury\Service\DefaultMethodMapper;
use App\Treasury\Service\TreasuryAccountSeeder;
use App\Treasury\Service\TreasuryActivation;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/** The switch: it enables the capability, brings the accounts in line, and switching off deletes nothing. */
#[AllowMockObjectsWithoutExpectations]
final class TreasuryActivationTest extends TestCase
{
    public function testSwitchingOnCreatesTheCapabilityRowAndTheTenantsAccounts(): void
    {
        $kit = $this->tenant();
        $persisted = [];

        $result = $this->activation($kit, null, $persisted)->set(true);

        self::assertTrue($result['enabled']);
        self::assertCount(1, $persisted);
        $row = $persisted[0];
        self::assertInstanceOf(TenantCapability::class, $row);
        self::assertSame('treasury.accounts', $row->getCapabilityKey());
        self::assertTrue($row->isEnabled());
        self::assertSame(CapabilitySource::Manual, $row->getSource());
        self::assertSame(['Caja Principal', 'Sin asignar PEN'], $result['created']);
        self::assertSame(2, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_account'));
    }

    public function testSwitchingOnTwiceDoesNotDuplicateAnything(): void
    {
        $kit = $this->tenant();
        $row = new TenantCapability('treasury.accounts')->setEnabled(false);
        $persisted = [];

        $this->activation($kit, $row, $persisted)->set(true);
        $second = $this->activation($kit, $row, $persisted)->set(true);

        self::assertSame([], $persisted, 'an existing row is updated, not recreated');
        self::assertSame([], $second['created']);
        self::assertSame(2, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_account'));
    }

    public function testSwitchingOffOnlyTurnsTheCapabilityOffAndKeepsEveryAccount(): void
    {
        $kit = $this->tenant();
        $kit->account('wallet', AccountKind::Wallet);
        $row = new TenantCapability('treasury.accounts')->setEnabled(true);
        $persisted = [];

        $result = $this->activation($kit, $row, $persisted)->set(false);

        self::assertFalse($row->isEnabled());
        self::assertSame(['enabled' => false, 'created' => [], 'mapped' => 0], $result);
        self::assertSame(
            1,
            (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_account'),
            'nothing is created or removed',
        );
    }

    private function tenant(): TreasuryTestDatabase
    {
        $kit = TreasuryTestDatabase::create();
        $kit->db->insert('local', ['local_id' => 'branch-main', 'descripcion' => 'Principal']);
        $kit->db->insert('cash_register', ['id' => 1, 'branch_id' => 'branch-main', 'name' => 'Caja Principal']);

        return $kit;
    }

    /** @param list<object> $persisted */
    private function activation(
        TreasuryTestDatabase $kit,
        ?TenantCapability $existing,
        array &$persisted,
    ): TreasuryActivation {
        $repository = $this->createMock(TenantCapabilityRepository::class);
        $repository->method('findByKey')->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $seeder = new TreasuryAccountSeeder(
            $kit->db,
            $kit->accounts,
            new DefaultMethodMapper($kit->db, $kit->accounts),
        );

        return new TreasuryActivation($repository, $em, $seeder);
    }
}
