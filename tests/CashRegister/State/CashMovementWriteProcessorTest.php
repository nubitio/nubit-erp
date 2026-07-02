<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Entity\CashMovementCategory;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Enum\CashMovementType;
use App\CashRegister\State\CashMovementWriteProcessor;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CashMovementWriteProcessorTest extends TestCase
{
    private function makeMovement(CashMovementType $type = CashMovementType::INCOME): CashMovement
    {
        $session = new CashSession();
        $ref = new \ReflectionProperty(CashSession::class, 'id');
        $ref->setValue($session, 7);

        $category = new CashMovementCategory();
        $catRef = new \ReflectionProperty(CashMovementCategory::class, 'id');
        $catRef->setValue($category, 2);
        $category->setType(CashMovementType::INCOME); // required: no default on typed property

        $movement = new CashMovement();
        $movement->setCashSession($session);
        $movement->setAmount('50.00');
        $movement->setDescription('Test movement');
        $movement->setCategory($category); // setCategory syncs $type from category
        $movement->setType($type); // override after setCategory
        $movement->setCreatedAt(new \DateTimeImmutable()); // required: no default

        return $movement;
    }

    // ── DELETE ────────────────────────────────────────────────────────────────

    public function testDeleteExecutesSqlAndReturnsNull(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('DELETE FROM cash_movements'), [42]);

        $security = $this->createStub(Security::class);
        $processor = new CashMovementWriteProcessor($conn, $security);

        $result = $processor->process($this->makeMovement(), new Delete(), ['id' => '42']);

        self::assertNull($result);
    }

    public function testDeleteWithZeroIdSkipsStatement(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::never())->method('executeStatement');

        $security = $this->createStub(Security::class);
        $processor = new CashMovementWriteProcessor($conn, $security);

        $result = $processor->process($this->makeMovement(), new Delete(), ['id' => '0']);

        self::assertNull($result);
    }

    // ── Unauthenticated ───────────────────────────────────────────────────────

    public function testPostThrowsWhenUserIsNull(): void
    {
        $conn = $this->createStub(Connection::class);
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $processor = new CashMovementWriteProcessor($conn, $security);

        $this->expectException(UnauthorizedHttpException::class);
        $processor->process($this->makeMovement(), new Post());
    }

    // ── Missing cashSession ───────────────────────────────────────────────────

    public function testPostThrowsWhenCashSessionIsNull(): void
    {
        $conn = $this->createStub(Connection::class);
        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $processor = new CashMovementWriteProcessor($conn, $security);

        $movement = new CashMovement();
        $movement->setType(CashMovementType::INCOME);
        $movement->setDescription('test');
        $movement->setCreatedAt(new \DateTimeImmutable());
        // no cashSession set

        $this->expectException(UnprocessableEntityHttpException::class);
        $processor->process($movement, new Post());
    }

    // ── POST (INSERT) ─────────────────────────────────────────────────────────

    public function testPostInsertsRowAndSetsIdOnEntity(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn
            ->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('INSERT INTO cash_movements'));
        $conn->method('lastInsertId')->willReturn('99');

        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $processor = new CashMovementWriteProcessor($conn, $security);
        $movement = $this->makeMovement(CashMovementType::INCOME);

        $result = $processor->process($movement, new Post());

        self::assertSame($movement, $result);
        self::assertSame(99, $result->getId());
    }

    public function testPostMapsExpenseTypeToCAShOut(): void
    {
        $capturedSql = '';
        $capturedParams = [];

        $conn = $this->createStub(Connection::class);
        $conn->method('executeStatement')->willReturnCallback(static function (string $sql, array $params) use (
            &$capturedSql,
            &$capturedParams,
        ): int {
            $capturedSql = $sql;
            $capturedParams = $params;

            return 1;
        });
        $conn->method('lastInsertId')->willReturn('1');

        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $processor = new CashMovementWriteProcessor($conn, $security);
        $processor->process($this->makeMovement(CashMovementType::EXPENSE), new Post());

        self::assertStringContainsString('INSERT', $capturedSql);
        self::assertSame('CASH_OUT', $capturedParams[1]);
    }

    // ── PATCH (UPDATE) ────────────────────────────────────────────────────────

    public function testPatchUpdatesExistingRow(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects(self::once())->method('executeStatement')->with(self::stringContains('UPDATE cash_movements'));

        $security = $this->createStub(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(1, 'admin'));

        $processor = new CashMovementWriteProcessor($conn, $security);
        $result = $processor->process($this->makeMovement(), new Patch(), ['id' => '5']);

        self::assertNotNull($result);
    }
}
