<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\GetCollection;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Enum\CashMovementType;
use App\CashRegister\State\CashSessionMovementsProvider;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CashSessionMovementsProviderTest extends TestCase
{
    /** @return array<string, mixed>[] */
    private static function movementRows(): array
    {
        return [
            [
                'id' => 10,
                'cash_session_id' => 7,
                'category_id' => null,
                'amount' => '25.00',
                'description' => 'Ingreso caja',
                'created_at' => '2026-04-30 08:00:00',
                'movement_type' => 'CASH_IN',
            ],
            [
                'id' => 11,
                'cash_session_id' => 7,
                'category_id' => null,
                'amount' => '10.00',
                'description' => null,
                'created_at' => '2026-04-30 08:30:00',
                'movement_type' => 'CASH_OUT',
            ],
        ];
    }

    private function makeProvider(
        bool $sessionFound = true,
        bool $sessionByLegacy = false,
        ?CashSession $sessionEntity = null,
    ): CashSessionMovementsProvider {
        $conn = $this->createStub(Connection::class);

        if (!$sessionFound) {
            $conn->method('fetchAssociative')->willReturn(false);
        } elseif ($sessionByLegacy) {
            // first call (direct) → false, second (legacy) → found
            $conn->method('fetchAssociative')->willReturnOnConsecutiveCalls(
                false,
                ['id' => 7],
            );
        } else {
            $conn->method('fetchAssociative')->willReturn(['id' => 7]);
        }

        $conn->method('fetchAllAssociative')->willReturn(self::movementRows());

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(
            static function (string $class, mixed $id) use ($sessionEntity): mixed {
                if ($class === CashSession::class) {
                    return $sessionEntity;
                }

                return null;
            }
        );

        return new CashSessionMovementsProvider($conn, $em);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function testProvideReturnsMappedMovements(): void
    {
        $provider = $this->makeProvider();
        $result = $provider->provide(new GetCollection(), ['id' => '7']);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertInstanceOf(CashMovement::class, $result[0]);
        self::assertInstanceOf(CashMovement::class, $result[1]);
    }

    public function testProvideMapsCashInToIncomeType(): void
    {
        $provider = $this->makeProvider();
        $movements = $provider->provide(new GetCollection(), ['id' => '7']);

        self::assertSame(CashMovementType::INCOME, $movements[0]->getType());
    }

    public function testProvideMapsCashOutToExpenseType(): void
    {
        $provider = $this->makeProvider();
        $movements = $provider->provide(new GetCollection(), ['id' => '7']);

        self::assertSame(CashMovementType::EXPENSE, $movements[1]->getType());
    }

    public function testProvideUsesDefaultDescriptionWhenNull(): void
    {
        $provider = $this->makeProvider();
        $movements = $provider->provide(new GetCollection(), ['id' => '7']);

        self::assertSame('Movimiento de caja', $movements[1]->getDescription());
    }

    public function testProvideSetsIdViaReflection(): void
    {
        $provider = $this->makeProvider();
        $movements = $provider->provide(new GetCollection(), ['id' => '7']);

        self::assertSame(10, $movements[0]->getId());
        self::assertSame(11, $movements[1]->getId());
    }

    public function testProvideFallsBackToLegacyCashShiftId(): void
    {
        $provider = $this->makeProvider(sessionByLegacy: true);
        $result = $provider->provide(new GetCollection(), ['id' => 'legacy-99']);

        self::assertCount(2, $result);
    }

    public function testProvideSetsSessionEntityOnMovement(): void
    {
        $session = new CashSession();
        $provider = $this->makeProvider(sessionEntity: $session);
        $movements = $provider->provide(new GetCollection(), ['id' => '7']);

        // setCashSession will set type from cashSession entity — but entity type is
        // overridden by setType() called just before setCashSession() — check session ref
        foreach ($movements as $movement) {
            self::assertSame($session, $movement->getCashSession());
        }
    }

    // ── Not found ─────────────────────────────────────────────────────────────

    public function testProvideThrowsNotFoundWhenSessionMissing(): void
    {
        $provider = $this->makeProvider(sessionFound: false);

        $this->expectException(NotFoundHttpException::class);
        $provider->provide(new GetCollection(), ['id' => '999']);
    }
}
